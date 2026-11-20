<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'Kudosly') }}</title>

        @fonts

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="min-h-screen bg-slate-50 text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
        <div class="flex min-h-screen flex-col items-center justify-center px-4 py-10 sm:px-6">
            <div class="w-full max-w-md">
                <div class="mb-8 text-center">
                    <a href="{{ route('login') }}" class="inline-flex items-center gap-2 text-xl font-semibold tracking-tight">
                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-600 text-base font-bold text-white">K</span>
                        <span>{{ config('app.name', 'Kudosly') }}</span>
                    </a>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8 dark:border-slate-800 dark:bg-slate-900">
                    @yield('content')
                </div>
            </div>
        </div>
    </body>
</html>
