<?php

use App\Billing\PlanType;
use App\Models\Plan;

/*
|--------------------------------------------------------------------------
| Plan identity
|--------------------------------------------------------------------------
|
| These tests are pure unit tests. They never touch the database, so they do
| not need RefreshDatabase and must not create plan rows.
|
*/

test('the free plan type is labelled Free and is not pro', function () {
    expect(PlanType::Free->value)->toBe('free')
        ->and(PlanType::Free->label())->toBe('Free')
        ->and(PlanType::Free->isPro())->toBeFalse();
});

test('the pro plan type is labelled Pro and is pro', function () {
    expect(PlanType::Pro->value)->toBe('pro')
        ->and(PlanType::Pro->label())->toBe('Pro')
        ->and(PlanType::Pro->isPro())->toBeTrue();
});

test('a plan resolves its type from its slug', function () {
    $plan = new Plan(['slug' => PlanType::Pro->value]);

    expect($plan->type())->toBe(PlanType::Pro)
        ->and($plan->isPro())->toBeTrue();
});

test('a plan with an unknown slug throws a value error', function () {
    $plan = new Plan(['slug' => 'enterprise']);

    expect(fn () => $plan->type())->toThrow(ValueError::class);
});

test('a plan price is converted from cents to dollars', function () {
    $plan = new Plan(['price' => 99900]);

    expect($plan->priceInDollars())->toBe(999.0);
});

test('a free plan reports zero dollars', function () {
    $plan = new Plan(['price' => 0]);

    expect($plan->priceInDollars())->toBe(0.0);
});
