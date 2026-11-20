<?php

use App\Billing\PlanType;
use App\Models\Space;
use App\Models\User;
use App\Spaces\SpaceSlugGenerator;

/*
|--------------------------------------------------------------------------
| Space management
|--------------------------------------------------------------------------
|
| Covers App\Http\Controllers\SpacesController, the space form requests and
| App\Spaces\SpaceFieldSeeder.
|
*/

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->owner->forceFill(['plan_slug' => PlanType::Free->value])->save();

    $this->other = User::factory()->create();
    $this->other->forceFill(['plan_slug' => PlanType::Free->value])->save();
});

/*
|--------------------------------------------------------------------------
| Creation
|--------------------------------------------------------------------------
*/

test('the create form can be rendered by an authenticated user', function () {
    $this->actingAs($this->owner)
        ->get(route('spaces.create'))
        ->assertOk()
        ->assertSee('Create a space');
});

test('a guest cannot reach the create form', function () {
    $this->get(route('spaces.create'))->assertRedirect(route('login'));
});

test('a user can create a space', function () {
    $response = $this->actingAs($this->owner)->post(route('spaces.store'), [
        'name' => 'Acme Reviews',
        'title' => 'What do you love about us?',
        'subtitle' => 'Your words mean a lot',
        'ask' => 'Tell us what you think of our product.',
        'theme' => 'minimal-light',
        'rating_enabled' => '1',
    ]);

    $space = Space::query()->where('name', 'Acme Reviews')->sole();

    $response->assertRedirect(route('spaces.created', ['space' => $space]));

    expect($space->user_id)->toBe($this->owner->getKey())
        ->and($space->title)->toBe('What do you love about us?')
        ->and($space->subtitle)->toBe('Your words mean a lot')
        ->and($space->ask)->toBe('Tell us what you think of our product.')
        ->and($space->theme)->toBe('minimal-light')
        ->and($space->rating_enabled)->toBeTrue();
});

test('a space gets a slug generated from its name', function () {
    $this->actingAs($this->owner)->post(route('spaces.store'), [
        'name' => 'Acme Reviews',
        'title' => 'Title',
        'ask' => 'Ask',
        'theme' => 'minimal-light',
        'rating_enabled' => '1',
    ]);

    $space = Space::query()->where('name', 'Acme Reviews')->sole();

    expect($space->slug)->toBe('acme-reviews');
});

test('a slug is generated that does not collide with an existing space', function () {
    Space::factory()->create(['slug' => 'acme-reviews']);

    $this->actingAs($this->owner)->post(route('spaces.store'), [
        'name' => 'Acme Reviews',
        'title' => 'Title',
        'ask' => 'Ask',
        'theme' => 'minimal-light',
        'rating_enabled' => '1',
    ]);

    $space = Space::query()->where('name', 'Acme Reviews')->sole();

    expect($space->slug)->not->toBe('acme-reviews')
        ->and($space->slug)->toStartWith('acme-reviews');
});

test('an explicit slug is used as given', function () {
    $this->actingAs($this->owner)->post(route('spaces.store'), [
        'name' => 'Acme Reviews',
        'slug' => 'my-custom-link',
        'title' => 'Title',
        'ask' => 'Ask',
        'theme' => 'minimal-light',
        'rating_enabled' => '1',
    ]);

    expect(Space::query()->where('slug', 'my-custom-link')->exists())->toBeTrue();
});

test('a space is created with the three optional fields enabled but not required', function () {
    $this->actingAs($this->owner)->post(route('spaces.store'), [
        'name' => 'Acme Reviews',
        'title' => 'Title',
        'ask' => 'Ask',
        'theme' => 'minimal-light',
        'rating_enabled' => '1',
    ]);

    $space = Space::query()->where('name', 'Acme Reviews')->sole();

    expect($space->fields)->toHaveCount(3);

    foreach ($space->fields as $field) {
        expect($field->enabled)->toBeTrue()
            ->and($field->required)->toBeFalse();
    }

    expect($space->enabledFields())->toHaveCount(3);
});

test('creating a space requires a name a title and an ask', function () {
    $this->actingAs($this->owner)
        ->post(route('spaces.store'), [])
        ->assertSessionHasErrors(['name', 'title', 'ask']);

    expect(Space::query()->count())->toBe(0);
});

test('creating a space requires a known theme', function () {
    $this->actingAs($this->owner)
        ->post(route('spaces.store'), [
            'name' => 'Acme Reviews',
            'title' => 'Title',
            'ask' => 'Ask',
            'theme' => 'not-a-theme',
            'rating_enabled' => '1',
        ])
        ->assertSessionHasErrors('theme');

    expect(Space::query()->count())->toBe(0);
});

test('creating a space rejects a slug that is already taken', function () {
    Space::factory()->create(['slug' => 'taken-link']);

    $this->actingAs($this->owner)
        ->post(route('spaces.store'), [
            'name' => 'Acme Reviews',
            'slug' => 'taken-link',
            'title' => 'Title',
            'ask' => 'Ask',
            'theme' => 'minimal-light',
            'rating_enabled' => '1',
        ])
        ->assertSessionHasErrors('slug');

    expect(Space::query()->where('slug', 'taken-link')->count())->toBe(1);
});

test('a guest cannot create a space', function () {
    $this->post(route('spaces.store'), [
        'name' => 'Acme Reviews',
        'title' => 'Title',
        'ask' => 'Ask',
        'theme' => 'minimal-light',
        'rating_enabled' => '1',
    ])->assertRedirect(route('login'));

    expect(Space::query()->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Quota enforcement
|--------------------------------------------------------------------------
*/

test('a free user cannot create a space once the limit is reached', function () {
    Space::factory()->count(3)->for($this->owner)->create();

    $this->actingAs($this->owner)
        ->post(route('spaces.store'), [
            'name' => 'Fourth Space',
            'title' => 'Title',
            'ask' => 'Ask',
            'theme' => 'minimal-light',
            'rating_enabled' => '1',
        ])
        ->assertForbidden();

    expect(Space::query()->where('name', 'Fourth Space')->exists())->toBeFalse();
});

test('a free user cannot reach the create form once the limit is reached', function () {
    Space::factory()->count(3)->for($this->owner)->create();

    $this->actingAs($this->owner)
        ->get(route('spaces.create'))
        ->assertRedirect(route('spaces.index'));
});

test('a pro user can create a space beyond the free limit', function () {
    $pro = User::factory()->create();
    $pro->forceFill(['plan_slug' => PlanType::Pro->value])->save();

    Space::factory()->count(3)->for($pro)->create();

    $this->actingAs($pro)->post(route('spaces.store'), [
        'name' => 'Fourth Space',
        'title' => 'Title',
        'ask' => 'Ask',
        'theme' => 'minimal-light',
        'rating_enabled' => '1',
    ])->assertRedirect();

    expect(Space::query()->where('name', 'Fourth Space')->exists())->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| The creation success screen
|--------------------------------------------------------------------------
*/

test('the creation success screen shows the public url and the actions', function () {
    $space = Space::factory()->for($this->owner)->create(['slug' => 'acme-reviews']);

    $this->actingAs($this->owner)
        ->get(route('spaces.created', ['space' => $space]))
        ->assertOk()
        ->assertSee('Copy link')
        ->assertSee('View space')
        ->assertSee('Go to spaces')
        ->assertSee(route('spaces.index'))
        ->assertSee(url('/s/acme-reviews'));
});

test('the creation success screen is not visible to another account', function () {
    $space = Space::factory()->for($this->owner)->create();

    $this->actingAs($this->other)
        ->get(route('spaces.created', ['space' => $space]))
        ->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Editing
|--------------------------------------------------------------------------
*/

test('the edit form can be rendered by the owner', function () {
    $space = Space::factory()->for($this->owner)->create();

    $this->actingAs($this->owner)
        ->get(route('spaces.edit', ['space' => $space]))
        ->assertOk()
        ->assertSee($space->name);
});

test('another account cannot reach the edit form', function () {
    $space = Space::factory()->for($this->owner)->create();

    $this->actingAs($this->other)
        ->get(route('spaces.edit', ['space' => $space]))
        ->assertForbidden();
});

test('the owner can update a space', function () {
    $space = Space::factory()->for($this->owner)->create();

    $this->actingAs($this->owner)
        ->put(route('spaces.update', ['space' => $space]), [
            'name' => 'Updated Name',
            'slug' => $space->slug,
            'title' => 'Updated Title',
            'subtitle' => 'Updated Subtitle',
            'ask' => 'Updated Ask',
            'theme' => 'modern-dark',
            'rating_enabled' => '0',
        ])
        ->assertRedirect(route('spaces.index'))
        ->assertSessionHas('status', 'space-updated');

    $space->refresh();

    expect($space->name)->toBe('Updated Name')
        ->and($space->title)->toBe('Updated Title')
        ->and($space->theme)->toBe('modern-dark')
        ->and($space->rating_enabled)->toBeFalse();
});

test('another account cannot update a space', function () {
    $space = Space::factory()->for($this->owner)->create(['name' => 'Original']);

    $this->actingAs($this->other)
        ->put(route('spaces.update', ['space' => $space]), [
            'name' => 'Hijacked',
            'slug' => $space->slug,
            'title' => 'Title',
            'ask' => 'Ask',
            'theme' => 'minimal-light',
            'rating_enabled' => '1',
        ])
        ->assertForbidden();

    expect($space->fresh()->name)->toBe('Original');
});

test('the owner can change the slug of a space', function () {
    $space = Space::factory()->for($this->owner)->create(['slug' => 'old-link']);

    $this->actingAs($this->owner)
        ->put(route('spaces.update', ['space' => $space]), [
            'name' => $space->name,
            'slug' => 'new-link',
            'title' => $space->title,
            'ask' => $space->ask,
            'theme' => $space->theme,
            'rating_enabled' => '1',
        ])
        ->assertSessionHasNoErrors();

    expect($space->fresh()->slug)->toBe('new-link');
});

/*
|--------------------------------------------------------------------------
| Deleting
|--------------------------------------------------------------------------
*/

test('the owner can delete a space', function () {
    $space = Space::factory()->for($this->owner)->create();

    $this->actingAs($this->owner)
        ->delete(route('spaces.destroy', ['space' => $space]))
        ->assertRedirect(route('spaces.index'))
        ->assertSessionHas('status', 'space-deleted');

    expect(Space::query()->whereKey($space->getKey())->exists())->toBeFalse();
    expect(Space::withTrashed()->whereKey($space->getKey())->exists())->toBeTrue();
});

test('another account cannot delete a space', function () {
    $space = Space::factory()->for($this->owner)->create();

    $this->actingAs($this->other)
        ->delete(route('spaces.destroy', ['space' => $space]))
        ->assertForbidden();

    expect(Space::query()->whereKey($space->getKey())->exists())->toBeTrue();
});

test('a deleted space no longer accepts new testimonials', function () {
    $space = Space::factory()->for($this->owner)->create();

    $this->actingAs($this->owner)->delete(route('spaces.destroy', ['space' => $space]));

    $this->post(route('spaces.public.store', ['space' => $space->slug]), [
        'customer_name' => 'Ada',
        'customer_email' => 'ada@example.com',
        'testimonial_text' => 'Great product',
        'rating' => 5,
    ])->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| The slug generator
|--------------------------------------------------------------------------
*/

test('the slug generator returns the slug of a free name', function () {
    expect((new SpaceSlugGenerator)->unique('Hello World'))->toBe('hello-world');
});

test('the slug generator falls back to a default when the name cannot be slugified', function () {
    expect((new SpaceSlugGenerator)->unique('!!!'))->toBe('space');
});

test('the slug generator skips a slug that belongs to a soft deleted space', function () {
    $trashed = Space::factory()->trashed()->create(['slug' => 'reserved-link']);

    expect((new SpaceSlugGenerator)->unique('Reserved Link'))->not->toBe('reserved-link')
        ->and($trashed->fresh())->not->toBeNull();
});
