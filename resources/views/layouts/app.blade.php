<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Kudosly</title>

        @fonts

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="min-h-screen bg-slate-50 text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
        <div class="min-h-screen lg:flex">
            <input type="checkbox" id="app-sidebar-toggle" class="peer sr-only" aria-hidden="true">

            <label for="app-sidebar-toggle" aria-hidden="true"
                class="fixed inset-0 z-30 hidden cursor-pointer bg-slate-900/50 peer-checked:block lg:hidden"></label>

            <aside class="fixed inset-y-0 left-0 z-40 w-64 -translate-x-full border-r border-slate-200 bg-white transition-transform peer-checked:translate-x-0 lg:sticky lg:top-0 lg:h-screen lg:translate-x-0 lg:overflow-y-auto dark:border-slate-800 dark:bg-slate-900">
                <x-app.layout.sidebar
    :current="$current ?? 'dashboard'"
    :account-name="$accountName ?? null"
    :account-initial="$accountInitial ?? 'U'"
    :account-photo-url="$accountPhotoUrl ?? null"
/>
            </aside>

            <div class="flex min-w-0 flex-1 flex-col">
                <x-app.layout.topbar :title="$title ?? 'Dashboard'" />

                <main class="mx-auto w-full max-w-7xl flex-1 px-4 py-8 sm:px-6 lg:px-8">
                    @yield('content')
                </main>
            </div>
        </div>
    </body>
</html>
