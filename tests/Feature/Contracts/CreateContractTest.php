<?php

declare(strict_types=1);

use App\Actions\Contracts\CreateContract;
use App\Actions\Payments\RecordPayment;
use App\Contracts\AllocationStrategy;
use App\Data\NewContractData;
use App\Data\PaymentData;
use App\Models\Apartment;
use App\Models\Client;
use App\Models\Contract;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function contractCreationUser(string $role = 'editor'): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function contractCreationInput(): array
{
    return [
        'client_id' => Client::factory()->create()->id,
        'apartment_id' => Apartment::factory()->create(['status' => 'free'])->id,
        'contract_date' => '2026-01-01', 'price' => '100.01',
        'first_due_date' => '2026-01-31', 'installments_count' => 3,
    ];
}

it('protects contract creation from guests and viewers', function (): void {
    $input = contractCreationInput();
    $this->get('/contracts/create')->assertRedirect(route('login'));
    $this->post('/contracts', $input)->assertRedirect(route('login'));
    $this->actingAs(contractCreationUser('viewer'));
    $this->get('/contracts/create')->assertForbidden();
    $this->post('/contracts', $input)->assertForbidden();
    $this->assertDatabaseCount('contracts', 0);
});

it('lists only sellable apartments and minimal client details in the form', function (): void {
    $input = contractCreationInput();
    Apartment::factory()->create(['status' => 'sold']);
    $legacy = Contract::factory()->create()->apartment;
    $legacy->update(['status' => 'free']);

    $this->actingAs(contractCreationUser())->get('/contracts/create')
        ->assertOk()->assertInertia(fn (Assert $page) => $page->component('Contracts/Create')
        ->has('apartments', 1)->where('apartments.0.id', $input['apartment_id'])
        ->where('apartments.0.price', fn (string $price): bool => preg_match('/^\d+\.\d{2}$/', $price) === 1)
        ->missing('clients.0.phone'));
});

it('creates the contract exact schedule sold status and allocation history atomically', function (): void {
    $input = contractCreationInput();
    $user = contractCreationUser();
    $this->actingAs($user)->post('/contracts', [...$input, 'price_minor' => 1, 'status' => 'free'])
        ->assertRedirect('/contracts/1')->assertSessionHas('success');

    $contract = Contract::query()->firstOrFail();
    expect($contract->price_minor)->toBe(10001)
        ->and($contract->installments()->orderBy('sequence')->pluck('amount_minor')->all())->toBe([3333, 3333, 3335])
        ->and($contract->installments()->sum('amount_minor'))->toBe(10001)
        ->and($contract->apartment->status)->toBe('sold')
        ->and($contract->client->currentAllocationRun->user_id)->toBe($user->id)
        ->and($contract->client->currentAllocationRun->reason)->toBe('contract_created');
});

it('accepts direct creation permission without relying on a role name', function (): void {
    $user = User::factory()->create();
    $user->givePermissionTo(['contracts.create', 'contracts.view']);
    $this->actingAs($user)->post('/contracts', contractCreationInput())->assertSessionHasNoErrors();
    $this->assertDatabaseCount('contracts', 1);
});

it('rejects invalid contract terms with Georgian errors before writing', function (array $change, string $field): void {
    $input = contractCreationInput();
    $this->actingAs(contractCreationUser())->from('/contracts/create')
        ->post('/contracts', array_replace($input, $change))
        ->assertRedirect('/contracts/create')->assertSessionHasErrors($field);
    expect(session('errors')->first($field))->toMatch('/[ა-ჰ]/u');
    $this->assertDatabaseCount('contracts', 0);
    $this->assertDatabaseCount('installments', 0);
    expect(Apartment::findOrFail($input['apartment_id'])->status)->toBe('free');
})->with([
    [['client_id' => 99999], 'client_id'], [['apartment_id' => 99999], 'apartment_id'],
    [['contract_date' => '2026-02-30'], 'contract_date'], [['first_due_date' => '2025-12-31'], 'first_due_date'],
    [['price' => '0'], 'price'], [['price' => '-1'], 'price'], [['price' => '1.001'], 'price'],
    [['price' => '92233720368547758.08'], 'price'], [['price' => 'no'], 'price'],
    [['price' => '0.02', 'installments_count' => 3], 'installments_count'],
    [['installments_count' => 0], 'installments_count'], [['installments_count' => 361], 'installments_count'],
    [['installments_count' => '1.5'], 'installments_count'],
]);

it('refuses a sold apartment without an existing contract', function (): void {
    $input = contractCreationInput();
    Apartment::findOrFail($input['apartment_id'])->update(['status' => 'sold']);
    $this->actingAs(contractCreationUser())->post('/contracts', $input)->assertSessionHasErrors('apartment_id');
    $this->assertDatabaseCount('contracts', 0);
});

it('cannot sell the same apartment twice even if its status was reset', function (): void {
    $input = contractCreationInput();
    $this->actingAs(contractCreationUser())->post('/contracts', $input)->assertSessionHasNoErrors();
    Apartment::findOrFail($input['apartment_id'])->update(['status' => 'free']);
    $this->post('/contracts', [...$input, 'client_id' => Client::factory()->create()->id])
        ->assertSessionHasErrors('apartment_id');
    $this->assertDatabaseCount('contracts', 1);
    $this->assertDatabaseCount('installments', 3);
});

it('allocates the clients existing prepayment to the new schedule', function (): void {
    $input = contractCreationInput();
    $client = Client::findOrFail($input['client_id']);
    $payment = app(RecordPayment::class)->handle(PaymentData::fromArray([
        'paid_at' => '2026-01-01', 'amount' => '50.00', 'personal_number' => $client->personal_number,
        'payer_name' => $client->name, 'bank_reference' => 'CONTRACT-PREPAYMENT',
    ]))->payment;
    $old = $client->fresh()->current_allocation_run_id;

    $this->actingAs(contractCreationUser())->post('/contracts', $input)->assertSessionHasNoErrors();

    $run = $client->fresh()->currentAllocationRun;
    expect($run->id)->not->toBe($old)
        ->and($run->allocations()->sum('amount_minor'))->toBe(5000)
        ->and($run->allocations()->pluck('payment_id')->unique()->all())->toBe([$payment->id]);
});

it('rolls back the entire sale when reallocation fails', function (): void {
    $input = contractCreationInput();
    app()->bind(AllocationStrategy::class, fn () => new class implements AllocationStrategy
    {
        public function allocate(array $payments, array $installments): array
        {
            throw new RuntimeException('სატესტო ჩავარდნა.');
        }
    });

    expect(fn () => app(CreateContract::class)->handle(new NewContractData(
        $input['client_id'], $input['apartment_id'], $input['contract_date'], 10001, $input['first_due_date'], 3,
    )))->toThrow(RuntimeException::class, 'სატესტო ჩავარდნა.');

    $this->assertDatabaseCount('contracts', 0);
    $this->assertDatabaseCount('installments', 0);
    $this->assertDatabaseCount('allocation_runs', 0);
    expect(Apartment::findOrFail($input['apartment_id'])->status)->toBe('free')
        ->and(Client::findOrFail($input['client_id'])->current_allocation_run_id)->toBeNull();
});
