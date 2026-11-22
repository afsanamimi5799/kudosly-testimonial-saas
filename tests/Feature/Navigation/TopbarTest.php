<?php

use App\Billing\PlanType;
use App\Models\User;
use Database\Seeders\PlanSeeder;

/*
|--------------------------------------------------------------------------
| The top bar
|--------------------------------------------------------------------------
|
| The top bar sits above every page of the application. It names the
| account that is signed in. The sign out used to live here as well, but
| it now belongs to the account menu at the bottom of the sidebar, so
| the bar holds nothing but the name of the page and the name of the
| account.
|
*/

beforeEach(function () {
    // The plan rows have to exist before the account is created, because
    // the dashboard always resolves the plan of the account.
    $this->seed(PlanSeeder::class);

    $this->user = User::factory()->create(['name' => 'Ada Lovelace']);
    $this->user->forceFill(['plan_slug' => PlanType::Free->value])->save();
});

test('the top bar no longer holds a logout button', function () {
    $html = $this->actingAs($this->user)->get(route('dashboard'))->getContent();

    // The top bar is inspected on its own, so the sign out of the
    // sidebar menu cannot hide a leftover button here.
    preg_match('/<header.*?<\/header>/s', $html, $matches);

    $header = $matches[0] ?? '';

    expect($header)->not->toContain('Log out')
        ->and($header)->not->toContain('aria-label="Log out"')
        ->and($header)->not->toContain('action="'.route('logout').'"');
});

test('the sign out is still reachable from the sidebar account menu', function () {
    $html = $this->actingAs($this->user)->get(route('dashboard'))->getContent();

    expect($html)->toContain('action="'.route('logout').'"', false)
        ->and($html)->toContain('Log out');
});

test('the top bar names the current page', function () {
    $html = $this->actingAs($this->user)->get(route('dashboard'))->getContent();

    expect($html)->toContain('Dashboard');
});
