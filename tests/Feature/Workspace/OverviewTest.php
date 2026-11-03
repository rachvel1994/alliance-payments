<?php

declare(strict_types=1);

use App\Actions\Payments\ReallocateClientPayments;
use App\Enums\ImportStatus;
use App\Models\Apartment;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Installment;
use App\Models\Payment;
use App\Models\PaymentImport;
use App\Models\Project;
use App\Models\User;
use App\Queries\OverviewQuery;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
});

function overviewPermissions(): array
{
    return ['clients.view', 'apartments.view', 'contracts.view', 'payments.view', 'imports.view'];
}

function overviewFixture(): array
{
    $project = Project::factory()->create(['name' => 'Sea Tower', 'city' => 'Batumi']);
    $client = Client::factory()->create(['name' => 'მარიამ ბერიძე']);
    $apartment = Apartment::factory()->for($project)->free()->create(['number' => 'A-301']);
    Apartment::factory()->for($project)->free()->create();
    $contract = Contract::factory()->for($client)->for($apartment)->create([
        'contract_date' => '2026-01-01', 'price_minor' => 30001,
    ]);
    foreach (['2026-01-01', '2026-02-01', '2026-03-01'] as $index => $date) {
        Installment::factory()->for($contract)->create([
            'sequence' => $index + 1, 'due_date' => $date, 'amount_minor' => $index === 2 ? 10001 : 10000,
        ]);
    }
    foreach ([['2026-01-02', 10000], ['2026-02-02', 2500], ['2026-04-01', 30000]] as [$date, $amount]) {
        Payment::factory()->for($client)->create(['paid_at' => $date, 'amount_minor' => $amount]);
    }
    Payment::factory()->create(['client_id' => null, 'paid_at' => '2026-02-10', 'amount_minor' => 1234]);
    app(ReallocateClientPayments::class)->handle($client->id);
    app(ReallocateClientPayments::class)->handle($client->id);

    return [$client, $contract];
}

it('returns real zero values and twelve empty monthly buckets without demo data', function (): void {
    $data = app(OverviewQuery::class)->handle(['as_of' => '2026-03-01'], overviewPermissions());
    expect($data['portfolio']['apartments'])->toBe(0)
        ->and($data['payments']['received_GEL'])->toBe('0.00')
        ->and($data['financial']['remaining_GEL'])->toBe('0.00')
        ->and($data['financial']['overdue_installments'])->toBe(0)
        ->and($data['financial']['states'])->each(fn ($row) => $row->count->toBe(0))
        ->and($data['financial']['upcoming'])->toBe([])
        ->and($data['imports']['files'])->toBe(0)
        ->and($data['monthly'])->toHaveCount(12)
        ->and($data['monthly'][0])->toBe(['month' => '2025-04', 'received_GEL' => '0.00', 'scheduled_GEL' => '0.00']);
});

it('counts only current allocations and payments available at the selected date', function (): void {
    [$client, $contract] = overviewFixture();
    $data = app(OverviewQuery::class)->handle(['as_of' => '2026-03-01'], overviewPermissions());
    expect($data['payments']['received_GEL'])->toBe('137.34')
        ->and($data['payments']['allocated_GEL'])->toBe('125.00')
        ->and($data['payments']['unallocated_GEL'])->toBe('12.34')
        ->and($data['payments']['count'])->toBe(3)
        ->and($data['payments']['matched_count'])->toBe(2)
        ->and($data['payments']['unmatched_count'])->toBe(1)
        ->and($data['financial']['contract_value_GEL'])->toBe('300.01')
        ->and($data['financial']['paid_GEL'])->toBe('125.00')
        ->and($data['financial']['paid_percent'])->toBe('41.7')
        ->and($data['financial']['remaining_GEL'])->toBe('175.01')
        ->and($data['financial']['overdue_GEL'])->toBe('75.00')
        ->and($data['financial']['overdue_installments'])->toBe(1)
        ->and($data['financial']['overdue_clients'])->toBe(1)
        ->and($data['financial']['states'])->toBe([
            ['status' => 'paid', 'count' => 1], ['status' => 'overdue', 'count' => 1],
            ['status' => 'partial', 'count' => 0], ['status' => 'pending', 'count' => 1],
        ])
        ->and($data['financial']['debtors'][0])->toBe([
            'name' => $client->name, 'amount_GEL' => '75.00',
            'url' => '/overdue?client_id='.$client->id.'&as_of=2026-03-01',
        ])
        ->and($data['financial']['upcoming'][0])->toBe([
            'name' => 'Sea Tower — ბინა A-301', 'date' => '2026-03-01', 'amount_GEL' => '100.01',
            'url' => '/contracts/'.$contract->id.'?as_of=2026-03-01',
        ]);
});

it('groups monthly receipts and scheduled amounts with exact cents and zero gaps', function (): void {
    overviewFixture();
    $rows = collect(app(OverviewQuery::class)->handle(['as_of' => '2026-03-01'], overviewPermissions())['monthly'])->keyBy('month');
    expect($rows['2026-01'])->toBe(['month' => '2026-01', 'received_GEL' => '100.00', 'scheduled_GEL' => '100.00'])
        ->and($rows['2026-02'])->toBe(['month' => '2026-02', 'received_GEL' => '37.34', 'scheduled_GEL' => '100.00'])
        ->and($rows['2026-03'])->toBe(['month' => '2026-03', 'received_GEL' => '0.00', 'scheduled_GEL' => '100.01'])
        ->and($rows->has('2026-04'))->toBeFalse();
});

it('includes both date boundaries in upcoming installments and excludes the following day', function (): void {
    $contract = Contract::factory()->create(['contract_date' => '2026-01-01', 'price_minor' => 30000]);
    foreach (['2026-03-01', '2026-03-31', '2026-04-01'] as $index => $date) {
        Installment::factory()->for($contract)->create(['sequence' => $index + 1, 'due_date' => $date, 'amount_minor' => 10000]);
    }
    $data = app(OverviewQuery::class)->handle(['as_of' => '2026-03-01'], overviewPermissions());
    expect(array_column($data['financial']['upcoming'], 'date'))->toBe(['2026-03-01', '2026-03-31']);
});

it('links the unmatched widget to an actually filtered payment list', function (): void {
    overviewFixture();
    $user = User::factory()->create();
    $user->assignRole('viewer');
    $this->actingAs($user)->get('/payments?client_match=unmatched')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('total', 1)->where('rows.0.client_match', 'unmatched')->where('filters.client_match', 'unmatched'));
    $this->get('/payments?client_match=matched')->assertOk()->assertInertia(fn (Assert $page) => $page->where('total', 3));
    $this->from('/payments')->get('/payments?client_match=invalid')->assertSessionHasErrors('client_match');
});

it('excludes future contracts from historical liabilities while keeping current inventory explicit', function (): void {
    overviewFixture();
    $future = Contract::factory()->create(['contract_date' => '2026-04-01', 'price_minor' => 50000]);
    Installment::factory()->for($future)->create(['due_date' => '2026-04-01', 'amount_minor' => 50000]);
    $data = app(OverviewQuery::class)->handle(['as_of' => '2026-03-01'], overviewPermissions());
    expect($data['portfolio']['contracts'])->toBe(1)
        ->and($data['portfolio']['sold'])->toBe(2)
        ->and($data['financial']['contract_value_GEL'])->toBe('300.01')
        ->and($data['financial']['upcoming'])->toHaveCount(1)
        ->and($data['projects'][0]['free'])->toBe(1)
        ->and($data['projects'][0]['sold'])->toBe(1);
});

it('excludes fully prepaid upcoming installments and reports the excess as unallocated', function (): void {
    overviewFixture();
    $data = app(OverviewQuery::class)->handle(['as_of' => '2026-04-01'], overviewPermissions());
    expect($data['financial']['upcoming'])->toBe([])
        ->and($data['financial']['debtors'])->toBe([])
        ->and($data['financial']['remaining_GEL'])->toBe('0.00')
        ->and($data['payments']['unallocated_GEL'])->toBe('137.33');
});

it('does not classify a partially prepaid future installment as overdue', function (): void {
    $contract = Contract::factory()->create(['contract_date' => '2026-01-01', 'price_minor' => 10001]);
    Installment::factory()->for($contract)->create(['due_date' => '2026-03-01', 'amount_minor' => 10001]);
    Payment::factory()->create(['client_id' => $contract->client_id, 'paid_at' => '2026-02-01', 'amount_minor' => 2500]);
    app(ReallocateClientPayments::class)->handle($contract->client_id);
    $data = app(OverviewQuery::class)->handle(['as_of' => '2026-02-15'], overviewPermissions());
    expect($data['financial']['overdue_GEL'])->toBe('0.00')
        ->and($data['financial']['states'][2])->toBe(['status' => 'partial', 'count' => 1])
        ->and($data['financial']['upcoming'][0]['amount_GEL'])->toBe('75.01');
});

it('preserves amounts larger than the JavaScript safe integer range as decimal strings', function (): void {
    Contract::factory()->create(['contract_date' => '2026-01-01', 'price_minor' => 9007199254740993]);
    Payment::factory()->create(['client_id' => null, 'paid_at' => '2026-01-01', 'amount_minor' => 9007199254740993]);
    $data = app(OverviewQuery::class)->handle(['as_of' => '2026-03-01'], overviewPermissions());
    expect($data['financial']['remaining_GEL'])->toBe('90071992547409.93')
        ->and($data['payments']['unallocated_GEL'])->toBe('90071992547409.93');
});

it('summarizes real import outcomes and the current processing backlog', function (): void {
    foreach ([ImportStatus::CompletedWithErrors, ImportStatus::Queued, ImportStatus::Failed] as $index => $status) {
        PaymentImport::query()->create(['filename' => 'statement-'.$index.'.xlsx', 'file_hash' => hash('sha256', (string) $index),
            'status' => $status, 'imported_rows' => $index === 0 ? 8 : 0, 'duplicate_rows' => $index === 0 ? 2 : 0,
            'rejected_rows' => $index === 0 ? 1 : 0]);
    }
    $imports = app(OverviewQuery::class)->handle([], overviewPermissions())['imports'];
    expect($imports['files'])->toBe(3)->and($imports['imported'])->toBe(8)
        ->and($imports['duplicates'])->toBe(2)->and($imports['rejected'])->toBe(1)
        ->and($imports['statuses']['queued'])->toBe(1)->and($imports['statuses']['failed'])->toBe(1)
        ->and($imports['recent'][0]['filename'])->toBe('statement-2.xlsx');
});

it('redacts every underlying dataset without its source permission', function (): void {
    overviewFixture();
    $user = User::factory()->create();
    $user->givePermissionTo('overview.view');
    $this->actingAs($user)->get('/overview')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Overview')->where('payments', null)->where('financial', null)->where('imports', null)
        ->where('portfolio.clients', null)->where('portfolio.contracts', null)->where('portfolio.apartments', null)
        ->has('projects', 0)->has('monthly', 0));
});

it('keeps debtor identities hidden without clients view permission', function (): void {
    overviewFixture();
    $data = app(OverviewQuery::class)->handle(['as_of' => '2026-03-01'], ['payments.view', 'contracts.view']);
    expect($data['financial']['overdue_GEL'])->toBe('75.00')
        ->and($data['financial']['overdue_clients'])->toBeNull()
        ->and($data['financial']['debtors'])->toBe([])
        ->and($data['portfolio']['clients'])->toBeNull();
});

it('serves both overview entry points to editor and viewer', function (string $role): void {
    $user = User::factory()->create();
    $user->assignRole($role);
    foreach (['/overview', '/dashboard'] as $path) {
        $this->actingAs($user)->get($path.'?as_of=2026-03-01')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Overview')->where('asOf', '2026-03-01')->has('monthly', 12));
    }
})->with(['editor', 'viewer']);

it('requires authentication and an independent overview permission', function (string $path): void {
    $this->get($path)->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->get($path)->assertForbidden();
})->with(['/overview', '/dashboard']);

it('rejects invalid report dates with Georgian validation', function (): void {
    $user = User::factory()->create();
    $user->assignRole('viewer');
    $this->actingAs($user)->from('/overview')->get('/overview?as_of=2026-02-30')
        ->assertRedirect('/overview')->assertSessionHasErrors('as_of');
    expect(session('errors')->first('as_of'))->toMatch('/[ა-ჰ]/u');
});
