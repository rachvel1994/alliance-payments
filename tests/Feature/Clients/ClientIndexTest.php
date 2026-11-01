<?php

declare(strict_types=1);

use App\Models\Client;
use App\Models\Contract;
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

it('redirects guests to login', function (): void {
    $this->get(route('clients.index'))->assertRedirect(route('login'));
});

it('forbids authenticated users without the clients view permission', function (): void {
    $this->actingAs(User::factory()->create())->get(route('clients.index'))->assertForbidden();
});

it('lists clients and contract counts for permitted roles', function (string $role): void {
    $user = User::factory()->create();
    $user->assignRole($role);
    $first = Client::factory()->create(['name' => 'Alpha', 'personal_number' => '01005098765']);
    Client::factory()->create(['name' => 'Zulu']);
    Contract::factory()->count(2)->for($first)->create();

    $this->actingAs($user)->get(route('clients.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Clients/Index')
            ->where('filters', ['q' => '', 'sort' => 'name', 'direction' => 'asc'])
            ->where('clients.total', 2)
            ->where('clients.per_page', 20)
            ->has('clients.data', 2)
            ->where('clients.data.0.id', $first->id)
            ->where('clients.data.0.personal_number', '01005098765')
            ->where('clients.data.0.contracts_count', 2)
            ->where('clients.data.1.contracts_count', 0)
            ->missing('clients.data.0.current_allocation_run_id')
            ->missing('clients.data.0.created_at'));
})->with(['editor', 'viewer']);

it('searches by name or personal number without losing a zero search', function (string $term): void {
    $viewer = User::factory()->create();
    $viewer->assignRole('viewer');
    $target = Client::factory()->create([
        'name' => 'გიორგი ბერიძე',
        'personal_number' => '01005098765',
    ]);
    Client::factory()->create(['name' => 'Other', 'personal_number' => '11111111111']);

    $this->actingAs($viewer)->get(route('clients.index', ['q' => $term]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.q', trim($term))
            ->where('clients.total', 1)
            ->has('clients.data', 1)
            ->where('clients.data.0.id', $target->id));
})->with(['case insensitive' => ['  ბერიძე  '], 'Georgian' => ['გიორგი'], 'personal number' => ['0100509'], 'zero' => ['0']]);

it('paginates twenty clients and preserves validated search and sorting', function (): void {
    $viewer = User::factory()->create();
    $viewer->assignRole('viewer');
    $clients = Client::factory()->count(21)->create(['name' => 'Client']);

    $this->actingAs($viewer)->get(route('clients.index', [
        'q' => 'Client', 'sort' => 'id', 'direction' => 'desc', 'page' => 2,
    ]))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('clients.total', 21)
        ->where('clients.current_page', 2)
        ->where('clients.last_page', 2)
        ->has('clients.data', 1)
        ->where('clients.data.0.id', $clients->first()->id)
        ->where('clients.next_page_url', null)
        ->where('clients.prev_page_url', fn (string $url): bool => str_contains($url, 'q=Client')
            && str_contains($url, 'sort=id') && str_contains($url, 'direction=desc')
            && str_contains($url, 'page=1')));
});

it('returns an empty list for an unmatched search', function (): void {
    $viewer = User::factory()->create();
    $viewer->assignRole('viewer');
    Client::factory()->create(['name' => 'Known Client']);

    $this->actingAs($viewer)->get(route('clients.index', ['q' => 'NONEXISTENT']))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('clients.total', 0)->has('clients.data', 0));
});

it('rejects invalid filters with Georgian messages', function (string $field, mixed $value, string $message): void {
    $viewer = User::factory()->create();
    $viewer->assignRole('viewer');

    $this->actingAs($viewer)->from(route('clients.index'))
        ->get(route('clients.index', [$field => $value]))
        ->assertRedirect(route('clients.index'))
        ->assertSessionHasErrors([$field => $message]);
})->with([
    'array search' => ['q', ['invalid'], 'საძიებო ტექსტი უნდა იყოს სტრიქონი.'],
    'long search' => ['q', str_repeat('ა', 101), 'საძიებო ტექსტი არ უნდა აღემატებოდეს 100 სიმბოლოს.'],
    'unsafe sort' => ['sort', 'name; drop table clients', 'აირჩიე დალაგების ველი სიიდან.'],
    'direction' => ['direction', 'sideways', 'აირჩიე დალაგების მიმართულება სიიდან.'],
    'page type' => ['page', 'one', 'გვერდის ნომერი უნდა იყოს მთელი რიცხვი.'],
    'page range' => ['page', 0, 'გვერდის ნომერი უნდა იყოს მინიმუმ 1.'],
]);
