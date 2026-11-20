@extends('layouts.app')

@section('content')
    <div class="mx-auto max-w-3xl space-y-8">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Edit widget</h1>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                Adjust how this widget looks, then copy the embed code into your website.
            </p>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <dl class="space-y-2 text-sm">
                <div class="flex items-center justify-between gap-4">
                    <dt class="text-slate-500 dark:text-slate-400">Space</dt>
                    <dd class="font-medium text-slate-900 dark:text-slate-100">{{ $widget->space?->name ?? 'Unknown space' }}</dd>
                </div>
                <div class="flex items-center justify-between gap-4">
                    <dt class="text-slate-500 dark:text-slate-400">Widget ID</dt>
                    <dd class="font-mono text-xs font-medium text-slate-900 dark:text-slate-100">{{ $widget->public_id }}</dd>
                </div>
            </dl>
        </div>

        @include('widgets.partials.form', ['widget' => $widget])

        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-base font-semibold tracking-tight text-slate-900 dark:text-slate-100">Embed code</h2>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                Add one of these snippets to any page of your website.
            </p>

            <div class="mt-4">
                @include('widgets.partials.embed-code', ['widget' => $widget])
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ route('widgets.preview', ['widget' => $widget]) }}"
                class="text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">
                Open the live preview
            </a>
            <span class="text-slate-300 dark:text-slate-700">·</span>
            <a href="{{ route('widgets.index') }}"
                class="text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">
                Back to widgets
            </a>
        </div>
    </div>
@endsection
