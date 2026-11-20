<?php

namespace App\Http\Controllers;

use App\Models\Widget;
use App\Widgets\EmbedRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * The public endpoints of an embeddable widget.
 *
 * These endpoints are reachable without a session. A widget is addressed by
 * its unguessable public id, and it may only ever show the testimonials that
 * are approved and that the customer gave explicit permission to display.
 *
 * The responses are identical for every visitor, so they may be cached by
 * any intermediary. They never vary by cookie and never carry a session.
 */
class WidgetEmbedController extends Controller
{
    /**
     * The number of minutes a cached embed response stays fresh.
     */
    private const CACHE_MINUTES = 5;

    /**
     * The number of minutes a cached data response stays fresh.
     */
    private const DATA_CACHE_MINUTES = 1;

    /**
     * Show the widget as a standalone document for an iframe.
     */
    public function show(Request $request, Widget $widget, EmbedRenderer $renderer): Response|View
    {
        $testimonials = $renderer->testimonials($widget);

        $response = response()->view('widgets.embed', [
            'widget' => $widget,
            'testimonials' => $testimonials,
        ]);

        return $this->withCacheHeaders($response, $widget, self::CACHE_MINUTES);
    }

    /**
     * Show the settings of the widget as JSON.
     *
     * The payload carries the settings and the rendered testimonials only.
     * It never carries a customer email address, a space identifier or any
     * other private record.
     */
    public function data(Request $request, Widget $widget, EmbedRenderer $renderer): JsonResponse
    {
        $testimonials = $renderer->testimonials($widget);

        $response = response()->json([
            'widget_id' => $widget->public_id,
            'layout' => $widget->layoutEnum()->value,
            'theme_mode' => $widget->themeModeEnum()->value,
            'background_color' => $widget->backgroundColor(),
            'animation_enabled' => (bool) $widget->animation_enabled,
            'display_limit' => $widget->displayLimit(),
            'embed_url' => $widget->embedUrl(),
            'testimonials' => $testimonials->map(fn ($testimonial): array => [
                'customer_name' => $testimonial->customer_name,
                'company_name' => $testimonial->company_name,
                'testimonial_text' => $testimonial->testimonial_text,
                'rating' => $testimonial->rating,
            ])->values(),
        ]);

        return $this->withCacheHeaders($response, $widget, self::DATA_CACHE_MINUTES);
    }

    /**
     * Serve the loader script used by the JavaScript embed.
     *
     * The script only inserts an iframe into the placeholder element. It
     * never injects a stylesheet and never touches the styles of the host
     * website, so the embed cannot break the page it is placed on.
     */
    public function script(Request $request): Response
    {
        $response = response(view('widgets.embed-script')->render(), 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
        ]);

        return $response->header('Cache-Control', 'public, max-age=3600');
    }

    /**
     * Add the public cache headers to an embed response.
     *
     * The response is the same for every visitor, so it may be cached by
     * anybody. The entity tag is derived from the widget and the moment it
     * was last changed, so a settings change invalidates the cached copy.
     */
    private function withCacheHeaders(Response|JsonResponse $response, Widget $widget, int $minutes): Response|JsonResponse
    {
        $etag = '"'.$widget->public_id.'-'.$widget->updated_at?->getTimestamp().'-'.$widget->displayLimit().'"';

        if ($request = request()) {
            if ($request->headers->get('If-None-Match') === $etag) {
                return $response->setNotModified();
            }
        }

        return $response
            ->header('Cache-Control', 'public, max-age='.($minutes * 60))
            ->header('ETag', $etag);
    }
}
