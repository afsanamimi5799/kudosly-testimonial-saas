@extends('layouts.app')

@section('content')
    <div class="mx-auto max-w-3xl space-y-8">
        <div>
            <h2 class="text-2xl font-semibold tracking-tight">Edit testimonial</h2>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                Correct the details of a testimonial sent to
                {{ $testimonial->space?->name ?? 'your space' }}.
            </p>
        </div>

        @if ($errors->any())
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
                Please correct the highlighted fields.
            </div>
        @endif

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-7 dark:border-slate-800 dark:bg-slate-900">
            @include('inbox.partials.form', ['testimonial' => $testimonial])
        </div>
    </div>
@endsection
