@props([
    'current' => 'dashboard',
])

<nav class="flex h-full flex-col gap-6 p-4" aria-label="Main">
    <div class="flex items-center gap-2.5 px-2">
        <a href="{{ route('dashboard') }}" class="flex min-w-0 items-center gap-2.5">
            <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-indigo-600 text-base font-bold text-white shadow-sm shadow-indigo-600/20">
                K
            </span>
            <span class="truncate text-base font-semibold tracking-tight text-slate-900 dark:text-slate-100">Kudosly</span>
        </a>
    </div>

    <div class="flex-1 space-y-1">
        <x-app.nav-link label="Overview" :href="route('dashboard')" :active="$current === 'dashboard'">
            <x-slot:icon>
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M3 12a9 9 0 1 0 18 0 9 9 0 0 0-18 0Z" />
                    <path d="m15.5 8.5-2.2 4.6-4.6 2.2 2.2-4.6 4.6-2.2Z" />
                </svg>
            </x-slot:icon>
        </x-app.nav-link>

        <x-app.nav-link label="Spaces" :href="route('spaces.index')" :active="$current === 'spaces'">
            <x-slot:icon>
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M12 3 3 7.5l9 4.5 9-4.5L12 3Z" />
                    <path d="m3 12 9 4.5L21 12" />
                    <path d="m3 16.5 9 4.5 9-4.5" />
                </svg>
            </x-slot:icon>
        </x-app.nav-link>

        <x-app.nav-link label="Inbox" :href="route('inbox.index')" :active="$current === 'inbox'">
            <x-slot:icon>
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M3 13h4l2 3h6l2-3h4" />
                    <path d="M5.5 5h13l2.5 8v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-6l2.5-8Z" />
                </svg>
            </x-slot:icon>
        </x-app.nav-link>

        <x-app.nav-link label="Wall of Love" :href="route('wall-of-love.index')" :active="$current === 'wall-of-love'">
            <x-slot:icon>
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M12 20.5S4 15.8 4 10.2A4.2 4.2 0 0 1 12 8a4.2 4.2 0 0 1 8 2.2c0 5.6-8 10.3-8 10.3Z" />
                </svg>
            </x-slot:icon>
        </x-app.nav-link>

        <x-app.nav-link label="Widgets" :href="route('widgets.index')" :active="$current === 'widgets'">
            <x-slot:icon>
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="3" y="3" width="7" height="7" rx="1.5" />
                    <rect x="14" y="3" width="7" height="7" rx="1.5" />
                    <rect x="3" y="14" width="7" height="7" rx="1.5" />
                    <rect x="14" y="14" width="7" height="7" rx="1.5" />
                </svg>
            </x-slot:icon>
        </x-app.nav-link>

        <x-app.nav-link label="Billing" :href="route('billing.index')" :active="$current === 'billing'">
            <x-slot:icon>
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="2.5" y="5" width="19" height="14" rx="2.5" />
                    <path d="M2.5 10h19" />
                    <path d="M6.5 15h3" />
                </svg>
            </x-slot:icon>
        </x-app.nav-link>

        <x-app.nav-link label="Settings" :href="route('profile.edit')" :active="$current === 'profile'">
            <x-slot:icon>
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="3" />
                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6 1.65 1.65 0 0 0 10 3.09V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1Z" />
                </svg>
            </x-slot:icon>
        </x-app.nav-link>
    </div>

    <div class="border-t border-slate-200 pt-4 dark:border-slate-800">
        <div class="flex items-center gap-3 rounded-xl px-2 py-1.5">
            <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-sm font-semibold text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300">
                {{ Str::upper(Str::substr(auth()->user()?->name ?? 'U', 0, 1)) }}
            </span>

            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-medium text-slate-900 dark:text-slate-100">
                    {{ auth()->user()?->name }}
                </p>
                <p class="truncate text-xs text-slate-500 dark:text-slate-400">Signed in</p>
            </div>
        </div>
    </div>
</nav>
