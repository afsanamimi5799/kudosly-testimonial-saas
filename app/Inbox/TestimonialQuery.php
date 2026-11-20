<?php

namespace App\Inbox;

use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Builds the queries behind the testimonial inbox.
 *
 * Every query is scoped to the spaces owned by the account. The scope is
 * applied to the counts as well as to the listing, so a filter can never
 * report a number taken from another account's testimonials.
 */
class TestimonialQuery
{
    /**
     * The base query for the account's testimonials.
     *
     * The scope is expressed as a subquery on the owned spaces so that a
     * single query is enough to both filter and order.
     */
    public function forUser(User $user): Builder
    {
        return Testimonial::query()
            ->whereIn('space_id', $user->spaces()->select('spaces.id'));
    }

    /**
     * Apply the filters to a query.
     */
    public function apply(Builder $query, TestimonialFilters $filters): Builder
    {
        if ($filters->spaceId !== null) {
            $query->where('space_id', $filters->spaceId);
        }

        if ($filters->status !== null) {
            $query->where('status', $filters->status);
        }

        if ($filters->rating !== null) {
            $query->where('rating', $filters->rating);
        }

        if ($filters->favoritesOnly) {
            $query->favorited();
        }

        $rangeStart = $filters->rangeStart();

        if ($rangeStart !== null) {
            $query->where('created_at', '>=', $rangeStart);
        }

        $search = $filters->search;

        if ($search !== null) {
            $query->where(function (Builder $query) use ($search): void {
                $query->where('customer_name', 'like', '%'.$search.'%')
                    ->orWhere('testimonial_text', 'like', '%'.$search.'%')
                    ->orWhere('company_name', 'like', '%'.$search.'%');
            });
        }

        return $query;
    }

    /**
     * The testimonials of the account that match the filters.
     */
    public function results(User $user, TestimonialFilters $filters): Builder
    {
        return $this->apply($this->forUser($user), $filters)
            ->with('space:id,name,slug')
            ->orderedForInbox();
    }

    /**
     * The number of testimonials per moderation status.
     *
     * The counts ignore the active filters so that the badges always show
     * the state of the whole inbox.
     *
     * @return array<string, int>
     */
    public function statusCounts(User $user): array
    {
        $counts = $this->forUser($user)
            ->select('status', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $counts = $counts->map(fn ($count): int => (int) $count);

        return [
            'pending' => $counts->get('pending', 0),
            'approved' => $counts->get('approved', 0),
            'hidden' => $counts->get('hidden', 0),
        ];
    }

    /**
     * The total number of testimonials across every moderation status.
     */
    public function totalCount(User $user): int
    {
        return $this->forUser($user)->count();
    }

    /**
     * The number of favourites of the account.
     */
    public function favoriteCount(User $user): int
    {
        return $this->forUser($user)->favorited()->count();
    }

    /**
     * The number of testimonials marked for the wall of love.
     */
    public function wallOfLoveCount(User $user): int
    {
        return $this->forUser($user)->wallOfLove()->count();
    }
}
