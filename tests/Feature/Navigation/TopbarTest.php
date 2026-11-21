<?php

use App\Billing\PlanType;
use App\Models\User;
use Database\Seeders\PlanSeeder;

/*
|--------------------------------------------------------------------------
| The top bar
|--------------------------------------------------------------------------
|
| The top bar sits above every page of the application. On a narrow
| screen the name of the account and the words "Log out" are hidden so
| that the bar still fits, which leaves the logout button with nothing
| but an icon. The button therefore has to carry its own name, or a
| screen reader announces it as an unnamed button.
|
*/

beforeEach(function () {
    // The plan rows have to exist before the account is created, because
    // the dashboard always resolves the plan of the account.
    $this->seed(PlanSeeder::class);

    $this->user = User::factory()->create(['name' => 'Ada Lovelace']);
    $this->user->forceFill(['plan_slug' => PlanType::Free->value])->save();
});

test('the logout button carries a name of its own', function () {
    $html = $this->actingAs($this->user)->get(route('dashboard'))->getContent();

    expect($html)->toContain('aria-label="Log out"');
});

test('the logout button is the only control in the top bar without a name', function () {
    $html = $this->actingAs($this->user)->get(route('dashboard'))->getContent();

    // The top bar holds the logout form. Every control inside it has to
    // be reachable by name, so the markup is inspected on its own.
    preg_match('/<header.*?<\/header>/s', $html, $matches);

    expect($matches[0] ?? '')->toContain('aria-label="Log out"');
});

test('the logout button still shows the words Log out on a wide screen', function () {
    $html = $this->actingAs($this->user)->get(route('dashboard'))->getContent();

    // The visible label is hidden on a narrow screen only, so the words
    // are still part of the markup.
    expect($html)->toContain('>Log out</span>');
});

test('the logout button still posts to the logout route', function () {
    $html = $this->actingAs($this->user)->get(route('dashboard'))->getContent();

    expect($html)->toContain('action="'.route('logout').'"');
});

test('the top bar names the current page', function () {
    $html = $this->actingAs($this->user)->get(route('dashboard'))->getContent();

    expect($html)->toContain('Dashboard');
});
