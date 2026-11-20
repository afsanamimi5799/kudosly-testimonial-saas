<?php

use App\Enums\TestimonialStatus;
use App\Models\Space;
use App\Models\Testimonial;

/*
|--------------------------------------------------------------------------
| Testimonial scopes and states
|--------------------------------------------------------------------------
|
| Covers App\Models\Testimonial and its factory states. The scopes query the
| database, so these tests live in the Feature scope where RefreshDatabase
| is applied.
|
*/

beforeEach(function () {
    $this->space = Space::factory()->create();
});

test('the approved scope returns only approved testimonials', function () {
    $approved = Testimonial::factory()->approved()->for($this->space)->create();
    $pending = Testimonial::factory()->pending()->for($this->space)->create();
    $hidden = Testimonial::factory()->hidden()->for($this->space)->create();

    $ids = Testimonial::query()->approved()->pluck('id')->all();

    expect($ids)->toContain($approved->id)
        ->and($ids)->not->toContain($pending->id)
        ->and($ids)->not->toContain($hidden->id)
        ->and($ids)->toHaveCount(1);
});

test('the publicly visible scope requires approval and sharing consent', function () {
    $visible = Testimonial::factory()->publiclyVisible()->for($this->space)->create();

    $approvedWithoutConsent = Testimonial::factory()
        ->for($this->space)
        ->create([
            'status' => TestimonialStatus::Approved,
            'sharing_consent' => false,
        ]);

    $pendingWithConsent = Testimonial::factory()
        ->for($this->space)
        ->create([
            'status' => TestimonialStatus::Pending,
            'sharing_consent' => true,
        ]);

    $ids = Testimonial::query()->publiclyVisible()->pluck('id')->all();

    expect($ids)->toContain($visible->id)
        ->and($ids)->not->toContain($approvedWithoutConsent->id)
        ->and($ids)->not->toContain($pendingWithConsent->id)
        ->and($ids)->toHaveCount(1);
});

test('the status is cast to the testimonial status enum', function () {
    $testimonial = Testimonial::factory()->approved()->for($this->space)->create();

    expect($testimonial->status)->toBe(TestimonialStatus::Approved)
        ->and($testimonial->status->label())->toBe('Approved');
});

test('the pending factory state produces a pending testimonial', function () {
    $testimonial = Testimonial::factory()->pending()->for($this->space)->create();

    expect($testimonial->status)->toBe(TestimonialStatus::Pending);
});

test('the hidden factory state produces a hidden testimonial', function () {
    $testimonial = Testimonial::factory()->hidden()->for($this->space)->create();

    expect($testimonial->status)->toBe(TestimonialStatus::Hidden);
});

test('the favourite and wall of love factory states set their flags', function () {
    $testimonial = Testimonial::factory()
        ->favorite()
        ->wallOfLove()
        ->for($this->space)
        ->create();

    expect($testimonial->is_favorite)->toBeTrue()
        ->and($testimonial->is_wall_of_love)->toBeTrue();
});

test('the rating and consent flags are cast to their primitive types', function () {
    $testimonial = Testimonial::factory()->for($this->space)->create([
        'rating' => 4,
        'sharing_consent' => true,
    ]);

    expect($testimonial->rating)->toBe(4)
        ->and($testimonial->sharing_consent)->toBeTrue();
});

test('a testimonial belongs to a space and a space counts its testimonials', function () {
    Testimonial::factory()->count(3)->for($this->space)->create();

    expect($this->space->testimonialCount())->toBe(3)
        ->and($this->space->testimonials()->count())->toBe(3);
});
