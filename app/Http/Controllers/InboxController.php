<?php

namespace App\Http\Controllers;

use App\Inbox\TestimonialFilters;
use App\Inbox\TestimonialQuery;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The testimonial inbox.
 *
 * Every testimonial is scoped to the spaces owned by the authenticated
 * account. The scope is applied by the query, so it applies to the listing
 * and to the count badges alike.
 */
class InboxController extends Controller
{
    /**
     * Show the testimonials submitted to the account's spaces.
     */
    public function index(Request $request, TestimonialQuery $query): View
    {
        $user = $request->user();

        $filters = TestimonialFilters::fromRequest($request);

        $testimonials = $query->results($user, $filters)
            ->paginate(TestimonialFilters::PER_PAGE)
            ->withQueryString();

        $statusCounts = $query->statusCounts($user);

        return view('inbox', [
            'user' => $user,
            'testimonials' => $testimonials,
            'filters' => $filters,
            'spaces' => $filters->spacesFor($user),
            'totalCount' => $query->totalCount($user),
            'pendingCount' => $statusCounts['pending'],
            'approvedCount' => $statusCounts['approved'],
            'hiddenCount' => $statusCounts['hidden'],
            'favoriteCount' => $query->favoriteCount($user),
            'wallOfLoveCount' => $query->wallOfLoveCount($user),
        ]);
    }
}
