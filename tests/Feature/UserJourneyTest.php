<?php

use App\Enums\SpaceFieldKey;
use App\Enums\TestimonialStatus;
use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;
use Database\Seeders\PlanSeeder;

/*
|--------------------------------------------------------------------------
| The complete user journey
|--------------------------------------------------------------------------
|
| One test that walks the whole way a customer travels: register, create a
| space, collect a testimonial through the public page, moderate it, put it
| on the wall of love, publish it in a widget, and read the numbers on the
| dashboard. It also checks that another account can never reach any of it.
|
| The journey is deliberately run through the real forms and the real
| public pages rather than through the models, so that a break anywhere
| along the way shows up here.
|
*/

beforeEach(function () {
    $this->seed(PlanSeeder::class);
});

/*
|--------------------------------------------------------------------------
| The whole journey
|--------------------------------------------------------------------------
*/

test('a new customer registers, collects a testimonial and publishes it', function () {
    // 1. Register a new Free account and land on the dashboard.
    $this->post(route('register'), [
        'name' => 'Grace Hopper',
        'email' => 'grace@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticated();

    $owner = User::query()->where('email', 'grace@example.com')->sole();

    expect($owner->plan_slug)->toBe('free')
        ->and($owner->spacesUsed())->toBe(0);

    // 2. Create a space with a theme and the configurable fields.
    $this->post(route('spaces.store'), [
        'name' => 'Acme Reviews',
        'slug' => 'acme-reviews',
        'title' => 'What do you love about us?',
        'subtitle' => 'Your words mean a lot',
        'ask' => 'Tell us what you think of our product.',
        'theme' => 'modern-dark',
        'rating_enabled' => '1',
    ])->assertRedirect(route('spaces.created', ['space' => $owner->spaces()->sole()]));

    $space = $owner->spaces()->sole();

    expect($space->slug)->toBe('acme-reviews')
        ->and($space->theme)->toBe('modern-dark')
        ->and($space->rating_enabled)->toBeTrue();

    // Every configurable field starts enabled and optional.
    foreach (SpaceFieldKey::cases() as $key) {
        expect($space->hasFieldEnabled($key))->toBeTrue()
            ->and($space->hasFieldRequired($key))->toBeFalse();
    }

    // 3. Make the company name required so the field configuration is
    //    exercised on the live form rather than only on the settings page.
    $this->put(route('spaces.fields.update', ['space' => $space]), [
        'fields' => [
            'company_name' => ['enabled' => '1', 'required' => '1'],
            'social_url' => ['enabled' => '1', 'required' => '0'],
            'profile_photo' => ['enabled' => '0', 'required' => '0'],
        ],
    ])->assertRedirect(route('spaces.index'));

    expect($space->fresh()->hasFieldRequired(SpaceFieldKey::CompanyName))->toBeTrue()
        ->and($space->fresh()->hasFieldEnabled(SpaceFieldKey::ProfilePhoto))->toBeFalse();

    // 4. The collection page is public and shows the configured form.
    $this->get(route('spaces.public.show', ['space' => $space->slug]))
        ->assertOk()
        ->assertSee('What do you love about us?')
        ->assertSee('Company name')
        ->assertDontSee('Profile photo', escape: false);

    // 5. A visitor submits a testimonial through the public form.
    $this->post(route('spaces.public.store', ['space' => $space->slug]), [
        'customer_name' => 'Ada Lovelace',
        'customer_email' => 'ada@example.com',
        'company_name' => 'Analytical Engines Ltd',
        'social_url' => 'https://example.com/ada',
        'testimonial_text' => 'This product changed how I work.',
        'rating' => 5,
        'sharing_consent' => '1',
    ])->assertRedirect(route('spaces.public.thanks', ['space' => $space->slug]));

    $testimonial = Testimonial::query()->sole();

    expect($testimonial->customer_name)->toBe('Ada Lovelace')
        ->and($testimonial->company_name)->toBe('Analytical Engines Ltd')
        ->and($testimonial->status)->toBe(TestimonialStatus::Pending)
        ->and($testimonial->sharing_consent)->toBeTrue();

    // 6. The testimonial is pending in the owner's inbox, and nowhere else.
    $this->actingAs($owner)
        ->get(route('inbox.index'))
        ->assertOk()
        ->assertSee('Ada Lovelace')
        ->assertSee('This product changed how I work.')
        ->assertViewHas('pendingCount', 1)
        ->assertViewHas('totalCount', 1);

    // A pending testimonial is not public anywhere yet.
    $this->get(route('spaces.public.show', ['space' => $space->slug]))
        ->assertOk()
        ->assertDontSee('Ada Lovelace');

    $this->get(route('wall.public.show', ['space' => $space->slug]))
        ->assertOk()
        ->assertDontSee('Ada Lovelace');

    // 7. The owner approves the testimonial.
    $this->actingAs($owner)
        ->post(route('testimonials.approve', ['testimonial' => $testimonial]))
        ->assertRedirect(route('inbox.index'));

    expect($testimonial->fresh()->status)->toBe(TestimonialStatus::Approved);

    // 8. The owner adds it to the wall of love.
    $this->actingAs($owner)
        ->post(route('testimonials.wall-of-love', ['testimonial' => $testimonial]))
        ->assertRedirect(route('inbox.index'));

    expect($testimonial->fresh()->is_wall_of_love)->toBeTrue();

    // 9. The approved and consented testimonial is now on the public wall.
    $this->get(route('wall.public.show', ['space' => $space->slug]))
        ->assertOk()
        ->assertSee('Ada Lovelace')
        ->assertSee('This product changed how I work.');

    // 10. Create a Masonry widget and a Carousel widget for the space.
    $this->post(route('widgets.store'), [
        'space_id' => $space->getKey(),
        'layout' => 'masonry',
        'theme_mode' => 'light',
        'animation_enabled' => '1',
        'display_limit' => 10,
    ])->assertRedirect();

    $this->post(route('widgets.store'), [
        'space_id' => $space->getKey(),
        'layout' => 'carousel',
        'theme_mode' => 'dark',
        'animation_enabled' => '0',
        'display_limit' => 10,
    ])->assertRedirect();

    $masonry = $space->widgets()->where('layout', 'masonry')->sole();
    $carousel = $space->widgets()->where('layout', 'carousel')->sole();

    // 11. Both embed responses carry the approved testimonial.
    $this->get(route('widgets.embed.show', ['widget' => $masonry->public_id]))
        ->assertOk()
        ->assertSee('Ada Lovelace')
        ->assertSee('This product changed how I work.')
        ->assertSee('class="kudosly-widget-masonry"', escape: false);

    $this->get(route('widgets.embed.show', ['widget' => $carousel->public_id]))
        ->assertOk()
        ->assertSee('Ada Lovelace')
        ->assertSee('This product changed how I work.')
        ->assertSee('class="kudosly-widget-carousel"', escape: false);

    $this->get(route('widgets.embed.data', ['widget' => $masonry->public_id]))
        ->assertOk()
        ->assertJsonPath('testimonials.0.customer_name', 'Ada Lovelace')
        ->assertJsonPath('layout', 'masonry');

    $this->get(route('widgets.embed.data', ['widget' => $carousel->public_id]))
        ->assertOk()
        ->assertJsonPath('testimonials.0.customer_name', 'Ada Lovelace')
        ->assertJsonPath('layout', 'carousel');

    // 12. The dashboard reflects the submitted data.
    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertViewHas('spacesCount', 1)
        ->assertViewHas('totalTestimonials', 1)
        ->assertViewHas('uniqueCustomers', 1)
        ->assertViewHas('testimonialsThisMonth', 1)
        ->assertViewHas('averageRating', '5.0')
        ->assertSee('Ada Lovelace')
        ->assertSee('This product changed how I work.');

    $chart = $this->actingAs($owner)->get(route('dashboard'))->viewData('chart');

    expect($chart['total'])->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Public visibility needs approval and consent
|--------------------------------------------------------------------------
*/

test('a testimonial is only public when it is approved and consented', function () {
    $owner = User::factory()->create();
    $owner->forceFill(['plan_slug' => 'free'])->save();

    $space = Space::factory()->for($owner)->create();

    // Approved but no consent.
    $noConsent = Testimonial::factory()->approved()->for($space)->create([
        'customer_name' => 'No Consent Person',
        'sharing_consent' => false,
    ]);

    // Consented but not approved.
    $notApproved = Testimonial::factory()->pending()->for($space)->create([
        'customer_name' => 'Not Approved Person',
        'sharing_consent' => true,
    ]);

    // Approved and consented.
    $public = Testimonial::factory()->approved()->for($space)->create([
        'customer_name' => 'Public Person',
        'sharing_consent' => true,
    ]);

    // A widget on the space shows only the public one.
    $widget = $space->widgets()->create([
        'layout' => 'masonry',
        'theme_mode' => 'light',
        'animation_enabled' => true,
        'display_limit' => 10,
    ]);

    $embed = $this->get(route('widgets.embed.show', ['widget' => $widget->public_id]))->getContent();

    expect($embed)->toContain('Public Person')
        ->and($embed)->not->toContain('No Consent Person')
        ->and($embed)->not->toContain('Not Approved Person');

    // Taking the consent away removes it from the embed.
    $public->update(['sharing_consent' => false]);

    $embed = $this->get(route('widgets.embed.show', ['widget' => $widget->public_id]))->getContent();

    expect($embed)->not->toContain('Public Person');

    // Hiding it removes it from the embed too.
    $public->update(['sharing_consent' => true]);
    $public->update(['status' => TestimonialStatus::Hidden]);

    $embed = $this->get(route('widgets.embed.show', ['widget' => $widget->public_id]))->getContent();

    expect($embed)->not->toContain('Public Person');
});

/*
|--------------------------------------------------------------------------
| The Free plan limits
|--------------------------------------------------------------------------
*/

test('the free plan limits the number of spaces and testimonials', function () {
    $owner = User::factory()->create();
    $owner->forceFill(['plan_slug' => 'free'])->save();

    expect($owner->maxSpaces())->toBe(3)
        ->and($owner->maxTestimonialsPerSpace())->toBe(100);

    // The account may create spaces up to the limit.
    Space::factory()->count(2)->for($owner)->create();

    $this->actingAs($owner)
        ->post(route('spaces.store'), [
            'name' => 'Third space',
            'title' => 'Third',
            'ask' => 'Tell us what you think.',
            'theme' => 'minimal-light',
            'rating_enabled' => '1',
        ])->assertRedirect();

    expect($owner->spacesUsed())->toBe(3);

    // The fourth space is refused. The quota is checked by the policy
    // before the action runs, so the request is turned away outright.
    $this->actingAs($owner)
        ->post(route('spaces.store'), [
            'name' => 'Fourth space',
            'title' => 'Fourth',
            'ask' => 'Tell us what you think.',
            'theme' => 'minimal-light',
            'rating_enabled' => '1',
        ])->assertForbidden();

    expect($owner->spacesUsed())->toBe(3);

    // A space stops accepting testimonials once the limit is reached.
    $space = $owner->spaces()->first();

    Testimonial::factory()->count(100)->for($space)->create();

    $this->post(route('spaces.public.store', ['space' => $space->slug]), [
        'customer_name' => 'Ada Lovelace',
        'customer_email' => 'ada@example.com',
        'testimonial_text' => 'Great product',
        'rating' => 5,
    ])->assertSessionHasErrors('error');

    expect($space->testimonials()->count())->toBe(100);
});

/*
|--------------------------------------------------------------------------
| One account cannot reach another
|--------------------------------------------------------------------------
*/

test('one account cannot reach the spaces or testimonials of another', function () {
    $owner = User::factory()->create();
    $owner->forceFill(['plan_slug' => 'free'])->save();

    $other = User::factory()->create();
    $other->forceFill(['plan_slug' => 'free'])->save();

    $space = Space::factory()->for($owner)->create();

    $testimonial = Testimonial::factory()->approved()->for($space)->create([
        'customer_name' => 'Ada Lovelace',
        'sharing_consent' => true,
    ]);

    $widget = $space->widgets()->create([
        'layout' => 'masonry',
        'theme_mode' => 'light',
        'animation_enabled' => true,
        'display_limit' => 10,
    ]);

    // The other account cannot see the space, the testimonial or the widget
    // in its own listing pages.
    $this->actingAs($other)
        ->get(route('spaces.index'))
        ->assertOk()
        ->assertDontSee($space->name);

    $this->actingAs($other)
        ->get(route('inbox.index'))
        ->assertOk()
        ->assertDontSee('Ada Lovelace')
        ->assertViewHas('totalCount', 0);

    $this->actingAs($other)
        ->get(route('widgets.index'))
        ->assertOk()
        ->assertDontSee($widget->public_id);

    // The other account cannot moderate, heart, edit or delete it.
    $this->actingAs($other)
        ->post(route('testimonials.approve', ['testimonial' => $testimonial]))
        ->assertForbidden();

    $this->actingAs($other)
        ->post(route('testimonials.hide', ['testimonial' => $testimonial]))
        ->assertForbidden();

    $this->actingAs($other)
        ->post(route('testimonials.wall-of-love', ['testimonial' => $testimonial]))
        ->assertForbidden();

    $this->actingAs($other)
        ->put(route('testimonials.update', ['testimonial' => $testimonial]), [
            'customer_name' => 'Mallory',
            'customer_email' => 'mallory@example.com',
            'testimonial_text' => 'Changed',
        ])->assertForbidden();

    $this->actingAs($other)
        ->delete(route('testimonials.destroy', ['testimonial' => $testimonial]))
        ->assertForbidden();

    $this->actingAs($other)
        ->put(route('spaces.update', ['space' => $space]), [
            'name' => 'Stolen space',
            'title' => 'Stolen',
            'ask' => 'Stolen',
            'theme' => 'minimal-light',
            'rating_enabled' => '1',
        ])->assertForbidden();

    $this->actingAs($other)
        ->delete(route('spaces.destroy', ['space' => $space]))->assertForbidden();

    $this->actingAs($other)
        ->put(route('widgets.update', ['widget' => $widget]), [
            'layout' => 'carousel',
            'theme_mode' => 'dark',
            'animation_enabled' => '0',
            'display_limit' => 5,
        ])->assertForbidden();

    $this->actingAs($other)
        ->delete(route('widgets.destroy', ['widget' => $widget]))->assertForbidden();

    // The other account cannot create a widget for a space it does not own.
    $this->actingAs($other)
        ->post(route('widgets.store'), [
            'space_id' => $space->getKey(),
            'layout' => 'masonry',
            'theme_mode' => 'light',
            'animation_enabled' => '1',
            'display_limit' => 10,
        ])->assertForbidden();

    expect($space->widgets()->count())->toBe(1);

    // Nothing was changed by any of the attempts above.
    expect($testimonial->fresh()->customer_name)->toBe('Ada Lovelace')
        ->and($testimonial->fresh()->status)->toBe(TestimonialStatus::Approved)
        ->and($testimonial->fresh()->is_wall_of_love)->toBeFalse()
        ->and($space->fresh()->name)->not->toBe('Stolen space')
        ->and($widget->fresh()->layout)->toBe('masonry');
});

/*
|--------------------------------------------------------------------------
| A guest cannot reach the private pages
|--------------------------------------------------------------------------
*/

test('a guest cannot reach the private pages of the journey', function () {
    $owner = User::factory()->create();
    $owner->forceFill(['plan_slug' => 'free'])->save();

    $space = Space::factory()->for($owner)->create();

    $widget = $space->widgets()->create([
        'layout' => 'masonry',
        'theme_mode' => 'light',
        'animation_enabled' => true,
        'display_limit' => 10,
    ]);

    foreach ([
        route('dashboard'),
        route('spaces.index'),
        route('spaces.create'),
        route('spaces.edit', ['space' => $space]),
        route('spaces.fields', ['space' => $space]),
        route('inbox.index'),
        route('wall-of-love.index'),
        route('widgets.index'),
        route('widgets.create'),
        route('widgets.edit', ['widget' => $widget]),
        route('widgets.preview', ['widget' => $widget]),
        route('billing.index'),
        route('profile.edit'),
    ] as $url) {
        $this->get($url)->assertRedirect(route('login'));
    }
});
