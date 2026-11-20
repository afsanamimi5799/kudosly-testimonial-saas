<?php

use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Testimonial policy
|--------------------------------------------------------------------------
|
| Covers App\Policies\TestimonialPolicy. Ownership of a testimonial is
| resolved through the space it was submitted to.
|
*/

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->owner->forceFill(['plan_slug' => 'free'])->save();

    $this->other = User::factory()->create();
    $this->other->forceFill(['plan_slug' => 'free'])->save();

    $this->space = Space::factory()->for($this->owner)->create();
    $this->testimonial = Testimonial::factory()->for($this->space)->create();
});

test('the owner of the space can view the testimonial', function () {
    expect($this->owner->can('view', $this->testimonial))->toBeTrue();
});

test('the owner of the space can update the testimonial', function () {
    expect($this->owner->can('update', $this->testimonial))->toBeTrue();
});

test('the owner of the space can delete the testimonial', function () {
    expect($this->owner->can('delete', $this->testimonial))->toBeTrue();
});

test('the owner of the space can approve the testimonial', function () {
    expect($this->owner->can('approve', $this->testimonial))->toBeTrue();
});

test('the owner of the space can hide the testimonial', function () {
    expect($this->owner->can('hide', $this->testimonial))->toBeTrue();
});

test('the owner of the space can favourite the testimonial', function () {
    expect($this->owner->can('favorite', $this->testimonial))->toBeTrue();
});

test('the owner of the space can add the testimonial to the wall', function () {
    expect($this->owner->can('wallOfLove', $this->testimonial))->toBeTrue();
});

test('another account cannot view the testimonial', function () {
    expect($this->other->can('view', $this->testimonial))->toBeFalse();
});

test('another account cannot update the testimonial', function () {
    expect($this->other->can('update', $this->testimonial))->toBeFalse();
});

test('another account cannot delete the testimonial', function () {
    expect($this->other->can('delete', $this->testimonial))->toBeFalse();
});

test('another account cannot approve the testimonial', function () {
    expect($this->other->can('approve', $this->testimonial))->toBeFalse();
});

test('another account cannot hide the testimonial', function () {
    expect($this->other->can('hide', $this->testimonial))->toBeFalse();
});

test('another account cannot favourite the testimonial', function () {
    expect($this->other->can('favorite', $this->testimonial))->toBeFalse();
});

test('another account cannot add the testimonial to the wall', function () {
    expect($this->other->can('wallOfLove', $this->testimonial))->toBeFalse();
});

test('the ownership check does not depend on the type of the foreign key', function () {
    $user = new User;

    $user->forceFill(['id' => (string) $this->owner->getKey()]);

    $space = new Space;

    $space->forceFill(['user_id' => $this->owner->getKey()]);

    $testimonial = new Testimonial;

    $testimonial->setRelation('space', $space);

    expect($user->can('view', $testimonial))->toBeTrue();
});
