<div class="space-y-8">
    <div>
        <h2 class="text-2xl font-semibold tracking-tight">Wall of Love</h2>
        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
            The testimonials you have marked with a heart. Each space has its own public wall.
        </p>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <x-app.stat-card label="On The Wall" :value="$wallOfLoveCount" />
        <x-app.stat-card label="Visible Publicly" :value="$publicCount" />
    </div>

    @if ($testimonials->isEmpty())
        <x-app.empty-state
            title="Nothing on the wall yet"
            description="Mark a testimonial with a heart from your inbox and it appears here."
        />
    @else
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($testimonials as $testimonial)
                <figure class="flex h-full flex-col rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    @if (! $testimonial->isPubliclyVisible())
                        <p class="mb-3 rounded-md bg-amber-50 px-2 py-1 text-xs font-medium text-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                            Not public yet &mdash; this testimonial still needs approval and sharing consent.
                        </p>
                    @endif

                    <blockquote class="flex-1 text-sm text-slate-700 dark:text-slate-300">
                        {{ Str::limit($testimonial->testimonial_text, 220) }}
                    </blockquote>

                    <figcaption class="mt-4 border-t border-slate-200 pt-4 dark:border-slate-800">
                        <p class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ $testimonial->customer_name }}</p>
                        @if ($testimonial->company_name)
                            <p class="text-sm text-slate-600 dark:text-slate-400">{{ $testimonial->company_name }}</p>
                        @endif
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            {{ $testimonial->space?->name ?? 'Unknown space' }}
                        </p>

                        @if ($testimonial->space)
                            <a href="{{ route('wall.public.show', ['space' => $testimonial->space->slug]) }}"
                                class="mt-3 inline-flex items-center rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                                View public wall
                            </a>
                        @endif
                    </figcaption>
                </figure>
            @endforeach
        </div>
    @endif
</div>
