@extends('layouts.app')

@section('content')
    <div class="mx-auto max-w-3xl space-y-8">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Edit space</h1>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                Update how this space introduces itself and collects testimonials.
            </p>
        </div>

        @include('spaces.partials.form', ['space' => $space, 'themes' => $themes])

        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ route('spaces.fields', ['space' => $space]) }}"
                class="text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">
                Configure collection fields
            </a>
            <span class="text-slate-300 dark:text-slate-700">·</span>
            <a href="{{ route('spaces.edit', ['space' => $space]) }}"
                class="text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">
                Edit details
            </a>
        </div>
    </div>
@endsection
