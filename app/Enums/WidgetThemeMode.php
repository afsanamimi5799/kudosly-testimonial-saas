<?php

namespace App\Enums;

/**
 * The colour scheme of an embeddable widget.
 *
 * The slug values are persisted in the `theme_mode` column on the widgets
 * table, so they must remain stable once released.
 */
enum WidgetThemeMode: string
{
    case Light = 'light';
    case Dark = 'dark';

    /**
     * Human readable theme name.
     */
    public function label(): string
    {
        return match ($this) {
            self::Light => 'Light',
            self::Dark => 'Dark',
        };
    }

    /**
     * The background classes of the widget body.
     *
     * The classes are scoped inside the widget document, so they can never
     * leak into a host website that embeds the widget.
     */
    public function bodyClasses(): string
    {
        return match ($this) {
            self::Light => 'bg-white text-slate-900',
            self::Dark => 'bg-slate-900 text-slate-100',
        };
    }

    /**
     * The classes used by a single testimonial card.
     */
    public function cardClasses(): string
    {
        return match ($this) {
            self::Light => 'bg-white border-slate-200 shadow-sm',
            self::Dark => 'bg-slate-800 border-slate-700 shadow-none',
        };
    }

    /**
     * The classes used for secondary text.
     */
    public function mutedClasses(): string
    {
        return match ($this) {
            self::Light => 'text-slate-500',
            self::Dark => 'text-slate-400',
        };
    }
}
