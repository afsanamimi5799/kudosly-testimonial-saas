<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

/*
|--------------------------------------------------------------------------
| Password reset
|--------------------------------------------------------------------------
|
| Covers App\Http\Controllers\Auth\PasswordResetLinkController and
| App\Http\Controllers\Auth\NewPasswordController.
|
*/

test('the forgot password page can be rendered', function () {
    $this->get(route('password.request'))->assertOk();
});

test('a reset link is sent for a known email address', function () {
    Notification::fake();

    $user = User::factory()->create(['email' => 'ada@example.com']);

    $this->post(route('password.email'), [
        'email' => 'ada@example.com',
    ])->assertSessionHasNoErrors();

    Notification::assertSentTo($user, ResetPassword::class);
});

test('a reset link request does not reveal whether the account exists', function () {
    Notification::fake();

    $this->post(route('password.email'), [
        'email' => 'nobody@example.com',
    ])->assertSessionHasNoErrors();

    Notification::assertNothingSent();
});

test('a reset link request requires an email address', function () {
    Notification::fake();

    $this->post(route('password.email'), [
        'email' => '',
    ])->assertSessionHasErrors('email');

    Notification::assertNothingSent();
});

test('the reset form can be rendered with a token', function () {
    $this->get(route('password.reset', ['token' => 'a-token', 'email' => 'ada@example.com']))
        ->assertOk()
        ->assertSee('a-token', escape: false);
});

test('a valid token resets the password', function () {
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'ada@example.com',
        'password' => 'password123',
    ]);

    $this->post(route('password.email'), ['email' => 'ada@example.com']);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        $response = $this->post(route('password.store'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('login'));

        return true;
    });

    expect(Hash::check('new-password123', $user->fresh()->password))->toBeTrue()
        ->and(Hash::check('password123', $user->fresh()->password))->toBeFalse();
});

test('an invalid token is rejected', function () {
    $user = User::factory()->create([
        'email' => 'ada@example.com',
        'password' => 'password123',
    ]);

    $this->post(route('password.store'), [
        'token' => 'not-a-real-token',
        'email' => 'ada@example.com',
        'password' => 'new-password123',
        'password_confirmation' => 'new-password123',
    ])->assertSessionHasErrors('email');

    expect(Hash::check('password123', $user->fresh()->password))->toBeTrue();
});

test('an expired token is rejected', function () {
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'ada@example.com',
        'password' => 'password123',
    ]);

    $this->post(route('password.email'), ['email' => 'ada@example.com']);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        // Expire the token that was just issued.
        $this->travel(config('auth.passwords.users.expire') + 1)->minutes();

        $this->post(route('password.store'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ])->assertSessionHasErrors('email');

        return true;
    });

    expect(Hash::check('password123', $user->fresh()->password))->toBeTrue();
});

test('the new password must be confirmed', function () {
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'ada@example.com',
        'password' => 'password123',
    ]);

    $this->post(route('password.email'), ['email' => 'ada@example.com']);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        $this->post(route('password.store'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'new-password123',
            'password_confirmation' => 'different-password123',
        ])->assertSessionHasErrors('password');

        return true;
    });

    expect(Hash::check('password123', $user->fresh()->password))->toBeTrue();
});

test('the new password must be at least eight characters', function () {
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'ada@example.com',
        'password' => 'password123',
    ]);

    $this->post(route('password.email'), ['email' => 'ada@example.com']);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        $this->post(route('password.store'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');

        return true;
    });

    expect(Hash::check('password123', $user->fresh()->password))->toBeTrue();
});

test('the reset requires a token an email address and a password', function () {
    $this->post(route('password.store'), [])
        ->assertSessionHasErrors(['token', 'email', 'password']);
});

test('a guest can reach the forgot password and reset password pages', function () {
    $this->get(route('password.request'))->assertOk();
    $this->get(route('password.reset', ['token' => 'a-token']))->assertOk();
});
