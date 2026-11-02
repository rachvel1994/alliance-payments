<?php

declare(strict_types=1);

use App\Actions\Clients\SaveClient;
use App\Actions\Payments\RecordPayment;
use App\Contracts\AllocationStrategy;
use App\Data\PaymentData;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function managedClientInput(): array
{
    return ['name' => 'ახალი კლიენტი', 'personal_number' => '01005098765', 'phone' => '+995555123456'];
}

function clientManagementUser(string $role = 'editor'): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('requires authentication and write permissions on every client management route', function (): void {
    $client = Client::factory()->create();
    foreach ([['get', '/clients/create'], ['post', '/clients'], ['get', '/clients/'.$client->id.'/edit'], ['patch', '/clients/'.$client->id]] as [$method, $path]) {
        $this->{$method}($path)->assertRedirect(route('login'));
    }
    $this->actingAs(clientManagementUser('viewer'));
    foreach ([['get', '/clients/create'], ['post', '/clients'], ['get', '/clients/'.$client->id.'/edit'], ['patch', '/clients/'.$client->id]] as [$method, $path]) {
        $this->{$method}($path, managedClientInput())->assertForbidden();
    }
    expect($client->fresh()->name)->toBe($client->name);
    $this->assertDatabaseCount('clients', 1);
});

it('serves both client forms to editors without allocation internals', function (): void {
    $client = Client::factory()->create();
    $this->actingAs(clientManagementUser())->get('/clients/create')->assertInertia(fn (Assert $page) => $page
        ->component('Clients/Form')->where('client', null));
    $this->get('/clients/'.$client->id.'/edit')->assertInertia(fn (Assert $page) => $page
        ->component('Clients/Form')->where('client.id', $client->id)->missing('client.current_allocation_run_id'));
});

it('creates trimmed client data with a leading zero and ignores extra fields', function (): void {
    $this->actingAs(clientManagementUser())->post('/clients', [
        ...managedClientInput(), 'name' => '  ახალი კლიენტი  ', 'phone' => ' ', 'id' => 100, 'current_allocation_run_id' => 100,
    ])->assertRedirect('/clients')->assertSessionHasNoErrors()->assertSessionHas('success');
    $client = Client::query()->sole();
    expect($client->name)->toBe('ახალი კლიენტი')->and($client->personal_number)->toBe('01005098765')
        ->and($client->phone)->toBeNull()->and($client->id)->not->toBe(100)
        ->and($client->current_allocation_run_id)->toBeNull();
});

it('updates contact data while keeping an unchanged unique personal number', function (): void {
    $client = Client::factory()->create(managedClientInput());
    $this->actingAs(clientManagementUser())->patch('/clients/'.$client->id, [
        ...managedClientInput(), 'name' => 'განახლებული კლიენტი', 'phone' => '+995555111111',
    ])->assertRedirect('/clients')->assertSessionHasNoErrors();
    expect($client->fresh()->name)->toBe('განახლებული კლიენტი')->and($client->fresh()->phone)->toBe('+995555111111');
});

it('allows personal number correction before financial activity', function (): void {
    $client = Client::factory()->create();
    $this->actingAs(clientManagementUser())->patch('/clients/'.$client->id, managedClientInput())->assertSessionHasNoErrors();
    expect($client->fresh()->personal_number)->toBe('01005098765');
});

it('protects the personal number after financial activity but permits contact changes', function (string $activity): void {
    $client = Client::factory()->create(managedClientInput());
    if ($activity === 'contract') {
        Contract::factory()->for($client)->create();
    } else {
        Payment::factory()->for($client)->create();
    }
    $this->actingAs(clientManagementUser())->patch('/clients/'.$client->id, [
        ...managedClientInput(), 'personal_number' => '01005098766',
    ])->assertSessionHasErrors('personal_number');
    expect($client->fresh()->personal_number)->toBe('01005098765');
    $this->patch('/clients/'.$client->id, [...managedClientInput(), 'name' => 'შესწორებული სახელი'])->assertSessionHasNoErrors();
    expect($client->fresh()->name)->toBe('შესწორებული სახელი');
})->with(['contract', 'payment']);

it('matches previously unknown payments when the client is created', function (): void {
    $payment = app(RecordPayment::class)->handle(PaymentData::fromArray([
        'paid_at' => '2026-01-01', 'amount' => '50.01', 'personal_number' => '01005098765',
        'payer_name' => 'უცნობი კლიენტი', 'bank_reference' => 'CLIENT-MATCH',
    ]))->payment;
    expect($payment->client_id)->toBeNull();
    $user = clientManagementUser();
    $this->actingAs($user)->post('/clients', managedClientInput())->assertSessionHasNoErrors();
    $client = Client::query()->sole();
    expect($payment->fresh()->client_id)->toBe($client->id)
        ->and($client->currentAllocationRun->reason)->toBe('client_matched')
        ->and($client->currentAllocationRun->user_id)->toBe($user->id)
        ->and($payment->fresh()->amount_minor)->toBe(5001);
});

it('rejects invalid and duplicate client data with Georgian errors', function (array $change, string $field): void {
    Client::factory()->create(['personal_number' => '11111111111']);
    $this->actingAs(clientManagementUser())->post('/clients', array_replace(managedClientInput(), $change))
        ->assertSessionHasErrors($field);
    expect(session('errors')->first($field))->toMatch('/[ა-ჰ]/u');
    $this->assertDatabaseCount('clients', 1);
})->with([
    [['name' => ' '], 'name'], [['name' => str_repeat('a', 256)], 'name'], [['name' => []], 'name'],
    [['personal_number' => '11111111111'], 'personal_number'], [['personal_number' => '123'], 'personal_number'],
    [['personal_number' => 1005098765], 'personal_number'], [['phone' => []], 'phone'],
]);

it('handles a database unique conflict as a validation error', function (): void {
    Client::factory()->create(managedClientInput());
    expect(fn () => app(SaveClient::class)->handle(managedClientInput()))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('clients', 1);
});

it('honors separately granted client permissions without a role', function (): void {
    $user = User::factory()->create();
    $user->givePermissionTo('clients.create');
    $this->actingAs($user)->post('/clients', managedClientInput())->assertSessionHasNoErrors();
    $client = Client::query()->sole();
    $this->patch('/clients/'.$client->id, managedClientInput())->assertForbidden();
    $user->givePermissionTo('clients.update');
    $this->patch('/clients/'.$client->id, managedClientInput())->assertSessionHasNoErrors();
});

it('returns 404 for a missing client edit or update', function (): void {
    $this->actingAs(clientManagementUser())->get('/clients/999999/edit')->assertNotFound();
    $this->patch('/clients/999999', managedClientInput())->assertNotFound();
});

it('rolls back client creation and payment matching if allocation fails', function (): void {
    $payment = Payment::factory()->unmatched()->create(['personal_number' => '01005098765']);
    app()->bind(AllocationStrategy::class, fn () => new class implements AllocationStrategy
    {
        public function allocate(array $payments, array $installments): array
        {
            throw new RuntimeException('სატესტო ჩავარდნა.');
        }
    });
    expect(fn () => app(SaveClient::class)->handle(managedClientInput()))->toThrow(RuntimeException::class);
    $this->assertDatabaseCount('clients', 0);
    $this->assertDatabaseCount('allocation_runs', 0);
    expect($payment->fresh()->client_id)->toBeNull();
});
