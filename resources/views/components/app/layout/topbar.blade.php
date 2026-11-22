@props([
    'title' => 'Dashboard',
])

<header class="sticky top-0 z-20 border-b border-slate-200 bg-white/90 backdrop-blur dark:border-slate-800 dark:bg-slate-900/90">
    <div class="flex items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8">
        <div class="flex min-w-0 items-center gap-3">
            <label for="app-sidebar-toggle"
                class="inline-flex h-9 w-9 shrink-0 cursor-pointer items-center justify-center rounded-lg border border-slate-200 text-slate-600 transition hover:bg-slate-100 lg:hidden dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                <span class="sr-only">Toggle navigation</span>
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 5.5h14M3 10h14M3 14.5h14" />
                </svg>
            </label>

            <h1 class="truncate text-base font-semibold tracking-tight text-slate-900 dark:text-slate-100">{{ $title }}</h1>
        </div>

        <div class="flex shrink-0 items-center gap-3">
            <span class="hidden text-sm text-slate-600 sm:inline dark:text-slate-400">{{ auth()->user()?->name }}</span>
        </div>
    </div>
</header>
