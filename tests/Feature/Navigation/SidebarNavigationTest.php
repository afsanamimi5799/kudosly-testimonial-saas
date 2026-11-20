<?php

use App\Billing\PlanType;
use App\Enums\TestimonialStatus;
use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;
use App\Models\Widget;
use Database\Seeders\PlanSeeder;

/*
|--------------------------------------------------------------------------
| Sidebar navigation
|--------------------------------------------------------------------------
|
| Covers the five sidebar pages: Spaces, Inbox, Wall of Love, Widgets and
| Billing. Every page must load for an authenticated user, must be behind
| the authentication middleware, and must never expose another account's
| records.
|
*/

beforeEach(function () {
    $this->seed(PlanSeeder::class);

    $this->user = User::factory()->create(['name' => 'Ada Lovelace']);
    $this->user->forceFill(['plan_slug' => PlanType::Free->value])->save();

    $this->other = User::factory()->create(['name' => 'Mallory']);
    $this->other->forceFill(['plan_slug' => PlanType::Free->value])->save();
});

/*
|--------------------------------------------------------------------------
| The pages load
|--------------------------------------------------------------------------
*/

test('every sidebar page loads for an authenticated user', function (string $routeName) {
    $this->actingAs($this->user)
        ->get(route($routeName))
        ->assertOk();
})->with([
    'spaces' => 'spaces.index',
    'inbox' => 'inbox.index',
    'wall of love' => 'wall-of-love.index',
    'widgets' => 'widgets.index',
    'billing' => 'billing.index',
]);

test('every sidebar page redirects a guest to the login page', function (string $routeName) {
    $this->get(route($routeName))->assertRedirect(route('login'));
})->with([
    'spaces' => 'spaces.index',
    'inbox' => 'inbox.index',
    'wall of love' => 'wall-of-love.index',
    'widgets' => 'widgets.index',
    'billing' => 'billing.index',
]);

test('the sidebar renders every link without a soon badge', function () {
    $response = $this->actingAs($this->user)->get(route('dashboard'));

    $response->assertOk()
        ->assertSee('Overview')
        ->assertSee('Spaces')
        ->assertSee('Inbox')
        ->assertSee('Wall of Love')
        ->assertSee('Widgets')
        ->assertSee('Billing')
        ->assertSee('Settings')
        ->assertDontSee('Soon');
});

test('the sidebar links to every page', function () {
    $response = $this->actingAs($this->user)->get(route('dashboard'));

    $response->assertOk()
        ->assertSee(route('spaces.index'), false)
        ->assertSee(route('inbox.index'), false)
        ->assertSee(route('wall-of-love.index'), false)
        ->assertSee(route('widgets.index'), false)
        ->assertSee(route('billing.index'), false)
        ->assertSee(route('profile.edit'), false);
});

test('every sidebar page renders inside the application layout', function (string $routeName) {
    $response = $this->actingAs($this->user)->get(route($routeName));

    $response->assertOk();

    // The layout wrapper must supply the document, the sidebar and the
    // compiled stylesheet. Without it the page renders as bare HTML.
    $html = $response->getContent();

    expect($html)->toContain('<!DOCTYPE html>')
        ->and($html)->toContain('<aside')
        ->and($html)->toContain('app-sidebar-toggle')
        ->and($html)->toMatch('/<link[^>]+rel="stylesheet"[^>]*>/');
})->with([
    'spaces' => 'spaces.index',
    'inbox' => 'inbox.index',
    'wall of love' => 'wall-of-love.index',
    'widgets' => 'widgets.index',
    'billing' => 'billing.index',
]);

test('the dashboard still renders inside the application layout', function () {
    $response = $this->actingAs($this->user)->get(route('dashboard'));

    $response->assertOk();

    $html = $response->getContent();

    expect($html)->toContain('<!DOCTYPE html>')
        ->and($html)->toContain('<aside')
        ->and($html)->toMatch('/<link[^>]+rel="stylesheet"[^>]*>/');
});

/*
|--------------------------------------------------------------------------
| Active highlighting
|--------------------------------------------------------------------------
*/

test('the sidebar highlights the current page', function (string $routeName, string $expectedKey, string $expectedLabel) {
    $this->actingAs($this->user);

    $this->get(route($routeName))->assertOk();

    // The sidebar lives in the app layout. The navigation context is shared
    // with the layout by the application service provider, so render the
    // layout to inspect the navigation markup.
    $html = view('layouts.app')->render();

    expect($html)->toContain('aria-current="page"');

    preg_match('/<a[^>]*aria-current="page"[^>]*>(.*?)<\/a>/s', $html, $matches);

    expect($matches[1] ?? '')->toContain($expectedLabel);
})->with([
    'spaces' => ['spaces.index', 'spaces', 'Spaces'],
    'inbox' => ['inbox.index', 'inbox', 'Inbox'],
    'wall of love' => ['wall-of-love.index', 'wall-of-love', 'Wall of Love'],
    'widgets' => ['widgets.index', 'widgets', 'Widgets'],
    'billing' => ['billing.index', 'billing', 'Billing'],
]);

test('the dashboard keeps highlighting the overview link', function () {
    $this->actingAs($this->user);

    $this->get(route('dashboard'))->assertOk();

    $html = view('layouts.app')->render();

    expect($html)->toContain('aria-current="page"');

    preg_match('/<a[^>]*aria-current="page"[^>]*>(.*?)<\/a>/s', $html, $matches);

    expect($matches[1] ?? '')->toContain('Overview');
});

/*
|--------------------------------------------------------------------------
| Empty states
|--------------------------------------------------------------------------
*/

test('the spaces page shows an empty state when the account owns no spaces', function () {
    $this->actingAs($this->user)
        ->get(route('spaces.index'))
        ->assertOk()
        ->assertSee('No spaces yet');
});

test('the inbox page shows an empty state when there are no testimonials', function () {
    $this->actingAs($this->user)
        ->get(route('inbox.index'))
        ->assertOk()
        ->assertSee('No testimonials yet');
});

test('the wall of love page shows an empty state when nothing is on the wall', function () {
    $this->actingAs($this->user)
        ->get(route('wall-of-love.index'))
        ->assertOk()
        ->assertSee('Nothing on the wall yet');
});

test('the widgets page shows an empty state when there are no widgets', function () {
    $this->actingAs($this->user)
        ->get(route('widgets.index'))
        ->assertOk()
        ->assertSee('No widgets yet');
});

/*
|--------------------------------------------------------------------------
| The pages use real data
|--------------------------------------------------------------------------
*/

test('the spaces page lists the spaces owned by the account', function () {
    $space = Space::factory()->for($this->user)->create(['name' => 'Ada Reviews']);

    $this->actingAs($this->user)
        ->get(route('spaces.index'))
        ->assertOk()
        ->assertSee('Ada Reviews')
        ->assertSee('Total Spaces')
        ->assertViewHas('spacesCount', 1);
});

test('the spaces page does not list the spaces of another account', function () {
    Space::factory()->for($this->other)->create(['name' => 'Mallory Reviews']);

    $this->actingAs($this->user)
        ->get(route('spaces.index'))
        ->assertOk()
        ->assertDontSee('Mallory Reviews')
        ->assertViewHas('spacesCount', 0);
});

test('the inbox page lists the testimonials of the account', function () {
    $space = Space::factory()->for($this->user)->create();
    Testimonial::factory()->for($space)->create(['customer_name' => 'Grace Hopper']);

    $this->actingAs($this->user)
        ->get(route('inbox.index'))
        ->assertOk()
        ->assertSee('Grace Hopper')
        ->assertViewHas('totalCount', 1);
});

test('the inbox page does not list the testimonials of another account', function () {
    $otherSpace = Space::factory()->for($this->other)->create();
    Testimonial::factory()->for($otherSpace)->create(['customer_name' => 'Alan Turing']);

    $this->actingAs($this->user)
        ->get(route('inbox.index'))
        ->assertOk()
        ->assertDontSee('Alan Turing')
        ->assertViewHas('totalCount', 0);
});

test('the wall of love page only lists testimonials that are public and flagged', function () {
    $space = Space::factory()->for($this->user)->create();

    $onWall = Testimonial::factory()->for($space)->create([
        'customer_name' => 'Public Person',
        'is_wall_of_love' => true,
        'status' => TestimonialStatus::Approved,
        'sharing_consent' => true,
    ]);

    $notOnWall = Testimonial::factory()->for($space)->create([
        'customer_name' => 'Private Person',
        'is_wall_of_love' => false,
    ]);

    $response = $this->actingAs($this->user)->get(route('wall-of-love.index'));

    $response->assertOk()
        ->assertSee('Public Person')
        ->assertDontSee('Private Person')
        ->assertViewHas('wallOfLoveCount', 1);

    expect($onWall->id)->not->toBe($notOnWall->id);
});

test('the widgets page lists the widgets of the account', function () {
    $space = Space::factory()->for($this->user)->create(['name' => 'Ada Reviews']);
    Widget::factory()->for($space)->create();

    $this->actingAs($this->user)
        ->get(route('widgets.index'))
        ->assertOk()
        ->assertSee('Ada Reviews')
        ->assertViewHas('widgetsCount', 1);
});

test('the widgets page does not list the widgets of another account', function () {
    $otherSpace = Space::factory()->for($this->other)->create(['name' => 'Mallory Reviews']);
    Widget::factory()->for($otherSpace)->create();

    $this->actingAs($this->user)
        ->get(route('widgets.index'))
        ->assertOk()
        ->assertDontSee('Mallory Reviews')
        ->assertViewHas('widgetsCount', 0);
});

test('the billing page reports the current plan and the available plans', function () {
    $this->actingAs($this->user)
        ->get(route('billing.index'))
        ->assertOk()
        ->assertSee('You are on the Free plan.')
        ->assertSee('Current plan')
        ->assertSee('Free')
        ->assertSee('Pro');
});

test('the billing page no longer says that billing is unavailable', function () {
    $this->actingAs($this->user)
        ->get(route('billing.index'))
        ->assertOk()
        ->assertDontSee('not available yet');
});

/*
|--------------------------------------------------------------------------
| The original dashboard still works
|--------------------------------------------------------------------------
*/

test('the dashboard still renders its statistics and plan usage', function () {
    Space::factory()->count(2)->for($this->user)->create();

    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Total Spaces')
        ->assertSee('Total Testimonials')
        ->assertSee('Unique Customers')
        ->assertSee('Testimonials This Month')
        ->assertSee('Average Rating')
        ->assertSee('Plan and usage')
        ->assertSee('You are on the Free plan.')
        ->assertViewHas('spacesCount', 2);
});

test('the profile page still works', function () {
    $this->actingAs($this->user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('Profile');
});
