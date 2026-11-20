<?php

namespace App\Enums;

/**
 * The layout of an embeddable widget.
 *
 * The slug values are persisted in the `layout` column on the widgets table,
 * so they must remain stable once released.
 */
enum WidgetLayout: string
{
    case Masonry = 'masonry';
    case Carousel = 'carousel';

    /**
     * Human readable layout name.
     */
    public function label(): string
    {
        return match ($this) {
            self::Masonry => 'Masonry Grid',
            self::Carousel => 'Carousel',
        };
    }

    /**
     * Short explanation of the layout.
     */
    public function description(): string
    {
        return match ($this) {
            self::Masonry => 'Testimonials flow down the page in a tidy grid.',
            self::Carousel => 'Testimonials slide horizontally in a single row.',
        };
    }
}
