<?php

namespace App\Http\Controllers\PublicWall;

use App\Http\Controllers\Controller;
use App\Models\Space;
use Illuminate\View\View;

/**
 * The public wall of love of a space.
 *
 * The page is reachable without a session. It shows only the testimonials
 * that the owner has marked with a heart and that are eligible for public
 * display, which means they are approved and the customer gave explicit
 * permission to share them.
 *
 * A favourite or a heart flag never makes a testimonial public on its own.
 * The wall applies the public visibility rules on top of the heart flag, so
 * a hidden testimonial or a testimonial without sharing consent stays off
 * the wall even when the owner has hearted it.
 */
class WallController extends Controller
{
    /**
     * Show the wall of love of a space.
     */
    public function show(Space $space): View
    {
        $testimonials = $space->testimonials()
            ->wallOfLove()
            ->publiclyVisible()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return view('public.wall.show', [
            'space' => $space,
            'testimonials' => $testimonials,
        ]);
    }
}
