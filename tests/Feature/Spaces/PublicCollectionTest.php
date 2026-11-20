<?php

use App\Enums\SpaceFieldKey;
use App\Enums\TestimonialStatus;
use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Public testimonial collection
|--------------------------------------------------------------------------
|
| Covers App\Http\Controllers\PublicCollection\SpaceCollectionController and
| App\Http\Requests\SubmitTestimonialRequest.
|
*/

beforeEach(function () {
    $owner = User::factory()->create();
    $owner->forceFill(['plan_slug' => 'free'])->save();

    $this->space = Space::factory()->for($owner)->create([
        'slug' => 'acme-reviews',
        'title' => 'What do you love about us?',
        'subtitle' => 'Your words mean a lot',
        'ask' => 'Tell us what you think of our product.',
        'rating_enabled' => true,
    ]);

    // Every optional field is enabled but not required by default.
    foreach (SpaceFieldKey::cases() as $index => $key) {
        $this->space->fields()->create([
            'field_key' => $key->value,
            'enabled' => true,
            'required' => false,
            'sort_order' => $index,
        ]);
    }
});

/*
|--------------------------------------------------------------------------
| Viewing the collection page
|--------------------------------------------------------------------------
*/

test('a visitor can view the collection page without an account', function () {
    $this->get(route('spaces.public.show', ['space' => $this->space->slug]))
        ->assertOk()
        ->assertSee('What do you love about us?')
        ->assertSee('Tell us what you think of our product.')
        ->assertSee('Your words mean a lot');
});

test('the collection page shows the consent checkbox', function () {
    $this->get(route('spaces.public.show', ['space' => $this->space->slug]))
        ->assertOk()
        ->assertSee('I give permission for my testimonial to be publicly displayed');
});

test('the collection page shows the rating input when the rating is enabled', function () {
    $this->get(route('spaces.public.show', ['space' => $this->space->slug]))
        ->assertOk()
        ->assertSee('Your rating', escape: false);
});

test('the collection page hides the rating input when the rating is disabled', function () {
    $this->space->update(['rating_enabled' => false]);

    $this->get(route('spaces.public.show', ['space' => $this->space->slug]))
        ->assertOk()
        ->assertDontSee('Your rating', escape: false);
});

test('the collection page only shows the fields the space collects', function () {
    $this->space->fields()->where('field_key', SpaceFieldKey::CompanyName->value)->update(['enabled' => false]);

    $this->get(route('spaces.public.show', ['space' => $this->space->slug]))
        ->assertOk()
        ->assertDontSee('Company name', escape: false)
        ->assertSee('Social profile link', escape: false);
});

test('a visitor can view the thank you page', function () {
    $this->get(route('spaces.public.thanks', ['space' => $this->space->slug]))
        ->assertOk()
        ->assertSee('You', escape: false);
});

test('an unknown slug returns a not found response', function () {
    $this->get(route('spaces.public.show', ['space' => 'does-not-exist']))->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| Submitting a testimonial
|--------------------------------------------------------------------------
*/

test('a visitor can submit a testimonial without an account', function () {
    $response = $this->post(route('spaces.public.store', ['space' => $this->space->slug]), [
        'customer_name' => 'Ada Lovelace',
        'customer_email' => 'ada@example.com',
        'testimonial_text' => 'This product changed how I work.',
        'rating' => 5,
        'sharing_consent' => '1',
    ]);

    $response->assertRedirect(route('spaces.public.thanks', ['space' => $this->space->slug]));

    $testimonial = Testimonial::query()->sole();

    expect($testimonial->customer_name)->toBe('Ada Lovelace')
        ->and($testimonial->customer_email)->toBe('ada@example.com')
        ->and($testimonial->testimonial_text)->toBe('This product changed how I work.')
        ->and($testimonial->rating)->toBe(5)
        ->and($testimonial->sharing_consent)->toBeTrue()
        ->and($testimonial->status)->toBe(TestimonialStatus::Pending)
        ->and($testimonial->space_id)->toBe($this->space->getKey());
});

test('a submitted testimonial is stored as pending by default', function () {
    $this->post(route('spaces.public.store', ['space' => $this->space->slug]), [
        'customer_name' => 'Ada Lovelace',
        'customer_email' => 'ada@example.com',
        'testimonial_text' => 'Great product',
        'rating' => 4,
    ]);

    expect(Testimonial::query()->sole()->status)->toBe(TestimonialStatus::Pending);
});

test('a testimonial can be submitted without sharing consent', function () {
    $this->post(route('spaces.public.store', ['space' => $this->space->slug]), [
        'customer_name' => 'Ada Lovelace',
        'customer_email' => 'ada@example.com',
        'testimonial_text' => 'Great product',
        'rating' => 4,
    ])->assertRedirect(route('spaces.public.thanks', ['space' => $this->space->slug]));

    expect(Testimonial::query()->sole()->sharing_consent)->toBeFalse();
});

test('the customer email is lowercased and trimmed', function () {
    $this->post(route('spaces.public.store', ['space' => $this->space->slug]), [
        'customer_name' => 'Ada Lovelace',
        'customer_email' => '  ADA@Example.COM  ',
        'testimonial_text' => 'Great product',
        'rating' => 4,
    ]);

    expect(Testimonial::query()->sole()->customer_email)->toBe('ada@example.com');
});

test('a submission requires a name an email and the testimonial text', function () {
    $this->post(route('spaces.public.store', ['space' => $this->space->slug]), [])
        ->assertSessionHasErrors(['customer_name', 'customer_email', 'testimonial_text']);

    expect(Testimonial::query()->count())->toBe(0);
});

test('a submission requires a rating when the space asks for one', function () {
    $this->post(route('spaces.public.store', ['space' => $this->space->slug]), [
        'customer_name' => 'Ada Lovelace',
        'customer_email' => 'ada@example.com',
        'testimonial_text' => 'Great product',
    ])->assertSessionHasErrors('rating');

    expect(Testimonial::query()->count())->toBe(0);
});

test('a submission does not require a rating when the space does not ask for one', function () {
    $this->space->update(['rating_enabled' => false]);

    $this->post(route('spaces.public.store', ['space' => $this->space->slug]), [
        'customer_name' => 'Ada Lovelace',
        'customer_email' => 'ada@example.com',
        'testimonial_text' => 'Great product',
    ])->assertSessionHasNoErrors();

    expect(Testimonial::query()->sole()->rating)->toBeNull();
});

test('a rating outside the one to five range is rejected', function () {
    $this->post(route('spaces.public.store', ['space' => $this->space->slug]), [
        'customer_name' => 'Ada Lovelace',
        'customer_email' => 'ada@example.com',
        'testimonial_text' => 'Great product',
        'rating' => 9,
    ])->assertSessionHasErrors('rating');
});

/*
|--------------------------------------------------------------------------
| Configurable fields
|--------------------------------------------------------------------------
*/

test('an optional field may be left blank', function () {
    $this->post(route('spaces.public.store', ['space' => $this->space->slug]), [
        'customer_name' => 'Ada Lovelace',
        'customer_email' => 'ada@example.com',
        'testimonial_text' => 'Great product',
        'rating' => 4,
    ])->assertSessionHasNoErrors();

    expect(Testimonial::query()->sole()->company_name)->toBeNull();
});

test('a required field must be filled in', function () {
    $this->space->fields()
        ->where('field_key', SpaceFieldKey::CompanyName->value)
        ->update(['required' => true]);

    $this->post(route('spaces.public.store', ['space' => $this->space->slug]), [
        'customer_name' => 'Ada Lovelace',
        'customer_email' => 'ada@example.com',
        'testimonial_text' => 'Great product',
        'rating' => 4,
    ])->assertSessionHasErrors('company_name');

    expect(Testimonial::query()->count())->toBe(0);
});

test('a disabled field is never validated', function () {
    $this->space->fields()
        ->where('field_key', SpaceFieldKey::CompanyName->value)
        ->update(['enabled' => false, 'required' => true]);

    $this->post(route('spaces.public.store', ['space' => $this->space->slug]), [
        'customer_name' => 'Ada Lovelace',
        'customer_email' => 'ada@example.com',
        'testimonial_text' => 'Great product',
        'rating' => 4,
    ])->assertSessionHasNoErrors();
});

test('a value submitted for a disabled field is not stored', function () {
    $this->space->fields()
        ->where('field_key', SpaceFieldKey::CompanyName->value)
        ->update(['enabled' => false]);

    $this->post(route('spaces.public.store', ['space' => $this->space->slug]), [
        'customer_name' => 'Ada Lovelace',
        'customer_email' => 'ada@example.com',
        'company_name' => 'Smuggled Ltd',
        'testimonial_text' => 'Great product',
        'rating' => 4,
    ]);

    expect(Testimonial::query()->sole()->company_name)->toBeNull();
});

test('an enabled field stores the submitted value', function () {
    $this->post(route('spaces.public.store', ['space' => $this->space->slug]), [
        'customer_name' => 'Ada Lovelace',
        'customer_email' => 'ada@example.com',
        'company_name' => 'Analytical Engines Ltd',
        'social_url' => 'https://example.com/ada',
        'testimonial_text' => 'Great product',
        'rating' => 4,
    ])->assertSessionHasNoErrors();

    $testimonial = Testimonial::query()->sole();

    expect($testimonial->company_name)->toBe('Analytical Engines Ltd')
        ->and($testimonial->social_url)->toBe('https://example.com/ada');
});

test('a social link must be a valid url when it is collected', function () {
    $this->post(route('spaces.public.store', ['space' => $this->space->slug]), [
        'customer_name' => 'Ada Lovelace',
        'customer_email' => 'ada@example.com',
        'social_url' => 'not a url',
        'testimonial_text' => 'Great product',
        'rating' => 4,
    ])->assertSessionHasErrors('social_url');
});

/*
|--------------------------------------------------------------------------
| Profile photo uploads
|--------------------------------------------------------------------------
*/

test('a profile photo can be uploaded', function () {
    Storage::fake('public');

    $this->post(route('spaces.public.store', ['space' => $this->space->slug]), [
        'customer_name' => 'Ada Lovelace',
        'customer_email' => 'ada@example.com',
        'testimonial_text' => 'Great product',
        'rating' => 4,
        'profile_photo' => UploadedFile::fake()->image('avatar.jpg'),
    ])->assertSessionHasNoErrors();

    $path = Testimonial::query()->sole()->profile_photo_path;

    expect($path)->not->toBeNull();

    Storage::disk('public')->assertExists($path);
});

test('a profile photo must be an image', function () {
    Storage::fake('public');

    $this->post(route('spaces.public.store', ['space' => $this->space->slug]), [
        'customer_name' => 'Ada Lovelace',
        'customer_email' => 'ada@example.com',
        'testimonial_text' => 'Great product',
        'rating' => 4,
        'profile_photo' => UploadedFile::fake()->create('document.pdf', 100),
    ])->assertSessionHasErrors('profile_photo');

    expect(Testimonial::query()->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Plan limits and rate limiting
|--------------------------------------------------------------------------
*/

test('a space that reached its testimonial limit rejects new submissions', function () {
    // The free plan allows 100 testimonials per space.
    Testimonial::factory()->count(100)->for($this->space)->create();

    $this->post(route('spaces.public.store', ['space' => $this->space->slug]), [
        'customer_name' => 'Ada Lovelace',
        'customer_email' => 'ada@example.com',
        'testimonial_text' => 'Great product',
        'rating' => 4,
    ])->assertSessionHasErrors('customer_email');

    expect(Testimonial::query()->count())->toBe(100);
});

test('submissions from the same address are throttled', function () {
    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $this->post(route('spaces.public.store', ['space' => $this->space->slug]), [
            'customer_name' => 'Ada Lovelace',
            'customer_email' => 'ada@example.com',
            'testimonial_text' => 'Great product',
            'rating' => 4,
        ]);
    }

    $response = $this->post(route('spaces.public.store', ['space' => $this->space->slug]), [
        'customer_name' => 'Ada Lovelace',
        'customer_email' => 'ada@example.com',
        'testimonial_text' => 'Great product',
        'rating' => 4,
    ]);

    $response->assertSessionHasErrors('customer_email');

    expect(Testimonial::query()->count())->toBe(5);
});

/*
|--------------------------------------------------------------------------
| Privacy
|--------------------------------------------------------------------------
*/

test('the collection page never exposes a customer email address', function () {
    Testimonial::factory()->approved()->for($this->space)->create([
        'customer_email' => 'private@example.com',
    ]);

    $this->get(route('spaces.public.show', ['space' => $this->space->slug]))
        ->assertOk()
        ->assertDontSee('private@example.com');
});
