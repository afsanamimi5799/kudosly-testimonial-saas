<?php

namespace App\Widgets;

use App\Enums\WidgetLayout;
use App\Enums\WidgetThemeMode;

/**
 * The settings that can be applied to an embeddable widget.
 *
 * The values are read from the request, checked against the allowed values,
 * and then stored on the widget. Keeping the parsing in one place means the
 * editor, the renderer and the validation rules always agree on what a
 * setting means.
 */
class WidgetSettings
{
    /**
     * The smallest number of testimonials a widget may show.
     */
    public const DISPLAY_LIMIT_MIN = 1;

    /**
     * The largest number of testimonials a widget may show.
     *
     * The limit bounds the public query so that a widget can never ask the
     * database for an unbounded number of records.
     */
    public const DISPLAY_LIMIT_MAX = 100;

    /**
     * The number of testimonials shown by default.
     */
    public const DISPLAY_LIMIT_DEFAULT = 10;

    /**
     * The background colours that can be picked in the editor.
     *
     * The list is an allowlist. A custom colour is accepted as well, but it
     * has to be a plain six digit hex value.
     *
     * @var list<string>
     */
    public const BACKGROUND_COLORS = [
        '#ffffff',
        '#f8fafc',
        '#eef2ff',
        '#ecfdf5',
        '#fff7ed',
        '#fdf2f8',
        '#1e293b',
        '#0f172a',
        '#312e81',
        '#134e4a',
    ];

    /**
     * The layout of the widget.
     */
    public WidgetLayout $layout = WidgetLayout::Masonry;

    /**
     * The colour scheme of the widget.
     */
    public WidgetThemeMode $themeMode = WidgetThemeMode::Light;

    /**
     * The background colour of the widget, if one was chosen.
     */
    public ?string $backgroundColor = null;

    /**
     * Whether the widget animates its testimonials in.
     */
    public bool $animationEnabled = true;

    /**
     * The number of testimonials the widget shows.
     */
    public int $displayLimit = self::DISPLAY_LIMIT_DEFAULT;

    /**
     * Build the settings from the validated request data.
     *
     * Every value is normalised, so a value that slipped past the validation
     * rules still cannot produce an invalid widget.
     */
    public static function fromArray(array $data): self
    {
        $settings = new self;

        $settings->layout = self::normaliseLayout($data['layout'] ?? null);
        $settings->themeMode = self::normaliseThemeMode($data['theme_mode'] ?? null);
        $settings->backgroundColor = self::normaliseBackgroundColor($data['background_color'] ?? null);
        $settings->animationEnabled = (bool) ($data['animation_enabled'] ?? false);
        $settings->displayLimit = self::normaliseDisplayLimit($data['display_limit'] ?? null);

        return $settings;
    }

    /**
     * The layout, falling back to the default for an unknown value.
     */
    public static function normaliseLayout(mixed $value): WidgetLayout
    {
        if ($value instanceof WidgetLayout) {
            return $value;
        }

        return WidgetLayout::tryFrom(is_string($value) ? $value : '')
            ?? WidgetLayout::Masonry;
    }

    /**
     * The colour scheme, falling back to the default for an unknown value.
     */
    public static function normaliseThemeMode(mixed $value): WidgetThemeMode
    {
        if ($value instanceof WidgetThemeMode) {
            return $value;
        }

        return WidgetThemeMode::tryFrom(is_string($value) ? $value : '')
            ?? WidgetThemeMode::Light;
    }

    /**
     * The background colour, or null when no valid colour was given.
     */
    public static function normaliseBackgroundColor(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $colour = strtolower(trim($value));

        if ($colour === '') {
            return null;
        }

        return preg_match('/^#[0-9a-f]{6}$/', $colour) === 1 ? $colour : null;
    }

    /**
     * The number of testimonials to show, clamped to the allowed range.
     */
    public static function normaliseDisplayLimit(mixed $value): int
    {
        if (! is_numeric($value)) {
            return self::DISPLAY_LIMIT_DEFAULT;
        }

        return max(
            self::DISPLAY_LIMIT_MIN,
            min(self::DISPLAY_LIMIT_MAX, (int) $value)
        );
    }

    /**
     * The values to store on the widget.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'layout' => $this->layout->value,
            'theme_mode' => $this->themeMode->value,
            'background_color' => $this->backgroundColor,
            'animation_enabled' => $this->animationEnabled,
            'display_limit' => $this->displayLimit,
        ];
    }
}
