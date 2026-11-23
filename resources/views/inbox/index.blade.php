<div class="space-y-8">
    <div>
        <h2 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-100">Inbox</h2>
        <p class="mt-1.5 text-sm text-slate-600 dark:text-slate-400">
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

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
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
            >
                <x-slot:icon>
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M8 10.5h8M8 14h5" />
                        <path d="M20.5 11.5a7.5 7.5 0 0 1-7.5 7.5c-1.2 0-2.35-.28-3.38-.78L4 20l1.78-5.62A7.5 7.5 0 0 1 13 4a7.5 7.5 0 0 1 7.5 7.5Z" />
                    </svg>
                </x-slot:icon>
            </x-app.empty-state>
        @else
            <x-app.empty-state
                title="No testimonials yet"
                description="Once you share a collection link, the testimonials sent to your spaces appear here for review."
            >
                <x-slot:icon>
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M8 10.5h8M8 14h5" />
                        <path d="M20.5 11.5a7.5 7.5 0 0 1-7.5 7.5c-1.2 0-2.35-.28-3.38-.78L4 20l1.78-5.62A7.5 7.5 0 0 1 13 4a7.5 7.5 0 0 1 7.5 7.5Z" />
                    </svg>
                </x-slot:icon>
            </x-app.empty-state>
        @endif
    @else
        {{-- The list is not clipped, so that the action menu of a testimonial
             can open past the edge of the card and past the edge of the list.
             The rounded corners are kept by rounding the first and the last
             card instead of rounding the list itself. --}}
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <ul class="divide-y divide-slate-200 dark:divide-slate-800">
                @foreach ($testimonials as $testimonial)
                    <li class="first:rounded-t-xl last:rounded-b-xl">
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

<script>
    (function () {
        var menus = Array.prototype.slice.call(
            document.querySelectorAll('[data-inbox-actions]')
        );

        if (menus.length === 0) {
            return;
        }

        // A menu opens below its button, but there is not always room for it
        // there. When the button sits close to the bottom of the window, the
        // menu is turned around and opens above the button instead, so that
        // every action stays visible and can be clicked.
        function place(details) {
            var menu = details.querySelector('[data-inbox-actions-menu]');

            if (! menu) {
                return;
            }

            // Measure the menu without its offset so that the space it needs
            // is known before it is placed.
            menu.classList.remove('bottom-full', 'mb-1', 'mt-1');
            menu.style.bottom = '';
            menu.style.marginTop = '';
            menu.style.left = '';
            menu.style.right = '';

            var button = details.getBoundingClientRect();
            var spaceBelow = window.innerHeight - button.bottom;
            var menuHeight = menu.offsetHeight;
            var fitsBelow = spaceBelow >= menuHeight + 8;

            if (! fitsBelow && button.top >= menuHeight + 8) {
                // There is more room above the button than below it, so the
                // menu opens upwards.
                menu.classList.add('bottom-full', 'mb-1');
            } else {
                menu.classList.add('mt-1');
            }

            // On a narrow screen the menu is wider than the space to the left
            // of the button, so it is moved across until it sits inside the
            // window and no longer runs off the edge.
            //
            // The menu is positioned against the button, so the place is
            // worked out relative to the button and not to the window.
            var menuWidth = menu.offsetWidth;
            var offsetLeft = button.left - details.getBoundingClientRect().left;
            var left = offsetLeft + button.width - menuWidth;

            if (left < 8) {
                left = 8;
            }

            var maxLeft = window.innerWidth - menuWidth - 8;

            if (left > maxLeft) {
                left = maxLeft;
            }

            if (left < 0) {
                left = 0;
            }

            menu.style.left = Math.round(left) + 'px';
            menu.style.right = 'auto';
        }

        menus.forEach(function (details) {
            details.addEventListener('toggle', function () {
                if (details.open) {
                    place(details);
                }
            });
        });

        window.addEventListener('resize', function () {
            menus.forEach(function (details) {
                if (details.open) {
                    place(details);
                }
            });
        });
    })();
</script>
