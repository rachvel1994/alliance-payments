<?php

declare(strict_types=1);

use App\Actions\Apartments\SaveApartment;
use App\Models\Apartment;
use App\Models\Contract;
use App\Models\Project;
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

function apartmentManagementUser(string $role = 'editor'): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function managedApartmentInput(?Project $project = null): array
{
    return [
        'project_id' => ($project ?? Project::factory()->create())->id, 'number' => 'A-101',
        'floor' => 0, 'area' => '50.25', 'price' => '100000.01',
    ];
}

it('protects all apartment management routes from guests and viewers', function (): void {
    $apartment = Apartment::factory()->free()->create();
    $input = managedApartmentInput();
    $paths = [['get', '/apartments/create'], ['post', '/apartments'],
        ['get', '/apartments/'.$apartment->id.'/edit'], ['patch', '/apartments/'.$apartment->id]];
    foreach ($paths as [$method, $path]) {
        $this->{$method}($path)->assertRedirect(route('login'));
    }
    $this->actingAs(apartmentManagementUser('viewer'));
    foreach ($paths as [$method, $path]) {
        $this->{$method}($path, $input)->assertForbidden();
    }
    $this->assertDatabaseCount('apartments', 1);
});

it('serves project options and the exact price to the apartment forms', function (): void {
    $apartment = Apartment::factory()->free()->create(['price_minor' => 10000001]);
    $this->actingAs(apartmentManagementUser())->get('/apartments/create')->assertInertia(fn (Assert $page) => $page
        ->component('Apartments/Form')->where('apartment', null)->has('projects', 1));
    $this->get('/apartments/'.$apartment->id.'/edit')->assertInertia(fn (Assert $page) => $page
        ->component('Apartments/Form')->where('apartment.price', '100000.01')->where('apartment.contract_exists', false));
});

it('creates a free apartment using exact money and ignores a forged sold status', function (): void {
    $this->actingAs(apartmentManagementUser())->post('/apartments', [
        ...managedApartmentInput(), 'status' => 'sold', 'price_minor' => 1,
    ])->assertRedirect('/apartments')->assertSessionHasNoErrors()->assertSessionHas('success');
    $apartment = Apartment::query()->sole();
    expect($apartment->price_minor)->toBe(10000001)->and($apartment->area)->toBe('50.25')
        ->and($apartment->status)->toBe('free')->and($apartment->floor)->toBe(0);
});

it('updates only a free apartment and keeps the same composite business key', function (): void {
    $input = managedApartmentInput();
    $apartment = Apartment::factory()->free()->create(['project_id' => $input['project_id'], 'number' => 'A-101']);
    $this->actingAs(apartmentManagementUser())->patch('/apartments/'.$apartment->id, $input)
        ->assertRedirect('/apartments')->assertSessionHasNoErrors();
    expect($apartment->fresh()->price_minor)->toBe(10000001)->and($apartment->fresh()->area)->toBe('50.25');
});

it('enforces apartment number uniqueness within a project', function (): void {
    $input = managedApartmentInput();
    Apartment::factory()->free()->create(['project_id' => $input['project_id'], 'number' => 'A-101']);
    $this->actingAs(apartmentManagementUser())->post('/apartments', $input)->assertSessionHasErrors('number');
    $this->post('/apartments', [...$input, 'project_id' => Project::factory()->create()->id])->assertSessionHasNoErrors();
    $this->assertDatabaseCount('apartments', 2);
});

it('refuses sold or already contracted apartments even if the browser sends an old free status', function (string $state): void {
    $input = managedApartmentInput();
    $apartment = $state === 'contracted' ? Contract::factory()->create()->apartment : Apartment::factory()->create(['status' => 'sold']);
    if ($state === 'contracted') {
        $apartment->update(['status' => 'free']);
    }
    $old = $apartment->only('project_id', 'number', 'price_minor', 'status');
    $this->actingAs(apartmentManagementUser())->patch('/apartments/'.$apartment->id, [...$input, 'status' => 'free'])
        ->assertSessionHasErrors('number');
    expect($apartment->fresh()->only(array_keys($old)))->toBe($old);
})->with(['sold', 'contracted']);

it('rejects invalid apartment fields with Georgian errors', function (array $change, string $field): void {
    $this->actingAs(apartmentManagementUser())->post('/apartments', array_replace(managedApartmentInput(), $change))
        ->assertSessionHasErrors($field);
    expect(session('errors')->first($field))->toMatch('/[ა-ჰ]/u');
    $this->assertDatabaseCount('apartments', 0);
})->with([
    [['project_id' => 99999], 'project_id'], [['project_id' => []], 'project_id'],
    [['number' => ' '], 'number'], [['number' => []], 'number'],
    [['floor' => -1], 'floor'], [['floor' => '1.5'], 'floor'], [['floor' => 2147483648], 'floor'],
    [['area' => 0], 'area'], [['area' => '1.001'], 'area'], [['area' => '100000000.00'], 'area'],
    [['price' => 0], 'price'], [['price' => '1.001'], 'price'], [['price' => []], 'price'],
    [['price' => '92233720368547758.08'], 'price'],
]);

it('handles a database composite unique conflict as a validation error', function (): void {
    $input = managedApartmentInput();
    Apartment::factory()->free()->create(['project_id' => $input['project_id'], 'number' => $input['number']]);
    expect(fn () => app(SaveApartment::class)->handle([...$input, 'price_minor' => 10000001]))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('apartments', 1);
});

it('honors direct apartment write permissions and keeps viewers read only', function (): void {
    $user = User::factory()->create();
    $user->givePermissionTo('apartments.create');
    $input = managedApartmentInput();
    $this->actingAs($user)->post('/apartments', $input)->assertSessionHasNoErrors();
    $apartment = Apartment::query()->sole();
    $this->patch('/apartments/'.$apartment->id, $input)->assertForbidden();
    $user->givePermissionTo('apartments.update');
    $this->patch('/apartments/'.$apartment->id, $input)->assertSessionHasNoErrors();
});

it('returns 404 for missing apartment edit and update records', function (): void {
    $this->actingAs(apartmentManagementUser())->get('/apartments/999999/edit')->assertNotFound();
    $this->patch('/apartments/999999', managedApartmentInput())->assertNotFound();
});
