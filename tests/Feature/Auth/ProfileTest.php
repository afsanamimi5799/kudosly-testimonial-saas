<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

/*
|--------------------------------------------------------------------------
| Profile management
|--------------------------------------------------------------------------
|
| Covers App\Http\Controllers\ProfileController, App\Http\Requests\
| ProfileUpdateRequest and App\Http\Requests\PasswordUpdateRequest.
|
*/

test('the profile page can be rendered by an authenticated user', function () {
    $user = User::factory()->create(['name' => 'Ada Lovelace']);

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('Ada Lovelace');
});

test('the profile information can be updated', function () {
    $user = User::factory()->create([
        'name' => 'Old Name',
        'email' => 'old@example.com',
    ]);

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'New Name',
            'email' => 'new@example.com',
        ])
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHas('status', 'profile-updated');

    expect($user->fresh()->name)->toBe('New Name')
        ->and($user->fresh()->email)->toBe('new@example.com');
});

test('the profile email is lowercased and trimmed on update', function () {
    $user = User::factory()->create(['email' => 'old@example.com']);

    $this->actingAs($user)->patch(route('profile.update'), [
        'name' => 'Ada Lovelace',
        'email' => '  NEW@Example.COM  ',
    ]);

    expect($user->fresh()->email)->toBe('new@example.com');
});

test('a user can keep their own email address unchanged', function () {
    $user = User::factory()->create(['email' => 'ada@example.com']);

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
        ])
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHasNoErrors();
});

test('the profile email cannot be changed to an address used by another user', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $user = User::factory()->create(['email' => 'ada@example.com']);

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Ada Lovelace',
            'email' => 'taken@example.com',
        ])
        ->assertSessionHasErrors('email');

    expect($user->fresh()->email)->toBe('ada@example.com');
});

test('the profile name is required on update', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => '',
            'email' => $user->email,
        ])
        ->assertSessionHasErrors('name');
});

test('the password can be updated with the current password', function () {
    $user = User::factory()->create([
        'email' => 'ada@example.com',
        'password' => 'password123',
    ]);

    $this->actingAs($user)
        ->put(route('password.update'), [
            'current_password' => 'password123',
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ])
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHas('status', 'password-updated');

    expect(Hash::check('new-password123', $user->fresh()->password))->toBeTrue();
});

test('the password cannot be updated without the correct current password', function () {
    $user = User::factory()->create([
        'email' => 'ada@example.com',
        'password' => 'password123',
    ]);

    $this->actingAs($user)
        ->put(route('password.update'), [
            'current_password' => 'wrong-password',
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ])
        ->assertSessionHasErrors('current_password');

    expect(Hash::check('password123', $user->fresh()->password))->toBeTrue();
});

test('the new password must be confirmed', function () {
    $user = User::factory()->create([
        'email' => 'ada@example.com',
        'password' => 'password123',
    ]);

    $this->actingAs($user)
        ->put(route('password.update'), [
            'current_password' => 'password123',
            'password' => 'new-password123',
            'password_confirmation' => 'different-password123',
        ])
        ->assertSessionHasErrors('password');
});

test('the new password must be at least eight characters', function () {
    $user = User::factory()->create([
        'email' => 'ada@example.com',
        'password' => 'password123',
    ]);

    $this->actingAs($user)
        ->put(route('password.update'), [
            'current_password' => 'password123',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])
        ->assertSessionHasErrors('password');
});

test('a guest cannot reach the profile page', function () {
    $this->get(route('profile.edit'))->assertRedirect(route('login'));
});

test('a guest cannot update the profile', function () {
    $this->patch(route('profile.update'), [
        'name' => 'Mallory',
        'email' => 'mallory@example.com',
    ])->assertRedirect(route('login'));
});

test('a guest cannot update the password', function () {
    $this->put(route('password.update'), [
        'current_password' => 'password123',
        'password' => 'new-password123',
        'password_confirmation' => 'new-password123',
    ])->assertRedirect(route('login'));
});
