@extends('layouts.app')

@section('content')
    <div class="mx-auto max-w-2xl space-y-8">
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-6 dark:border-amber-900 dark:bg-amber-950">
            <h1 class="text-lg font-semibold tracking-tight text-amber-900 dark:text-amber-100">
                Your checkout was cancelled
            </h1>
            <p class="mt-1 text-sm text-amber-800 dark:text-amber-300">
                Nothing has been charged and your plan has not changed. You can start the upgrade again whenever you are ready.
            </p>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-base font-semibold tracking-tight text-slate-900 dark:text-slate-100">
                Your current plan
            </h2>

            <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
                You are on the {{ $user->currentPlan()->name }} plan. Nothing about your spaces, widgets or collected testimonials has changed.
            </p>

            <div class="mt-6 flex flex-wrap items-center gap-3">
                <a href="{{ route('billing.index') }}"
                    class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-500">
                    Back to billing
                </a>
                <a href="{{ route('dashboard') }}"
                    class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
                    Go to dashboard
                </a>
            </div>
        </div>
    </div>
@endsection
