<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    app()->setLocale('ka');
});

it('renders the authentication document with the Georgian language', function (): void {
    $this->get('/login')->assertOk()->assertSee('lang="ka"', false);
});

it('returns Georgian login validation messages with readable field names', function (array $input, string $field, string $message): void {
    $this->postJson('/login', $input)->assertUnprocessable()
        ->assertJsonValidationErrors($field)->assertJsonPath('errors.'.$field.'.0', $message);
    $this->assertGuest();
})->with([
    'missing email' => [['password' => 'password'], 'email', 'ველი „ელ. ფოსტა“ აუცილებელია.'],
    'invalid email' => [['email' => 'invalid', 'password' => 'password'], 'email', 'მიუთითე ელ. ფოსტის სწორი მისამართი.'],
    'invalid password type' => [['email' => 'user@example.test', 'password' => ['invalid']], 'password', 'პაროლი უნდა იყოს ტექსტი.'],
]);

it('returns a Georgian credentials error without authenticating the user', function (): void {
    $user = User::factory()->create();
    $this->postJson('/login', ['email' => $user->email, 'password' => 'wrong-password'])
        ->assertUnprocessable()->assertJsonPath('errors.email.0', 'ელ. ფოსტა ან პაროლი არასწორია.');
    $this->assertGuest();
});

it('translates the login lockout and replaces its time placeholder', function (): void {
    $this->freezeTime();
    $user = User::factory()->create();
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->postJson('/login', ['email' => $user->email, 'password' => 'wrong-password'])->assertUnprocessable();
    }
    $this->postJson('/login', ['email' => $user->email, 'password' => 'wrong-password'])
        ->assertUnprocessable()->assertJsonPath('errors.email.0', 'შესვლის მცდელობების რაოდენობა ამოიწურა. სცადე ხელახლა 60 წამში.');
    $this->assertGuest();
});

it('returns a Georgian reset-link status while dispatching the normal notification', function (): void {
    Notification::fake();
    $user = User::factory()->create();
    $this->from('/forgot-password')->post('/forgot-password', ['email' => $user->email])
        ->assertRedirect('/forgot-password')->assertSessionHas('status', 'პაროლის აღდგენის ბმული გამოგეგზავნა ელ. ფოსტაზე.');
    Notification::assertSentTo($user, ResetPassword::class);
});

it('returns a Georgian error for an unknown reset-link account', function (): void {
    Notification::fake();
    $this->postJson('/forgot-password', ['email' => 'missing@example.test'])
        ->assertUnprocessable()->assertJsonPath('errors.email.0', 'ამ ელ. ფოსტით ანგარიში ვერ მოიძებნა.');
    Notification::assertNothingSent();
});

it('renders password and verification emails in Georgian', function (): void {
    $user = User::factory()->create();
    $reset = (new ResetPassword('test-token'))->toMail($user);
    $verification = (new VerifyEmail)->toMail($user);
    $html = (string) $reset->render();
    expect($reset->subject)->toBe('პაროლის აღდგენის შეტყობინება')
        ->and($html)->toContain('მოგესალმებით!', 'პაროლის აღდგენა', 'პატივისცემით,', 'ყველა უფლება დაცულია.', 'დააკოპირე ქვემოთ მოცემული ბმული')
        ->not->toContain('Hello!', 'Regards,', 'All rights reserved.', ':count', ':actionText')
        ->and($verification->subject)->toBe('ელ. ფოსტის დადასტურება')
        ->and((string) $verification->render())->toContain('ელ. ფოსტის დასადასტურებლად გამოიყენე ქვემოთ მოცემული ღილაკი.');
});

it('rejects an invalid reset token in Georgian without changing the password', function (): void {
    $user = User::factory()->create();
    $original = $user->password;
    $this->postJson('/reset-password', [
        'email' => $user->email, 'token' => 'invalid-token',
        'password' => 'new-password', 'password_confirmation' => 'new-password',
    ])->assertUnprocessable()->assertJsonPath('errors.email.0', 'პაროლის აღდგენის ბმული არასწორია ან ვადა გაუვიდა.');
    expect($user->fresh()->password)->toBe($original);
});

it('resets the password and returns a Georgian success status', function (): void {
    $user = User::factory()->create();
    $token = Password::createToken($user);
    $this->post('/reset-password', [
        'email' => $user->email, 'token' => $token,
        'password' => 'new-password', 'password_confirmation' => 'new-password',
    ])->assertRedirect(route('login'))->assertSessionHas('status', 'პაროლი წარმატებით შეიცვალა.');
    expect(Hash::check('new-password', $user->fresh()->password))->toBeTrue();
});

it('validates password recovery values in Georgian before calling the broker', function (array $changes, string $field, string $message): void {
    $user = User::factory()->create();
    $this->postJson('/reset-password', array_replace([
        'email' => $user->email, 'token' => 'test-token',
        'password' => 'new-password', 'password_confirmation' => 'new-password',
    ], $changes))->assertUnprocessable()->assertJsonPath('errors.'.$field.'.0', $message);
    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
})->with([
    'short password' => [['password' => 'short', 'password_confirmation' => 'short'], 'password', 'პაროლი უნდა შეიცავდეს მინიმუმ 8 სიმბოლოს.'],
    'confirmation mismatch' => [['password_confirmation' => 'different'], 'password', 'პაროლი და მისი დადასტურება არ ემთხვევა.'],
    'token array' => [['token' => ['invalid']], 'token', 'აღდგენის კოდი უნდა იყოს ტექსტი.'],
    'password array' => [['password' => ['invalid']], 'password', 'პაროლი უნდა იყოს ტექსტი.'],
]);

it('validates password confirmation in Georgian without accepting malformed input', function (array $input, string $message): void {
    $user = User::factory()->create();
    $this->actingAs($user)->postJson('/confirm-password', $input)
        ->assertUnprocessable()->assertJsonPath('errors.password.0', $message);
    $this->assertAuthenticatedAs($user);
    expect(session()->has('auth.password_confirmed_at'))->toBeFalse();
})->with([
    'missing' => [[], 'ველი „პაროლი“ აუცილებელია.'],
    'array' => [['password' => ['invalid']], 'პაროლი უნდა იყოს ტექსტი.'],
    'incorrect' => [['password' => 'incorrect-password'], 'პაროლი არასწორია.'],
]);

it('renders the production not-found page in Georgian', function (): void {
    config(['app.debug' => false]);
    $this->get('/missing-authentication-page')->assertNotFound()->assertSee('გვერდი ვერ მოიძებნა');
});
