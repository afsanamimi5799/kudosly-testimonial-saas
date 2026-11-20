<?php

use App\Billing\Contracts\StripeGateway;
use App\Billing\PlanCatalog;
use App\Billing\SubscriptionStatus;
use App\Billing\SubscriptionStatusService;
use App\Models\User;
use Laravel\Cashier\Subscription;
use Tests\Fakes\FakeStripeGateway;

/**
 * The customer portal is where an account cancels, renews or updates its
 * card. These tests cover the rules that decide whether the portal may be
 * opened and what the billing page says about the subscription.
 */
beforeEach(function () {
    $this->stripe = new FakeStripeGateway;

    $this->app->instance(StripeGateway::class, $this->stripe);

    $this->user = User::factory()->create([
        'plan_slug' => 'free',
        'stripe_id' => 'cus_test_portal',
    ]);
});

/**
 * Give the account a subscription record.
 */
function subscribe(User $user, array $attributes = []): Subscription
{
    return $user->subscriptions()->create(array_merge([
        'type' => PlanCatalog::SUBSCRIPTION_TYPE,
        'stripe_id' => 'sub_test_'.uniqid(),
        'stripe_status' => 'active',
        'stripe_price' => 'price_test_pro_yearly',
        'quantity' => 1,
    ], $attributes));
}

it('sends the account to the Stripe customer portal', function () {
    subscribe($this->user);

    $response = $this->actingAs($this->user)->post(route('billing.portal'));

    $response->assertRedirect('https://billing.stripe.com/p/session/test');
    expect($this->stripe->portalCount)->toBe(1);
});

it('sends the account back to the billing page when the portal closes', function () {
    subscribe($this->user);

    $this->actingAs($this->user)->post(route('billing.portal'));

    expect($this->stripe->portalOptions['return_url'])->toBe(route('billing.index'));
});

it('refuses a guest', function () {
    $this->post(route('billing.portal'))->assertRedirect(route('login'));

    expect($this->stripe->portalCount)->toBe(0);
});

it('reports that an account with no Stripe customer has nothing to manage', function () {
    $user = User::factory()->create([
        'plan_slug' => 'free',
        'stripe_id' => null,
    ]);

    $response = $this->actingAs($user)->post(route('billing.portal'));

    $response->assertRedirect(route('billing.index'));
    $response->assertSessionHas('error');
    expect($this->stripe->portalCount)->toBe(0);
});

it('reports that Stripe has to be connected before the portal can be opened', function () {
    subscribe($this->user);

    $this->stripe->configured = false;

    $response = $this->actingAs($this->user)->post(route('billing.portal'));

    $response->assertRedirect(route('billing.index'));
    $response->assertSessionHas('error');
    expect($this->stripe->portalCount)->toBe(0);
});

it('reports it when Stripe returns no address for the portal', function () {
    subscribe($this->user);

    $this->stripe->portalUrl = '';

    $response = $this->actingAs($this->user)->post(route('billing.portal'));

    $response->assertRedirect(route('billing.index'));
    $response->assertSessionHas('error');
});

it('does not change the plan when the portal is opened', function () {
    subscribe($this->user, ['stripe_status' => 'active']);

    $this->user->forceFill(['plan_slug' => 'pro'])->save();

    $this->actingAs($this->user)->post(route('billing.portal'))->assertRedirect();

    // The portal only sends the account to Stripe. What is decided there
    // comes back through the webhook, never through the redirect.
    expect($this->user->refresh()->plan_slug)->toBe('pro');
});

it('shows the manage subscription button when the account can open the portal', function () {
    subscribe($this->user);

    $this->actingAs($this->user)->get(route('billing.index'))
        ->assertOk()
        ->assertSee('Manage subscription');
});

it('hides the manage subscription button when the account has no Stripe customer', function () {
    $user = User::factory()->create([
        'plan_slug' => 'free',
        'stripe_id' => null,
    ]);

    $this->actingAs($user)->get(route('billing.index'))
        ->assertOk()
        ->assertDontSee('Manage subscription');
});

it('reports an active subscription on the billing page', function () {
    subscribe($this->user, ['stripe_status' => 'active']);

    $this->actingAs($this->user)->get(route('billing.index'))
        ->assertOk()
        ->assertSee('Active')
        ->assertSee('Your subscription is running');
});

it('reports a trialing subscription on the billing page', function () {
    subscribe($this->user, ['stripe_status' => 'trialing']);

    $this->actingAs($this->user)->get(route('billing.index'))
        ->assertOk()
        ->assertSee('Trialing');
});

it('reports a past due subscription on the billing page', function () {
    subscribe($this->user, ['stripe_status' => 'past_due']);

    $this->actingAs($this->user)->get(route('billing.index'))
        ->assertOk()
        ->assertSee('Payment past due');
});

it('reports a cancelled subscription on the billing page', function () {
    subscribe($this->user, [
        'stripe_status' => 'canceled',
        'ends_at' => now()->subDay(),
    ]);

    $this->actingAs($this->user)->get(route('billing.index'))
        ->assertOk()
        ->assertSee('Canceled');
});

it('reports an incomplete subscription on the billing page', function () {
    subscribe($this->user, ['stripe_status' => 'incomplete']);

    $this->actingAs($this->user)->get(route('billing.index'))
        ->assertOk()
        ->assertSee('Incomplete');
});

it('reports no subscription for an account on the free plan', function () {
    $this->actingAs($this->user)->get(route('billing.index'))
        ->assertOk()
        ->assertSee('No subscription');
});

it('does not treat a subscription that ends at the close of the paid period as cancelled', function () {
    $service = app(SubscriptionStatusService::class);

    subscribe($this->user, [
        'stripe_status' => 'active',
        'ends_at' => now()->addMonth(),
    ]);

    expect($service->for($this->user))->toBe(SubscriptionStatus::Active)
        ->and($service->endsAtPeriodEnd($this->user))->toBeTrue();
});

it('tells the billing page when the subscription is set to end later', function () {
    $endsAt = now()->addMonth();

    subscribe($this->user, [
        'stripe_status' => 'active',
        'ends_at' => $endsAt,
    ]);

    $this->actingAs($this->user)->get(route('billing.index'))
        ->assertOk()
        ->assertSee('Your subscription is set to end on');
});

it('reads a subscription that has ended in the past as cancelled', function () {
    $service = app(SubscriptionStatusService::class);

    subscribe($this->user, [
        'stripe_status' => 'active',
        'ends_at' => now()->subDay(),
    ]);

    expect($service->for($this->user))->toBe(SubscriptionStatus::Canceled)
        ->and($service->endsAtPeriodEnd($this->user))->toBeFalse();
});

it('reads the most recent subscription when the account has resubscribed', function () {
    $service = app(SubscriptionStatusService::class);

    subscribe($this->user, [
        'stripe_status' => 'canceled',
        'ends_at' => now()->subMonth(),
    ]);

    subscribe($this->user, ['stripe_status' => 'active']);

    expect($service->for($this->user))->toBe(SubscriptionStatus::Active);
});

it('reads an unpaid subscription as past due', function () {
    $service = app(SubscriptionStatusService::class);

    subscribe($this->user, ['stripe_status' => 'unpaid']);

    expect($service->for($this->user))->toBe(SubscriptionStatus::PastDue);
});

it('reads an expired incomplete subscription as cancelled', function () {
    $service = app(SubscriptionStatusService::class);

    subscribe($this->user, ['stripe_status' => 'incomplete_expired']);

    expect($service->for($this->user))->toBe(SubscriptionStatus::Canceled);
});

it('gives every status a label and a description', function () {
    foreach (SubscriptionStatus::cases() as $status) {
        expect($status->label())->not->toBe('')
            ->and($status->description())->not->toBe('');
    }
});

it('marks only the active and trialing states as paying', function () {
    expect(SubscriptionStatus::Active->isPaying())->toBeTrue()
        ->and(SubscriptionStatus::Trialing->isPaying())->toBeTrue()
        ->and(SubscriptionStatus::PastDue->isPaying())->toBeFalse()
        ->and(SubscriptionStatus::Canceled->isPaying())->toBeFalse()
        ->and(SubscriptionStatus::Incomplete->isPaying())->toBeFalse()
        ->and(SubscriptionStatus::None->isPaying())->toBeFalse();
});
