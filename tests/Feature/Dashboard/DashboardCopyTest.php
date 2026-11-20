<?php

use App\Billing\PlanType;
use App\Models\Space;
use App\Models\User;

/**
 * The dashboard used to tell accounts that spaces and billing were not
 * available yet. Those statements are gone, so these tests make sure they
 * stay gone and that the page offers the way forward instead.
 */
beforeEach(function () {
    $this->user = User::factory()->create([
        'plan_slug' => PlanType::Free->value,
    ]);
});

it('no longer says that billing is unavailable', function () {
    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Billing is not available yet')
        ->assertDontSee('will arrive in a later release');
});

it('no longer says that creating spaces is unavailable', function () {
    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Creating and editing spaces is not available yet')
        ->assertDontSee('so there is nothing to list');
});

it('no longer says that collection arrives later', function () {
    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Collection arrives in a later release');
});

it('offers a way to create the first space', function () {
    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Create your first space')
        ->assertSee(route('spaces.create'));
});

it('links the plan card to the billing page', function () {
    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Upgrade to Pro for more spaces')
        ->assertSee(route('billing.index'));
});

it('shows the yearly Pro price on the upgrade banner', function () {
    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('$999')
        ->assertSee('per year');
});

it('offers a working search box for the spaces', function () {
    Space::factory()->for($this->user)->create(['name' => 'Product Reviews']);

    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('id="dashboard-space-search"', false)
        ->assertDontSee('disabled aria-disabled="true"', false);
});

it('gives each space the text the search matches against', function () {
    Space::factory()->for($this->user)->create([
        'name' => 'Product Reviews',
        'title' => 'Tell us what you think',
    ]);

    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('data-space-search="product reviews tell us what you think"', false);
});

it('shows a message when no space matches the search', function () {
    Space::factory()->for($this->user)->create(['name' => 'Product Reviews']);

    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('No spaces match your search.');
});

it('does not offer the upgrade banner to an account that is already on Pro', function () {
    $this->user->forceFill(['plan_slug' => PlanType::Pro->value])->save();

    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Upgrade for more spaces.');
});

it('shows the plan name for an account that is on Pro', function () {
    $this->user->forceFill(['plan_slug' => PlanType::Pro->value])->save();

    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('You are on the Pro plan.');
});

it('sends the created space page to the spaces list rather than the dashboard', function () {
    $space = Space::factory()->for($this->user)->create();

    $this->actingAs($this->user)
        ->get(route('spaces.created', ['space' => $space]))
        ->assertOk()
        ->assertSee('Go to spaces')
        ->assertSee(route('spaces.index'));
});
