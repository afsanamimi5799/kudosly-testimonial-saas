<?php

namespace App\Http\Controllers;

use App\Billing\PlanType;
use App\Dashboard\SubmissionChart;
use App\Models\Testimonial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * The number of recent testimonials shown on the dashboard.
     */
    private const RECENT_LIMIT = 5;

    /**
     * Show the dashboard overview.
     *
     * Every statistic is derived from the authenticated account's own spaces.
     * The customer email address is never passed to the view, and the
     * recent testimonials show the customer name and a truncated excerpt of
     * the testimonial only.
     */
    public function __invoke(Request $request, SubmissionChart $chart): View
    {
        $user = $request->user();

        $user->loadMissing('plan');

        $spacesCount = $user->spacesUsed();
        $maxSpaces = $user->maxSpaces();
        $spacesRemaining = $user->spaceQuotaRemaining();

        $testimonials = Testimonial::query()
            ->whereIn('space_id', $user->spaces()->select('spaces.id'))
            ->select([
                'id',
                'customer_email',
                'rating',
                'created_at',
            ]);

        $totalTestimonials = (clone $testimonials)->count();
        $uniqueCustomers = (clone $testimonials)
            ->whereNotNull('customer_email')
            ->whereRaw("TRIM(customer_email) <> ''")
            ->distinct()
            ->count(DB::raw('LOWER(TRIM(customer_email))'));
        $testimonialsThisMonth = (clone $testimonials)
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->count();

        $averageRating = (float) (clone $testimonials)->whereNotNull('rating')->avg('rating');

        $spaces = $user->spaces()
            ->withCount('testimonials')
            ->orderByDesc('id')
            ->get();

        $range = $chart->normaliseRange($request->integer('range') ?: null);

        $submissionChart = $chart->forUser($user, $range);

        $recentTestimonials = Testimonial::query()
            ->whereIn('space_id', $user->spaces()->select('spaces.id'))
            ->with('space:id,name,slug')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::RECENT_LIMIT)
            ->get();

        return view('dashboard', [
            'user' => $user,
            'spaces' => $spaces,
            'spacesCount' => $spacesCount,
            'spacesRemaining' => $spacesRemaining,
            'maxSpaces' => $maxSpaces,
            'totalTestimonials' => $totalTestimonials,
            'uniqueCustomers' => $uniqueCustomers,
            'testimonialsThisMonth' => $testimonialsThisMonth,
            'averageRating' => $averageRating > 0 ? number_format($averageRating, 1) : '—',
            'averageRatingFootnote' => $averageRating > 0 ? 'out of 5, where rated' : 'no ratings yet',
            'planName' => $user->effectivePlan()->name,
            'isFreePlan' => $user->hasPlan(PlanType::Free),
            'spaceUsagePercentage' => $maxSpaces > 0 ? (int) min(100, round(($spacesCount / $maxSpaces) * 100)) : 0,
            'chart' => $submissionChart,
            'chartRange' => $range,
            'chartRanges' => SubmissionChart::RANGES,
            'recentTestimonials' => $recentTestimonials,
        ]);
    }
}
