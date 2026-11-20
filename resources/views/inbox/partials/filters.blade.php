{{-- The inbox filter bar. Every filter is a GET field so that the filters
     survive a page change and can be shared as a link. --}}
<form method="GET" action="{{ route('inbox.index') }}"
    class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <div class="sm:col-span-2 lg:col-span-2">
            <label for="inbox-search" class="sr-only">Search testimonials</label>

            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400 sm:hidden">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="11" cy="11" r="7" />
                    <path d="m20 20-3.5-3.5" />
                </svg>
            </span>

            <input id="inbox-search" type="search" name="search" value="{{ $filters->search }}"
                placeholder="Search name, company or text..."
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-500">
        </div>

        <div>
            <label for="inbox-space" class="sr-only">Filter by space</label>
            <select id="inbox-space" name="space_id"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                <option value="">All spaces</option>
                @foreach ($spaces as $space)
                    <option value="{{ $space->id }}" @selected($filters->spaceId === $space->id)>{{ $space->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="inbox-status" class="sr-only">Filter by status</label>
            <select id="inbox-status" name="status"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                <option value="">All statuses</option>
                @foreach (\App\Enums\TestimonialStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected($filters->status === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="inbox-rating" class="sr-only">Filter by rating</label>
            <select id="inbox-rating" name="rating"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                <option value="">All ratings</option>
                @foreach (\App\Inbox\TestimonialFilters::RATINGS as $rating)
                    <option value="{{ $rating }}" @selected($filters->rating === $rating)>{{ $rating }} star{{ $rating === 1 ? '' : 's' }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="mt-3 flex flex-wrap items-center gap-2">
        @foreach (\App\Inbox\TestimonialFilters::RANGES as $range)
            <a href="{{ route('inbox.index', array_merge($filters->toQuery(), ['range' => $range, 'page' => null])) }}"
                class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-medium transition {{ $filters->range === $range ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-slate-200 text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800' }}">
                Last {{ $range }} days
            </a>
        @endforeach

        <a href="{{ route('inbox.index', array_merge($filters->toQuery(), ['favorites' => $filters->favoritesOnly ? null : 1, 'page' => null])) }}"
            class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-medium transition {{ $filters->favoritesOnly ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-slate-200 text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800' }}">
            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="m12 20.5S4 15.8 4 10.2A4.2 4.2 0 0 1 12 8a4.2 4.2 0 0 1 8 2.2c0 5.6-8 10.3-8 10.3Z" />
            </svg>
            Favourites
        </a>

        <button type="submit"
            class="inline-flex items-center rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-indigo-500">
            Apply
        </button>

        @if ($filters->hasAny())
            <a href="{{ route('inbox.index') }}"
                class="inline-flex items-center rounded-lg px-3 py-1.5 text-xs font-medium text-slate-600 transition hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100">
                Clear filters
            </a>

            <span class="text-xs text-slate-500 dark:text-slate-400">
                {{ $filters->activeCount() }} filter{{ $filters->activeCount() === 1 ? '' : 's' }} applied
            </span>
        @endif
    </div>
</form>
