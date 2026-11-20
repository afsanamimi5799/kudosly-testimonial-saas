<?php

use App\Models\Space;
use App\Models\User;
use App\Models\Widget;
use Database\Seeders\PlanSeeder;

/*
|--------------------------------------------------------------------------
| Widget authorization
|--------------------------------------------------------------------------
|
| A widget belongs to the account that owns the space it displays. Every
| management action must be refused for a guest and for an account that
| does not own the widget.
|
*/

beforeEach(function () {
    $this->seed(PlanSeeder::class);

    $this->owner = User::factory()->create();
    $this->owner->forceFill(['plan_slug' => 'free'])->save();

    $this->other = User::factory()->create();
    $this->other->forceFill(['plan_slug' => 'free'])->save();

    $this->space = Space::factory()->for($this->owner)->create();
    $this->otherSpace = Space::factory()->for($this->other)->create();

    $this->widget = Widget::factory()->for($this->space)->create();
    $this->foreignWidget = Widget::factory()->for($this->otherSpace)->create();
});

/*
|--------------------------------------------------------------------------
| The policy
|--------------------------------------------------------------------------
*/

test('the owner may view update and delete the widget', function () {
    expect($this->owner->can('view', $this->widget))->toBeTrue()
        ->and($this->owner->can('update', $this->widget))->toBeTrue()
        ->and($this->owner->can('delete', $this->widget))->toBeTrue();
});

test('another account may not view update or delete the widget', function () {
    expect($this->other->can('view', $this->widget))->toBeFalse()
        ->and($this->other->can('update', $this->widget))->toBeFalse()
        ->and($this->other->can('delete', $this->widget))->toBeFalse();
});

test('the owner may create a widget for their own space', function () {
    expect($this->owner->can('manageWidget', $this->space))->toBeTrue();
});

test('another account may not create a widget for a foreign space', function () {
    expect($this->other->can('manageWidget', $this->space))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| The routes
|--------------------------------------------------------------------------
*/

test('a guest cannot open the widget pages', function (string $routeName) {
    $this->get(route($routeName, ['widget' => $this->widget->id]))->assertRedirect(route('login'));
})->with([
    'index' => 'widgets.index',
    'create' => 'widgets.create',
    'edit' => 'widgets.edit',
    'preview' => 'widgets.preview',
]);

test('a guest cannot store a widget', function () {
    $this->post(route('widgets.store'), [
        'space_id' => $this->space->id,
        'layout' => 'masonry',
        'theme_mode' => 'light',
        'animation_enabled' => 1,
        'display_limit' => 10,
    ])->assertRedirect(route('login'));

    // The fixture already holds two widgets. The guest must not have added
    // a third one.
    expect(Widget::query()->count())->toBe(2);
});

test('a guest cannot update a widget', function () {
    $this->put(route('widgets.update', ['widget' => $this->widget]), [
        'layout' => 'carousel',
        'theme_mode' => 'dark',
        'animation_enabled' => 0,
        'display_limit' => 3,
    ])->assertRedirect(route('login'));

    expect($this->widget->refresh()->layout)->toBe('masonry');
});

test('a guest cannot delete a widget', function () {
    $this->delete(route('widgets.destroy', ['widget' => $this->widget]))->assertRedirect(route('login'));

    expect(Widget::query()->count())->toBe(2);
});

test('another account cannot open the edit form', function () {
    $this->actingAs($this->other)
        ->get(route('widgets.edit', ['widget' => $this->widget]))
        ->assertForbidden();
});

test('another account cannot open the preview', function () {
    $this->actingAs($this->other)
        ->get(route('widgets.preview', ['widget' => $this->widget]))
        ->assertForbidden();
});

test('another account cannot update the widget', function () {
    $this->actingAs($this->other)
        ->put(route('widgets.update', ['widget' => $this->widget]), [
            'layout' => 'carousel',
            'theme_mode' => 'dark',
            'animation_enabled' => 0,
            'display_limit' => 3,
        ])
        ->assertForbidden();

    expect($this->widget->refresh()->layout)->toBe('masonry');
});

test('another account cannot delete the widget', function () {
    $this->actingAs($this->other)
        ->delete(route('widgets.destroy', ['widget' => $this->widget]))
        ->assertForbidden();

    expect(Widget::query()->count())->toBe(2);
});

test('another account cannot create a widget for a foreign space', function () {
    $this->actingAs($this->other)
        ->post(route('widgets.store'), [
            'space_id' => $this->space->id,
            'layout' => 'masonry',
            'theme_mode' => 'light',
            'animation_enabled' => 1,
            'display_limit' => 10,
        ])
        ->assertForbidden();

    expect(Widget::query()->count())->toBe(2);
});

/*
|--------------------------------------------------------------------------
| The listing
|--------------------------------------------------------------------------
*/

test('the widget list never shows a widget of another account', function () {
    $this->actingAs($this->owner)
        ->get(route('widgets.index'))
        ->assertOk()
        ->assertViewHas('widgetsCount', 1);

    expect($this->widget->space->name)->not->toBe($this->foreignWidget->space->name);
});
