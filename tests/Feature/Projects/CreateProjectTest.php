<?php

declare(strict_types=1);

use App\Actions\Projects\CreateProject;
use App\Models\Apartment;
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

function projectCreationUser(string $role = 'editor'): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('requires authentication to open and submit the project form', function (): void {
    $this->get('/projects/create')->assertRedirect(route('login'));
    $this->post('/projects', ['name' => 'New Tower', 'city' => 'Batumi'])->assertRedirect(route('login'));
    $this->assertDatabaseCount('projects', 0);
});

it('forbids viewers and users without project creation permission', function (bool $viewer): void {
    $user = $viewer ? projectCreationUser('viewer') : User::factory()->create();
    $this->actingAs($user)->get('/projects/create')->assertForbidden();
    $this->post('/projects', ['name' => 'New Tower', 'city' => 'Batumi'])->assertForbidden();
    $this->assertDatabaseCount('projects', 0);
})->with([true, false]);

it('opens the Inertia project form for editors', function (): void {
    $this->actingAs(projectCreationUser())->get('/projects/create')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Projects/Create')
            ->where('auth.permissions', fn ($permissions): bool => in_array('projects.create', $permissions->all(), true)));
});

it('creates a trimmed project and redirects to a preselected apartment form', function (): void {
    $response = $this->actingAs(projectCreationUser())->post('/projects', [
        'name' => '  ახალი პროექტი  ', 'city' => '  ბათუმი  ', 'id' => 99999, 'apartments' => [['number' => 'forged']],
    ])->assertSessionHasNoErrors()->assertSessionHas('success');

    $project = Project::query()->sole();
    $response->assertRedirect(route('apartments.create', ['project_id' => $project->id]));
    expect($project->name)->toBe('ახალი პროექტი')->and($project->city)->toBe('ბათუმი')->and($project->id)->not->toBe(99999);
    $this->assertDatabaseCount('apartments', 0);
    $this->get('/apartments/create?project_id='.$project->id)->assertInertia(fn (Assert $page) => $page
        ->component('Apartments/Form')->where('selectedProjectId', $project->id)
        ->has('projects', 1)->where('projects.0.id', $project->id));
});

it('can add an apartment to a newly created project', function (): void {
    $this->actingAs(projectCreationUser())->post('/projects', ['name' => 'New Tower', 'city' => 'Batumi'])
        ->assertSessionHasNoErrors();
    $project = Project::query()->sole();
    $this->post('/apartments', [
        'project_id' => $project->id, 'number' => 'A-1', 'floor' => 1, 'area' => '50.25', 'price' => '100000.01',
    ])->assertSessionHasNoErrors()->assertRedirect('/apartments');
    expect(Apartment::query()->sole()->project_id)->toBe($project->id)
        ->and($project->apartments()->count())->toBe(1);
});

it('allows the same project name in different cities', function (): void {
    Project::factory()->create(['name' => 'New Tower', 'city' => 'Batumi']);
    $this->actingAs(projectCreationUser())->post('/projects', ['name' => 'New Tower', 'city' => 'Tbilisi'])
        ->assertSessionHasNoErrors();
    $this->assertDatabaseCount('projects', 2);
});

it('rejects a trimmed duplicate of the project name and city', function (): void {
    Project::factory()->create(['name' => 'New Tower', 'city' => 'Batumi']);
    $this->actingAs(projectCreationUser())->from('/projects/create')
        ->post('/projects', ['name' => ' New Tower ', 'city' => ' Batumi '])
        ->assertRedirect('/projects/create')->assertSessionHasErrors('name');
    expect(session('errors')->first('name'))->toBe('ამ ქალაქში ასეთი პროექტი უკვე არსებობს.');
    $this->assertDatabaseCount('projects', 1);
});

it('returns Georgian errors for invalid project fields without writing', function (array $change, string $field): void {
    $this->actingAs(projectCreationUser())->post('/projects', array_replace(['name' => 'New Tower', 'city' => 'Batumi'], $change))
        ->assertSessionHasErrors($field);
    expect(session('errors')->first($field))->toMatch('/[ა-ჰ]/u');
    $this->assertDatabaseCount('projects', 0);
})->with([
    [['name' => ' '], 'name'], [['name' => []], 'name'], [['name' => str_repeat('a', 256)], 'name'],
    [['city' => ' '], 'city'], [['city' => ['Batumi']], 'city'], [['city' => str_repeat('a', 256)], 'city'],
    [['city' => 123], 'city'],
]);

it('converts a database unique conflict into a Georgian validation error', function (): void {
    $data = ['name' => 'New Tower', 'city' => 'Batumi'];
    app(CreateProject::class)->handle($data);
    try {
        app(CreateProject::class)->handle($data);
        $this->fail('Expected duplicate project validation.');
    } catch (ValidationException $error) {
        expect($error->errors())->toBe(['name' => ['ამ ქალაქში ასეთი პროექტი უკვე არსებობს.']]);
    }
    $this->assertDatabaseCount('projects', 1);
});

it('honors a direct permission without requiring a role or apartment creation permission', function (): void {
    $user = User::factory()->create();
    $user->givePermissionTo('projects.create');
    $this->actingAs($user)->get('/projects/create')->assertOk();
    $this->post('/projects', ['name' => 'New Tower', 'city' => 'Batumi'])
        ->assertRedirect('/projects/create')->assertSessionHasNoErrors()->assertSessionHas('success');
    $this->get('/apartments/create')->assertForbidden();
    $this->assertDatabaseCount('projects', 1);
});

it('grants editors the new permission and keeps viewers read only after reseeding', function (): void {
    $editor = projectCreationUser();
    $viewer = projectCreationUser('viewer');
    $this->seed(RolesAndPermissionsSeeder::class);
    expect($editor->fresh()->can('projects.create'))->toBeTrue()
        ->and($viewer->fresh()->can('projects.create'))->toBeFalse();
});

it('rejects invalid apartment project preselection with Georgian validation', function (string $query): void {
    $this->actingAs(projectCreationUser())->from('/apartments')->get('/apartments/create?'.$query)
        ->assertRedirect('/apartments')->assertSessionHasErrors('project_id');
    expect(session('errors')->first('project_id'))->toMatch('/[ა-ჰ]/u');
})->with(['project_id=999999', 'project_id[]=1']);
