<?php

use App\Billing\PlanCatalog;
use App\Billing\PlanType;
use App\Models\Plan;
use App\Models\Space;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;

/*
|--------------------------------------------------------------------------
| Space authorization
|--------------------------------------------------------------------------
|
| Covers App\Policies\SpacePolicy. There are no Space routes yet, so the
| policy is exercised directly against the Gate.
|
*/

beforeEach(function () {
    // The plan rows are inserted by the migration, but the missing-plan tests
    // below remove the free plan row. Seeding first guarantees every test in
    // this file starts from a known set of plans.
    $this->seed(PlanSeeder::class);

    // UserFactory does not set plan_slug, and the attribute is not fillable,
    // so the created model has no plan_slug at all. The relation then looks
    // for a plan whose slug is NULL. Force the value onto the model and save
    // it, exactly as RegisteredUserController does when it assigns the plan.
    $this->owner = User::factory()->create();
    $this->owner->forceFill(['plan_slug' => PlanType::Free->value])->save();

    $this->other = User::factory()->create();
    $this->other->forceFill(['plan_slug' => PlanType::Free->value])->save();

    $this->space = Space::factory()->for($this->owner)->create();
});

test('the policy is discovered automatically for the space model', function () {
    expect(Gate::getPolicyFor(Space::class))->toBeInstanceOf(App\Policies\SpacePolicy::class);
});

test('any authenticated user may list spaces', function () {
    expect(Gate::forUser($this->other)->allows('viewAny', Space::class))->toBeTrue();
});

test('the owner may view update and delete the space', function () {
    $gate = Gate::forUser($this->owner);

    expect($gate->allows('view', $this->space))->toBeTrue()
        ->and($gate->allows('update', $this->space))->toBeTrue()
        ->and($gate->allows('delete', $this->space))->toBeTrue();
});

test('the owner may manage the space fields testimonials and widget', function () {
    $gate = Gate::forUser($this->owner);

    expect($gate->allows('manageFields', $this->space))->toBeTrue()
        ->and($gate->allows('viewTestimonials', $this->space))->toBeTrue()
        ->and($gate->allows('manageWidget', $this->space))->toBeTrue();
});

test('a different user may not view update or delete the space', function () {
    $gate = Gate::forUser($this->other);

    expect($gate->allows('view', $this->space))->toBeFalse()
        ->and($gate->allows('update', $this->space))->toBeFalse()
        ->and($gate->allows('delete', $this->space))->toBeFalse();
});

test('a different user may not manage the space fields testimonials or widget', function () {
    $gate = Gate::forUser($this->other);

    expect($gate->allows('manageFields', $this->space))->toBeFalse()
        ->and($gate->allows('viewTestimonials', $this->space))->toBeFalse()
        ->and($gate->allows('manageWidget', $this->space))->toBeFalse();
});

test('ownership is compared as normalised string keys', function () {
    $space = Space::factory()->create(['user_id' => (string) $this->owner->getKey()]);

    expect(Gate::forUser($this->owner)->allows('view', $space))->toBeTrue();
});

test('the owner may create a space while quota remains', function () {
    expect(Gate::forUser($this->owner)->allows('create', Space::class))->toBeTrue();
});

test('the owner may not create a space once the free plan limit is reached', function () {
    Space::factory()->count(2)->for($this->owner)->create();

    expect($this->owner->spacesUsed())->toBe(3)
        ->and($this->owner->maxSpaces())->toBe(3)
        ->and($this->owner->canCreateSpace())->toBeFalse()
        ->and(Gate::forUser($this->owner)->allows('create', Space::class))->toBeFalse();
});

test('the owner may create a space one below the free plan limit', function () {
    Space::factory()->count(1)->for($this->owner)->create();

    expect($this->owner->spacesUsed())->toBe(2)
        ->and($this->owner->spaceQuotaRemaining())->toBe(1)
        ->and($this->owner->hasSpaceQuota())->toBeTrue()
        ->and($this->owner->canCreateSpace())->toBeTrue()
        ->and(Gate::forUser($this->owner)->allows('create', Space::class))->toBeTrue();
});

test('a pro plan user may create spaces beyond the free plan limit', function () {
    $pro = User::factory()->create(['plan_slug' => 'pro']);

    Space::factory()->count(3)->for($pro)->create();

    expect($pro->maxSpaces())->toBe(25)
        ->and($pro->canCreateSpace())->toBeTrue()
        ->and(Gate::forUser($pro)->allows('create', Space::class))->toBeTrue();
});

test('the gate fails closed for an unknown ability', function () {
    expect(Gate::forUser($this->owner)->allows('nonexistentAbility', $this->space))->toBeFalse();
});

test('the gate denies an unknown ability for a non owner as well', function () {
    expect(Gate::forUser($this->other)->allows('nonexistentAbility', $this->space))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Plan catalog
|--------------------------------------------------------------------------
|
| PlanCatalog reads the plan limits from the plans table and raises rather
| than silently falling back to hardcoded values when a plan row is missing.
|
*/

test('the catalog finds the plan matching a plan type', function () {
    $plan = (new PlanCatalog)->find(PlanType::Free);

    expect($plan)->not->toBeNull()
        ->and($plan->slug)->toBe(PlanType::Free->value)
        ->and($plan->max_spaces)->toBe(3);
});

test('the catalog lists every available plan ordered by price', function () {
    $plans = (new PlanCatalog)->all();

    expect($plans)->toHaveCount(2)
        ->and($plans->first()->slug)->toBe(PlanType::Free->value)
        ->and($plans->last()->slug)->toBe(PlanType::Pro->value);
});

test('the catalog returns the maximum number of spaces for a plan', function () {
    expect((new PlanCatalog)->maxSpaces(PlanType::Free))->toBe(3)
        ->and((new PlanCatalog)->maxSpaces(PlanType::Pro))->toBe(25);
});

test('the catalog returns the maximum number of testimonials per space for a plan', function () {
    expect((new PlanCatalog)->maxTestimonialsPerSpace(PlanType::Free))->toBe(100)
        ->and((new PlanCatalog)->maxTestimonialsPerSpace(PlanType::Pro))->toBe(1000);
});

test('the catalog raises a model not found exception when the plan row is missing', function () {
    // Move every account off the plan so the row can be removed, then remove
    // it. The catalog must raise rather than fall back to a default limit.
    User::query()->update(['plan_slug' => PlanType::Pro->value]);

    Plan::query()->where('slug', PlanType::Free->value)->delete();

    expect(fn () => (new PlanCatalog)->maxSpaces(PlanType::Free))
        ->toThrow(ModelNotFoundException::class);
});

test('the catalog raises a model not found exception for testimonials per space too', function () {
    User::query()->update(['plan_slug' => PlanType::Pro->value]);

    Plan::query()->where('slug', PlanType::Free->value)->delete();

    expect(fn () => (new PlanCatalog)->maxTestimonialsPerSpace(PlanType::Free))
        ->toThrow(ModelNotFoundException::class);
});
