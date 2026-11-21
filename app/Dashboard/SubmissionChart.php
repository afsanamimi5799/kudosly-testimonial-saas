<?php

namespace App\Dashboard;

use App\Models\Testimonial;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * The daily testimonial submission series shown on the dashboard.
 *
 * The series is built in PHP rather than in the database so that the same
 * result is produced on every database engine. The counts are read in one
 * query and then spread across the days of the range, which keeps the
 * result stable whether the database is SQLite or MySQL.
 */
class SubmissionChart
{
    /**
     * The number of days that can be selected.
     *
     * @var list<int>
     */
    public const RANGES = [7, 30, 90];

    /**
     * The number of days shown when no range is selected.
     */
    public const DEFAULT_RANGE = 30;

    /**
     * The height of the chart in pixels.
     */
    public const HEIGHT = 180;

    /**
     * The width of the chart in pixels.
     */
    public const WIDTH = 720;

    /**
     * The space kept free on each side of the plot for the date labels.
     *
     * The first and the last label are centred on the first and the last
     * bar, so half of each of them reaches past the edge of the plot. The
     * viewBox is widened by this much on both sides so that neither of
     * them is cut off.
     */
    public const LABEL_PADDING = 24;

    /**
     * Build the chart for the account.
     *
     * The returned series always has one entry per day in the range, in
     * chronological order. Days without a submission are included with a
     * count of zero so that the chart has an even spacing.
     *
     * @return array{labels: list<string>, counts: list<int>, total: int, range: int}
     */
    public function forUser(User $user, ?int $range = null): array
    {
        $range = $this->normaliseRange($range);

        $start = CarbonImmutable::today()->subDays($range - 1);

        $counts = Testimonial::query()
            ->whereIn('space_id', $user->spaces()->select('spaces.id'))
            ->where('created_at', '>=', $start->startOfDay())
            ->get(['created_at'])
            ->groupBy(fn (Testimonial $testimonial): string => $testimonial->created_at?->toDateString())
            ->map(fn ($group): int => $group->count());

        $labels = [];
        $series = [];

        for ($day = 0; $day < $range; $day++) {
            $date = $start->addDays($day);

            $labels[] = $date->format('j M');
            $series[] = (int) ($counts[$date->toDateString()] ?? 0);
        }

        return [
            'labels' => $labels,
            'counts' => $series,
            'total' => array_sum($series),
            'range' => $range,
        ];
    }

    /**
     * The range to show, falling back to the default.
     */
    public function normaliseRange(?int $range): int
    {
        if ($range === null || ! in_array($range, self::RANGES, true)) {
            return self::DEFAULT_RANGE;
        }

        return $range;
    }

    /**
     * The height of the tallest bar, in pixels.
     *
     * A chart with no submissions still has a usable scale, so the height
     * never collapses to zero.
     */
    public function barHeight(int $count, int $max): int
    {
        if ($max <= 0) {
            return 0;
        }

        return (int) round(($count / $max) * self::HEIGHT);
    }
}
