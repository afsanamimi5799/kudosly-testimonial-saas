<div class="space-y-8">
    <div>
        <h2 class="text-2xl font-semibold tracking-tight">Billing</h2>
        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
            Your plan and what it includes.
        </p>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h3 class="text-lg font-semibold tracking-tight">Plan and usage</h3>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                    You are on the {{ $currentPlan->name }} plan.
                </p>
            </div>

            <p class="text-sm font-medium text-slate-600 dark:text-slate-400">
                <span class="text-slate-900 dark:text-slate-100">{{ $spacesUsed }}</span>
                of
                <span class="text-slate-900 dark:text-slate-100">{{ $maxSpaces }}</span>
                spaces used
            </p>
        </div>

        <div class="mt-4 h-2 w-full overflow-hidden rounded-full bg-slate-200 dark:bg-slate-800">
            <div class="h-full rounded-full bg-indigo-600" style="width: {{ $spaceUsagePercentage }}%"></div>
        </div>

        @if ($spaceUsagePercentage >= 100)
            <p class="mt-3 text-sm text-amber-700 dark:text-amber-400">
                You have reached the space limit included in your plan.
            </p>
        @else
            <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">
                {{ $spacesRemaining }} {{ Str::plural('space', $spacesRemaining) }} remaining on your plan.
            </p>
        @endif

        <p class="mt-4 text-sm text-slate-500 dark:text-slate-400">
            Your plan allows up to {{ $maxTestimonialsPerSpace }} {{ Str::plural('testimonial', $maxTestimonialsPerSpace) }} per space.
        </p>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        @foreach ($plans as $plan)
            @php $isCurrent = $plan->slug === $currentPlan->slug; @endphp

            <div class="rounded-xl border p-6 shadow-sm {{ $isCurrent ? 'border-indigo-200 bg-indigo-50/50 dark:border-indigo-900 dark:bg-indigo-950/30' : 'border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900' }}">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-lg font-semibold tracking-tight text-slate-900 dark:text-slate-100">{{ $plan->name }}</h3>

                    @if ($isCurrent)
                        <span class="rounded-md bg-indigo-600 px-2 py-0.5 text-xs font-medium text-white">Current plan</span>
                    @endif
                </div>

                <p class="mt-2 text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-100">
                    {{ $plan->priceInDollars() > 0 ? '$'.number_format($plan->priceInDollars(), 0).'/month' : 'Free' }}
                </p>

                <dl class="mt-4 space-y-2 text-sm">
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-slate-500 dark:text-slate-400">Spaces</dt>
                        <dd class="font-medium text-slate-900 dark:text-slate-100">{{ $plan->max_spaces }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-slate-500 dark:text-slate-400">Testimonials per space</dt>
                        <dd class="font-medium text-slate-900 dark:text-slate-100">{{ $plan->max_testimonials_per_space }}</dd>
                    </div>
                </dl>
            </div>
        @endforeach
    </div>

    <p class="text-sm text-slate-500 dark:text-slate-400">
        Changing plan and making payments are not available yet, so there is nothing to buy or upgrade here.
    </p>
</div>
