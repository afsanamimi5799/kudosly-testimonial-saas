<?php

namespace App\Inbox;

use App\Enums\TestimonialStatus;
use App\Models\Space;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The filters that can be applied to the testimonial inbox.
 *
 * The filters are read from the query string once, validated, and then
 * applied by the testimonial query. Keeping the parsing in one place means
 * the filter bar and the query always agree on what a filter means.
 */
class TestimonialFilters
{
    /**
     * The number of testimonials shown on one page of the inbox.
     */
    public const PER_PAGE = 12;

    /**
     * The statuses that can be filtered on.
     *
     * @var list<string>
     */
    public const STATUSES = ['pending', 'approved', 'hidden'];

    /**
     * The ratings that can be filtered on.
     *
     * @var list<int>
     */
    public const RATINGS = [1, 2, 3, 4, 5];

    /**
     * The number of days of the date range filter.
     *
     * @var list<int>
     */
    public const RANGES = [7, 30, 90];

    /**
     * The space the inbox is filtered to, if any.
     */
    public ?int $spaceId = null;

    /**
     * The moderation status the inbox is filtered to, if any.
     */
    public ?string $status = null;

    /**
     * The rating the inbox is filtered to, if any.
     */
    public ?int $rating = null;

    /**
     * Whether the inbox is filtered to favourites only.
     */
    public bool $favoritesOnly = false;

    /**
     * The number of days the inbox is filtered to, if any.
     */
    public ?int $range = null;

    /**
     * The free text search term, if any.
     */
    public ?string $search = null;

    /**
     * Build the filters from the request.
     *
     * Every value is taken from the query string and checked against the
     * allowed values. An unknown value is ignored rather than rejected, so
     * a hand edited URL can never produce an invalid query.
     */
    public static function fromRequest(Request $request): self
    {
        $filters = new self;

        $spaceId = $request->integer('space_id');

        if ($spaceId > 0) {
            $filters->spaceId = $spaceId;
        }

        $status = $request->string('status')->toString();

        if (in_array($status, self::STATUSES, true)) {
            $filters->status = $status;
        }

        $rating = $request->integer('rating');

        if (in_array($rating, self::RATINGS, true)) {
            $filters->rating = $rating;
        }

        $range = $request->integer('range');

        if (in_array($range, self::RANGES, true)) {
            $filters->range = $range;
        }

        $filters->favoritesOnly = $request->boolean('favorites');

        $search = trim($request->string('search')->toString());

        if ($search !== '') {
            $filters->search = $search;
        }

        return $filters;
    }

    /**
     * Determine whether any filter is active.
     */
    public function hasAny(): bool
    {
        return $this->spaceId !== null
            || $this->status !== null
            || $this->rating !== null
            || $this->favoritesOnly
            || $this->range !== null
            || $this->search !== null;
    }

    /**
     * The number of active filters.
     */
    public function activeCount(): int
    {
        return (int) ($this->spaceId !== null)
            + (int) ($this->status !== null)
            + (int) ($this->rating !== null)
            + (int) $this->favoritesOnly
            + (int) ($this->range !== null)
            + (int) ($this->search !== null);
    }

    /**
     * The moderation status as an enum.
     */
    public function statusEnum(): ?TestimonialStatus
    {
        return $this->status === null
            ? null
            : TestimonialStatus::from($this->status);
    }

    /**
     * The start of the date range, if a range is selected.
     */
    public function rangeStart(): ?Carbon
    {
        if ($this->range === null) {
            return null;
        }

        return now()->subDays($this->range - 1)->startOfDay();
    }

    /**
     * The query string of the current filters, without the page number.
     *
     * The filter links and the pagination links are built from this, so a
     * filter is never dropped when the page changes.
     *
     * @return array<string, string>
     */
    public function toQuery(): array
    {
        $query = [];

        if ($this->spaceId !== null) {
            $query['space_id'] = (string) $this->spaceId;
        }

        if ($this->status !== null) {
            $query['status'] = $this->status;
        }

        if ($this->rating !== null) {
            $query['rating'] = (string) $this->rating;
        }

        if ($this->favoritesOnly) {
            $query['favorites'] = '1';
        }

        if ($this->range !== null) {
            $query['range'] = (string) $this->range;
        }

        if ($this->search !== null) {
            $query['search'] = $this->search;
        }

        return $query;
    }

    /**
     * The spaces of the account, for the filter dropdown.
     *
     * @return Collection<int, Space>
     */
    public function spacesFor(User $user): Collection
    {
        return $user->spaces()->orderBy('name')->get();
    }
}
