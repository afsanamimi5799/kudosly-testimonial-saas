<?php

use App\Enums\TestimonialStatus;
use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Public wall of love
|--------------------------------------------------------------------------
|
| Covers the public wall page of a space and the account level wall
| management page.
|
*/

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->owner->forceFill(['plan_slug' => 'free'])->save();

    $this->other = User::factory()->create();
    $this->other->forceFill(['plan_slug' => 'free'])->save();

    $this->space = Space::factory()->for($this->owner)->create([
        'name' => 'Main space',
        'title' => 'What do you think?',
    ]);

    $this->foreignSpace = Space::factory()->for($this->other)->create();
});

test('the public wall is reachable without a session', function () {
    $this->get(route('wall.public.show', ['space' => $this->space->slug]))
        ->assertOk()
        ->assertSee('What do you think?')
        ->assertSee('Wall of Love');
});

test('the public wall shows a hearted testimonial that is approved and consented', function () {
    Testimonial::factory()->wallOfLove()->publiclyVisible()->for($this->space)->create([
        'customer_name' => 'Ada Lovelace',
        'testimonial_text' => 'A wonderful product.',
    ]);

    $this->get(route('wall.public.show', ['space' => $this->space->slug]))
        ->assertOk()
        ->assertSee('Ada Lovelace')
        ->assertSee('A wonderful product.');
});

test('hiding a testimonial removes it from the public wall', function () {
    $testimonial = Testimonial::factory()->wallOfLove()->publiclyVisible()->for($this->space)->create([
        'customer_name' => 'Ada Lovelace',
        'testimonial_text' => 'A wonderful product.',
    ]);

    $this->get(route('wall.public.show', ['space' => $this->space->slug]))
        ->assertOk()
        ->assertSee('Ada Lovelace');

    $this->actingAs($this->owner)
        ->post(route('testimonials.hide', ['testimonial' => $testimonial]))
        ->assertRedirect(route('inbox.index'));

    $this->get(route('wall.public.show', ['space' => $this->space->slug]))
        ->assertOk()
        ->assertDontSee('Ada Lovelace');
});

test('approving a hidden testimonial puts it back on the public wall', function () {
    $testimonial = Testimonial::factory()->wallOfLove()->for($this->space)->create([
        'customer_name' => 'Ada Lovelace',
        'testimonial_text' => 'A wonderful product.',
        'status' => TestimonialStatus::Hidden,
        'sharing_consent' => true,
    ]);

    $this->get(route('wall.public.show', ['space' => $this->space->slug]))
        ->assertOk()
        ->assertDontSee('Ada Lovelace');

    $this->actingAs($this->owner)
        ->post(route('testimonials.approve', ['testimonial' => $testimonial]))
        ->assertRedirect(route('inbox.index'));

    $this->get(route('wall.public.show', ['space' => $this->space->slug]))
        ->assertOk()
        ->assertSee('Ada Lovelace');
});

test('the public wall does not show a testimonial that was not hearted', function () {
    Testimonial::factory()->publiclyVisible()->for($this->space)->create([
        'customer_name' => 'Grace Hopper',
    ]);

    $this->get(route('wall.public.show', ['space' => $this->space->slug]))
        ->assertOk()
        ->assertDontSee('Grace Hopper');
});

test('the public wall does not show a pending testimonial', function () {
    Testimonial::factory()->wallOfLove()->pending()->for($this->space)->create([
        'customer_name' => 'Pending Person',
    ]);

    $this->get(route('wall.public.show', ['space' => $this->space->slug]))
        ->assertOk()
        ->assertDontSee('Pending Person');
});

test('the public wall does not show a hidden testimonial', function () {
    Testimonial::factory()->wallOfLove()->hidden()->for($this->space)->create([
        'customer_name' => 'Hidden Person',
    ]);

    $this->get(route('wall.public.show', ['space' => $this->space->slug]))
        ->assertOk()
        ->assertDontSee('Hidden Person');
});

test('the public wall does not show a testimonial without sharing consent', function () {
    Testimonial::factory()->wallOfLove()->for($this->space)->create([
        'customer_name' => 'Private Person',
        'status' => TestimonialStatus::Approved,
        'sharing_consent' => false,
    ]);

    $this->get(route('wall.public.show', ['space' => $this->space->slug]))
        ->assertOk()
        ->assertDontSee('Private Person');
});

test('a favourite never overrides the public visibility rules', function () {
    Testimonial::factory()->favorite()->wallOfLove()->hidden()->for($this->space)->create([
        'customer_name' => 'Favourite But Hidden',
    ]);

    Testimonial::factory()->favorite()->wallOfLove()->for($this->space)->create([
        'customer_name' => 'Favourite Without Consent',
        'status' => TestimonialStatus::Approved,
        'sharing_consent' => false,
    ]);

    $this->get(route('wall.public.show', ['space' => $this->space->slug]))
        ->assertOk()
        ->assertDontSee('Favourite But Hidden')
        ->assertDontSee('Favourite Without Consent');
});

test('the public wall only shows the testimonials of its own space', function () {
    Testimonial::factory()->wallOfLove()->publiclyVisible()->for($this->space)->create([
        'customer_name' => 'Own Customer',
    ]);

    Testimonial::factory()->wallOfLove()->publiclyVisible()->for($this->foreignSpace)->create([
        'customer_name' => 'Foreign Customer',
    ]);

    $this->get(route('wall.public.show', ['space' => $this->space->slug]))
        ->assertOk()
        ->assertSee('Own Customer')
        ->assertDontSee('Foreign Customer');
});

test('the public wall never exposes a customer email address', function () {
    Testimonial::factory()->wallOfLove()->publiclyVisible()->for($this->space)->create([
        'customer_name' => 'Ada Lovelace',
        'customer_email' => 'private.customer@example.com',
    ]);

    $response = $this->get(route('wall.public.show', ['space' => $this->space->slug]));

    $response->assertOk()->assertDontSee('private.customer@example.com');
});

test('a soft deleted testimonial is excluded from the public wall', function () {
    Testimonial::factory()->wallOfLove()->publiclyVisible()->for($this->space)->create([
        'customer_name' => 'Removed Customer',
    ])->delete();

    $this->get(route('wall.public.show', ['space' => $this->space->slug]))
        ->assertOk()
        ->assertDontSee('Removed Customer');
});

test('the public wall shows the rating of a testimonial', function () {
    Testimonial::factory()->wallOfLove()->publiclyVisible()->for($this->space)->create([
        'customer_name' => 'Rated Customer',
        'rating' => 5,
    ]);

    $this->get(route('wall.public.show', ['space' => $this->space->slug]))
        ->assertOk()
        ->assertSee('Rated 5 out of 5');
});

test('the public wall shows an empty state when there is nothing on it', function () {
    $this->get(route('wall.public.show', ['space' => $this->space->slug]))
        ->assertOk()
        ->assertSee('No testimonials on the wall yet.');
});

test('the public wall of an unknown space is not found', function () {
    $this->get('/wall/no-such-space')->assertNotFound();
});

test('the account wall page lists every hearted testimonial', function () {
    Testimonial::factory()->wallOfLove()->for($this->space)->create([
        'customer_name' => 'Hearted Customer',
    ]);

    $this->actingAs($this->owner)
        ->get(route('wall-of-love.index'))
        ->assertOk()
        ->assertSee('Hearted Customer');
});

test('the account wall page lists a testimonial that is not yet public', function () {
    Testimonial::factory()->wallOfLove()->pending()->for($this->space)->create([
        'customer_name' => 'Still Pending',
    ]);

    $this->actingAs($this->owner)
        ->get(route('wall-of-love.index'))
        ->assertOk()
        ->assertSee('Still Pending')
        ->assertSee('Not public yet');
});

test('the account wall page never shows the testimonials of another account', function () {
    Testimonial::factory()->wallOfLove()->publiclyVisible()->for($this->foreignSpace)->create([
        'customer_name' => 'Foreign Customer',
    ]);

    $this->actingAs($this->owner)
        ->get(route('wall-of-love.index'))
        ->assertOk()
        ->assertDontSee('Foreign Customer');
});

test('a guest cannot reach the account wall page', function () {
    $this->get(route('wall-of-love.index'))->assertRedirect(route('login'));
});
