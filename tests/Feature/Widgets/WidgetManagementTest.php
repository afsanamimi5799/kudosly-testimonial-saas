<?php

use App\Enums\WidgetLayout;
use App\Enums\WidgetThemeMode;
use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;
use App\Models\Widget;
use Database\Seeders\PlanSeeder;

/*
|--------------------------------------------------------------------------
| Widget management
|--------------------------------------------------------------------------
|
| Covers the widget list, the creation form, the update form, the live
| preview and the embed code that is offered once a widget is saved.
|
*/

beforeEach(function () {
    $this->seed(PlanSeeder::class);

    $this->owner = User::factory()->create();
    $this->owner->forceFill(['plan_slug' => 'free'])->save();

    $this->other = User::factory()->create();
    $this->other->forceFill(['plan_slug' => 'free'])->save();

    $this->space = Space::factory()->for($this->owner)->create(['name' => 'Main space']);
    $this->otherSpace = Space::factory()->for($this->other)->create(['name' => 'Foreign space']);
});

/*
|--------------------------------------------------------------------------
| The list
|--------------------------------------------------------------------------
*/

test('the widget list shows the widgets of the account', function () {
    Widget::factory()->for($this->space)->create();

    $this->actingAs($this->owner)
        ->get(route('widgets.index'))
        ->assertOk()
        ->assertSee('Main space')
        ->assertViewHas('widgetsCount', 1);
});

test('the widget list never shows the widgets of another account', function () {
    Widget::factory()->for($this->otherSpace)->create();

    $this->actingAs($this->owner)
        ->get(route('widgets.index'))
        ->assertOk()
        ->assertDontSee('Foreign space')
        ->assertViewHas('widgetsCount', 0);
});

test('the widget list offers a way to create a widget', function () {
    $this->actingAs($this->owner)
        ->get(route('widgets.index'))
        ->assertOk()
        ->assertSee('Create widget')
        ->assertSee(route('widgets.create'), escape: false);
});

test('a guest is redirected to the login page', function () {
    $this->get(route('widgets.index'))->assertRedirect(route('login'));
});

test('the widget list renders inside the application layout', function () {
    $html = $this->actingAs($this->owner)->get(route('widgets.index'))->getContent();

    expect($html)->toContain('<!DOCTYPE html>')
        ->and($html)->toContain('<aside')
        ->and($html)->toMatch('/<link[^>]+rel="stylesheet"[^>]*>/');
});

/*
|--------------------------------------------------------------------------
| Creating a widget
|--------------------------------------------------------------------------
*/

test('the create form is shown for an authenticated user', function () {
    $this->actingAs($this->owner)
        ->get(route('widgets.create'))
        ->assertOk()
        ->assertSee('Create widget')
        ->assertSee(route('widgets.store'), escape: false)
        ->assertSee('Main space');
});

test('a guest cannot open the create form', function () {
    $this->get(route('widgets.create'))->assertRedirect(route('login'));
});

test('a widget is created with the chosen settings', function () {
    $response = $this->actingAs($this->owner)->post(route('widgets.store'), [
        'space_id' => $this->space->id,
        'layout' => 'carousel',
        'theme_mode' => 'dark',
        'background_color' => '#eef2ff',
        'animation_enabled' => 0,
        'display_limit' => 5,
    ]);

    $response->assertRedirect();

    $widget = Widget::query()->firstOrFail();

    expect($widget->space_id)->toBe($this->space->id)
        ->and($widget->layout)->toBe('carousel')
        ->and($widget->theme_mode)->toBe('dark')
        ->and($widget->background_color)->toBe('#eef2ff')
        ->and($widget->animation_enabled)->toBeFalse()
        ->and($widget->display_limit)->toBe(5);
});

test('a created widget is given a public id', function () {
    $this->actingAs($this->owner)->post(route('widgets.store'), [
        'space_id' => $this->space->id,
        'layout' => 'masonry',
        'theme_mode' => 'light',
        'animation_enabled' => 1,
        'display_limit' => 10,
    ]);

    $widget = Widget::query()->firstOrFail();

    expect($widget->public_id)->not->toBeEmpty()
        ->and($widget->public_id)->toMatch('/^[0-9a-f-]{36}$/');
});

test('two widgets never share a public id', function () {
    Widget::factory()->count(5)->for($this->space)->create();

    $publicIds = Widget::query()->pluck('public_id');

    expect($publicIds->unique()->count())->toBe($publicIds->count());
});

test('a widget cannot be created for a space of another account', function () {
    $this->actingAs($this->owner)
        ->post(route('widgets.store'), [
            'space_id' => $this->otherSpace->id,
            'layout' => 'masonry',
            'theme_mode' => 'light',
            'animation_enabled' => 1,
            'display_limit' => 10,
        ])
        ->assertForbidden();

    expect(Widget::query()->count())->toBe(0);
});

test('a guest cannot create a widget', function () {
    $this->post(route('widgets.store'), [
        'space_id' => $this->space->id,
        'layout' => 'masonry',
        'theme_mode' => 'light',
        'animation_enabled' => 1,
        'display_limit' => 10,
    ])->assertRedirect(route('login'));

    expect(Widget::query()->count())->toBe(0);
});

test('an unknown layout is rejected', function () {
    $this->actingAs($this->owner)
        ->post(route('widgets.store'), [
            'space_id' => $this->space->id,
            'layout' => 'bogus',
            'theme_mode' => 'light',
            'animation_enabled' => 1,
            'display_limit' => 10,
        ])
        ->assertSessionHasErrors('layout');
});

test('an unknown theme mode is rejected', function () {
    $this->actingAs($this->owner)
        ->post(route('widgets.store'), [
            'space_id' => $this->space->id,
            'layout' => 'masonry',
            'theme_mode' => 'bogus',
            'animation_enabled' => 1,
            'display_limit' => 10,
        ])
        ->assertSessionHasErrors('theme_mode');
});

test('an invalid background colour is rejected', function () {
    $this->actingAs($this->owner)
        ->post(route('widgets.store'), [
            'space_id' => $this->space->id,
            'layout' => 'masonry',
            'theme_mode' => 'light',
            'background_color' => 'red; position: fixed',
            'animation_enabled' => 1,
            'display_limit' => 10,
        ])
        ->assertSessionHasErrors('background_color');
});

test('a display limit outside the allowed range is rejected', function () {
    $this->actingAs($this->owner)
        ->post(route('widgets.store'), [
            'space_id' => $this->space->id,
            'layout' => 'masonry',
            'theme_mode' => 'light',
            'animation_enabled' => 1,
            'display_limit' => 5000,
        ])
        ->assertSessionHasErrors('display_limit');
});

/*
|--------------------------------------------------------------------------
| Updating a widget
|--------------------------------------------------------------------------
*/

test('the edit form is shown for the owner', function () {
    $widget = Widget::factory()->for($this->space)->create();

    $this->actingAs($this->owner)
        ->get(route('widgets.edit', ['widget' => $widget]))
        ->assertOk()
        ->assertSee('Edit widget')
        ->assertSee(route('widgets.update', ['widget' => $widget]), escape: false)
        ->assertSee($widget->public_id);
});

test('a widget is updated with the chosen settings', function () {
    $widget = Widget::factory()->for($this->space)->create();

    $this->actingAs($this->owner)
        ->put(route('widgets.update', ['widget' => $widget]), [
            'layout' => 'carousel',
            'theme_mode' => 'dark',
            'background_color' => '#134e4a',
            'animation_enabled' => 0,
            'display_limit' => 3,
        ])
        ->assertRedirect();

    $widget->refresh();

    expect($widget->layout)->toBe('carousel')
        ->and($widget->theme_mode)->toBe('dark')
        ->and($widget->background_color)->toBe('#134e4a')
        ->and($widget->animation_enabled)->toBeFalse()
        ->and($widget->display_limit)->toBe(3);
});

test('the settings survive a page reload', function () {
    $widget = Widget::factory()->for($this->space)->create();

    $this->actingAs($this->owner)->put(route('widgets.update', ['widget' => $widget]), [
        'layout' => 'carousel',
        'theme_mode' => 'dark',
        'animation_enabled' => 0,
        'display_limit' => 7,
    ]);

    $this->actingAs($this->owner)
        ->get(route('widgets.edit', ['widget' => $widget]))
        ->assertOk()
        ->assertSee('carousel', escape: false)
        ->assertSee('value="7"', escape: false);
});

test('the public id cannot be changed', function () {
    $widget = Widget::factory()->for($this->space)->create();
    $original = $widget->public_id;

    $this->actingAs($this->owner)->put(route('widgets.update', ['widget' => $widget]), [
        'public_id' => 'forged-id',
        'layout' => 'masonry',
        'theme_mode' => 'light',
        'animation_enabled' => 1,
        'display_limit' => 10,
    ]);

    expect($widget->refresh()->public_id)->toBe($original);
});

/*
|--------------------------------------------------------------------------
| The embed code
|--------------------------------------------------------------------------
*/

test('the edit form offers a copyable iframe snippet', function () {
    $widget = Widget::factory()->for($this->space)->create();

    $html = $this->actingAs($this->owner)
        ->get(route('widgets.edit', ['widget' => $widget]))
        ->getContent();

    expect($html)->toContain(route('widgets.embed.show', ['widget' => $widget->public_id]), escape: false)
        ->and($html)->toContain('&lt;iframe')
        ->and($html)->toContain('data-kudosly-widget=&quot;'.$widget->public_id.'&quot;');
});

test('the edit form offers a copyable javascript snippet', function () {
    $widget = Widget::factory()->for($this->space)->create();

    $html = $this->actingAs($this->owner)
        ->get(route('widgets.edit', ['widget' => $widget]))
        ->getContent();

    expect($html)->toContain(route('widgets.embed.script'), escape: false)
        ->and($html)->toContain('embed.js');
});

/*
|--------------------------------------------------------------------------
| The embed code is labelled
|--------------------------------------------------------------------------
|
| The code blocks are read out loud by a screen reader, so each of them
| must carry the name of the heading that sits above it. Without it the
| reader announces an unnamed box of text.
|
*/

test('the iframe code box is named by its heading', function () {
    $widget = Widget::factory()->for($this->space)->create();

    $html = $this->actingAs($this->owner)
        ->get(route('widgets.edit', ['widget' => $widget]))
        ->getContent();

    expect($html)->toContain('id="widget-iframe-code-heading"')
        ->and($html)->toContain('aria-labelledby="widget-iframe-code-heading"');
});

test('the javascript code box is named by its heading', function () {
    $widget = Widget::factory()->for($this->space)->create();

    $html = $this->actingAs($this->owner)
        ->get(route('widgets.edit', ['widget' => $widget]))
        ->getContent();

    expect($html)->toContain('id="widget-script-code-heading"')
        ->and($html)->toContain('aria-labelledby="widget-script-code-heading"');
});

test('the code boxes point at headings that are actually on the page', function () {
    $widget = Widget::factory()->for($this->space)->create();

    $html = $this->actingAs($this->owner)
        ->get(route('widgets.edit', ['widget' => $widget]))
        ->getContent();

    preg_match('/aria-labelledby="widget-iframe-code-heading"/', $html, $iframe);
    preg_match('/aria-labelledby="widget-script-code-heading"/', $html, $script);

    expect($iframe)->not->toBeEmpty()
        ->and($script)->not->toBeEmpty()
        ->and($html)->toContain('id="widget-iframe-code-heading"')
        ->and($html)->toContain('id="widget-script-code-heading"');
});

/*
|--------------------------------------------------------------------------
| The copy button keeps its own label
|--------------------------------------------------------------------------
|
| After a successful copy the button says "Copied" for a moment and then
| goes back to what it said before. It must not fall back to a generic
| word, because the two buttons carry different labels.
|
*/

test('the copy button restores the label it had before copying', function () {
    $widget = Widget::factory()->for($this->space)->create();

    $html = $this->actingAs($this->owner)
        ->get(route('widgets.edit', ['widget' => $widget]))
        ->getContent();

    // The label of the button is read before it is replaced, and the same
    // text is put back afterwards.
    expect($html)->toContain('const original = button.textContent;')
        ->and($html)->toContain('button.textContent = original;')
        ->and($html)->not->toContain("button.dataset.copyLabel || 'Copy'");
});

/*
|--------------------------------------------------------------------------
| The preview
|--------------------------------------------------------------------------
*/

test('the preview renders the widget with its testimonials', function () {
    $widget = Widget::factory()->for($this->space)->create();

    Testimonial::factory()->approved()->for($this->space)->create([
        'customer_name' => 'Ada Lovelace',
        'sharing_consent' => true,
    ]);

    $this->actingAs($this->owner)
        ->get(route('widgets.preview', ['widget' => $widget]))
        ->assertOk()
        ->assertSee('Ada Lovelace')
        ->assertSee('Widget preview');
});

test('the preview uses the shared renderer', function () {
    $widget = Widget::factory()->for($this->space)->create();

    $html = $this->actingAs($this->owner)
        ->get(route('widgets.preview', ['widget' => $widget]))
        ->getContent();

    expect($html)->toContain('kudosly-widget')
        ->and($html)->toContain('data-kudosly-widget-root="'.$widget->public_id.'"');
});

test('the preview of a carousel widget shows the carousel markup', function () {
    $widget = Widget::factory()->for($this->space)->create(['layout' => 'carousel']);

    $html = $this->actingAs($this->owner)
        ->get(route('widgets.preview', ['widget' => $widget]))
        ->getContent();

    expect($html)->toContain('kudosly-widget-carousel');
});

test('the preview of a masonry widget shows the masonry markup', function () {
    $widget = Widget::factory()->for($this->space)->create(['layout' => 'masonry']);

    $html = $this->actingAs($this->owner)
        ->get(route('widgets.preview', ['widget' => $widget]))
        ->getContent();

    expect($html)->toContain('kudosly-widget-masonry');
});

/*
|--------------------------------------------------------------------------
| Deleting a widget
|--------------------------------------------------------------------------
*/

test('a widget is deleted and disappears from the list', function () {
    $widget = Widget::factory()->for($this->space)->create();

    $this->actingAs($this->owner)
        ->delete(route('widgets.destroy', ['widget' => $widget]))
        ->assertRedirect(route('widgets.index'));

    expect(Widget::query()->count())->toBe(0)
        ->and(Widget::withTrashed()->count())->toBe(1);
});

test('a deleted widget stops rendering on its embed', function () {
    $widget = Widget::factory()->for($this->space)->create();

    Testimonial::factory()->approved()->for($this->space)->create([
        'customer_name' => 'Ada Lovelace',
        'sharing_consent' => true,
    ]);

    $this->actingAs($this->owner)->delete(route('widgets.destroy', ['widget' => $widget]));

    $this->get(route('widgets.embed.show', ['widget' => $widget->public_id]))->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| The space and widget counts
|--------------------------------------------------------------------------
*/

test('the widget list counts every widget of the account', function () {
    Widget::factory()->count(3)->for($this->space)->create();

    $this->actingAs($this->owner)
        ->get(route('widgets.index'))
        ->assertOk()
        ->assertViewHas('widgetsCount', 3);
});

test('the widget list ignores a soft deleted widget', function () {
    $widget = Widget::factory()->for($this->space)->create();
    $widget->delete();

    $this->actingAs($this->owner)
        ->get(route('widgets.index'))
        ->assertOk()
        ->assertViewHas('widgetsCount', 0);
});

/*
|--------------------------------------------------------------------------
| The layouts and the theme modes are known values
|--------------------------------------------------------------------------
*/

test('the widget exposes its layout and theme as enums', function () {
    $widget = Widget::factory()->for($this->space)->create([
        'layout' => 'carousel',
        'theme_mode' => 'dark',
    ]);

    expect($widget->layoutEnum())->toBe(WidgetLayout::Carousel)
        ->and($widget->themeModeEnum())->toBe(WidgetThemeMode::Dark);
});

test('an unknown layout falls back to masonry', function () {
    $widget = Widget::factory()->for($this->space)->create(['layout' => 'bogus']);

    expect($widget->layoutEnum())->toBe(WidgetLayout::Masonry);
});

test('an unknown theme mode falls back to light', function () {
    $widget = Widget::factory()->for($this->space)->create(['theme_mode' => 'bogus']);

    expect($widget->themeModeEnum())->toBe(WidgetThemeMode::Light);
});
