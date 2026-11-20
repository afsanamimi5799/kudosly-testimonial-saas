{{-- The most recent testimonials across the account's spaces.
     The customer email address is never rendered here. --}}
<section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-7 dark:border-slate-800 dark:bg-slate-900">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h3 class="text-lg font-semibold tracking-tight text-slate-900 dark:text-slate-100">Recent testimonials</h3>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                The latest testimonials sent to your spaces.
            </p>
        </div>

        <a href="{{ route('inbox.index') }}"
            class="inline-flex items-center rounded-lg border border-slate-200 px-3 py-1.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
            Open inbox
        </a>
    </div>

    @if ($recentTestimonials->isEmpty())
        <div class="mt-6 rounded-xl border border-dashed border-slate-300 px-6 py-10 text-center dark:border-slate-700">
            <p class="text-sm font-medium text-slate-900 dark:text-slate-100">No testimonials yet</p>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                Share a collection link to start collecting kind words.
            </p>
        </div>
    @else
        <ul class="mt-6 divide-y divide-slate-200 dark:divide-slate-800">
            @foreach ($recentTestimonials as $testimonial)
                <li class="flex flex-wrap items-start justify-between gap-3 py-4 first:pt-0 last:pb-0">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-slate-900 dark:text-slate-100">
                            {{ $testimonial->customer_name }}
                            @if ($testimonial->company_name)
                                <span class="font-normal text-slate-500 dark:text-slate-400">&middot; {{ $testimonial->company_name }}</span>
                            @endif
                        </p>

                        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                            {{ Str::limit($testimonial->testimonial_text, 140) }}
                        </p>

                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            {{ $testimonial->space?->name ?? 'Unknown space' }}
                            &middot;
                            {{ $testimonial->created_at?->diffForHumans() }}
                        </p>
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        @if ($testimonial->rating !== null)
                            <span class="inline-flex items-center gap-1 rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                <svg class="h-3 w-3 text-amber-500" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                    <path d="m12 3 2.7 5.5 6 .9-4.35 4.2 1.03 6L12 16.8 6.62 19.6l1.03-6L3.3 9.4l6-.9L12 3Z" />
                                </svg>
                                {{ $testimonial->rating }}
                            </span>
                        @endif

                        <span class="rounded-md px-2 py-0.5 text-xs font-medium {{ $testimonial->status->badgeClasses() }}">
                            {{ $testimonial->status->label() }}
                        </span>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</section>
