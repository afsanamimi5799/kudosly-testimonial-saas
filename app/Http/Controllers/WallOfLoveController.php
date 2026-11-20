<?php

namespace App\Http\Controllers;

use App\Models\Testimonial;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The account level wall of love management page.
 *
 * This page lists every testimonial the account has marked with a heart,
 * across all of its spaces. It is the management view. The public wall of
 * each space lives at /wall/{slug} and only shows the testimonials that are
 * also eligible for public display.
 */
class WallOfLoveController extends Controller
{
    /**
     * Show the testimonials flagged for the wall of love.
     *
     * The list is scoped to the authenticated account's own spaces. Every
     * flagged testimonial is listed, including the ones that are not yet
     * eligible for public display, so that the owner can see why a
     * testimonial is not on the public wall yet.
     */
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        $wallOfLove = Testimonial::query()
            ->whereIn('space_id', $user->spaces()->select('spaces.id'))
            ->wallOfLove()
            ->with('space:id,name,slug')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return view('wall-of-love', [
            'user' => $user,
            'testimonials' => $wallOfLove,
            'wallOfLoveCount' => $wallOfLove->count(),
            'publicCount' => $wallOfLove->filter(
                fn (Testimonial $testimonial): bool => $testimonial->isPubliclyVisible()
            )->count(),
        ]);
    }
}
