<?php

declare(strict_types=1);

use App\Models\Apartment;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Installment;
use App\Models\Payment;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    fake()->seed(20261031);
});

it('creates unique clients and projects with varied cities', function (): void {
    $clients = Client::factory()->count(20)->create();
    $projects = Project::factory()->count(20)->create();

    expect($clients->pluck('personal_number')->unique())->toHaveCount(20)
        ->and($projects->pluck('name')->unique())->toHaveCount(20)
        ->and($projects->pluck('city')->unique()->count())->toBeGreaterThan(1);

    foreach ($clients as $client) {
        expect($client->personal_number)->toMatch('/\A\d{11}\z/');
    }
});

it('varies apartment prices areas and statuses within the same project', function (): void {
    $project = Project::factory()->create();
    $apartments = Apartment::factory()->for($project)->count(32)->create();

    expect($apartments->pluck('project_id')->unique()->all())->toBe([$project->id])
        ->and($apartments->pluck('number')->unique())->toHaveCount(32)
        ->and($apartments->pluck('price_minor')->unique()->count())->toBeGreaterThan(1)
        ->and($apartments->pluck('area')->unique()->count())->toBeGreaterThan(1)
        ->and($apartments->pluck('status')->unique()->sort()->values()->all())->toBe(['free', 'sold']);

    $this->assertDatabaseCount('projects', 1);
});

it('prices apartments using their project city and overridden area', function (string $city, int $minimum, int $maximum): void {
    $project = Project::factory()->create(['city' => $city]);
    $apartment = Apartment::factory()->for($project)->create(['area' => '50.25']);

    expect($apartment->price_minor)->toBeInt()
        ->toBeGreaterThanOrEqual(5025 * $minimum)
        ->toBeLessThanOrEqual(5025 * $maximum);
})->with([
    ['Tbilisi', 1800, 3200],
    ['Batumi', 1400, 2600],
    ['Kobuleti', 1100, 1900],
    ['Kutaisi', 900, 1600],
]);

it('supports explicit free and sold apartment states', function (): void {
    $project = Project::factory()->create();

    expect(Apartment::factory()->for($project)->free()->create()->status)->toBe('free')
        ->and(Apartment::factory()->for($project)->sold()->create()->status)->toBe('sold');
});

it('links contracts to existing clients and apartments and marks the apartment sold', function (): void {
    $client = Client::factory()->create();
    $apartment = Apartment::factory()->free()->create(['price_minor' => 10000001]);
    $contract = Contract::factory()->for($client)->for($apartment)->create();

    expect($contract->client_id)->toBe($client->id)
        ->and($contract->apartment_id)->toBe($apartment->id)
        ->and($contract->price_minor)->toBeInt()->toBeGreaterThanOrEqual(9000000)->toBeLessThanOrEqual(10000001)
        ->and($apartment->fresh()->status)->toBe('sold')
        ->and($contract->contract_date->lte(now()))->toBeTrue();

    $this->assertDatabaseCount('clients', 1);
    $this->assertDatabaseCount('apartments', 1);
});

it('creates standalone installments with valid contract relations amounts and dates', function (): void {
    $installments = Installment::factory()->count(8)->create();

    foreach ($installments as $installment) {
        expect($installment->amount_minor)->toBeInt()->toBeGreaterThan(0)
            ->toBeLessThanOrEqual($installment->contract->price_minor)
            ->and($installment->due_date->gte($installment->contract->contract_date))->toBeTrue();
    }
});

it('creates a complete schedule with an exact total and stable month end dates', function (): void {
    $contract = Contract::factory()->create(['contract_date' => '2026-01-31', 'price_minor' => 1000001]);
    $installments = Installment::factory()->schedule($contract, 7)->create();

    expect($installments)->toHaveCount(7)
        ->and($installments->pluck('contract_id')->unique()->all())->toBe([$contract->id])
        ->and($installments->pluck('sequence')->all())->toBe(range(1, 7))
        ->and($installments->sum('amount_minor'))->toBe($contract->price_minor)
        ->and($installments->min('amount_minor'))->toBeGreaterThan(0)
        ->and($installments[0]->due_date->toDateString())->toBe('2026-01-31')
        ->and($installments[1]->due_date->toDateString())->toBe('2026-02-28')
        ->and($installments[2]->due_date->toDateString())->toBe('2026-03-31');

    $this->assertDatabaseCount('contracts', 1);
    $this->assertDatabaseCount('installments', 7);
});

it('rejects schedules that cannot have positive installments', function (int $count): void {
    $contract = Contract::factory()->create(['price_minor' => 3]);

    expect(fn () => Installment::factory()->schedule($contract, $count))->toThrow(InvalidArgumentException::class);

    $this->assertDatabaseCount('installments', 0);
})->with([0, 4]);

it('creates varied unmatched payments without losing personal number formatting', function (): void {
    $payments = Payment::factory()->unmatched()->count(24)->create();

    expect($payments->pluck('bank_reference')->unique())->toHaveCount(24)
        ->and($payments->pluck('amount_minor')->unique()->count())->toBeGreaterThan(1)
        ->and($payments->pluck('paid_at')->unique()->count())->toBeGreaterThan(1);

    foreach ($payments as $payment) {
        expect($payment->client_id)->toBeNull()
            ->and($payment->amount_minor)->toBeInt()->toBeGreaterThan(0)
            ->and($payment->personal_number === null || preg_match('/\A\d{11}\z/', $payment->personal_number) === 1)->toBeTrue();
    }

    $this->assertDatabaseCount('clients', 0);
});

it('keeps matched payment details consistent with the selected client', function (): void {
    $client = Client::factory()->create(['personal_number' => '01001012345']);
    $payment = Payment::factory()->for($client)->create();

    expect($payment->client_id)->toBe($client->id)
        ->and($payment->personal_number)->toBe('01001012345')
        ->and($payment->payer_name)->toBe($client->name);

    $this->assertDatabaseCount('clients', 1);

    $matched = Payment::factory()->matched()->create();

    expect($matched->client)->not->toBeNull()
        ->and($matched->personal_number)->toBe($matched->client->personal_number)
        ->and($matched->payer_name)->toBe($matched->client->name);
});
