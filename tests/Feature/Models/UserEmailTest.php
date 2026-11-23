<?php

use App\Models\User;

/*
|--------------------------------------------------------------------------
| User email address
|--------------------------------------------------------------------------
|
| The email address of an account is stored in a canonical form, so that a
| sign in, a password reset and a profile update all look the same account
| up the same way.
|
*/

test('an email address is stored in lower case', function () {
    $user = User::factory()->create(['email' => 'ada@example.com']);

    $user->email = 'ADA@Example.com';
    $user->save();

    expect($user->fresh()->email)->toBe('ada@example.com');
});

test('an email address is stored without surrounding spaces', function () {
    $user = User::factory()->create(['email' => 'ada@example.com']);

    $user->email = '  ada@example.com  ';
    $user->save();

    expect($user->fresh()->email)->toBe('ada@example.com');
});

test('an email address is canonical as it is created', function () {
    $user = new User;

    $user->name = 'Ada';
    $user->email = '  ADA@Example.com ';
    $user->password = 'password123';

    expect($user->email)->toBe('ada@example.com');
});
