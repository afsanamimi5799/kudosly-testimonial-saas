<div class="space-y-8">
    <div>
        <h2 class="text-2xl font-semibold tracking-tight">Inbox</h2>
        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
            Review, approve and organise the testimonials sent to your spaces.
        </p>
    </div>

    @foreach ([
        'testimonial-approved' => 'The testimonial has been approved.',
        'testimonial-hidden' => 'The testimonial has been hidden and is no longer shown publicly.',
        'testimonial-favorited' => 'The testimonial has been pinned to the top of your inbox.',
        'testimonial-unfavorited' => 'The testimonial is no longer pinned.',
        'testimonial-added-to-wall' => 'The testimonial has been added to your wall of love.',
        'testimonial-removed-from-wall' => 'The testimonial has been removed from your wall of love.',
        'testimonial-updated' => 'The testimonial has been updated.',
        'testimonial-deleted' => 'The testimonial has been deleted and removed from your inbox and every public page.',
    ] as $key => $message)
        @if (session('status') === $key)
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">
                {{ $message }}
            </div>
        @endif
    @endforeach

    @if (session('error'))
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">
            {{ session('error') }}
        </div>
    @endif

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <x-app.stat-card label="Total Testimonials" :value="$totalCount" />
        <x-app.stat-card label="Pending" :value="$pendingCount" />
        <x-app.stat-card label="Approved" :value="$approvedCount" />
    </div>

    @include('inbox.partials.filters')

    @if ($testimonials->isEmpty())
        @if ($filters->hasAny())
            <x-app.empty-state
                title="No testimonials match these filters"
                description="Try clearing the filters to see every testimonial sent to your spaces."
            />
        @else
            <x-app.empty-state
                title="No testimonials yet"
                description="Once you share a collection link, the testimonials sent to your spaces appear here for review."
            />
        @endif
    @else
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <ul class="divide-y divide-slate-200 dark:divide-slate-800">
                @foreach ($testimonials as $testimonial)
                    <li>
                        @include('inbox.partials.item', ['testimonial' => $testimonial])
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-slate-500 dark:text-slate-400">
                Showing {{ $testimonials->firstItem() }} to {{ $testimonials->lastItem() }}
                of {{ $testimonials->total() }} testimonial{{ $testimonials->total() === 1 ? '' : 's' }}
            </p>

            @if ($testimonials->hasPages())
                <nav class="flex items-center gap-1" aria-label="Inbox pagination">
                    @if ($testimonials->onFirstPage())
                        <span class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm text-slate-300 dark:border-slate-800 dark:text-slate-600">
                            Previous
                        </span>
                    @else
                        <a href="{{ $testimonials->previousPageUrl() }}"
                            class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                            Previous
                        </a>
                    @endif

                    <span class="px-2 text-sm text-slate-500 dark:text-slate-400">
                        Page {{ $testimonials->currentPage() }} of {{ $testimonials->lastPage() }}
                    </span>

                    @if ($testimonials->hasMorePages())
                        <a href="{{ $testimonials->nextPageUrl() }}"
                            class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                            Next
                        </a>
                    @else
                        <span class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm text-slate-300 dark:border-slate-800 dark:text-slate-600">
                            Next
                        </span>
                    @endif
                </nav>
            @endif
        </div>
    @endif
</div>
