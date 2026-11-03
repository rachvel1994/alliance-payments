<?php

declare(strict_types=1);

use App\Actions\Payments\RecordPayment;
use App\Data\PaymentData;
use App\Models\Apartment;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Installment;
use App\Models\Project;
use App\Queries\WorkspaceQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function presentationFixture(): array
{
    $client = Client::factory()->create(['name' => 'გიორგი ბერიძე', 'personal_number' => '01005098765', 'phone' => '+995555123456']);
    $project = Project::factory()->create(['name' => 'Sea Tower', 'city' => 'Batumi']);
    $apartment = Apartment::factory()->for($project)->create(['number' => 'A-301']);
    $contract = Contract::factory()->for($client)->for($apartment)->create(['contract_date' => '2026-01-01', 'price_minor' => 20000]);
    foreach (['2026-01-01', '2026-02-01'] as $index => $date) {
        Installment::factory()->for($contract)->create(['sequence' => $index + 1, 'due_date' => $date, 'amount_minor' => 10000]);
    }
    $payment = app(RecordPayment::class)->handle(PaymentData::fromArray([
        'paid_at' => '2026-02-01', 'amount' => '125.00', 'personal_number' => $client->personal_number,
        'payer_name' => $client->name, 'bank_reference' => 'NAMED-CONTRACT', 'purpose' => 'ბინის გადახდა',
    ]))->payment;

    return [$contract->fresh(), $payment, $project];
}

it('uses the same readable contract name and historical link throughout the workspace', function (): void {
    [$contract, $payment] = presentationFixture();
    $query = app(WorkspaceQuery::class);
    $filters = ['as_of' => '2026-03-01'];
    $names = [
        $query->contracts($filters)['rows'][0]['contract'],
        $query->contract($contract, $filters)['summary']['contract'],
        $query->overdue($filters)['rows'][0]['contract'],
        $query->payment($payment, $filters)['rows'][0]['contract'],
        $query->apartments($filters)['rows'][0]['contract'],
    ];
    foreach ($names as $name) {
        expect($name['label'])->toBe('Sea Tower — ბინა A-301')
            ->and($name['description'])->toBe('Batumi · 2026-01-01')
            ->and($name['permission'])->toBe('contracts.view')
            ->and($name['url'])->toEndWith('/contracts/'.$contract->id.'?as_of=2026-03-01');
    }
});

it('exposes client contact and payment context alongside exact financial totals', function (): void {
    [$contract, $payment, $project] = presentationFixture();
    $query = app(WorkspaceQuery::class);
    $summary = $query->contract($contract, [])['summary'];
    expect($summary['client']['label'])->toBe('გიორგი ბერიძე')
        ->and($summary['client']['url'])->toEndWith('/clients?q=01005098765')
        ->and($summary['client']['permission'])->toBe('clients.view')
        ->and($summary['phone'])->toBe('+995555123456')
        ->and($summary['project']['url'])->toEndWith('/apartments?project_id='.$project->id)
        ->and($summary['paid_GEL'])->toBe('125.00')
        ->and($summary['remaining_GEL'])->toBe('75.00');
    $paymentSummary = $query->payment($payment, [])['summary'];
    expect($paymentSummary['personal_number'])->toBe('01005098765')
        ->and($paymentSummary['date'])->toBe('2026-02-01')
        ->and($paymentSummary['purpose'])->toBe('ბინის გადახდა')
        ->and($paymentSummary['amount_GEL'])->toBe('125.00');
});

it('links payments by their bank reference while retaining historical allocation details', function (): void {
    [$contract, $payment] = presentationFixture();
    $query = app(WorkspaceQuery::class);
    $row = $query->payments(['as_of' => '2026-03-01'])['rows'][0];
    expect($row['bank_reference']['label'])->toBe('NAMED-CONTRACT')
        ->and($row['bank_reference']['url'])->toEndWith('/payments/'.$payment->id.'?as_of=2026-03-01');
    $installment = $query->contract($contract, ['as_of' => '2026-03-01'])['rows'][0];
    expect($installment['_links'][0]['label'])->toBe('NAMED-CONTRACT')
        ->and($installment['_links'][0]['description'])->toBe('100.00 ₾ · 2026-02-01')
        ->and($installment['_links'][0]['permission'])->toBe('payments.view')
        ->and($installment['_links'][0]['url'])->toEndWith('/payments/'.$payment->id.'?as_of=2026-03-01');
});
