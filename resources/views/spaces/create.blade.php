@extends('layouts.app')

@section('content')
    <div class="mx-auto max-w-3xl space-y-8">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Create a space</h1>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                A space is a page where you collect testimonials. You can change any of this later.
            </p>
        </div>

        @include('spaces.partials.form', ['space' => null, 'themes' => $themes])

        <div class="flex items-center gap-3">
            <a href="{{ route('spaces.index') }}"
                class="text-sm font-medium text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100">
                Cancel
            </a>
        </div>
    </div>
@endsection
