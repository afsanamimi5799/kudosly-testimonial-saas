<?php

use App\Enums\TestimonialStatus;
use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Testimonial moderation
|--------------------------------------------------------------------------
|
| Covers the approve, hide, favourite, wall of love, edit and delete
| actions, and the ownership checks that guard them.
|
*/

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->owner->forceFill(['plan_slug' => 'free'])->save();

    $this->other = User::factory()->create();
    $this->other->forceFill(['plan_slug' => 'free'])->save();

    $this->space = Space::factory()->for($this->owner)->create();

    $this->testimonial = Testimonial::factory()->for($this->space)->create([
        'customer_name' => 'Ada Lovelace',
        'status' => TestimonialStatus::Pending,
    ]);
});

test('the owner can approve a testimonial', function () {
    $this->actingAs($this->owner)
        ->post(route('testimonials.approve', ['testimonial' => $this->testimonial]))
        ->assertRedirect(route('inbox.index'))
        ->assertSessionHas('status', 'testimonial-approved');

    expect($this->testimonial->refresh()->status)->toBe(TestimonialStatus::Approved);
});

test('the owner can hide a testimonial', function () {
    $this->actingAs($this->owner)
        ->post(route('testimonials.hide', ['testimonial' => $this->testimonial]))
        ->assertRedirect(route('inbox.index'))
        ->assertSessionHas('status', 'testimonial-hidden');

    expect($this->testimonial->refresh()->status)->toBe(TestimonialStatus::Hidden);
});

test('the owner can mark a testimonial as a favourite', function () {
    $this->actingAs($this->owner)
        ->post(route('testimonials.favorite', ['testimonial' => $this->testimonial]))
        ->assertRedirect(route('inbox.index'))
        ->assertSessionHas('status', 'testimonial-favorited');

    expect($this->testimonial->refresh()->is_favorite)->toBeTrue();
});

test('the owner can clear the favourite flag', function () {
    $this->testimonial->update(['is_favorite' => true]);

    $this->actingAs($this->owner)
        ->post(route('testimonials.favorite', ['testimonial' => $this->testimonial]))
        ->assertSessionHas('status', 'testimonial-unfavorited');

    expect($this->testimonial->refresh()->is_favorite)->toBeFalse();
});

test('the owner can add a testimonial to the wall of love', function () {
    $this->actingAs($this->owner)
        ->post(route('testimonials.wall-of-love', ['testimonial' => $this->testimonial]))
        ->assertRedirect(route('inbox.index'))
        ->assertSessionHas('status', 'testimonial-added-to-wall');

    expect($this->testimonial->refresh()->is_wall_of_love)->toBeTrue();
});

test('the owner can remove a testimonial from the wall of love', function () {
    $this->testimonial->update(['is_wall_of_love' => true]);

    $this->actingAs($this->owner)
        ->post(route('testimonials.wall-of-love', ['testimonial' => $this->testimonial]))
        ->assertSessionHas('status', 'testimonial-removed-from-wall');

    expect($this->testimonial->refresh()->is_wall_of_love)->toBeFalse();
});

test('favouriting a testimonial does not change the moderation status', function () {
    $this->actingAs($this->owner)
        ->post(route('testimonials.favorite', ['testimonial' => $this->testimonial]));

    expect($this->testimonial->refresh()->status)->toBe(TestimonialStatus::Pending);
});

test('adding a testimonial to the wall does not change the moderation status', function () {
    $this->actingAs($this->owner)
        ->post(route('testimonials.wall-of-love', ['testimonial' => $this->testimonial]));

    expect($this->testimonial->refresh()->status)->toBe(TestimonialStatus::Pending);
});

test('the favourite flag and the wall flag are independent of each other', function () {
    $this->actingAs($this->owner)
        ->post(route('testimonials.favorite', ['testimonial' => $this->testimonial]));

    expect($this->testimonial->refresh()->is_favorite)->toBeTrue()
        ->and($this->testimonial->refresh()->is_wall_of_love)->toBeFalse();

    $this->actingAs($this->owner)
        ->post(route('testimonials.wall-of-love', ['testimonial' => $this->testimonial]));

    expect($this->testimonial->refresh()->is_favorite)->toBeTrue()
        ->and($this->testimonial->refresh()->is_wall_of_love)->toBeTrue();
});

test('the owner can open the edit form of a testimonial', function () {
    $this->actingAs($this->owner)
        ->get(route('testimonials.edit', ['testimonial' => $this->testimonial]))
        ->assertOk()
        ->assertSee('Edit testimonial')
        ->assertSee('Ada Lovelace');
});

test('the owner can edit a testimonial', function () {
    $this->actingAs($this->owner)
        ->put(route('testimonials.update', ['testimonial' => $this->testimonial]), [
            'customer_name' => 'Ada L',
            'customer_email' => 'ADA@Example.com',
            'company_name' => 'Acme',
            'social_url' => 'https://example.com',
            'testimonial_text' => 'An updated testimonial.',
            'rating' => 5,
        ])
        ->assertRedirect(route('inbox.index'))
        ->assertSessionHas('status', 'testimonial-updated');

    $testimonial = $this->testimonial->refresh();

    expect($testimonial->customer_name)->toBe('Ada L')
        ->and($testimonial->customer_email)->toBe('ada@example.com')
        ->and($testimonial->company_name)->toBe('Acme')
        ->and($testimonial->testimonial_text)->toBe('An updated testimonial.')
        ->and($testimonial->rating)->toBe(5);
});

test('the edit form requires the customer name the email and the testimonial', function () {
    $this->actingAs($this->owner)
        ->put(route('testimonials.update', ['testimonial' => $this->testimonial]), [
            'customer_name' => '',
            'customer_email' => 'not-an-email',
            'testimonial_text' => '',
        ])
        ->assertSessionHasErrors(['customer_name', 'customer_email', 'testimonial_text']);
});

test('the edit form rejects a rating outside the one to five range', function () {
    $this->actingAs($this->owner)
        ->put(route('testimonials.update', ['testimonial' => $this->testimonial]), [
            'customer_name' => 'Ada Lovelace',
            'customer_email' => 'ada@example.com',
            'testimonial_text' => 'A testimonial.',
            'rating' => 9,
        ])
        ->assertSessionHasErrors('rating');
});

test('editing a testimonial does not change the moderation status', function () {
    $this->actingAs($this->owner)
        ->put(route('testimonials.update', ['testimonial' => $this->testimonial]), [
            'customer_name' => 'Ada Lovelace',
            'customer_email' => 'ada@example.com',
            'testimonial_text' => 'A testimonial.',
        ])
        ->assertSessionHasNoErrors();

    expect($this->testimonial->refresh()->status)->toBe(TestimonialStatus::Pending);
});

test('the owner can delete a testimonial', function () {
    $this->actingAs($this->owner)
        ->delete(route('testimonials.destroy', ['testimonial' => $this->testimonial]))
        ->assertRedirect(route('inbox.index'))
        ->assertSessionHas('status', 'testimonial-deleted');

    expect(Testimonial::query()->withTrashed()->find($this->testimonial->id))->not->toBeNull()
        ->and(Testimonial::query()->find($this->testimonial->id))->toBeNull();
});

test('a deleted testimonial no longer appears in the inbox', function () {
    $this->actingAs($this->owner)->delete(route('testimonials.destroy', ['testimonial' => $this->testimonial]));

    $this->actingAs($this->owner)
        ->get(route('inbox.index'))
        ->assertOk()
        ->assertDontSee('Ada Lovelace');
});

test('another account cannot approve a testimonial', function () {
    $this->actingAs($this->other)
        ->post(route('testimonials.approve', ['testimonial' => $this->testimonial]))
        ->assertForbidden();

    expect($this->testimonial->refresh()->status)->toBe(TestimonialStatus::Pending);
});

test('another account cannot hide a testimonial', function () {
    $this->actingAs($this->other)
        ->post(route('testimonials.hide', ['testimonial' => $this->testimonial]))
        ->assertForbidden();

    expect($this->testimonial->refresh()->status)->toBe(TestimonialStatus::Pending);
});

test('another account cannot favourite a testimonial', function () {
    $this->actingAs($this->other)
        ->post(route('testimonials.favorite', ['testimonial' => $this->testimonial]))
        ->assertForbidden();

    expect($this->testimonial->refresh()->is_favorite)->toBeFalse();
});

test('another account cannot add a testimonial to the wall', function () {
    $this->actingAs($this->other)
        ->post(route('testimonials.wall-of-love', ['testimonial' => $this->testimonial]))
        ->assertForbidden();

    expect($this->testimonial->refresh()->is_wall_of_love)->toBeFalse();
});

test('another account cannot edit a testimonial', function () {
    $this->actingAs($this->other)
        ->get(route('testimonials.edit', ['testimonial' => $this->testimonial]))
        ->assertForbidden();

    $this->actingAs($this->other)
        ->put(route('testimonials.update', ['testimonial' => $this->testimonial]), [
            'customer_name' => 'Hacked',
            'customer_email' => 'hacked@example.com',
            'testimonial_text' => 'Hacked.',
        ])
        ->assertForbidden();

    expect($this->testimonial->refresh()->customer_name)->toBe('Ada Lovelace');
});

test('another account cannot delete a testimonial', function () {
    $this->actingAs($this->other)
        ->delete(route('testimonials.destroy', ['testimonial' => $this->testimonial]))
        ->assertForbidden();

    expect(Testimonial::query()->find($this->testimonial->id))->not->toBeNull();
});

test('a guest cannot reach any moderation action', function () {
    $this->post(route('testimonials.approve', ['testimonial' => $this->testimonial]))->assertRedirect(route('login'));
    $this->post(route('testimonials.hide', ['testimonial' => $this->testimonial]))->assertRedirect(route('login'));
    $this->post(route('testimonials.favorite', ['testimonial' => $this->testimonial]))->assertRedirect(route('login'));
    $this->post(route('testimonials.wall-of-love', ['testimonial' => $this->testimonial]))->assertRedirect(route('login'));
    $this->delete(route('testimonials.destroy', ['testimonial' => $this->testimonial]))->assertRedirect(route('login'));

    expect($this->testimonial->refresh()->status)->toBe(TestimonialStatus::Pending)
        ->and($this->testimonial->refresh()->is_favorite)->toBeFalse()
        ->and($this->testimonial->refresh()->is_wall_of_love)->toBeFalse();
});

test('a deleted testimonial cannot be moderated', function () {
    $this->testimonial->delete();

    $this->actingAs($this->owner)
        ->post(route('testimonials.approve', ['testimonial' => $this->testimonial]))
        ->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| The profile photo
|--------------------------------------------------------------------------
|
| The uploaded photo is a copy of the customer's picture. It stays publicly
| reachable at its storage URL even when the testimonial is gone, so it
| must not outlive the testimonial it belongs to.
|
*/

test('deleting a testimonial removes its profile photo from storage', function () {
    Storage::fake('public');

    $this->actingAs($this->owner)
        ->post(route('spaces.public.store', ['space' => $this->space->slug]), [
            'customer_name' => 'Ada Lovelace',
            'customer_email' => 'ada@example.com',
            'testimonial_text' => 'A wonderful product.',
            'rating' => 5,
            'profile_photo' => UploadedFile::fake()->image('avatar.jpg'),
        ])
        ->assertSessionHasNoErrors();

    $testimonial = Testimonial::query()
        ->where('customer_email', 'ada@example.com')
        ->sole();

    $path = $testimonial->profile_photo_path;

    expect($path)->not->toBeNull();

    Storage::disk('public')->assertExists($path);

    $this->actingAs($this->owner)
        ->delete(route('testimonials.destroy', ['testimonial' => $testimonial]))
        ->assertRedirect(route('inbox.index'));

    Storage::disk('public')->assertMissing($path);
});

test('deleting a testimonial without a profile photo does not fail', function () {
    Storage::fake('public');

    expect($this->testimonial->profile_photo_path)->toBeNull();

    $this->actingAs($this->owner)
        ->delete(route('testimonials.destroy', ['testimonial' => $this->testimonial]))
        ->assertRedirect(route('inbox.index'))
        ->assertSessionHas('status', 'testimonial-deleted');

    expect(Testimonial::query()->find($this->testimonial->id))->toBeNull();
});

test('another account cannot delete a testimonial and its photo is kept', function () {
    Storage::fake('public');

    Storage::disk('public')->put('profile-photos/keep.jpg', 'photo');

    $testimonial = Testimonial::factory()->for($this->space)->create([
        'profile_photo_path' => 'profile-photos/keep.jpg',
    ]);

    $this->actingAs($this->other)
        ->delete(route('testimonials.destroy', ['testimonial' => $testimonial]))
        ->assertForbidden();

    Storage::disk('public')->assertExists('profile-photos/keep.jpg');
});

/*
|--------------------------------------------------------------------------
| Unhiding a testimonial
|--------------------------------------------------------------------------
|
| A hidden testimonial can be made public again by approving it once more.
|
*/

test('a hidden testimonial can be approved again', function () {
    $this->testimonial->update(['status' => TestimonialStatus::Hidden]);

    $this->actingAs($this->owner)
        ->post(route('testimonials.approve', ['testimonial' => $this->testimonial]))
        ->assertRedirect(route('inbox.index'))
        ->assertSessionHas('status', 'testimonial-approved');

    expect($this->testimonial->refresh()->status)->toBe(TestimonialStatus::Approved);
});

test('the inbox offers an approve action for a hidden testimonial', function () {
    $this->testimonial->update(['status' => TestimonialStatus::Hidden]);

    $this->actingAs($this->owner)
        ->get(route('inbox.index'))
        ->assertOk()
        ->assertSee(route('testimonials.approve', ['testimonial' => $this->testimonial]), escape: false);
});

test('approving a hidden testimonial makes it public again when it carries consent', function () {
    $this->testimonial->update([
        'status' => TestimonialStatus::Hidden,
        'sharing_consent' => true,
    ]);

    $this->actingAs($this->owner)
        ->post(route('testimonials.approve', ['testimonial' => $this->testimonial]));

    expect($this->testimonial->refresh()->isPubliclyVisible())->toBeTrue();
});
