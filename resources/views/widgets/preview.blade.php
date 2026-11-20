@extends('layouts.app')

@section('content')
    <div class="space-y-8">
        <div>
            <h2 class="text-2xl font-semibold tracking-tight">Widget preview</h2>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                This is exactly what the widget looks like on your website. Only approved testimonials with sharing consent are shown.
            </p>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <dl class="space-y-2 text-sm">
                <div class="flex items-center justify-between gap-4">
                    <dt class="text-slate-500 dark:text-slate-400">Space</dt>
                    <dd class="font-medium text-slate-900 dark:text-slate-100">{{ $widget->space?->name ?? 'Unknown space' }}</dd>
                </div>
                <div class="flex items-center justify-between gap-4">
                    <dt class="text-slate-500 dark:text-slate-400">Layout</dt>
                    <dd class="font-medium text-slate-900 dark:text-slate-100">{{ $widget->layoutEnum()->label() }}</dd>
                </div>
                <div class="flex items-center justify-between gap-4">
                    <dt class="text-slate-500 dark:text-slate-400">Testimonials shown</dt>
                    <dd class="font-medium text-slate-900 dark:text-slate-100">{{ $testimonials->count() }}</dd>
                </div>
            </dl>
        </div>

        <div class="rounded-xl border border-slate-200 bg-slate-50 p-6 shadow-sm dark:border-slate-800 dark:bg-slate-950">
            @include('widgets.partials.styles')

            <div class="kudosly-widget-preview">
                @include('widgets.partials.render', ['widget' => $widget, 'testimonials' => $testimonials])
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ route('widgets.edit', ['widget' => $widget]) }}"
                class="text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">
                Edit the widget
            </a>
            <span class="text-slate-300 dark:text-slate-700">·</span>
            <a href="{{ $widget->embedUrl() }}" target="_blank" rel="noopener"
                class="text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">
                Open the embed
            </a>
        </div>
    </div>
@endsection
