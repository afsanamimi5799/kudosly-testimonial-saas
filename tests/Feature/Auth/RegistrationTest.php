<?php

use App\Billing\PlanType;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Registration
|--------------------------------------------------------------------------
|
| Covers App\Http\Controllers\Auth\RegisteredUserController and
| App\Http\Requests\Auth\RegisterRequest.
|
*/

test('the registration page can be rendered', function () {
    $this->get(route('register'))->assertOk();
});

test('a user can register and is sent to the dashboard', function () {
    $response = $this->post(route('register'), [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertRedirect(route('dashboard', absolute: false));

    $user = User::query()->where('email', 'ada@example.com')->sole();

    expect($user->name)->toBe('Ada Lovelace')
        ->and($user->plan_slug)->toBe(PlanType::Free->value);

    $this->assertAuthenticatedAs($user);
});

test('registration always assigns the free plan regardless of submitted input', function () {
    $this->post(route('register'), [
        'name' => 'Grace Hopper',
        'email' => 'grace@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'plan_slug' => PlanType::Pro->value,
        'stripe_id' => 'cus_forged',
    ]);

    $user = User::query()->where('email', 'grace@example.com')->sole();

    expect($user->plan_slug)->toBe(PlanType::Free->value)
        ->and($user->stripe_id)->toBeNull();
});

test('registration lowercases and trims the email address', function () {
    $this->post(route('register'), [
        'name' => 'Alan Turing',
        'email' => '  Alan@Example.COM  ',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $user = User::query()->where('email', 'alan@example.com')->sole();

    expect($user->email)->toBe('alan@example.com');
});

test('registration requires a name', function () {
    $this->post(route('register'), [
        'name' => '',
        'email' => 'noname@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertSessionHasErrors('name');

    expect(User::query()->where('email', 'noname@example.com')->exists())->toBeFalse();
});

test('registration requires a valid email address', function () {
    $this->post(route('register'), [
        'name' => 'Bad Email',
        'email' => 'not-an-email',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertSessionHasErrors('email');

    expect(User::query()->count())->toBe(0);
});

test('registration rejects a duplicate email address', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->post(route('register'), [
        'name' => 'Impostor',
        'email' => 'taken@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertSessionHasErrors('email');

    expect(User::query()->where('email', 'taken@example.com')->count())->toBe(1);
});

test('registration rejects a duplicate email regardless of case', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->post(route('register'), [
        'name' => 'Impostor',
        'email' => 'TAKEN@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertSessionHasErrors('email');
});

test('registration requires a confirmed password', function () {
    $this->post(route('register'), [
        'name' => 'No Confirm',
        'email' => 'noconfirm@example.com',
        'password' => 'password123',
        'password_confirmation' => 'different123',
    ])->assertSessionHasErrors('password');
});

test('registration rejects a password shorter than eight characters', function () {
    $this->post(route('register'), [
        'name' => 'Short Pass',
        'email' => 'short@example.com',
        'password' => 'short',
        'password_confirmation' => 'short',
    ])->assertSessionHasErrors('password');
});

test('an authenticated user is redirected away from the registration page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('register'))
        ->assertRedirect(route('dashboard'));
});
