<?php

use App\Enums\SpaceTheme;
use App\Models\Space;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Space themes
|--------------------------------------------------------------------------
|
| Covers App\Enums\SpaceTheme and the theme rendering on the collection page.
|
*/

beforeEach(function () {
    $owner = User::factory()->create();
    $owner->forceFill(['plan_slug' => 'free'])->save();

    $this->space = Space::factory()->for($owner)->create(['slug' => 'themed-space']);
});

test('the enum offers the three predefined themes', function () {
    expect(SpaceTheme::cases())->toHaveCount(3)
        ->and(SpaceTheme::MinimalLight->value)->toBe('minimal-light')
        ->and(SpaceTheme::ModernDark->value)->toBe('modern-dark')
        ->and(SpaceTheme::SoftGradient->value)->toBe('soft-gradient');
});

test('every theme has a label a description and a full set of styles', function () {
    foreach (SpaceTheme::cases() as $theme) {
        expect($theme->label())->not->toBeEmpty()
            ->and($theme->description())->not->toBeEmpty()
            ->and($theme->bodyClasses())->not->toBeEmpty()
            ->and($theme->panelClasses())->not->toBeEmpty()
            ->and($theme->buttonClasses())->not->toBeEmpty()
            ->and($theme->inputClasses())->not->toBeEmpty()
            ->and($theme->mutedClasses())->not->toBeEmpty();
    }
});

test('the three themes render distinctly on the collection page', function () {
    $bodies = [];

    foreach (SpaceTheme::cases() as $theme) {
        $this->space->update(['theme' => $theme->value]);

        $response = $this->get(route('spaces.public.show', ['space' => $this->space->slug]));

        $response->assertOk();

        $bodies[] = $response->getContent();
    }

    expect($bodies[0])->not->toBe($bodies[1])
        ->and($bodies[1])->not->toBe($bodies[2])
        ->and($bodies[0])->not->toBe($bodies[2]);
});

test('the collection page applies the body classes of the space theme', function () {
    $this->space->update(['theme' => SpaceTheme::ModernDark->value]);

    $this->get(route('spaces.public.show', ['space' => $this->space->slug]))
        ->assertOk()
        ->assertSee(SpaceTheme::ModernDark->bodyClasses(), escape: false);
});

test('the create form offers every theme with a live preview', function () {
    $owner = User::factory()->create();
    $owner->forceFill(['plan_slug' => 'free'])->save();

    $this->actingAs($owner)
        ->get(route('spaces.create'))
        ->assertOk()
        ->assertSee('data-theme-option="minimal-light"', escape: false)
        ->assertSee('data-theme-option="modern-dark"', escape: false)
        ->assertSee('data-theme-option="soft-gradient"', escape: false)
        ->assertSee('theme-preview', escape: false);
});

test('a space keeps the theme it was created with', function () {
    $owner = User::factory()->create();
    $owner->forceFill(['plan_slug' => 'free'])->save();

    $this->actingAs($owner)->post(route('spaces.store'), [
        'name' => 'Themed Space',
        'title' => 'Title',
        'ask' => 'Ask',
        'theme' => 'soft-gradient',
        'rating_enabled' => '1',
    ])->assertSessionHasNoErrors();

    expect(Space::query()->where('name', 'Themed Space')->sole()->theme)->toBe('soft-gradient');
});
