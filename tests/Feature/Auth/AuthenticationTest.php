<?php

use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Session authentication
|--------------------------------------------------------------------------
|
| Covers App\Http\Controllers\Auth\AuthenticatedSessionController and
| App\Http\Requests\Auth\LoginRequest, including the login rate limiter.
|
*/

test('the login page can be rendered', function () {
    $this->get(route('login'))->assertOk();
});

test('a user can log in with valid credentials', function () {
    $user = User::factory()->create([
        'email' => 'ada@example.com',
        'password' => 'password123',
    ]);

    $this->post(route('login'), [
        'email' => 'ada@example.com',
        'password' => 'password123',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
});

test('login fails with an incorrect password', function () {
    User::factory()->create([
        'email' => 'ada@example.com',
        'password' => 'password123',
    ]);

    $this->post(route('login'), [
        'email' => 'ada@example.com',
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('login fails with an unknown email address', function () {
    $this->post(route('login'), [
        'email' => 'nobody@example.com',
        'password' => 'password123',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('login requires an email address and a password', function () {
    $this->post(route('login'), [])
        ->assertSessionHasErrors(['email', 'password']);

    $this->assertGuest();
});

test('login is throttled after too many failed attempts', function () {
    User::factory()->create([
        'email' => 'ada@example.com',
        'password' => 'password123',
    ]);

    for ($attempt = 1; $attempt <= LoginRequest::MAX_ATTEMPTS; $attempt++) {
        $this->post(route('login'), [
            'email' => 'ada@example.com',
            'password' => 'wrong-password',
        ]);
    }

    $response = $this->post(route('login'), [
        'email' => 'ada@example.com',
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrors('email');

    $this->assertGuest();

    // The correct password is still rejected while the throttle is active.
    $this->post(route('login'), [
        'email' => 'ada@example.com',
        'password' => 'password123',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('a guest is redirected to the login page when visiting the dashboard', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('a guest is redirected to the login page when visiting the profile page', function () {
    $this->get(route('profile.edit'))->assertRedirect(route('login'));
});

test('an authenticated user is redirected away from the login page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('login'))
        ->assertRedirect(route('dashboard'));
});

test('an authenticated user can log out', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('logout'))
        ->assertRedirect('/');

    $this->assertGuest();
});

test('a guest cannot log out', function () {
    $this->post(route('logout'))->assertRedirect(route('login'));
});
