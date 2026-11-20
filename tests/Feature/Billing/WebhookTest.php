<?php

use App\Billing\PlanType;
use App\Models\Plan;
use App\Models\StripeWebhookEvent;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Stripe\Webhook;

/**
 * The webhook is the only place where an account is given Pro, so these
 * tests cover the three things that must hold: only a verified event is
 * trusted, a repeated event changes nothing, and the plan follows the
 * subscription Stripe reports.
 */
beforeEach(function () {
    Config::set('cashier.webhook.secret', 'whsec_test_secret');
    Config::set('cashier.webhook.tolerance', 300);

    $this->user = User::factory()->create([
        'plan_slug' => PlanType::Free->value,
        'stripe_id' => 'cus_test_customer',
    ]);

    // The Pro plan row is created by a migration, so it is read and given
    // the price id the test needs rather than created a second time.
    $this->proPlan = Plan::query()->where('slug', PlanType::Pro->value)->firstOrFail();
    $this->proPlan->forceFill(['stripe_price_id' => 'price_test_pro_yearly'])->save();
});

/**
 * Post a signed payload to the webhook the way Stripe posts it.
 *
 * @param  array<string, mixed>  $payload
 */
function postSignedWebhook(array $payload, string $secret = 'whsec_test_secret')
{
    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    $timestamp = time();
    $signature = hash_hmac('sha256', $timestamp.'.'.$body, $secret);

    return test()->call(
        'POST',
        'stripe/webhook',
        [],
        [],
        [],
        [
            'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
            'CONTENT_TYPE' => 'application/json',
        ],
        $body
    );
}

/**
 * The body of a subscription event as Stripe sends it.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function subscriptionPayload(array $overrides = []): array
{
    return array_replace([
        'id' => 'sub_test_'.uniqid(),
        'object' => 'subscription',
        'customer' => 'cus_test_customer',
        'status' => 'active',
        'current_period_end' => now()->addYear()->timestamp,
        'items' => [
            'data' => [
                [
                    'id' => 'si_test_'.uniqid(),
                    'quantity' => 1,
                    'price' => [
                        'id' => 'price_test_pro_yearly',
                        'product' => 'prod_test_pro',
                    ],
                ],
            ],
        ],
    ], $overrides);
}

it('accepts a signed event and answers with success', function () {
    $response = postSignedWebhook([
        'id' => 'evt_'.uniqid(),
        'type' => 'customer.subscription.created',
        'data' => ['object' => subscriptionPayload()],
    ]);

    $response->assertStatus(200);
});

it('refuses an event whose signature does not match', function () {
    $body = json_encode([
        'id' => 'evt_'.uniqid(),
        'type' => 'customer.subscription.created',
        'data' => ['object' => subscriptionPayload()],
    ], JSON_THROW_ON_ERROR);

    $response = $this->call(
        'POST',
        'stripe/webhook',
        [],
        [],
        [],
        [
            'HTTP_STRIPE_SIGNATURE' => 't='.time().',v1=deadbeef',
            'CONTENT_TYPE' => 'application/json',
        ],
        $body
    );

    $response->assertStatus(400);
    expect($this->user->refresh()->plan_slug)->toBe(PlanType::Free->value);
});

it('refuses an event with no signature at all', function () {
    $body = json_encode([
        'id' => 'evt_'.uniqid(),
        'type' => 'customer.subscription.created',
        'data' => ['object' => subscriptionPayload()],
    ], JSON_THROW_ON_ERROR);

    $response = $this->call(
        'POST',
        'stripe/webhook',
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/json'],
        $body
    );

    $response->assertStatus(400);
    expect(StripeWebhookEvent::query()->count())->toBe(0);
});

it('refuses every event when no webhook secret is configured', function () {
    Config::set('cashier.webhook.secret', null);

    $response = postSignedWebhook([
        'id' => 'evt_'.uniqid(),
        'type' => 'customer.subscription.created',
        'data' => ['object' => subscriptionPayload()],
    ]);

    $response->assertStatus(503);
    expect(StripeWebhookEvent::query()->count())->toBe(0);
    expect($this->user->refresh()->plan_slug)->toBe(PlanType::Free->value);
});

it('grants pro when a subscription becomes active', function () {
    postSignedWebhook([
        'id' => 'evt_'.uniqid(),
        'type' => 'customer.subscription.created',
        'data' => ['object' => subscriptionPayload(['status' => 'active'])],
    ])->assertStatus(200);

    expect($this->user->refresh()->plan_slug)->toBe(PlanType::Pro->value);
    expect($this->user->subscriptions()->count())->toBe(1);
});

it('grants pro when a subscription is trialing', function () {
    postSignedWebhook([
        'id' => 'evt_'.uniqid(),
        'type' => 'customer.subscription.created',
        'data' => ['object' => subscriptionPayload(['status' => 'trialing'])],
    ])->assertStatus(200);

    expect($this->user->refresh()->plan_slug)->toBe(PlanType::Pro->value);
});

it('leaves the plan alone when the subscription is not paying', function () {
    postSignedWebhook([
        'id' => 'evt_'.uniqid(),
        'type' => 'customer.subscription.created',
        'data' => ['object' => subscriptionPayload(['status' => 'incomplete'])],
    ])->assertStatus(200);

    expect($this->user->refresh()->plan_slug)->toBe(PlanType::Free->value);
});

it('records the event so it can be recognised later', function () {
    $eventId = 'evt_'.uniqid();

    postSignedWebhook([
        'id' => $eventId,
        'type' => 'customer.subscription.created',
        'data' => ['object' => subscriptionPayload()],
    ])->assertStatus(200);

    $event = StripeWebhookEvent::query()->where('stripe_event_id', $eventId)->first();

    expect($event)->not->toBeNull()
        ->and($event->type)->toBe('customer.subscription.created')
        ->and($event->processed_at)->not->toBeNull();
});

it('ignores an event it has already handled', function () {
    $eventId = 'evt_'.uniqid();

    $payload = [
        'id' => $eventId,
        'type' => 'customer.subscription.created',
        'data' => ['object' => subscriptionPayload()],
    ];

    postSignedWebhook($payload)->assertStatus(200);

    expect($this->user->refresh()->plan_slug)->toBe(PlanType::Pro->value);

    // The account is moved back to the free plan, then the very same event
    // is delivered again. A repeated event must not take the plan away.
    $this->user->forceFill(['plan_slug' => PlanType::Free->value])->save();

    postSignedWebhook($payload)->assertStatus(200);

    expect(StripeWebhookEvent::query()->count())->toBe(1);
    expect($this->user->refresh()->plan_slug)->toBe(PlanType::Free->value);
});

it('answers a repeated event with success so Stripe stops retrying', function () {
    $payload = [
        'id' => 'evt_'.uniqid(),
        'type' => 'customer.subscription.created',
        'data' => ['object' => subscriptionPayload()],
    ];

    postSignedWebhook($payload)->assertStatus(200);
    postSignedWebhook($payload)->assertStatus(200);

    expect(StripeWebhookEvent::query()->count())->toBe(1);
});

it('takes the plan away when the subscription is deleted', function () {
    postSignedWebhook([
        'id' => 'evt_'.uniqid(),
        'type' => 'customer.subscription.created',
        'data' => ['object' => subscriptionPayload(['status' => 'active'])],
    ])->assertStatus(200);

    expect($this->user->refresh()->plan_slug)->toBe(PlanType::Pro->value);

    $subscriptionId = $this->user->subscriptions()->first()->stripe_id;

    postSignedWebhook([
        'id' => 'evt_'.uniqid(),
        'type' => 'customer.subscription.deleted',
        'data' => ['object' => subscriptionPayload([
            'id' => $subscriptionId,
            'status' => 'canceled',
        ])],
    ])->assertStatus(200);

    expect($this->user->refresh()->plan_slug)->toBe(PlanType::Free->value);
});

it('keeps pro when the subscription is set to end at the close of the paid period', function () {
    postSignedWebhook([
        'id' => 'evt_'.uniqid(),
        'type' => 'customer.subscription.created',
        'data' => ['object' => subscriptionPayload(['status' => 'active'])],
    ])->assertStatus(200);

    expect($this->user->refresh()->plan_slug)->toBe(PlanType::Pro->value);

    $subscriptionId = $this->user->subscriptions()->first()->stripe_id;

    postSignedWebhook([
        'id' => 'evt_'.uniqid(),
        'type' => 'customer.subscription.updated',
        'data' => ['object' => subscriptionPayload([
            'id' => $subscriptionId,
            'status' => 'active',
            'cancel_at_period_end' => true,
        ])],
    ])->assertStatus(200);

    $subscription = $this->user->subscriptions()->first();

    expect($this->user->refresh()->plan_slug)->toBe(PlanType::Pro->value);
    expect($subscription->ends_at)
        ->not->toBeNull()
        ->and($subscription->ends_at->isFuture())->toBeTrue();
});

it('does not grant pro on a completed checkout on its own', function () {
    postSignedWebhook([
        'id' => 'evt_'.uniqid(),
        'type' => 'checkout.session.completed',
        'data' => ['object' => [
            'id' => 'cs_test_'.uniqid(),
            'object' => 'checkout.session',
            'customer' => 'cus_test_customer',
            'mode' => 'subscription',
        ]],
    ])->assertStatus(200);

    expect($this->user->refresh()->plan_slug)->toBe(PlanType::Free->value);
});

it('keeps the plan when a payment fails', function () {
    postSignedWebhook([
        'id' => 'evt_'.uniqid(),
        'type' => 'customer.subscription.created',
        'data' => ['object' => subscriptionPayload(['status' => 'active'])],
    ])->assertStatus(200);

    expect($this->user->refresh()->plan_slug)->toBe(PlanType::Pro->value);

    postSignedWebhook([
        'id' => 'evt_'.uniqid(),
        'type' => 'invoice.payment_failed',
        'data' => ['object' => [
            'id' => 'in_test_'.uniqid(),
            'object' => 'invoice',
            'customer' => 'cus_test_customer',
        ]],
    ])->assertStatus(200);

    expect($this->user->refresh()->plan_slug)->toBe(PlanType::Pro->value);
});

it('keeps the plan when an invoice is paid', function () {
    postSignedWebhook([
        'id' => 'evt_'.uniqid(),
        'type' => 'customer.subscription.created',
        'data' => ['object' => subscriptionPayload(['status' => 'active'])],
    ])->assertStatus(200);

    postSignedWebhook([
        'id' => 'evt_'.uniqid(),
        'type' => 'invoice.paid',
        'data' => ['object' => [
            'id' => 'in_test_'.uniqid(),
            'object' => 'invoice',
            'customer' => 'cus_test_customer',
        ]],
    ])->assertStatus(200);

    expect($this->user->refresh()->plan_slug)->toBe(PlanType::Pro->value);
});

it('does not touch another account when the customer is unknown', function () {
    $other = User::factory()->create([
        'plan_slug' => PlanType::Free->value,
        'stripe_id' => 'cus_somebody_else',
    ]);

    postSignedWebhook([
        'id' => 'evt_'.uniqid(),
        'type' => 'customer.subscription.created',
        'data' => ['object' => subscriptionPayload(['customer' => 'cus_unknown_customer'])],
    ])->assertStatus(200);

    expect($other->refresh()->plan_slug)->toBe(PlanType::Free->value);
    expect($this->user->refresh()->plan_slug)->toBe(PlanType::Free->value);
});

it('ignores an event type it does not know', function () {
    postSignedWebhook([
        'id' => 'evt_'.uniqid(),
        'type' => 'some.other.event',
        'data' => ['object' => ['id' => 'x_'.uniqid()]],
    ])->assertStatus(200);

    expect(StripeWebhookEvent::query()->count())->toBe(1);
    expect($this->user->refresh()->plan_slug)->toBe(PlanType::Free->value);
});

it('does not need a signed in user', function () {
    expect(auth()->check())->toBeFalse();

    postSignedWebhook([
        'id' => 'evt_'.uniqid(),
        'type' => 'customer.subscription.created',
        'data' => ['object' => subscriptionPayload()],
    ])->assertStatus(200);

    expect($this->user->refresh()->plan_slug)->toBe(PlanType::Pro->value);
});

it('builds the event the same way Stripe does', function () {
    $body = json_encode([
        'id' => 'evt_'.uniqid(),
        'type' => 'customer.subscription.created',
        'data' => ['object' => subscriptionPayload()],
    ], JSON_THROW_ON_ERROR);

    $timestamp = time();
    $signature = hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_test_secret');

    $event = Webhook::constructEvent(
        $body,
        "t={$timestamp},v1={$signature}",
        'whsec_test_secret',
        300
    );

    expect($event->id)->toStartWith('evt_')
        ->and($event->type)->toBe('customer.subscription.created');
});

it('records the subscription details on the account', function () {
    postSignedWebhook([
        'id' => 'evt_'.uniqid(),
        'type' => 'customer.subscription.created',
        'data' => ['object' => subscriptionPayload(['status' => 'active'])],
    ])->assertStatus(200);

    $subscription = $this->user->subscriptions()->first();

    expect($subscription->ends_at)->toBeNull()
        ->and($subscription->stripe_status)->toBe('active')
        ->and($subscription->stripe_price)->toBe('price_test_pro_yearly');
});

it('records the trial end when the subscription is on a trial', function () {
    postSignedWebhook([
        'id' => 'evt_'.uniqid(),
        'type' => 'customer.subscription.created',
        'data' => ['object' => subscriptionPayload([
            'status' => 'past_due',
            'trial_end' => Carbon::now()->addDays(7)->timestamp,
        ])],
    ])->assertStatus(200);

    $subscription = $this->user->subscriptions()->first();

    expect($subscription->trial_ends_at)->not->toBeNull()
        ->and($subscription->stripe_status)->toBe('past_due');
});
