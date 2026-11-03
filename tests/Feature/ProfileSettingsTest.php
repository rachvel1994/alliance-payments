<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
});

it('serves the current profile to both roles without exposing password attributes', function (string $role): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = User::factory()->create();
    $user->assignRole($role);

    $this->actingAs($user)->get('/profile')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Profile/Edit')->where('emailVerified', true)->where('roles', [config('access.roles.'.$role)])->missing('account')
        ->where('auth.user.id', $user->id)->missing('auth.user.password')->missing('auth.user.remember_token'));
})->with(['editor', 'viewer']);

it('reports the actual email verification state on the profile', function (): void {
    $user = User::factory()->unverified()->create();
    $this->actingAs($user)->get('/profile')->assertInertia(fn (Assert $page) => $page
        ->where('emailVerified', false)->where('roles', [])->missing('account'));
});

it('keeps profile data unchanged and returns Georgian validation errors', function (array $input, string $field): void {
    $user = User::factory()->create();
    User::factory()->create(['email' => 'occupied@example.test']);
    $this->actingAs($user)->from('/profile')->patch('/profile', array_replace([
        'name' => $user->name, 'email' => $user->email,
    ], $input))->assertRedirect('/profile')->assertSessionHasErrors($field);

    expect(session('errors')->first($field))->toMatch('/[ა-ჰ]/u')
        ->and($user->fresh()->name)->toBe($user->name)
        ->and($user->fresh()->email)->toBe($user->email);
})->with([
    'missing name' => [['name' => ''], 'name'],
    'long name' => [['name' => str_repeat('a', 256)], 'name'],
    'invalid email' => [['email' => 'invalid'], 'email'],
    'uppercase email' => [['email' => 'UPPER@example.test'], 'email'],
    'duplicate email' => [['email' => 'occupied@example.test'], 'email'],
]);

it('returns Georgian password errors without changing the stored password', function (array $input, string $field): void {
    $user = User::factory()->create();
    $original = $user->password;
    $this->actingAs($user)->from('/profile')->put('/password', array_replace([
        'current_password' => 'password', 'password' => 'a-new-password', 'password_confirmation' => 'a-new-password',
    ], $input))->assertRedirect('/profile')->assertSessionHasErrors($field);

    expect(session('errors')->first($field))->toMatch('/[ა-ჰ]/u')
        ->and($user->fresh()->password)->toBe($original);
})->with([
    'incorrect current password' => [['current_password' => 'wrong'], 'current_password'],
    'short new password' => [['password' => 'short', 'password_confirmation' => 'short'], 'password'],
    'confirmation mismatch' => [['password_confirmation' => 'different-password'], 'password'],
]);

it('keeps accounts when the removed profile deletion endpoint is called', function (string $password): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('admin');
    $this->actingAs($user)->from('/profile')->delete('/profile', ['password' => $password])
        ->assertStatus(405);

    expect($user->fresh())->not->toBeNull();
    $this->assertAuthenticatedAs($user);
})->with(['wrong-password', '']);
