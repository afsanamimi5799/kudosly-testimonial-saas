<?php

use App\Billing\Contracts\StripeGateway;
use App\Billing\PlanCatalog;
use App\Billing\PlanType;
use App\Models\Plan;
use App\Models\User;
use Tests\Fakes\FakeStripeGateway;

/**
 * The billing page tells the account what it is on, what it costs and what
 * it can do next. These tests cover the copy and the actions it offers.
 */
beforeEach(function () {
    $this->stripe = new FakeStripeGateway;

    $this->app->instance(StripeGateway::class, $this->stripe);

    $this->user = User::factory()->create([
        'plan_slug' => PlanType::Free->value,
        'stripe_id' => 'cus_test_billing',
    ]);

    $this->proPlan = Plan::query()->where('slug', PlanType::Pro->value)->firstOrFail();
    $this->proPlan->forceFill(['stripe_price_id' => 'price_test_pro_yearly'])->save();
});

it('shows the yearly Pro price rather than a monthly one', function () {
    $this->actingAs($this->user)
        ->get(route('billing.index'))
        ->assertOk()
        ->assertSee('$999/year')
        ->assertDontSee('/month');
});

it('shows the upgrade button on the Pro plan', function () {
    $this->actingAs($this->user)
        ->get(route('billing.index'))
        ->assertOk()
        ->assertSee('Upgrade to Pro')
        ->assertSee(route('billing.checkout'));
});

it('hides the upgrade button when Stripe is not configured', function () {
    $this->stripe->configured = false;

    $this->actingAs($this->user)
        ->get(route('billing.index'))
        ->assertOk()
        ->assertDontSee('Upgrade to Pro')
        ->assertSee('Payments are not switched on yet');
});

it('hides the upgrade button when the Pro plan has no Stripe price', function () {
    $this->proPlan->forceFill(['stripe_price_id' => null])->save();

    $this->actingAs($this->user)
        ->get(route('billing.index'))
        ->assertOk()
        ->assertDontSee('Upgrade to Pro');
});

it('does not offer the upgrade to an account that is already on Pro', function () {
    $this->user->forceFill(['plan_slug' => PlanType::Pro->value])->save();

    $this->actingAs($this->user)
        ->get(route('billing.index'))
        ->assertOk()
        ->assertDontSee('Upgrade to Pro');
});

it('shows the current plan and the space usage', function () {
    $this->actingAs($this->user)
        ->get(route('billing.index'))
        ->assertOk()
        ->assertSee('You are on the Free plan.')
        ->assertSee('spaces used')
        ->assertSee('Current plan');
});

it('shows the subscription status', function () {
    $this->actingAs($this->user)
        ->get(route('billing.index'))
        ->assertOk()
        ->assertSee('Subscription')
        ->assertSee('No subscription');
});

it('shows the manage subscription button when there is a subscription to manage', function () {
    $this->user->subscriptions()->create([
        'type' => PlanCatalog::SUBSCRIPTION_TYPE,
        'stripe_id' => 'sub_test_billing',
        'stripe_status' => 'active',
        'stripe_price' => 'price_test_pro_yearly',
        'quantity' => 1,
    ]);

    $this->actingAs($this->user)
        ->get(route('billing.index'))
        ->assertOk()
        ->assertSee('Manage subscription')
        ->assertSee(route('billing.portal'));
});

it('refuses a guest', function () {
    $this->get(route('billing.index'))->assertRedirect(route('login'));
});

it('shows the error message when the checkout could not be started', function () {
    $this->withSession(['error' => 'Stripe is not configured.'])
        ->actingAs($this->user)
        ->get(route('billing.index'))
        ->assertOk()
        ->assertSee('Stripe is not configured.');
});
