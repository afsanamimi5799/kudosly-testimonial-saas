<?php

use App\Billing\PlanCatalog;
use App\Billing\PlanType;
use App\Models\Plan;
use App\Models\Space;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Laravel\Cashier\Subscription;

/*
|--------------------------------------------------------------------------
| Plan entitlements
|--------------------------------------------------------------------------
|
| Kudosly Pro is sold as a yearly subscription. This covers the rules that
| decide which limits an account gets, and how those limits behave when the
| subscription behind them changes.
|
*/

beforeEach(function () {
    $this->seed(PlanSeeder::class);

    $this->catalog = new PlanCatalog;

    $this->free = User::factory()->create();
    $this->free->forceFill(['plan_slug' => 'free'])->save();

    $this->pro = User::factory()->create();
    $this->pro->forceFill(['plan_slug' => 'pro'])->save();
});

/*
|--------------------------------------------------------------------------
| The plan catalogue
|--------------------------------------------------------------------------
*/

test('the catalogue reports the free plan limits', function () {
    expect($this->catalog->maxSpaces(PlanType::Free))->toBe(3)
        ->and($this->catalog->maxTestimonialsPerSpace(PlanType::Free))->toBe(100);
});

test('the catalogue reports the pro plan limits', function () {
    expect($this->catalog->maxSpaces(PlanType::Pro))->toBe(25)
        ->and($this->catalog->maxTestimonialsPerSpace(PlanType::Pro))->toBe(1000);
});

test('the pro plan costs nine hundred and ninety nine dollars a year', function () {
    $pro = $this->catalog->find(PlanType::Pro);

    expect($pro->priceInDollars())->toBe(999.0)
        ->and($pro->yearlyPrice())->toBe(999.0)
        ->and($pro->yearlyPriceLabel())->toBe('$999')
        ->and($pro->isYearly())->toBeTrue()
        ->and($pro->billingInterval())->toBe('year');
});

test('the free plan costs nothing', function () {
    $free = $this->catalog->find(PlanType::Free);

    expect($free->priceInDollars())->toBe(0.0)
        ->and($free->yearlyPrice())->toBe(0.0)
        ->and($free->isPro())->toBeFalse();
});

test('the billing interval is a year for every plan', function () {
    expect(PlanType::Free->billingInterval())->toBe('year')
        ->and(PlanType::Pro->billingInterval())->toBe('year');
});

/*
|--------------------------------------------------------------------------
| The Stripe price id
|--------------------------------------------------------------------------
*/

test('the catalogue fails loudly when a plan has no stripe price id', function () {
    // The plans are seeded without a price id because the price lives in the
    // operator's own Stripe account. A checkout must not start against an
    // empty price id, so the catalogue has to refuse.
    expect(fn () => $this->catalog->stripePriceId(PlanType::Pro))
        ->toThrow(RuntimeException::class);
});

test('the catalogue reports that a plan has no stripe price id', function () {
    expect($this->catalog->hasStripePriceId(PlanType::Pro))->toBeFalse();
});

test('the catalogue returns the configured stripe price id', function () {
    Plan::query()->where('slug', 'pro')->update(['stripe_price_id' => 'price_pro_test']);

    expect($this->catalog->stripePriceId(PlanType::Pro))->toBe('price_pro_test')
        ->and($this->catalog->hasStripePriceId(PlanType::Pro))->toBeTrue();
});

test('the plan seeder never erases a configured stripe price id', function () {
    Plan::query()->where('slug', 'pro')->update(['stripe_price_id' => 'price_pro_test']);

    $this->seed(PlanSeeder::class);

    expect($this->catalog->stripePriceId(PlanType::Pro))->toBe('price_pro_test');
});

/*
|--------------------------------------------------------------------------
| The limits an account gets
|--------------------------------------------------------------------------
*/

test('a free account gets the free limits', function () {
    expect($this->free->maxSpaces())->toBe(3)
        ->and($this->free->maxTestimonialsPerSpace())->toBe(100)
        ->and($this->free->hasPlan(PlanType::Free))->toBeTrue();
});

test('a pro account gets the pro limits', function () {
    expect($this->pro->maxSpaces())->toBe(25)
        ->and($this->pro->maxTestimonialsPerSpace())->toBe(1000)
        ->and($this->pro->hasPlan(PlanType::Pro))->toBeTrue();
});

test('a pro account is not treated as a free account', function () {
    expect($this->pro->hasPlan(PlanType::Free))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| An account that was granted pro without a subscription
|--------------------------------------------------------------------------
|
| Kudosly was in use before Stripe was connected, so some accounts already
| hold Pro without a subscription record behind it. Removing the entitlement
| on the strength of a missing payment record would take away access an
| account already relies on, so the stored plan is honoured.
|
*/

test('a pro account without a subscription record keeps the pro limits', function () {
    expect($this->pro->subscriptions()->count())->toBe(0);

    expect($this->pro->maxSpaces())->toBe(25)
        ->and($this->pro->maxTestimonialsPerSpace())->toBe(1000);
});

test('a pro account without a subscription record is not reported as subscribed', function () {
    expect($this->pro->hasActiveSubscription())->toBeFalse();
});

test('a pro account with a valid subscription is reported as subscribed', function () {
    $this->pro->subscriptions()->create([
        'type' => PlanCatalog::SUBSCRIPTION_TYPE,
        'stripe_id' => 'sub_valid_test',
        'stripe_status' => 'active',
        'stripe_price' => 'price_pro_test',
    ]);

    expect($this->pro->hasActiveSubscription())->toBeTrue();
});

test('a cancelled subscription is not reported as subscribed', function () {
    // Cashier treats a subscription as cancelled by the ends_at date, so a
    // cancellation that has already run is described by a past ends_at.
    $this->pro->subscriptions()->create([
        'type' => PlanCatalog::SUBSCRIPTION_TYPE,
        'stripe_id' => 'sub_cancelled_test',
        'stripe_status' => 'canceled',
        'stripe_price' => 'price_pro_test',
        'ends_at' => now()->subDay(),
    ]);

    expect($this->pro->hasActiveSubscription())->toBeFalse();
});

test('a subscription cancelled at the end of the period stays valid until then', function () {
    // A subscription that is set to end at the close of the paid period must
    // not be treated as cancelled before that moment arrives.
    $this->pro->subscriptions()->create([
        'type' => PlanCatalog::SUBSCRIPTION_TYPE,
        'stripe_id' => 'sub_cancelling_test',
        'stripe_status' => 'active',
        'stripe_price' => 'price_pro_test',
        'ends_at' => now()->addMonth(),
    ]);

    expect($this->pro->hasActiveSubscription())->toBeTrue();
});

test('a subscription that has ended does not downgrade the stored plan on its own', function () {
    // The webhook is what writes the plan. Until it runs, the stored plan
    // stays as it is, so a lapse never silently strips an entitlement.
    $this->pro->subscriptions()->create([
        'type' => PlanCatalog::SUBSCRIPTION_TYPE,
        'stripe_id' => 'sub_ended_test',
        'stripe_status' => 'canceled',
        'stripe_price' => 'price_pro_test',
        'ends_at' => now()->subDay(),
    ]);

    expect($this->pro->maxSpaces())->toBe(25);
});

/*
|--------------------------------------------------------------------------
| The space quota
|--------------------------------------------------------------------------
*/

test('a free account can create spaces until the free quota is used', function () {
    expect($this->free->canCreateSpace())->toBeTrue()
        ->and($this->free->spaceQuotaRemaining())->toBe(3);
});

test('a free account cannot create a space beyond the free quota', function () {
    Space::factory()->count(3)->for($this->free)->create();

    expect($this->free->canCreateSpace())->toBeFalse()
        ->and($this->free->spaceQuotaRemaining())->toBe(0)
        ->and($this->free->hasSpaceQuota())->toBeFalse();
});

test('a pro account can create more spaces than the free plan allows', function () {
    Space::factory()->count(3)->for($this->pro)->create();

    expect($this->pro->canCreateSpace())->toBeTrue()
        ->and($this->pro->spaceQuotaRemaining())->toBe(22);
});
