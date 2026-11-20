<?php

namespace App\Enums;

/**
 * Visual theme of a space.
 *
 * The slug values are persisted in the `theme` column on the spaces table,
 * so they must remain stable once released.
 */
enum SpaceTheme: string
{
    case MinimalLight = 'minimal-light';
    case ModernDark = 'modern-dark';
    case SoftGradient = 'soft-gradient';

    /**
     * Human readable theme name.
     */
    public function label(): string
    {
        return match ($this) {
            self::MinimalLight => 'Minimal Light',
            self::ModernDark => 'Modern Dark',
            self::SoftGradient => 'Soft Gradient',
        };
    }

    /**
     * Short explanation of what the theme looks like.
     */
    public function description(): string
    {
        return match ($this) {
            self::MinimalLight => 'A clean light theme with plenty of white space.',
            self::ModernDark => 'A dark theme with high contrast accents.',
            self::SoftGradient => 'A soft gradient background with a friendly feel.',
        };
    }

    /**
     * The body classes used to render this theme.
     *
     * The themes are expressed with Tailwind utility classes so that the
     * collection page can be rendered without any custom stylesheet.
     */
    public function bodyClasses(): string
    {
        return match ($this) {
            self::MinimalLight => 'bg-white text-slate-900',
            self::ModernDark => 'bg-slate-950 text-slate-100',
            self::SoftGradient => 'bg-gradient-to-br from-indigo-50 via-white to-sky-50 text-slate-900',
        };
    }

    /**
     * The classes used for the card that holds the collection form.
     */
    public function panelClasses(): string
    {
        return match ($this) {
            self::MinimalLight => 'bg-white border-slate-200 shadow-sm',
            self::ModernDark => 'bg-slate-900 border-slate-800 shadow-none',
            self::SoftGradient => 'bg-white/80 border-white shadow-lg shadow-indigo-100/50 backdrop-blur',
        };
    }

    /**
     * The classes used for the primary button on the collection page.
     */
    public function buttonClasses(): string
    {
        return match ($this) {
            self::MinimalLight => 'bg-slate-900 text-white hover:bg-slate-800',
            self::ModernDark => 'bg-indigo-500 text-white hover:bg-indigo-400',
            self::SoftGradient => 'bg-indigo-600 text-white hover:bg-indigo-500',
        };
    }

    /**
     * The classes used for input fields on the collection page.
     */
    public function inputClasses(): string
    {
        return match ($this) {
            self::MinimalLight => 'border-slate-300 bg-white text-slate-900 placeholder-slate-400 focus:border-slate-500 focus:ring-slate-400',
            self::ModernDark => 'border-slate-700 bg-slate-950 text-slate-100 placeholder-slate-500 focus:border-indigo-400 focus:ring-indigo-500',
            self::SoftGradient => 'border-indigo-100 bg-white text-slate-900 placeholder-slate-400 focus:border-indigo-400 focus:ring-indigo-400',
        };
    }

    /**
     * The classes used for secondary text on the collection page.
     */
    public function mutedClasses(): string
    {
        return match ($this) {
            self::MinimalLight => 'text-slate-500',
            self::ModernDark => 'text-slate-400',
            self::SoftGradient => 'text-slate-600',
        };
    }
}
