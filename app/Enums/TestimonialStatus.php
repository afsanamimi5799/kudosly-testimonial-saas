<?php

namespace App\Enums;

/**
 * Moderation state of a testimonial.
 *
 * Mirrors the `status` enum column on the testimonials table.
 */
enum TestimonialStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Hidden = 'hidden';

    /**
     * Human readable status name.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Approved => 'Approved',
            self::Hidden => 'Hidden',
        };
    }

    /**
     * The badge classes used to render the status.
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Pending => 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-200',
            self::Approved => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-200',
            self::Hidden => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
        };
    }
}
