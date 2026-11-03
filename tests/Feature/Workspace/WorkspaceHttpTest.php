<?php

declare(strict_types=1);

use App\Actions\Payments\RecordPayment;
use App\Data\PaymentData;
use App\Enums\ImportRowStatus;
use App\Enums\ImportStatus;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Installment;
use App\Models\PaymentImport;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
});

function workspaceHttpUser(string $role = 'viewer'): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function workspaceHttpFixture(): array
{
    $client = Client::factory()->create();
    $contract = Contract::factory()->for($client)->create(['price_minor' => 10000]);
    Installment::factory()->for($contract)->create([
        'sequence' => 1, 'due_date' => '2026-01-01', 'amount_minor' => 10000,
    ]);
    $payment = app(RecordPayment::class)->handle(PaymentData::fromArray([
        'paid_at' => '2026-03-01', 'amount' => '100.00', 'personal_number' => $client->personal_number,
        'payer_name' => $client->name, 'bank_reference' => 'HTTP-PAYMENT',
    ]))->payment;
    $import = PaymentImport::query()->create([
        'filename' => 'http.xlsx', 'file_hash' => str_repeat('b', 64),
        'status' => ImportStatus::CompletedWithErrors, 'total_rows' => 1, 'rejected_rows' => 1,
    ]);
    $import->rows()->create([
        'row_number' => 2, 'status' => ImportRowStatus::Rejected,
        'raw_data' => ['bank_reference' => 'REJECTED'], 'message' => 'თანხა უნდა იყოს რიცხვი.',
    ]);

    return [$contract, $payment, $import];
}

function workspaceHttpPaths(): array
{
    [$contract, $payment, $import] = workspaceHttpFixture();

    return [
        '/overdue' => 'overdue', '/apartments' => 'apartments',
        '/contracts' => 'contracts', '/contracts/'.$contract->id => 'contract',
        '/payments' => 'payments', '/payments/'.$payment->id => 'payment',
        '/imports' => 'imports', '/imports/'.$import->id => 'import',
    ];
}

it('redirects guests from every financial page', function (): void {
    foreach (array_keys(workspaceHttpPaths()) as $path) {
        $this->get($path)->assertRedirect(route('login'));
    }
});

it('forbids financial pages without view permissions', function (): void {
    $this->actingAs(User::factory()->create());
    foreach (array_keys(workspaceHttpPaths()) as $path) {
        $this->get($path)->assertForbidden();
    }
});

it('serves all financial pages to the permitted roles', function (string $role): void {
    $this->actingAs(workspaceHttpUser($role));
    foreach (workspaceHttpPaths() as $path => $section) {
        $this->get($path)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Workspace')->where('section', $section)
            ->has('rows')->has('links')->has('summary')->has('auth.permissions'));
    }
})->with(['editor', 'viewer']);

it('uses permissions independently of role names', function (): void {
    $user = User::factory()->create();
    $user->givePermissionTo('apartments.view');

    $this->actingAs($user)->get('/apartments')->assertOk();
    $this->get('/contracts')->assertForbidden();
});

it('returns not found for absent and invalid bound records', function (string $path): void {
    $this->actingAs(workspaceHttpUser())->get($path)->assertNotFound();
})->with([
    '/contracts/999999', '/payments/999999', '/imports/999999',
    '/contracts/invalid', '/payments/invalid', '/imports/invalid',
]);

it('returns Georgian filter validation errors before executing queries', function (string $path, string $field): void {
    $this->actingAs(workspaceHttpUser())->from('/apartments')->get($path)
        ->assertRedirect('/apartments')->assertSessionHasErrors($field);
    expect(session('errors')->first($field))->toMatch('/[ა-ჰ]/u');
})->with([
    ['/apartments?status=invalid', 'status'],
    ['/contracts?sort=unsafe', 'sort'],
    ['/overdue?as_of=2026-02-30', 'as_of'],
    ['/imports?import_status=unknown', 'import_status'],
]);

it('keeps historical account calculations consistent across the HTTP pages', function (): void {
    [$contract, $payment] = workspaceHttpFixture();
    $this->actingAs(workspaceHttpUser());

    $this->get('/overdue?as_of=2026-02-15')->assertInertia(fn (Assert $page) => $page
        ->where('summary.overdue_GEL', '100.00'));
    $this->get('/contracts/'.$contract->id.'?as_of=2026-02-15')->assertInertia(fn (Assert $page) => $page
        ->where('summary.paid_GEL', '0.00')->where('rows.0.status', 'overdue'));
    $this->get('/payments/'.$payment->id.'?as_of=2026-02-15')->assertInertia(fn (Assert $page) => $page
        ->where('summary.included_as_of', 'future')->where('summary.unallocated_GEL', '100.00'));
    $this->get('/overdue?as_of=2026-03-02')->assertInertia(fn (Assert $page) => $page
        ->where('summary.overdue_GEL', '0.00')->has('rows', 0));
});
