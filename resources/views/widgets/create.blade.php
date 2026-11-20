@extends('layouts.app')

@section('content')
    <div class="mx-auto max-w-3xl space-y-8">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Create widget</h1>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                Choose the space to display and how the widget should look. You get the embed code once it is saved.
            </p>
        </div>

        @include('widgets.partials.form', ['widget' => null])
    </div>
@endsection
