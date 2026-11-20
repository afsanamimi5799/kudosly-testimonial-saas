<div class="space-y-8">
    {{-- Page header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <h2 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-100">Dashboard</h2>
            <p class="mt-1.5 text-sm text-slate-600 dark:text-slate-400">
                Welcome back, <span class="font-medium text-slate-900 dark:text-slate-100">{{ $user->name }}</span>.
            </p>
        </div>

        <div class="flex shrink-0 flex-col items-start gap-1.5 sm:items-end">
            <a href="{{ route('spaces.create') }}"
                class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm shadow-indigo-600/20 transition hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 sm:w-auto">
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path d="M10 3.75a.75.75 0 0 1 .75.75v4.75h4.75a.75.75 0 0 1 0 1.5h-4.75v4.75a.75.75 0 0 1-1.5 0v-4.75H4.75a.75.75 0 0 1 0-1.5h4.75V4.5a.75.75 0 0 1 .75-.75Z" />
                </svg>
                Create New Space
            </a>
        </div>
    </div>

    {{-- Overview cards --}}
    <div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3">
        {{-- Total testimonials --}}
        <div class="group relative flex flex-col justify-between overflow-hidden rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md dark:border-slate-800 dark:bg-slate-900 dark:hover:border-slate-700">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-sm font-medium tracking-tight text-slate-600 dark:text-slate-400">Total Testimonials</p>
                    <p class="mt-2.5 text-3xl font-semibold tracking-tight tabular-nums text-slate-900 dark:text-slate-100">{{ $totalTestimonials }}</p>
                </div>

                <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600 transition duration-200 group-hover:bg-indigo-100 dark:bg-indigo-950/60 dark:text-indigo-300 dark:group-hover:bg-indigo-950">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M8 10.5h8M8 14h5" />
                        <path d="M20.5 11.5a7.5 7.5 0 0 1-7.5 7.5c-1.2 0-2.35-.28-3.38-.78L4 20l1.78-5.62A7.5 7.5 0 0 1 13 4a7.5 7.5 0 0 1 7.5 7.5Z" />
                    </svg>
                </span>
            </div>

            <p class="mt-5 border-t border-slate-100 pt-3 text-xs font-medium text-slate-500 dark:border-slate-800/80 dark:text-slate-400">
                {{ $testimonialsThisMonth }} {{ Str::plural('testimonial', $testimonialsThisMonth) }} this month
            </p>
        </div>

        {{-- Total spaces --}}
        <div class="group relative flex flex-col justify-between overflow-hidden rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md dark:border-slate-800 dark:bg-slate-900 dark:hover:border-slate-700">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-sm font-medium tracking-tight text-slate-600 dark:text-slate-400">Total Spaces</p>
                    <p class="mt-2.5 text-3xl font-semibold tracking-tight tabular-nums text-slate-900 dark:text-slate-100">
                        {{ $spacesCount }}<span class="text-lg font-medium text-slate-400 dark:text-slate-500">/ {{ $maxSpaces }}</span>
                    </p>
                </div>

                <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600 transition duration-200 group-hover:bg-indigo-100 dark:bg-indigo-950/60 dark:text-indigo-300 dark:group-hover:bg-indigo-950">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 3 3 7.5l9 4.5 9-4.5L12 3Z" />
                        <path d="m3 12 9 4.5L21 12" />
                        <path d="m3 16.5 9 4.5 9-4.5" />
                    </svg>
                </span>
            </div>

            <div class="mt-5 border-t border-slate-100 pt-3 dark:border-slate-800/80">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-xs font-medium text-slate-500 dark:text-slate-400">
                        {{ $spacesRemaining }} {{ Str::plural('space', $spacesRemaining) }} remaining
                    </p>

                    <span class="text-xs font-medium tabular-nums text-slate-400 dark:text-slate-500">{{ $spaceUsagePercentage }}%</span>
                </div>

                <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                    <div class="h-full rounded-full bg-indigo-600 transition-all duration-500" style="width: {{ $spaceUsagePercentage }}%"></div>
                </div>
            </div>
        </div>

        {{-- Current plan --}}
        <div class="group relative flex flex-col justify-between overflow-hidden rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md dark:border-slate-800 dark:bg-slate-900 dark:hover:border-slate-700">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-sm font-medium tracking-tight text-slate-600 dark:text-slate-400">Current Plan</p>
                    <p class="mt-2.5 text-3xl font-semibold tracking-tight text-slate-900 dark:text-slate-100">{{ $planName }}</p>
                </div>

                <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600 transition duration-200 group-hover:bg-indigo-100 dark:bg-indigo-950/60 dark:text-indigo-300 dark:group-hover:bg-indigo-950">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M4 18h16" />
                        <path d="m4 18 1.2-8 3.8 3 2.5-5.5L14 14l3.8-4L19 18" />
                    </svg>
                </span>
            </div>

            <div class="mt-5 border-t border-slate-100 pt-3 dark:border-slate-800/80">
                @if ($isFreePlan)
                    <p class="text-xs font-medium text-slate-500 dark:text-slate-400">
                        <a href="{{ route('billing.index') }}" class="hover:text-slate-700 hover:underline dark:hover:text-slate-200">
                            Upgrade to Pro for more spaces and testimonials.
                        </a>
                    </p>
                @else
                    <p class="text-xs font-medium text-slate-500 dark:text-slate-400">
                        You are on the {{ $planName }} plan.
                    </p>
                @endif
            </div>
        </div>
    </div>

    {{-- Supporting statistics --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <x-app.overview.stat-card label="Unique Customers" :value="$uniqueCustomers">
            <x-slot:icon>
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M16 19.5a4 4 0 0 0-8 0" />
                    <circle cx="12" cy="7.5" r="4" />
                </svg>
            </x-slot:icon>
        </x-app.overview.stat-card>

        <x-app.overview.stat-card label="Testimonials This Month" :value="$testimonialsThisMonth">
            <x-slot:icon>
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="3" y="5" width="18" height="16" rx="2.5" />
                    <path d="M3 10h18M8 3v4M16 3v4" />
                </svg>
            </x-slot:icon>
        </x-app.overview.stat-card>

        <x-app.overview.stat-card label="Average Rating" :value="$averageRating" :footnote="$averageRatingFootnote">
            <x-slot:icon>
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="m12 3 2.7 5.5 6 .9-4.35 4.2 1.03 6L12 16.8 6.62 19.6l1.03-6L3.3 9.4l6-.9L12 3Z" />
                </svg>
            </x-slot:icon>
        </x-app.overview.stat-card>
    </div>

    {{-- Daily submission chart --}}
    @include('dashboard.partials.chart')

    {{-- Spaces section --}}
    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-7 dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-lg font-semibold tracking-tight text-slate-900 dark:text-slate-100">Spaces</h3>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                    Each space is a dedicated page where you collect testimonials.
                </p>
            </div>

            <div class="relative w-full sm:max-w-xs">
                <label for="dashboard-space-search" class="sr-only">Search spaces</label>

                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="11" cy="11" r="7" />
                        <path d="m20 20-3.5-3.5" />
                    </svg>
                </span>

                <input id="dashboard-space-search" type="search" name="search" placeholder="Search spaces..."
                    autocomplete="off"
                    class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2.5 pl-9 pr-3 text-sm text-slate-700 placeholder:text-slate-400 focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100 focus:outline-none dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-200 dark:placeholder:text-slate-500">
            </div>
        </div>

        <div class="mt-6">
            @if ($spacesCount === 0)
                {{-- Empty state with a CSS illustration --}}
                <div class="relative flex flex-col items-center overflow-hidden rounded-2xl border border-dashed border-slate-300 bg-slate-50/60 px-6 py-14 text-center dark:border-slate-700 dark:bg-slate-800/30">
                    {{-- Decorative layered cards --}}
                    <div class="pointer-events-none absolute inset-x-0 -top-2 flex justify-center opacity-[0.07] dark:opacity-[0.12]" aria-hidden="true">
                        <div class="h-24 w-40 -rotate-6 rounded-2xl border-2 border-indigo-600"></div>
                        <div class="-ml-20 h-24 w-40 rotate-6 rounded-2xl border-2 border-indigo-600"></div>
                    </div>

                    <span class="relative inline-flex h-16 w-16 items-center justify-center rounded-2xl bg-white text-indigo-500 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-700">
                        <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M12 3 3 7.5l9 4.5 9-4.5L12 3Z" />
                            <path d="m3 12 9 4.5L21 12" />
                            <path d="m3 16.5 9 4.5 9-4.5" />
                        </svg>
                    </span>

                    <h4 class="relative mt-5 text-base font-semibold tracking-tight text-slate-900 dark:text-slate-100">No spaces yet</h4>

                    <p class="relative mx-auto mt-2 max-w-md text-sm leading-relaxed text-slate-600 dark:text-slate-400">
                        Spaces let you collect testimonials on a dedicated page. Create a space to get a link you can share with your customers.
                    </p>

                    <a href="{{ route('spaces.create') }}"
                        class="relative mt-5 inline-flex items-center rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm shadow-indigo-600/20 transition hover:bg-indigo-500">
                        Create your first space
                    </a>
                </div>
            @else
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3" id="dashboard-space-list">
                    @foreach ($spaces as $space)
                        <div class="dashboard-space rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                            data-space-search="{{ Str::lower($space->name.' '.$space->title) }}">
                            <h4 class="truncate text-base font-semibold tracking-tight text-slate-900 dark:text-slate-100">{{ $space->name }}</h4>
                            <p class="mt-1 truncate text-sm text-slate-600 dark:text-slate-400">{{ $space->title }}</p>

                            <dl class="mt-4 space-y-2 text-sm">
                                <div class="flex items-center justify-between gap-4">
                                    <dt class="text-slate-500 dark:text-slate-400">Testimonials</dt>
                                    <dd class="font-medium tabular-nums text-slate-900 dark:text-slate-100">{{ $space->testimonials_count }}</dd>
                                </div>
                                <div class="flex items-center justify-between gap-4">
                                    <dt class="text-slate-500 dark:text-slate-400">Theme</dt>
                                    <dd class="font-medium text-slate-900 dark:text-slate-100">{{ $space->theme }}</dd>
                                </div>
                            </dl>
                        </div>
                    @endforeach
                </div>

                <p id="dashboard-space-empty" class="hidden rounded-xl border border-dashed border-slate-300 bg-slate-50/60 px-6 py-10 text-center text-sm text-slate-600 dark:border-slate-700 dark:bg-slate-800/30 dark:text-slate-400">
                    No spaces match your search.
                </p>
            @endif
        </div>
    </section>

    {{-- Plan and usage detail --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-7 dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h3 class="text-lg font-semibold tracking-tight text-slate-900 dark:text-slate-100">Plan and usage</h3>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                    You are on the {{ $planName }} plan.
                </p>
            </div>

            <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-sm font-medium text-slate-600 dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-300">
                <span class="tabular-nums text-slate-900 dark:text-slate-100">{{ $spacesCount }}</span>
                <span class="text-slate-400">of</span>
                <span class="tabular-nums text-slate-900 dark:text-slate-100">{{ $maxSpaces }}</span>
                <span>spaces used</span>
            </span>
        </div>

        <div class="mt-6 h-2.5 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
            <div class="h-full rounded-full bg-indigo-600 transition-all duration-500" style="width: {{ $spaceUsagePercentage }}%"></div>
        </div>

        @if ($spaceUsagePercentage >= 100)
            <p class="mt-4 flex items-start gap-2 text-sm font-medium text-amber-700 dark:text-amber-400">
                <svg class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495ZM10 5a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 5Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd" />
                </svg>
                You have reached the space limit included in your plan.
            </p>
        @else
            <p class="mt-4 text-sm text-slate-500 dark:text-slate-400">
                {{ $spacesRemaining }} {{ Str::plural('space', $spacesRemaining) }} remaining on your plan.
            </p>
        @endif
    </div>

    @if ($isFreePlan)
        <div class="flex flex-col gap-2 rounded-2xl border border-indigo-200 bg-indigo-50 px-5 py-4 text-sm text-indigo-900 dark:border-indigo-900/60 dark:bg-indigo-950/40 dark:text-indigo-200 sm:flex-row sm:items-center sm:gap-3">
            <span class="font-semibold">Upgrade for more spaces.</span>

            @if ($canUpgrade && $proPriceLabel)
                <a href="{{ route('billing.index') }}" class="font-medium text-indigo-700 underline underline-offset-2 hover:text-indigo-900 dark:text-indigo-300 dark:hover:text-indigo-100">
                    Pro is {{ $proPriceLabel }} per year.
                </a>
            @else
                <span class="text-indigo-700/80 dark:text-indigo-300/80">Pro is {{ $proPriceLabel ?? '$999' }} per year.</span>
            @endif
        </div>
    @endif

    {{-- Recent testimonials --}}
    @include('dashboard.partials.recent-testimonials')

    @if ($totalTestimonials === 0)
        <x-app.overview.empty-state
            title="No testimonials yet"
            description="Once you have a space, you can share a collection link and testimonials will appear here."
        >
            <x-slot:icon>
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M8 10.5h8M8 14h5" />
                    <path d="M20.5 11.5a7.5 7.5 0 0 1-7.5 7.5c-1.2 0-2.35-.28-3.38-.78L4 20l1.78-5.62A7.5 7.5 0 0 1 13 4a7.5 7.5 0 0 1 7.5 7.5Z" />
                </svg>
            </x-slot:icon>
        </x-app.overview.empty-state>
    @endif
</div>

<script>
    (function () {
        var input = document.getElementById('dashboard-space-search');

        if (! input) {
            return;
        }

        var cards = Array.prototype.slice.call(
            document.querySelectorAll('#dashboard-space-list .dashboard-space')
        );
        var empty = document.getElementById('dashboard-space-empty');

        if (cards.length === 0) {
            return;
        }

        input.addEventListener('input', function () {
            var term = input.value.trim().toLowerCase();
            var shown = 0;

            cards.forEach(function (card) {
                var matches = term === ''
                    || (card.getAttribute('data-space-search') || '').indexOf(term) !== -1;

                card.classList.toggle('hidden', ! matches);

                if (matches) {
                    shown++;
                }
            });

            if (empty) {
                empty.classList.toggle('hidden', shown !== 0);
            }
        });
    })();
</script>
