<?php

use App\Billing\Contracts\StripeGateway;
use App\Models\Plan;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Tests\Fakes\FakeStripeGateway;

/*
|--------------------------------------------------------------------------
| Stripe checkout
|--------------------------------------------------------------------------
|
| Covers the upgrade flow. The account is sent to Stripe to pay for the
| yearly Pro plan and the plan is only granted once the webhook confirms
| the payment, so these tests never reach Stripe and never grant Pro.
|
*/

beforeEach(function () {
    $this->seed(PlanSeeder::class);

    Plan::query()->where('slug', 'pro')->update(['stripe_price_id' => 'price_pro_test']);

    $this->user = User::factory()->create();
    $this->user->forceFill(['plan_slug' => 'free'])->save();

    $this->stripe = new FakeStripeGateway;

    $this->app->instance(StripeGateway::class, $this->stripe);
});

/*
|--------------------------------------------------------------------------
| Starting a checkout
|--------------------------------------------------------------------------
*/

test('the account is sent to stripe to start the upgrade', function () {
    $this->actingAs($this->user)
        ->post(route('billing.checkout'))
        ->assertRedirect('https://checkout.stripe.com/c/pay/cs_fake_1');
});

test('the checkout buys the yearly pro price from the server configuration', function () {
    $this->actingAs($this->user)->post(route('billing.checkout'));

    expect($this->stripe->priceId)->toBe('price_pro_test')
        ->and($this->stripe->sessionOptions['mode'])->toBe('subscription');
});

test('the checkout never sends an amount to stripe', function () {
    $this->actingAs($this->user)->post(route('billing.checkout'));

    // The price carries the amount on the Stripe side. The application never
    // decides what the customer pays.
    expect($this->stripe->sessionOptions)->not->toHaveKey('unit_amount')
        ->and($this->stripe->sessionOptions)->not->toHaveKey('line_items')
        ->and($this->stripe->sessionOptions)->not->toHaveKey('amount')
        ->and($this->stripe->sessionOptions)->not->toHaveKey('price');
});

test('the checkout records the account as the client reference', function () {
    $this->actingAs($this->user)->post(route('billing.checkout'));

    expect($this->stripe->sessionOptions['client_reference_id'])->toBe((string) $this->user->id);
});

test('the checkout records the plan in the subscription metadata', function () {
    $this->actingAs($this->user)->post(route('billing.checkout'));

    expect($this->stripe->sessionOptions['subscription_data']['metadata']['user_id'])
        ->toBe((string) $this->user->id)
        ->and($this->stripe->sessionOptions['subscription_data']['metadata']['plan'])
        ->toBe('pro');
});

test('the checkout stores the stripe customer on the account', function () {
    $this->actingAs($this->user)->post(route('billing.checkout'));

    expect($this->user->refresh()->stripe_id)->not->toBeNull();
});

test('the checkout sends the account to the success page when it is paid', function () {
    $this->actingAs($this->user)->post(route('billing.checkout'));

    expect($this->stripe->sessionOptions['success_url'])->toBe(route('billing.checkout.success'));
});

test('the checkout sends the account to the cancel page when it is abandoned', function () {
    $this->actingAs($this->user)->post(route('billing.checkout'));

    expect($this->stripe->sessionOptions['cancel_url'])->toBe(route('billing.checkout.cancelled'));
});

/*
|--------------------------------------------------------------------------
| The client may not choose the price
|--------------------------------------------------------------------------
*/

test('a client cannot choose the price', function () {
    $this->actingAs($this->user)
        ->post(route('billing.checkout'), ['price' => 'price_attacker_chosen', 'price_id' => 'price_attacker_chosen']);

    expect($this->stripe->priceId)->toBe('price_pro_test');
});

test('a client cannot choose the amount', function () {
    $this->actingAs($this->user)
        ->post(route('billing.checkout'), ['amount' => 1, 'unit_amount' => 1]);

    expect($this->stripe->sessionOptions)->not->toHaveKey('unit_amount')
        ->and($this->stripe->sessionOptions)->not->toHaveKey('amount');
});

test('a client cannot change the billing interval', function () {
    $this->actingAs($this->user)
        ->post(route('billing.checkout'), ['interval' => 'month', 'billing_interval' => 'month']);

    expect($this->stripe->priceId)->toBe('price_pro_test');
});

/*
|--------------------------------------------------------------------------
| Who may start a checkout
|--------------------------------------------------------------------------
*/

test('a guest cannot start a checkout', function () {
    $this->post(route('billing.checkout'))->assertRedirect(route('login'));

    expect($this->stripe->checkoutCount)->toBe(0);
});

test('a checkout on its own never grants the pro plan', function () {
    $this->actingAs($this->user)->post(route('billing.checkout'));

    // The plan is granted by the webhook once Stripe confirms the payment.
    expect($this->user->refresh()->plan_slug)->toBe('free')
        ->and($this->user->maxSpaces())->toBe(3);
});

/*
|--------------------------------------------------------------------------
| A missing configuration
|--------------------------------------------------------------------------
*/

test('a missing stripe secret is reported rather than thrown', function () {
    $this->stripe->configured = false;

    $this->actingAs($this->user)
        ->post(route('billing.checkout'))
        ->assertRedirect(route('billing.index'))
        ->assertSessionHas('error');

    expect($this->stripe->checkoutCount)->toBe(0);
});

test('a missing price id is reported rather than thrown', function () {
    Plan::query()->where('slug', 'pro')->update(['stripe_price_id' => null]);

    $this->actingAs($this->user)
        ->post(route('billing.checkout'))
        ->assertRedirect(route('billing.index'))
        ->assertSessionHas('error');

    expect($this->stripe->checkoutCount)->toBe(0);
});

/*
|--------------------------------------------------------------------------
| The pages Stripe returns to
|--------------------------------------------------------------------------
*/

test('the success page is reachable', function () {
    $this->actingAs($this->user)
        ->get(route('billing.checkout.success'))
        ->assertOk()
        ->assertSee('Thank you for your payment');
});

test('the cancelled page is reachable', function () {
    $this->actingAs($this->user)
        ->get(route('billing.checkout.cancelled'))
        ->assertOk()
        ->assertSee('checkout was cancelled');
});

test('the success page never grants the pro plan', function () {
    $this->actingAs($this->user)->get(route('billing.checkout.success'));

    expect($this->user->refresh()->plan_slug)->toBe('free');
});

test('a guest cannot reach the success page', function () {
    $this->get(route('billing.checkout.success'))->assertRedirect(route('login'));
});

test('a guest cannot reach the cancelled page', function () {
    $this->get(route('billing.checkout.cancelled'))->assertRedirect(route('login'));
});
