<?php

use App\Billing\PlanType;
use App\Models\User;
use Database\Seeders\PlanSeeder;

/*
|--------------------------------------------------------------------------
| The sidebar account menu
|--------------------------------------------------------------------------
|
| Covers the account section at the bottom of the sidebar: the disclosure
| button that opens it, the three entries it holds, and the way the
| top bar no longer carries a sign out of its own.
|
*/

beforeEach(function () {
    $this->seed(PlanSeeder::class);

    $this->user = User::factory()->create(['name' => 'Ada Lovelace']);
    $this->user->forceFill(['plan_slug' => PlanType::Free->value])->save();
});

/*
|--------------------------------------------------------------------------
| The button
|--------------------------------------------------------------------------
*/

test('the account section is a button that opens a menu', function () {
    $html = $this->actingAs($this->user)->get(route('dashboard'))->getContent();

    expect($html)->toContain('id="sidebar-account-trigger"', false)
        ->and($html)->toContain('id="sidebar-account-dropdown"', false)
        ->and($html)->toContain('aria-expanded="false"', false)
        ->and($html)->toContain('aria-controls="sidebar-account-dropdown"', false);
});

test('the account button names the account that is signed in', function () {
    $html = $this->actingAs($this->user)->get(route('dashboard'))->getContent();

    expect($html)->toContain('Ada Lovelace')
        ->and($html)->toContain('Signed in');
});

test('the account button shows the initial of the name', function () {
    $html = $this->actingAs($this->user)->get(route('dashboard'))->getContent();

    // The account is called Ada Lovelace, so the initial is an A.
    expect($this->user->profileInitial())->toBe('A');

    // The initial sits in the round shape beside the name of the account.
    preg_match('/rounded-full[^>]*>\s*([A-Z])\s*</s', $html, $matches);

    expect($matches[1] ?? '')->toBe('A');
});

/*
|--------------------------------------------------------------------------
| The entries
|--------------------------------------------------------------------------
*/

test('the menu holds my profile switch account and log out', function () {
    $html = $this->actingAs($this->user)->get(route('dashboard'))->getContent();

    expect($html)->toContain('My Profile')
        ->and($html)->toContain('Switch Account')
        ->and($html)->toContain('Log out');
});

test('my profile leads to the settings page', function () {
    $html = $this->actingAs($this->user)->get(route('dashboard'))->getContent();

    expect($html)->toContain('id="sidebar-account-profile"', false)
        ->and($html)->toContain('href="'.route('profile.edit').'"', false);
});

test('switch account posts to the switch account route', function () {
    $html = $this->actingAs($this->user)->get(route('dashboard'))->getContent();

    expect($html)->toContain('action="'.route('switch-account').'"', false);
});

test('log out posts to the logout route', function () {
    $html = $this->actingAs($this->user)->get(route('dashboard'))->getContent();

    expect($html)->toContain('action="'.route('logout').'"', false);
});

test('the menu is hidden until it is opened', function () {
    $html = $this->actingAs($this->user)->get(route('dashboard'))->getContent();

    preg_match('/id="sidebar-account-dropdown"[^>]*class="([^"]*)"/', $html, $matches);

    expect($matches[1] ?? '')->toContain('hidden');
});

/*
|--------------------------------------------------------------------------
| The script
|--------------------------------------------------------------------------
*/

test('the menu is driven by a script that closes it on escape and on an outside click', function () {
    $html = $this->actingAs($this->user)->get(route('dashboard'))->getContent();

    expect($html)->toContain("event.key === 'Escape'")
        ->and($html)->toContain('root.contains(event.target)')
        ->and($html)->toContain("dropdown.classList.add('hidden')")
        ->and($html)->toContain("trigger.setAttribute('aria-expanded', 'true')");
});

test('the script puts the focus back on the button when the menu is closed with escape', function () {
    $html = $this->actingAs($this->user)->get(route('dashboard'))->getContent();

    expect($html)->toContain('close(true)')
        ->and($html)->toContain('trigger.focus()');
});

/*
|--------------------------------------------------------------------------
| The top bar
|--------------------------------------------------------------------------
*/

test('the top bar no longer carries a sign out of its own', function () {
    $html = $this->actingAs($this->user)->get(route('dashboard'))->getContent();

    preg_match('/<header.*?<\/header>/s', $html, $matches);

    $header = $matches[0] ?? '';

    expect($header)->not->toContain('Log out')
        ->and($header)->not->toContain('route(\'logout\')');
});

test('the top bar still shows the name of the account', function () {
    $html = $this->actingAs($this->user)->get(route('dashboard'))->getContent();

    expect($html)->toContain('Ada Lovelace');
});

/*
|--------------------------------------------------------------------------
| The script
|--------------------------------------------------------------------------
*/

test('the sidebar is drawn on every page of the application', function (string $routeName) {
    $this->actingAs($this->user)
        ->get(route($routeName))
        ->assertOk()
        ->assertSee('id="sidebar-account-trigger"', false);
})->with([
    'spaces' => 'spaces.index',
    'inbox' => 'inbox.index',
    'wall of love' => 'wall-of-love.index',
    'widgets' => 'widgets.index',
    'billing' => 'billing.index',
    'settings' => 'profile.edit',
]);
