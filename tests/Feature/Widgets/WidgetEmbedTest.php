<?php

use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;
use App\Models\Widget;
use Database\Seeders\PlanSeeder;

/*
|--------------------------------------------------------------------------
| The public widget embed
|--------------------------------------------------------------------------
|
| Covers the public embed endpoints. A widget is embedded on an external
| website, so these endpoints work without a session. They may only ever
| expose approved testimonials that carry sharing consent, and they must
| never expose a customer email address.
|
*/

beforeEach(function () {
    $this->seed(PlanSeeder::class);

    $this->owner = User::factory()->create();
    $this->owner->forceFill(['plan_slug' => 'free'])->save();

    $this->other = User::factory()->create();
    $this->other->forceFill(['plan_slug' => 'free'])->save();

    $this->space = Space::factory()->for($this->owner)->create(['name' => 'Main space']);
    $this->otherSpace = Space::factory()->for($this->other)->create();

    $this->widget = Widget::factory()->for($this->space)->create();
});

/*
|--------------------------------------------------------------------------
| The iframe embed
|--------------------------------------------------------------------------
*/

test('the embed is reachable without a session', function () {
    $this->get(route('widgets.embed.show', ['widget' => $this->widget->public_id]))
        ->assertOk()
        ->assertSee('<!DOCTYPE html>', escape: false);
});

test('the embed renders the testimonials of the widget space', function () {
    Testimonial::factory()->approved()->for($this->space)->create([
        'customer_name' => 'Ada Lovelace',
        'sharing_consent' => true,
    ]);

    $this->get(route('widgets.embed.show', ['widget' => $this->widget->public_id]))
        ->assertOk()
        ->assertSee('Ada Lovelace');
});

test('the embed shows the testimonial text', function () {
    Testimonial::factory()->approved()->for($this->space)->create([
        'testimonial_text' => 'A wonderful product.',
        'sharing_consent' => true,
    ]);

    $this->get(route('widgets.embed.show', ['widget' => $this->widget->public_id]))
        ->assertOk()
        ->assertSee('A wonderful product.');
});

test('the embed never shows a pending testimonial', function () {
    Testimonial::factory()->pending()->for($this->space)->create([
        'customer_name' => 'Pending Person',
        'sharing_consent' => true,
    ]);

    $this->get(route('widgets.embed.show', ['widget' => $this->widget->public_id]))
        ->assertOk()
        ->assertDontSee('Pending Person');
});

test('hiding a testimonial removes it from the embed', function () {
    $testimonial = Testimonial::factory()->approved()->for($this->space)->create([
        'customer_name' => 'Ada Lovelace',
        'sharing_consent' => true,
    ]);

    $this->get(route('widgets.embed.show', ['widget' => $this->widget->public_id]))
        ->assertOk()
        ->assertSee('Ada Lovelace');

    $this->actingAs($this->owner)
        ->post(route('testimonials.hide', ['testimonial' => $testimonial]))
        ->assertRedirect(route('inbox.index'));

    $this->get(route('widgets.embed.show', ['widget' => $this->widget->public_id]))
        ->assertOk()
        ->assertDontSee('Ada Lovelace');
});

test('the data endpoint drops a testimonial that was hidden', function () {
    $testimonial = Testimonial::factory()->approved()->for($this->space)->create([
        'customer_name' => 'Ada Lovelace',
        'sharing_consent' => true,
    ]);

    $this->actingAs($this->owner)
        ->post(route('testimonials.hide', ['testimonial' => $testimonial]));

    $response = $this->get(route('widgets.embed.data', ['widget' => $this->widget->public_id]));

    $response->assertOk();

    expect($response->getContent())->not->toContain('Ada Lovelace');
});

test('the embed never shows a hidden testimonial', function () {
    Testimonial::factory()->hidden()->for($this->space)->create([
        'customer_name' => 'Hidden Person',
        'sharing_consent' => true,
    ]);

    $this->get(route('widgets.embed.show', ['widget' => $this->widget->public_id]))
        ->assertOk()
        ->assertDontSee('Hidden Person');
});

test('the embed never shows a testimonial without sharing consent', function () {
    Testimonial::factory()->approved()->for($this->space)->create([
        'customer_name' => 'Private Person',
        'sharing_consent' => false,
    ]);

    $this->get(route('widgets.embed.show', ['widget' => $this->widget->public_id]))
        ->assertOk()
        ->assertDontSee('Private Person');
});

test('a favourite never makes a testimonial public on its own', function () {
    Testimonial::factory()->favorite()->for($this->space)->create([
        'customer_name' => 'Favourite Person',
        'status' => 'pending',
        'sharing_consent' => true,
    ]);

    $this->get(route('widgets.embed.show', ['widget' => $this->widget->public_id]))
        ->assertOk()
        ->assertDontSee('Favourite Person');
});

test('the wall of love flag never makes a testimonial public on its own', function () {
    Testimonial::factory()->for($this->space)->create([
        'customer_name' => 'Wall Person',
        'is_wall_of_love' => true,
        'status' => 'pending',
        'sharing_consent' => true,
    ]);

    $this->get(route('widgets.embed.show', ['widget' => $this->widget->public_id]))
        ->assertOk()
        ->assertDontSee('Wall Person');
});

test('the embed never shows the testimonials of another account', function () {
    Testimonial::factory()->approved()->for($this->otherSpace)->create([
        'customer_name' => 'Foreign Person',
        'sharing_consent' => true,
    ]);

    $this->get(route('widgets.embed.show', ['widget' => $this->widget->public_id]))
        ->assertOk()
        ->assertDontSee('Foreign Person');
});

test('the embed never exposes a customer email address', function () {
    Testimonial::factory()->approved()->for($this->space)->create([
        'customer_name' => 'Ada Lovelace',
        'customer_email' => 'ada@example.test',
        'sharing_consent' => true,
    ]);

    $html = $this->get(route('widgets.embed.show', ['widget' => $this->widget->public_id]))->getContent();

    expect($html)->not->toContain('ada@example.test');
});

test('the embed never shows a soft deleted testimonial', function () {
    Testimonial::factory()->approved()->for($this->space)->create([
        'customer_name' => 'Removed Person',
        'sharing_consent' => true,
    ])->delete();

    $this->get(route('widgets.embed.show', ['widget' => $this->widget->public_id]))
        ->assertOk()
        ->assertDontSee('Removed Person');
});

test('the embed never exposes the space name or the owner', function () {
    $this->get(route('widgets.embed.show', ['widget' => $this->widget->public_id]))
        ->assertOk()
        ->assertDontSee('Main space')
        ->assertDontSee($this->owner->email);
});

test('the embed shows no more than the display limit of testimonials', function () {
    $this->widget->update(['display_limit' => 2]);

    Testimonial::factory()->count(5)->approved()->for($this->space)->create([
        'sharing_consent' => true,
    ]);

    $html = $this->get(route('widgets.embed.show', ['widget' => $this->widget->public_id]))->getContent();

    // The card markup carries the class as a class attribute. The
    // stylesheet mentions the class too, so only the markup is counted.
    expect(substr_count($html, 'class="kudosly-widget-card'))->toBe(2);
});

/*
|--------------------------------------------------------------------------
| The appearance
|--------------------------------------------------------------------------
*/

test('the embed reflects the masonry layout', function () {
    $this->widget->update(['layout' => 'masonry']);

    Testimonial::factory()->approved()->for($this->space)->create([
        'sharing_consent' => true,
    ]);

    $html = $this->get(route('widgets.embed.show', ['widget' => $this->widget->public_id]))->getContent();

    expect($html)->toContain('class="kudosly-widget-masonry"')
        ->and($html)->not->toContain('class="kudosly-widget-carousel"');
});

test('the embed reflects the carousel layout', function () {
    $this->widget->update(['layout' => 'carousel']);

    Testimonial::factory()->approved()->for($this->space)->create([
        'sharing_consent' => true,
    ]);

    $html = $this->get(route('widgets.embed.show', ['widget' => $this->widget->public_id]))->getContent();

    expect($html)->toContain('class="kudosly-widget-carousel"')
        ->and($html)->not->toContain('class="kudosly-widget-masonry"');
});

test('the embed reflects the dark theme', function () {
    $this->widget->update(['theme_mode' => 'dark']);

    $html = $this->get(route('widgets.embed.show', ['widget' => $this->widget->public_id]))->getContent();

    expect($html)->toContain('data-kudosly-theme="dark"');
});

test('the embed reflects the light theme', function () {
    $this->widget->update(['theme_mode' => 'light']);

    $html = $this->get(route('widgets.embed.show', ['widget' => $this->widget->public_id]))->getContent();

    expect($html)->toContain('data-kudosly-theme="light"');
});

test('the embed paints the background colour', function () {
    $this->widget->update(['background_color' => '#eef2ff']);

    $html = $this->get(route('widgets.embed.show', ['widget' => $this->widget->public_id]))->getContent();

    expect($html)->toContain('background-color: #eef2ff;');
});

test('the embed has no background style when no colour was chosen', function () {
    $html = $this->get(route('widgets.embed.show', ['widget' => $this->widget->public_id]))->getContent();

    expect($html)->not->toContain('background-color:');
});

test('the embed animates its testimonials when animation is enabled', function () {
    $this->widget->update(['animation_enabled' => true]);

    Testimonial::factory()->approved()->for($this->space)->create([
        'sharing_consent' => true,
    ]);

    $html = $this->get(route('widgets.embed.show', ['widget' => $this->widget->public_id]))->getContent();

    // The animation is expressed by the markup attribute. The stylesheet
    // always carries the rule, so the class name proves nothing.
    expect($html)->toContain('data-animate="kudosly-widget-animate"');
});

test('the embed is static when animation is disabled', function () {
    $this->widget->update(['animation_enabled' => false]);

    Testimonial::factory()->approved()->for($this->space)->create([
        'sharing_consent' => true,
    ]);

    $html = $this->get(route('widgets.embed.show', ['widget' => $this->widget->public_id]))->getContent();

    expect($html)->not->toContain('data-animate=');
});

test('the embed shows the rating of a rated testimonial', function () {
    Testimonial::factory()->approved()->for($this->space)->create([
        'rating' => 5,
        'sharing_consent' => true,
    ]);

    $html = $this->get(route('widgets.embed.show', ['widget' => $this->widget->public_id]))->getContent();

    expect($html)->toContain('kudosly-widget-rating')
        ->and($html)->toContain('kudosly-widget-star-on');
});

/*
|--------------------------------------------------------------------------
| The caching
|--------------------------------------------------------------------------
|
| The embed may be stored by any cache, but a cache must always ask the
| server whether the copy it holds is still current. A testimonial is only
| public while the customer keeps their permission, so a cached copy that
| was made before a permission was withdrawn may never be handed out.
|
*/

test('the embed response may be cached publicly', function () {
    $response = $this->get(route('widgets.embed.show', ['widget' => $this->widget->public_id]));

    expect($response->headers->get('Cache-Control'))->toContain('public')
        ->and($response->headers->get('ETag'))->not->toBeNull();
});

test('the embed response must be revalidated before it is reused', function () {
    $response = $this->get(route('widgets.embed.show', ['widget' => $this->widget->public_id]));

    // A cache may store the embed, but it may not serve it again without
    // asking, because the testimonials on it can stop being public at any
    // moment.
    expect($response->headers->get('Cache-Control'))->toContain('no-cache')
        ->and($response->headers->get('Cache-Control'))->toContain('must-revalidate');
});

test('the embed response is never fresh without asking', function () {
    $response = $this->get(route('widgets.embed.show', ['widget' => $this->widget->public_id]));

    // A max-age would let a cache serve the stored body for that long
    // without ever asking the server.
    expect($response->headers->get('Cache-Control'))->not->toContain('max-age');
});

test('the data response must be revalidated before it is reused', function () {
    $response = $this->get(route('widgets.embed.data', ['widget' => $this->widget->public_id]));

    expect($response->headers->get('Cache-Control'))->toContain('public')
        ->and($response->headers->get('Cache-Control'))->toContain('no-cache')
        ->and($response->headers->get('Cache-Control'))->toContain('must-revalidate')
        ->and($response->headers->get('Cache-Control'))->not->toContain('max-age');
});

test('the loader script is still cached for a long time', function () {
    $response = $this->get(route('widgets.embed.script'));

    // The script carries no testimonial, so it may be cached freely.
    expect($response->headers->get('Cache-Control'))->toContain('public')
        ->and($response->headers->get('Cache-Control'))->toContain('max-age=3600');
});

test('the embed response does not vary by cookie', function () {
    $response = $this->get(route('widgets.embed.show', ['widget' => $this->widget->public_id]));

    // The embed is the same for every visitor, so it must never be
    // negotiated on the visitor's cookie.
    expect((string) $response->headers->get('Vary'))->not->toContain('Cookie');
});

test('the embed response is not modified when the entity tag still matches', function () {
    $url = route('widgets.embed.show', ['widget' => $this->widget->public_id]);

    $first = $this->get($url);
    $etag = $first->headers->get('ETag');

    $this->withHeaders(['If-None-Match' => $etag])
        ->get($url)
        ->assertNotModified();
});

test('the embed entity tag changes when the widget changes', function () {
    $url = route('widgets.embed.show', ['widget' => $this->widget->public_id]);

    $first = $this->get($url)->headers->get('ETag');

    $this->widget->update(['display_limit' => 4]);

    $second = $this->get($url)->headers->get('ETag');

    expect($second)->not->toBe($first);
});

/*
|--------------------------------------------------------------------------
| Cached copies and withdrawn permission
|--------------------------------------------------------------------------
|
| A cache holds on to the embed it was given. When a testimonial stops
| being public, the copy the cache holds must stop matching, so that the
| next visitor is served a fresh body without that testimonial in it.
|
*/

test('the embed entity tag changes when a testimonial is hidden', function () {
    $url = route('widgets.embed.show', ['widget' => $this->widget->public_id]);

    $testimonial = Testimonial::factory()->approved()->for($this->space)->create([
        'customer_name' => 'Ada Lovelace',
        'sharing_consent' => true,
    ]);

    $first = $this->get($url)->headers->get('ETag');

    $this->actingAs($this->owner)
        ->post(route('testimonials.hide', ['testimonial' => $testimonial]));

    $second = $this->get($url)->headers->get('ETag');

    expect($second)->not->toBe($first);
});

test('a cached embed is not reused after a testimonial was hidden', function () {
    $url = route('widgets.embed.show', ['widget' => $this->widget->public_id]);

    $testimonial = Testimonial::factory()->approved()->for($this->space)->create([
        'customer_name' => 'Ada Lovelace',
        'sharing_consent' => true,
    ]);

    $etag = $this->get($url)->headers->get('ETag');

    $this->actingAs($this->owner)
        ->post(route('testimonials.hide', ['testimonial' => $testimonial]));

    // The cache asks with the tag it was given before the change. The tag
    // no longer matches, so the cache is handed a fresh body instead of the
    // copy it already holds.
    $response = $this->withHeaders(['If-None-Match' => $etag])->get($url);

    $response->assertOk();

    expect($response->getContent())->not->toContain('Ada Lovelace');
});

test('the data entity tag changes when a testimonial is hidden', function () {
    $url = route('widgets.embed.data', ['widget' => $this->widget->public_id]);

    $testimonial = Testimonial::factory()->approved()->for($this->space)->create([
        'customer_name' => 'Ada Lovelace',
        'sharing_consent' => true,
    ]);

    $first = $this->get($url)->headers->get('ETag');

    $this->actingAs($this->owner)
        ->post(route('testimonials.hide', ['testimonial' => $testimonial]));

    $second = $this->get($url)->headers->get('ETag');

    expect($second)->not->toBe($first);
});

test('a cached data payload is not reused after a testimonial was hidden', function () {
    $url = route('widgets.embed.data', ['widget' => $this->widget->public_id]);

    $testimonial = Testimonial::factory()->approved()->for($this->space)->create([
        'customer_name' => 'Ada Lovelace',
        'sharing_consent' => true,
    ]);

    $etag = $this->get($url)->headers->get('ETag');

    $this->actingAs($this->owner)
        ->post(route('testimonials.hide', ['testimonial' => $testimonial]));

    $response = $this->withHeaders(['If-None-Match' => $etag])->get($url);

    $response->assertOk();

    expect($response->getContent())->not->toContain('Ada Lovelace');
});

test('the embed entity tag changes when a testimonial is deleted', function () {
    $url = route('widgets.embed.show', ['widget' => $this->widget->public_id]);

    $testimonial = Testimonial::factory()->approved()->for($this->space)->create([
        'customer_name' => 'Ada Lovelace',
        'sharing_consent' => true,
    ]);

    $first = $this->get($url)->headers->get('ETag');

    $testimonial->delete();

    $second = $this->get($url)->headers->get('ETag');

    expect($second)->not->toBe($first);
});

test('a cached embed is not reused after a testimonial was deleted', function () {
    $url = route('widgets.embed.show', ['widget' => $this->widget->public_id]);

    $testimonial = Testimonial::factory()->approved()->for($this->space)->create([
        'customer_name' => 'Ada Lovelace',
        'sharing_consent' => true,
    ]);

    $etag = $this->get($url)->headers->get('ETag');

    $testimonial->delete();

    $response = $this->withHeaders(['If-None-Match' => $etag])->get($url);

    $response->assertOk();

    expect($response->getContent())->not->toContain('Ada Lovelace');
});

test('the embed entity tag changes when sharing consent is withdrawn', function () {
    $url = route('widgets.embed.show', ['widget' => $this->widget->public_id]);

    $testimonial = Testimonial::factory()->approved()->for($this->space)->create([
        'customer_name' => 'Ada Lovelace',
        'sharing_consent' => true,
    ]);

    $first = $this->get($url)->headers->get('ETag');

    $testimonial->update(['sharing_consent' => false]);

    $second = $this->get($url)->headers->get('ETag');

    expect($second)->not->toBe($first);
});

test('a cached embed is not reused after sharing consent was withdrawn', function () {
    $url = route('widgets.embed.show', ['widget' => $this->widget->public_id]);

    $testimonial = Testimonial::factory()->approved()->for($this->space)->create([
        'customer_name' => 'Ada Lovelace',
        'sharing_consent' => true,
    ]);

    $etag = $this->get($url)->headers->get('ETag');

    $testimonial->update(['sharing_consent' => false]);

    $response = $this->withHeaders(['If-None-Match' => $etag])->get($url);

    $response->assertOk();

    expect($response->getContent())->not->toContain('Ada Lovelace');
});

test('a cached data payload is not reused after sharing consent was withdrawn', function () {
    $url = route('widgets.embed.data', ['widget' => $this->widget->public_id]);

    $testimonial = Testimonial::factory()->approved()->for($this->space)->create([
        'customer_name' => 'Ada Lovelace',
        'sharing_consent' => true,
    ]);

    $etag = $this->get($url)->headers->get('ETag');

    $testimonial->update(['sharing_consent' => false]);

    $response = $this->withHeaders(['If-None-Match' => $etag])->get($url);

    $response->assertOk();

    expect($response->getContent())->not->toContain('Ada Lovelace');
});

test('the embed entity tag is unchanged while nothing about the content changes', function () {
    $url = route('widgets.embed.show', ['widget' => $this->widget->public_id]);

    Testimonial::factory()->approved()->for($this->space)->create([
        'customer_name' => 'Ada Lovelace',
        'sharing_consent' => true,
    ]);

    $first = $this->get($url)->headers->get('ETag');
    $second = $this->get($url)->headers->get('ETag');

    expect($second)->toBe($first);
});

/*
|--------------------------------------------------------------------------
| The data endpoint
|--------------------------------------------------------------------------
*/

test('the data endpoint returns the widget settings as json', function () {
    $this->widget->update([
        'layout' => 'carousel',
        'theme_mode' => 'dark',
        'display_limit' => 4,
    ]);

    $this->get(route('widgets.embed.data', ['widget' => $this->widget->public_id]))
        ->assertOk()
        ->assertJsonPath('layout', 'carousel')
        ->assertJsonPath('theme_mode', 'dark')
        ->assertJsonPath('display_limit', 4)
        ->assertJsonPath('widget_id', $this->widget->public_id);
});

test('the data endpoint never exposes a customer email address', function () {
    Testimonial::factory()->approved()->for($this->space)->create([
        'customer_name' => 'Ada Lovelace',
        'customer_email' => 'ada@example.test',
        'sharing_consent' => true,
    ]);

    $response = $this->get(route('widgets.embed.data', ['widget' => $this->widget->public_id]));

    expect($response->getContent())->not->toContain('ada@example.test');
});

test('the data endpoint only carries eligible testimonials', function () {
    Testimonial::factory()->approved()->for($this->space)->create([
        'customer_name' => 'Public Person',
        'sharing_consent' => true,
    ]);

    Testimonial::factory()->pending()->for($this->space)->create([
        'customer_name' => 'Pending Person',
        'sharing_consent' => true,
    ]);

    $response = $this->get(route('widgets.embed.data', ['widget' => $this->widget->public_id]));

    $response->assertOk()->assertJsonPath('testimonials.0.customer_name', 'Public Person');

    expect($response->getContent())->not->toContain('Pending Person');
});

/*
|--------------------------------------------------------------------------
| Unknown widgets
|--------------------------------------------------------------------------
*/

test('an unknown public id returns a not found response', function () {
    $this->get(route('widgets.embed.show', ['widget' => '00000000-0000-0000-0000-000000000000']))
        ->assertNotFound();
});

test('the embed of a soft deleted widget returns a not found response', function () {
    $this->widget->delete();

    $this->get(route('widgets.embed.show', ['widget' => $this->widget->public_id]))->assertNotFound();
});
