@extends('layouts.app')

@section('content')
    <div class="mx-auto max-w-2xl space-y-8">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-100">Profile</h1>
            <p class="mt-1.5 text-sm text-slate-600 dark:text-slate-400">Update your account details and password.</p>
        </div>

        @if (session('status') === 'profile-updated')
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">
                Your profile has been updated.
            </div>
        @endif

        @if (session('status') === 'password-updated')
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">
                Your password has been updated.
            </div>
        @endif

        @if (session('status') === 'profile-photo-updated')
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">
                Your profile photo has been updated.
            </div>
        @endif

        @if (session('status') === 'profile-photo-removed')
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">
                Your profile photo has been removed.
            </div>
        @endif

        @include('profile.partials.profile-photo-form')

        @include('profile.partials.update-profile-information-form')

        @include('profile.partials.update-password-form')
    </div>
@endsection
