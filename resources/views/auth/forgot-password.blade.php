@extends('layouts.guest')

@section('content')
    <h1 class="text-xl font-semibold tracking-tight">Reset your password</h1>
    <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
        Enter your email address and we'll send you a link to choose a new password.
    </p>

    @if (session('status'))
        <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-5">
        @csrf

        <div>
            <label for="email" class="block text-sm font-medium">Email address</label>
            <input id="email" name="email" type="email" autocomplete="username" required autofocus
                value="{{ old('email') }}"
                @error('email') aria-invalid="true" @enderror
                class="mt-1.5 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 dark:border-slate-700 dark:bg-slate-950">
            @error('email')
                <p class="mt-1.5 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit"
            class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900">
            Email password reset link
        </button>
    </form>

    <p class="mt-6 text-center text-sm text-slate-600 dark:text-slate-400">
        <a href="{{ route('login') }}" class="font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">Back to sign in</a>
    </p>
@endsection
