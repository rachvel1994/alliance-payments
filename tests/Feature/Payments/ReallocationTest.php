<?php

declare(strict_types=1);

use App\Actions\Payments\ReallocateClientPayments;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Installment;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('redirects guests to login without starting a reallocation', function (): void {
    $client = Client::factory()->create();

    $this->post(route('clients.reallocate', $client))->assertRedirect(route('login'));

    $this->assertDatabaseCount('allocation_runs', 0);
    expect($client->fresh()->current_allocation_run_id)->toBeNull();
});

it('returns an unauthorized response when a guest requests JSON', function (): void {
    $client = Client::factory()->create();

    $this->postJson(route('clients.reallocate', $client))->assertUnauthorized();

    $this->assertDatabaseCount('allocation_runs', 0);
});

it('forbids users without the reallocation permission', function (?string $role): void {
    $user = User::factory()->create();
    if ($role !== null) {
        $user->assignRole($role);
    }
    $client = Client::factory()->create();

    $this->actingAs($user)->post(route('clients.reallocate', $client))->assertForbidden();

    $this->assertDatabaseCount('allocation_runs', 0);
    expect($client->fresh()->current_allocation_run_id)->toBeNull();
})->with(['viewer' => ['viewer'], 'without a role' => [null]]);

it('reallocates for an editor and shares Georgian feedback through Inertia', function (): void {
    $this->withoutVite();
    $editor = User::factory()->create();
    $editor->assignRole('editor');
    $client = Client::factory()->create();
    $contract = Contract::factory()->for($client)->create(['price_minor' => 10000]);
    $installment = Installment::factory()->for($contract)->create([
        'sequence' => 1,
        'due_date' => '2026-01-01',
        'amount_minor' => 10000,
    ]);
    $payment = Payment::factory()->for($client)->create([
        'paid_at' => '2026-01-02',
        'amount_minor' => 6000,
    ]);
    $previous = app(ReallocateClientPayments::class)->handle($client->id);
    $history = $previous->allocations()->firstOrFail()->getAttributes();

    $this->actingAs($editor)->from(route('dashboard'))
        ->post(route('clients.reallocate', $client))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('success', 'გადახდების განაწილება განახლდა.');

    $current = $client->fresh()->currentAllocationRun;
    expect($current->id)->not->toBe($previous->id)
        ->and($current->user_id)->toBe($editor->id)
        ->and($current->reason)->toBe('manual')
        ->and($current->completed_at)->not->toBeNull()
        ->and($current->allocations()->firstOrFail()->payment_id)->toBe($payment->id)
        ->and($current->allocations()->firstOrFail()->installment_id)->toBe($installment->id)
        ->and((int) $current->allocations()->sum('amount_minor'))->toBe(6000)
        ->and($previous->allocations()->firstOrFail()->getAttributes())->toBe($history);
    $this->assertDatabaseCount('allocation_runs', 2);
    $this->assertDatabaseCount('payment_allocations', 2);

    $this->get(route('dashboard'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('flash.success', 'გადახდების განაწილება განახლდა.')
            ->where('auth.user.id', $editor->id));
});

it('allows a directly granted permission without requiring the editor role', function (): void {
    $user = User::factory()->create();
    $user->givePermissionTo('payments.reallocate');
    $client = Client::factory()->create();

    $this->actingAs($user)->from(route('dashboard'))
        ->post(route('clients.reallocate', $client))->assertRedirect(route('dashboard'));

    expect($client->fresh()->currentAllocationRun->user_id)->toBe($user->id);
    $this->assertDatabaseCount('allocation_runs', 1);
});

it('returns not found for a missing or invalid client', function (string $client): void {
    $editor = User::factory()->create();
    $editor->assignRole('editor');

    $this->actingAs($editor)->post(route('clients.reallocate', $client))
        ->assertNotFound()->assertSessionMissing('success');

    $this->assertDatabaseCount('allocation_runs', 0);
})->with(['missing' => ['999999'], 'invalid' => ['invalid-client']]);
