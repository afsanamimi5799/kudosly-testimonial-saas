@extends('layouts.app')

@section('content')
    <div class="mx-auto max-w-2xl space-y-8">
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-6 dark:border-emerald-900 dark:bg-emerald-950">
            <h1 class="text-lg font-semibold tracking-tight text-emerald-900 dark:text-emerald-100">
                Thank you for your payment
            </h1>
            <p class="mt-1 text-sm text-emerald-800 dark:text-emerald-300">
                Stripe has confirmed your payment. Your Pro plan is activated as soon as we receive the confirmation, which usually takes a few seconds.
            </p>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-base font-semibold tracking-tight text-slate-900 dark:text-slate-100">
                What happens next
            </h2>

            <ul class="mt-4 space-y-2 text-sm text-slate-600 dark:text-slate-400">
                <li class="flex gap-x-3">
                    <span aria-hidden="true">&middot;</span>
                    <span>Your plan changes to Pro and the higher limits apply straight away.</span>
                </li>
                <li class="flex gap-x-3">
                    <span aria-hidden="true">&middot;</span>
                    <span>Your existing spaces, widgets and testimonials stay exactly as they are.</span>
                </li>
                <li class="flex gap-x-3">
                    <span aria-hidden="true">&middot;</span>
                    <span>You can manage or cancel the subscription at any time from the billing page.</span>
                </li>
            </ul>

            <div class="mt-6 flex flex-wrap items-center gap-3">
                <a href="{{ route('billing.index') }}"
                    class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-500">
                    Go to billing
                </a>
                <a href="{{ route('dashboard') }}"
                    class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
                    Go to dashboard
                </a>
            </div>
        </div>
    </div>
@endsection
