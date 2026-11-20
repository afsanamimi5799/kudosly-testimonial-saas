<?php

namespace App\Widgets;

use App\Models\Testimonial;
use App\Models\Widget;
use Illuminate\Support\Collection;

/**
 * Collects the testimonials that a widget is allowed to show.
 *
 * A widget may only ever show testimonials that are approved and that the
 * customer gave explicit permission to display. Favourite and wall of love
 * flags never widen that set, and a soft deleted testimonial is excluded by
 * the model's global scope.
 *
 * The customer email address is never selected, so it cannot reach a
 * public response even by accident.
 */
class EmbedRenderer
{
    /**
     * The testimonials a widget shows.
     *
     * @return Collection<int, Testimonial>
     */
    public function testimonials(Widget $widget): Collection
    {
        return $widget->space
            ->testimonials()
            ->publiclyVisible()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($widget->displayLimit())
            ->get([
                'id',
                'customer_name',
                'company_name',
                'profile_photo_path',
                'testimonial_text',
                'rating',
                'created_at',
            ]);
    }
}
