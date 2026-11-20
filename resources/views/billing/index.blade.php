<div class="space-y-8">
    <div>
        <h2 class="text-2xl font-semibold tracking-tight">Billing</h2>
        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
            Your plan and what it includes.
        </p>
    </div>

    @if (session('error'))
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
            {{ session('error') }}
        </div>
    @endif

    @if (session('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200">
            {{ session('success') }}
        </div>
    @endif

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

    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <h3 class="text-lg font-semibold tracking-tight">Subscription</h3>

        <p class="mt-3 flex items-center gap-2 text-sm">
            <span class="font-medium text-slate-900 dark:text-slate-100">{{ $statusLabel }}</span>

            @if ($status->isPaying())
                <span class="rounded-md bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">
                    Paying
                </span>
            @elseif ($status->isEnded())
                <span class="rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                    Ended
                </span>
            @endif
        </p>

        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
            {{ $statusDescription }}
        </p>

        @if ($endsAtPeriodEnd && $subscriptionEndsAt)
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                Your subscription is set to end on {{ $subscriptionEndsAt->format('j F Y') }}. You keep everything your plan includes until then.
            </p>
        @endif

        @if ($canManageSubscription)
            <form method="POST" action="{{ route('billing.portal') }}" class="mt-4">
                @csrf
                <button type="submit" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">
                    Manage subscription
                </button>
            </form>
        @elseif ($hasSubscription)
            <p class="mt-4 text-sm text-slate-500 dark:text-slate-400">
                The subscription can be managed once Stripe is connected to this account.
            </p>
        @endif
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
                    @if ($plan->priceInDollars() > 0)
                        {{ $plan->yearlyPriceLabel() }}/year
                    @else
                        Free
                    @endif
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

                @if ($plan->isPro())
                    @if ($canUpgrade)
                        <form method="POST" action="{{ route('billing.checkout') }}" class="mt-5">
                            @csrf
                            <button type="submit" class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">
                                Upgrade to Pro
                            </button>
                        </form>
                    @elseif (! $stripeConfigured)
                        <p class="mt-5 text-sm text-slate-500 dark:text-slate-400">
                            Payments are not switched on yet, so the upgrade cannot be started.
                        </p>
                    @endif
                @endif
            </div>
        @endforeach
    </div>
</div>
