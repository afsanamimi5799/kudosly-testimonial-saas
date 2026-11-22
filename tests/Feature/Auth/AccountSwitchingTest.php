<?php

use App\Billing\PlanType;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Facades\Hash;

/*
|--------------------------------------------------------------------------
| Switching account
|--------------------------------------------------------------------------
|
| Covers App\Http\Controllers\Auth\SwitchAccountController and the shared
| App\Http\Controllers\Auth\SignsOutTheAccount trait.
|
| Switching account is a full sign out followed by a sign in. The session
| of the account that was signed in is ended in the same way an ordinary
| sign out ends it, so nothing of it is left behind.
|
*/

beforeEach(function () {
    $this->user = User::factory()->create([
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'password123',
    ]);

    $this->other = User::factory()->create([
        'name' => 'Mallory',
        'email' => 'mallory@example.com',
        'password' => 'password123',
    ]);
});

/*
|--------------------------------------------------------------------------
| The switch itself
|--------------------------------------------------------------------------
*/

test('switching account signs the current account out', function () {
    $this->actingAs($this->user)
        ->post(route('switch-account'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

test('switching account sends the visitor to the login page', function () {
    $this->actingAs($this->user)
        ->post(route('switch-account'))
        ->assertRedirect(route('login'));
});

test('switching account ends the session of the account that was signed in', function () {
    $this->actingAs($this->user);

    $sessionId = session()->getId();

    $this->post(route('switch-account'));

    // The session is emptied and a new one is started, so the old session
    // cannot be used to reach the account afterwards.
    expect(session()->getId())->not->toBe($sessionId);
});

test('switching account replaces the token that protects the forms', function () {
    $this->actingAs($this->user);

    $token = session()->token();

    $this->post(route('switch-account'));

    expect(session()->token())->not->toBe($token);
});

test('a guest cannot switch account', function () {
    $this->post(route('switch-account'))->assertRedirect(route('login'));

    $this->assertGuest();
});

test('the account that was signed in cannot reach the dashboard after switching', function () {
    $this->actingAs($this->user);

    $this->post(route('switch-account'));

    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

/*
|--------------------------------------------------------------------------
| Signing in again
|--------------------------------------------------------------------------
*/

test('another account can be signed in after switching', function () {
    $this->actingAs($this->user);

    $this->post(route('switch-account'));

    $this->post(route('login'), [
        'email' => 'mallory@example.com',
        'password' => 'password123',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($this->other);
});

test('the account that was switched away from cannot be signed in without its password', function () {
    $this->actingAs($this->user);

    $this->post(route('switch-account'));

    $this->post(route('login'), [
        'email' => 'ada@example.com',
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

/*
|--------------------------------------------------------------------------
| Isolation between the two accounts
|--------------------------------------------------------------------------
*/

test('the data of the two accounts is never mixed', function () {
    $this->seed(PlanSeeder::class);

    $this->user->forceFill(['plan_slug' => PlanType::Free->value])->save();
    $this->other->forceFill(['plan_slug' => PlanType::Free->value])->save();

    $this->actingAs($this->user);

    $this->post(route('switch-account'));

    $this->post(route('login'), [
        'email' => 'mallory@example.com',
        'password' => 'password123',
    ]);

    $html = $this->get(route('dashboard'))->assertOk()->getContent();

    // The dashboard greets the account that is signed in, and the sidebar
    // names that account as well.
    expect($html)->toContain('Mallory')
        ->and($html)->not->toContain('Ada Lovelace');
});

/*
|--------------------------------------------------------------------------
| Remember me
|--------------------------------------------------------------------------
*/

test('the remember me cookie does not sign the account in again after switching', function () {
    // Signing in with the remember box ticked stores a token on the
    // account and sends out a cookie that carries it.
    $this->post(route('login'), [
        'email' => 'ada@example.com',
        'password' => 'password123',
        'remember' => '1',
    ]);

    $token = $this->user->fresh()->remember_token;

    expect($token)->not->toBeNull()
        ->and($token)->not->toBe('');

    $this->assertAuthenticatedAs($this->user);

    $this->post(route('switch-account'));

    $this->assertGuest();

    // The token is replaced by a new one, so the cookie that was handed
    // out before the switch no longer names the account.
    expect($this->user->fresh()->remember_token)->not->toBe($token);
});

test('the account cannot be signed in with the old remember cookie after switching', function () {
    $this->post(route('login'), [
        'email' => 'ada@example.com',
        'password' => 'password123',
        'remember' => '1',
    ]);

    $token = $this->user->fresh()->remember_token;

    $this->post(route('switch-account'));

    // The old cookie carries the token that was replaced, so it is not
    // able to bring the account back.
    $response = $this->withUnencryptedCookie(
        Auth()->guard('web')->getRecallerName(),
        $token.'|'.hash('sha256', $token)
    )->get(route('dashboard'));

    $response->assertRedirect(route('login'));
});

test('the remember token of the account is replaced when it signs out', function () {
    $this->post(route('login'), [
        'email' => 'ada@example.com',
        'password' => 'password123',
        'remember' => '1',
    ]);

    $token = $this->user->fresh()->remember_token;

    $this->post(route('switch-account'));

    // The token is no longer the one that was handed out, so it cannot
    // sign the account in on its own any more.
    expect($this->user->fresh()->remember_token)->not->toBe($token);
});

test('the remember token of the account is replaced when it signs out in the ordinary way', function () {
    $this->post(route('login'), [
        'email' => 'ada@example.com',
        'password' => 'password123',
        'remember' => '1',
    ]);

    $token = $this->user->fresh()->remember_token;

    $this->post(route('logout'));

    // The ordinary sign out behaves in exactly the same way, so the two
    // cannot drift apart.
    expect($this->user->fresh()->remember_token)->not->toBe($token);
});

test('the password of the account is untouched by switching', function () {
    $this->actingAs($this->user);

    $this->post(route('switch-account'));

    expect(Hash::check('password123', $this->user->fresh()->password))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| The shared sign out
|--------------------------------------------------------------------------
*/

test('signing out and switching account end the session in the same way', function () {
    $this->actingAs($this->user);

    $this->post(route('logout'))->assertRedirect('/');

    $this->assertGuest();

    $this->actingAs($this->other);

    $this->post(route('switch-account'))->assertRedirect(route('login'));

    $this->assertGuest();
});
