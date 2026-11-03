<?php

declare(strict_types=1);

use App\Actions\Users\SaveUser;
use App\Models\Client;
use App\Models\User;
use Database\Seeders\DemoUsersSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
});

function managementUser(string $role = 'admin'): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function managementInput(array $overrides = []): array
{
    return array_replace(['name' => 'ახალი მომხმარებელი', 'email' => 'new-user@example.test', 'role' => 'viewer',
        'permissions' => [], 'password' => 'New-user-password42!', 'password_confirmation' => 'New-user-password42!'], $overrides);
}

it('restricts all user-management endpoints to administrators', function (string $kind): void {
    $target = User::factory()->create();
    if ($kind !== 'guest') {
        $user = $kind === 'no-role' ? User::factory()->create() : managementUser($kind === 'direct-permission' ? 'editor' : $kind);
        if ($kind === 'direct-permission') {
            $user->givePermissionTo('users.manage');
        }
        $this->actingAs($user);
    }
    foreach (['/admin/users', '/admin/users/create', '/admin/users/'.$target->id.'/edit'] as $path) {
        $response = $this->get($path);
        $kind === 'guest' ? $response->assertRedirect('/login') : $response->assertForbidden();
    }
    foreach (['post' => '/admin/users', 'patch' => '/admin/users/'.$target->id, 'delete' => '/admin/users/'.$target->id] as $method => $path) {
        $response = $this->{$method}($path, managementInput());
        $kind === 'guest' ? $response->assertRedirect('/login') : $response->assertForbidden();
    }
    expect(User::query()->where('email', 'new-user@example.test')->exists())->toBeFalse();
})->with(['guest', 'viewer', 'editor', 'no-role', 'direct-permission']);

it('lists only safe user attributes and filters by name email and role', function (): void {
    $admin = managementUser();
    $viewer = managementUser('viewer');
    $this->actingAs($admin)->get('/admin/users?q='.$viewer->email.'&role=viewer')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Users/Index')
            ->where('users.total', 1)->where('users.data.0.email', $viewer->email)
            ->where('users.data.0.roles', ['viewer'])->missing('users.data.0.password')->missing('users.data.0.remember_token'));
    $this->get('/admin/users?q='.urlencode($viewer->name))->assertInertia(fn (Assert $page) => $page->where('users.total', 1));
});

it('paginates users and keeps filters on the next page', function (): void {
    $admin = managementUser();
    User::factory()->count(21)->create(['name' => 'მოსაძებნი']);
    $this->actingAs($admin)->get('/admin/users?q='.urlencode('მოსაძებნი'))->assertInertia(fn (Assert $page) => $page
        ->has('users.data', 20)->where('users.total', 21)->where('users.last_page', 2)
        ->where('users.next_page_url', fn (string $url): bool => str_contains($url, 'page=2') && str_contains($url, 'q=')));
});

it('serves inherited and direct permissions without password data', function (): void {
    $admin = managementUser();
    $viewer = managementUser('viewer');
    $viewer->givePermissionTo('payments.import');
    $this->actingAs($admin)->get('/admin/users/'.$viewer->id.'/edit')->assertInertia(fn (Assert $page) => $page
        ->component('Admin/Users/Form')->where('user.role', 'viewer')->where('user.permissions', ['payments.import'])
        ->where('isSelf', false)->has('roles', 3)->has('permissionGroups', 5)->missing('user.password'));
    $this->get('/admin/users/'.$admin->id.'/edit')->assertInertia(fn (Assert $page) => $page
        ->where('isSelf', true)->where('user.role', 'admin'));
});

it('creates a user with a hashed password role and additional permissions', function (): void {
    $this->actingAs(managementUser())->post('/admin/users', managementInput(['permissions' => ['payments.import']]))
        ->assertSessionHasNoErrors()->assertRedirect('/admin/users');
    $user = User::query()->where('email', 'new-user@example.test')->firstOrFail();
    expect(Hash::check('New-user-password42!', $user->password))->toBeTrue()
        ->and($user->hasRole('viewer'))->toBeTrue()->and($user->can('payments.import'))->toBeTrue()
        ->and($user->can('clients.view'))->toBeTrue()->and($user->can('users.manage'))->toBeFalse();
});

it('supports individual permissions without an inherited role', function (): void {
    $this->actingAs(managementUser())->post('/admin/users', managementInput(['role' => null, 'permissions' => ['payments.view']]))
        ->assertSessionHasNoErrors();
    $user = User::query()->where('email', 'new-user@example.test')->firstOrFail();
    expect($user->roles()->count())->toBe(0)->and($user->can('payments.view'))->toBeTrue()
        ->and($user->can('clients.view'))->toBeFalse();
    $this->actingAs($user)->get('/payments')->assertOk();
    $this->get('/clients')->assertForbidden();
});

it('updates existing users without changing their password and removes revoked permissions', function (): void {
    $admin = managementUser();
    $target = managementUser('editor');
    $target->givePermissionTo('payments.import');
    $password = $target->password;
    $this->actingAs($admin)->patch('/admin/users/'.$target->id, [
        'name' => 'განახლებული სახელი', 'email' => $target->email, 'role' => 'viewer', 'permissions' => [],
    ])->assertSessionHasNoErrors()->assertRedirect('/admin/users');
    $target = $target->fresh();
    expect($target->name)->toBe('განახლებული სახელი')->and($target->password)->toBe($password)
        ->and($target->hasRole('viewer'))->toBeTrue()->and($target->can('payments.import'))->toBeFalse()
        ->and($target->can('clients.update'))->toBeFalse();
    $client = Client::factory()->create();
    $this->actingAs($target)->patch('/clients/'.$client->id, [])->assertForbidden();
});

it('does not store redundant direct permissions already inherited from the chosen role', function (): void {
    $this->actingAs(managementUser())->post('/admin/users', managementInput(['permissions' => ['clients.view', 'payments.import']]))
        ->assertSessionHasNoErrors();
    expect(User::query()->where('email', 'new-user@example.test')->firstOrFail()->permissions->pluck('name')->all())->toBe(['payments.import']);
});

it('validates account and permission changes in Georgian without creating an account', function (array $input, string $field): void {
    $this->actingAs(managementUser())->from('/admin/users/create')->post('/admin/users', managementInput($input))
        ->assertRedirect('/admin/users/create')->assertSessionHasErrors($field);
    expect(session('errors')->first($field))->toMatch('/[ა-ჰ]/u')
        ->and(User::query()->where('email', 'new-user@example.test')->exists())->toBeFalse();
})->with([
    [['name' => ''], 'name'], [['email' => 'invalid'], 'email'], [['email' => 'UPPER@example.test'], 'email'],
    [['role' => 'superuser'], 'role'], [['permissions' => 'all'], 'permissions'],
    [['permissions' => ['users.manage']], 'permissions.0'], [['permissions' => ['unknown']], 'permissions.0'],
    [['permissions' => ['payments.view', 'payments.view']], 'permissions.0'],
    [['password' => 'short', 'password_confirmation' => 'short'], 'password'],
    [['password_confirmation' => 'different'], 'password'],
]);

it('rejects duplicate emails and password replacement on an existing account', function (): void {
    $admin = managementUser();
    $target = managementUser('viewer');
    $this->actingAs($admin)->patch('/admin/users/'.$target->id, ['name' => $target->name,
        'email' => $admin->email, 'role' => 'viewer', 'permissions' => []])->assertSessionHasErrors('email');
    $this->patch('/admin/users/'.$target->id, ['name' => $target->name, 'email' => $target->email,
        'role' => 'viewer', 'permissions' => [], 'password' => 'Overridden42!'])->assertSessionHasErrors('password');
});

it('does not let an administrator change their own role or permissions', function (array $changes): void {
    $admin = managementUser();
    $this->actingAs($admin)->patch('/admin/users/'.$admin->id, array_replace([
        'name' => 'დაუშვებელი ცვლილება', 'email' => $admin->email, 'role' => 'admin', 'permissions' => [],
    ], $changes))->assertSessionHasErrors('role');
    expect($admin->fresh()->hasRole('admin'))->toBeTrue()->and($admin->fresh()->name)->toBe($admin->name);
})->with([[['role' => 'viewer']], [['role' => null]], [['permissions' => ['payments.import']]]]);

it('permits own account details to change while administrative access stays intact', function (): void {
    $admin = managementUser();
    $this->actingAs($admin)->patch('/admin/users/'.$admin->id, [
        'name' => 'ახალი სახელი', 'email' => $admin->email, 'role' => 'admin', 'permissions' => [],
    ])->assertSessionHasNoErrors();
    expect($admin->fresh()->name)->toBe('ახალი სახელი')->and($admin->fresh()->hasRole('admin'))->toBeTrue();
});

it('rechecks the acting administrator after waiting for a permission update transaction', function (): void {
    $admin = managementUser();
    $admin->load('roles.permissions');
    $target = managementUser('viewer');
    $admin->fresh()->syncRoles(['viewer']);
    expect(fn () => app(SaveUser::class)->handle(['name' => 'არ შეიცვალოს', 'email' => $target->email,
        'role' => 'admin', 'permissions' => []], $admin, $target))->toThrow(AuthorizationException::class);
    expect($target->fresh()->hasRole('viewer'))->toBeTrue();
});

it('protects the last administrator from deleting their account', function (): void {
    $admin = managementUser();
    $this->actingAs($admin)->from('/profile')->delete('/profile', ['password' => 'password'])
        ->assertStatus(405);
    expect($admin->fresh())->not->toBeNull();
    $this->assertAuthenticatedAs($admin);
});

it('denies self deletion even when another administrator exists', function (): void {
    $admin = managementUser();
    $other = managementUser();
    $this->actingAs($admin)->delete('/admin/users/'.$admin->id)->assertSessionHasErrors('user');
    expect($admin->fresh())->not->toBeNull()->and($other->fresh()->hasRole('admin'))->toBeTrue();
    $this->assertAuthenticatedAs($admin);
});

it('lets an administrator delete another account without logging out the administrator', function (): void {
    $admin = managementUser();
    $target = managementUser('viewer');
    $this->actingAs($admin)->delete('/admin/users/'.$target->id)->assertSessionHasNoErrors()->assertRedirect('/admin/users');
    expect($target->fresh())->toBeNull()->and($admin->fresh()->hasRole('admin'))->toBeTrue();
    $this->assertAuthenticatedAs($admin);
});

it('protects the last administrator from deletion through the admin endpoint', function (): void {
    $admin = managementUser();
    $this->actingAs($admin)->from('/admin/users')->delete('/admin/users/'.$admin->id)
        ->assertRedirect('/admin/users')->assertSessionHasErrors('user');
    expect($admin->fresh())->not->toBeNull();
    $this->assertAuthenticatedAs($admin);
});

it('denies self deletion to ordinary roles even with the correct password', function (string $role): void {
    $user = managementUser($role);
    $this->actingAs($user)->delete('/profile', ['password' => 'password'])->assertStatus(405);
    expect($user->fresh())->not->toBeNull();
    $this->assertAuthenticatedAs($user);
})->with(['editor', 'viewer']);

it('promotes only the explicitly named existing account and is repeatable', function (): void {
    $target = managementUser('editor');
    $this->artisan('users:admin', ['email' => $target->email])->assertSuccessful();
    $this->artisan('users:admin', ['email' => $target->email])->assertSuccessful();
    expect($target->fresh()->getRoleNames()->all())->toBe(['admin']);
    $this->artisan('users:admin', ['email' => 'missing@example.test'])->assertFailed();
    expect(User::query()->where('email', 'missing@example.test')->exists())->toBeFalse();
});

it('keeps administrator and regular roles separate when seeders run again', function (): void {
    $admin = managementUser();
    $this->seed(RolesAndPermissionsSeeder::class);
    expect($admin->fresh()->hasRole('admin'))->toBeTrue()
        ->and(managementUser('editor')->can('users.manage'))->toBeFalse()
        ->and(managementUser('viewer')->can('users.manage'))->toBeFalse();
});

it('preserves a promoted demo administrator when demo users are reseeded', function (): void {
    $user = User::factory()->create(['email' => 'editor@example.test']);
    $user->assignRole('admin');
    $repository = Env::getRepository();
    $saved = [];
    foreach (['DEMO_EDITOR_PASSWORD', 'DEMO_VIEWER_PASSWORD'] as $key) {
        $saved[$key] = $repository->get($key);
        $repository->clear($key);
        $repository->set($key, 'Test-seeder-password42!');
    }
    try {
        $this->seed(DemoUsersSeeder::class);
        expect($user->fresh()->hasRole('admin'))->toBeTrue();
    } finally {
        foreach ($saved as $key => $value) {
            $repository->clear($key);
            if ($value !== null) {
                $repository->set($key, $value);
            }
        }
    }
});

it('opens a permitted section on login for users with individual permissions', function (): void {
    $user = User::factory()->create();
    $user->givePermissionTo('payments.view');
    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/payments');
});
