<?php

use App\Billing\PlanType;
use App\Enums\SpaceFieldKey;
use App\Enums\SpaceTheme;
use App\Models\Space;
use App\Models\SpaceField;
use App\Models\Testimonial;
use App\Models\User;
use Database\Seeders\PlanSeeder;

/*
|--------------------------------------------------------------------------
| Space helpers and soft deletes
|--------------------------------------------------------------------------
|
| Covers the helper methods on App\Models\Space and the soft delete
| behaviour of both the space and the testimonial models.
|
*/

beforeEach(function () {
    // Guarantee the plan rows exist before any account is created, so the
    // space always resolves the owning account's plan.
    $this->seed(PlanSeeder::class);

    $this->user = User::factory()->create();
    $this->user->forceFill(['plan_slug' => PlanType::Free->value])->save();

    $this->space = Space::factory()->for($this->user)->create();
});

test('the theme is resolved from the stored slug', function () {
    $this->space->forceFill(['theme' => SpaceTheme::ModernDark->value])->save();

    expect($this->space->themeEnum())->toBe(SpaceTheme::ModernDark);
});

test('the default theme resolves to the minimal light theme', function () {
    expect($this->space->themeEnum())->toBe(SpaceTheme::MinimalLight);
});

test('an unrecognised theme slug raises a value error', function () {
    $this->space->forceFill(['theme' => 'not-a-real-theme'])->save();

    expect(fn () => $this->space->themeEnum())->toThrow(ValueError::class);
});

test('enabled fields only returns the enabled fields ordered by sort order', function () {
    SpaceField::factory()->for($this->space)->create([
        'field_key' => SpaceFieldKey::SocialUrl->value,
        'enabled' => true,
        'sort_order' => 2,
    ]);

    SpaceField::factory()->for($this->space)->create([
        'field_key' => SpaceFieldKey::CompanyName->value,
        'enabled' => true,
        'sort_order' => 1,
    ]);

    SpaceField::factory()->for($this->space)->create([
        'field_key' => SpaceFieldKey::ProfilePhoto->value,
        'enabled' => false,
        'sort_order' => 0,
    ]);

    $keys = $this->space->enabledFields()->pluck('field_key')->all();

    expect($keys)->toBe([
        SpaceFieldKey::CompanyName->value,
        SpaceFieldKey::SocialUrl->value,
    ]);
});

test('a space with no field rows has no enabled fields', function () {
    expect($this->space->enabledFields())->toHaveCount(0);
});

test('a field is enabled when it is collected by the space', function () {
    SpaceField::factory()->for($this->space)->create([
        'field_key' => SpaceFieldKey::CompanyName->value,
        'enabled' => true,
    ]);

    expect($this->space->hasFieldEnabled(SpaceFieldKey::CompanyName))->toBeTrue()
        ->and($this->space->hasFieldEnabled(SpaceFieldKey::SocialUrl))->toBeFalse();
});

test('a disabled field is not enabled', function () {
    SpaceField::factory()->for($this->space)->create([
        'field_key' => SpaceFieldKey::CompanyName->value,
        'enabled' => false,
    ]);

    expect($this->space->hasFieldEnabled(SpaceFieldKey::CompanyName))->toBeFalse();
});

test('a field is required only when it is enabled and marked required', function () {
    SpaceField::factory()->for($this->space)->create([
        'field_key' => SpaceFieldKey::CompanyName->value,
        'enabled' => true,
        'required' => true,
    ]);

    expect($this->space->hasFieldRequired(SpaceFieldKey::CompanyName))->toBeTrue()
        ->and($this->space->hasFieldRequired(SpaceFieldKey::SocialUrl))->toBeFalse();
});

test('a required but disabled field is not required', function () {
    SpaceField::factory()->for($this->space)->create([
        'field_key' => SpaceFieldKey::CompanyName->value,
        'enabled' => false,
        'required' => true,
    ]);

    expect($this->space->hasFieldRequired(SpaceFieldKey::CompanyName))->toBeFalse();
});

test('the testimonial limit comes from the owning account plan', function () {
    expect($this->space->testimonialLimit())->toBe(100);

    $pro = User::factory()->create(['plan_slug' => 'pro']);
    $proSpace = Space::factory()->for($pro)->create();

    expect($proSpace->testimonialLimit())->toBe(1000);
});

test('the space has not reached its testimonial limit when it is empty', function () {
    expect($this->space->hasReachedTestimonialLimit())->toBeFalse();
});

test('the space has not reached its testimonial limit while below it', function () {
    Testimonial::factory()->count(5)->for($this->space)->create();

    expect($this->space->testimonialCount())->toBe(5)
        ->and($this->space->hasReachedTestimonialLimit())->toBeFalse();
});

test('the space has reached its testimonial limit when the count matches it', function () {
    Testimonial::factory()->count(100)->for($this->space)->create();

    expect($this->space->testimonialCount())->toBe(100)
        ->and($this->space->hasReachedTestimonialLimit())->toBeTrue();
});

test('a soft deleted space is hidden from normal queries but the row survives', function () {
    $id = $this->space->getKey();

    $this->space->delete();

    expect($this->space->trashed())->toBeTrue()
        ->and(Space::query()->whereKey($id)->exists())->toBeFalse()
        ->and(Space::withTrashed()->whereKey($id)->exists())->toBeTrue();
});

test('a soft deleted space can be restored', function () {
    $id = $this->space->getKey();

    $this->space->delete();
    $this->space->restore();

    expect($this->space->trashed())->toBeFalse()
        ->and(Space::query()->whereKey($id)->exists())->toBeTrue();
});

test('a soft deleted space is no longer counted against the account quota', function () {
    expect($this->user->spacesUsed())->toBe(1);

    $this->space->delete();

    expect($this->user->spacesUsed())->toBe(0);
});

test('a soft deleted testimonial is hidden from normal queries but the row survives', function () {
    $testimonial = Testimonial::factory()->for($this->space)->create();
    $id = $testimonial->getKey();

    $testimonial->delete();

    expect($testimonial->trashed())->toBeTrue()
        ->and(Testimonial::query()->whereKey($id)->exists())->toBeFalse()
        ->and(Testimonial::withTrashed()->whereKey($id)->exists())->toBeTrue();
});

test('a soft deleted testimonial is not counted by the space', function () {
    Testimonial::factory()->count(3)->for($this->space)->create();

    expect($this->space->testimonialCount())->toBe(3);

    Testimonial::query()->first()->delete();

    expect($this->space->testimonialCount())->toBe(2);
});

test('a soft deleted testimonial can be restored', function () {
    $testimonial = Testimonial::factory()->for($this->space)->create();

    $testimonial->delete();
    $testimonial->restore();

    expect($testimonial->trashed())->toBeFalse()
        ->and($this->space->testimonialCount())->toBe(1);
});

test('the trashed factory state soft deletes the row rather than removing it', function () {
    $space = Space::factory()->trashed()->create();
    $testimonial = Testimonial::factory()->trashed()->for($this->space)->create();

    expect(Space::withTrashed()->whereKey($space->getKey())->exists())->toBeTrue()
        ->and(Space::query()->whereKey($space->getKey())->exists())->toBeFalse()
        ->and(Testimonial::withTrashed()->whereKey($testimonial->getKey())->exists())->toBeTrue()
        ->and(Testimonial::query()->whereKey($testimonial->getKey())->exists())->toBeFalse();
});
