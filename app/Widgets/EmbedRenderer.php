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

    /**
     * A fingerprint of the testimonials a widget is currently allowed to show.
     *
     * The fingerprint is derived from the very same rows the renderer would
     * display, so it can never describe a set that differs from the one on
     * the page. Hiding a testimonial, deleting it or taking away its sharing
     * consent all remove it from that set, and the fingerprint changes with
     * it. A cached copy that was made before the change therefore no longer
     * matches, and the cache is told to fetch a fresh body.
     *
     * Only identifiers and timestamps are hashed. No testimonial text and no
     * customer information is part of the value that leaves the server.
     */
    public function fingerprint(Widget $widget): string
    {
        $rows = $widget->space
            ->testimonials()
            ->publiclyVisible()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($widget->displayLimit())
            ->get(['id', 'created_at', 'updated_at']);

        $parts = $rows
            ->map(fn (Testimonial $testimonial): string => implode(':', [
                $testimonial->getKey(),
                $testimonial->created_at?->getTimestamp(),
                $testimonial->updated_at?->getTimestamp(),
            ]))
            ->all();

        return substr(hash('sha256', implode('|', $parts)), 0, 16);
    }
}
