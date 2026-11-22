@props([
    'current' => 'dashboard',
    'accountName' => null,
    'accountInitial' => 'U',
    'accountPhotoUrl' => null,
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
        <div class="relative" id="sidebar-account-menu">
            <button type="button"
                id="sidebar-account-trigger"
                aria-expanded="false"
                aria-controls="sidebar-account-dropdown"
                class="flex w-full items-center gap-3 rounded-xl px-2 py-1.5 text-left transition hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:hover:bg-slate-800">
                <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-full bg-indigo-100 text-sm font-semibold text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300">
                    @if ($accountPhotoUrl)
                        <img src="{{ $accountPhotoUrl }}" alt="" class="h-full w-full object-cover">
                    @else
                        {{ $accountInitial }}
                    @endif
                </span>

                <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-medium text-slate-900 dark:text-slate-100">
                        {{ $accountName }}
                    </span>
                    <span class="block truncate text-xs text-slate-500 dark:text-slate-400">Signed in</span>
                </span>

                <svg class="h-4 w-4 shrink-0 text-slate-400 transition-transform dark:text-slate-500" id="sidebar-account-chevron"
                    viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                </svg>
            </button>

            <div id="sidebar-account-dropdown"
                class="absolute bottom-full left-0 z-50 mb-2 hidden w-full min-w-[12rem] overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-lg dark:border-slate-700 dark:bg-slate-900"
                aria-labelledby="sidebar-account-trigger">
                <a href="{{ route('profile.edit') }}"
                    id="sidebar-account-profile"
                    class="flex items-center gap-2.5 px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-100 focus:bg-slate-100 focus:outline-none dark:text-slate-200 dark:hover:bg-slate-800 dark:focus:bg-slate-800">
                    <svg class="h-4 w-4 shrink-0 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M16 19.5a4 4 0 0 0-8 0" />
                        <circle cx="12" cy="7.5" r="4" />
                    </svg>
                    My Profile
                </a>

                <form method="POST" action="{{ route('switch-account') }}">
                    @csrf
                    <button type="submit"
                        class="flex w-full items-center gap-2.5 px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-100 focus:bg-slate-100 focus:outline-none dark:text-slate-200 dark:hover:bg-slate-800 dark:focus:bg-slate-800">
                        <svg class="h-4 w-4 shrink-0 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M7.5 8.25h9m-9 3H12m-9.75 1.51V6.63c0-1.08.73-2.01 1.75-2.29a48.6 48.6 0 0 1 11.5 0c1.02.28 1.75 1.21 1.75 2.29v6.06c0 .55-.23 1.07-.62 1.44l-3.13 3.13a2 2 0 0 1-2.83 0l-1.6-1.6" />
                            <path d="M3.75 15v2.25A2.25 2.25 0 0 0 6 19.5h2.25" />
                        </svg>
                        Switch Account
                    </button>
                </form>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                        class="flex w-full items-center gap-2.5 px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-100 focus:bg-slate-100 focus:outline-none dark:text-slate-200 dark:hover:bg-slate-800 dark:focus:bg-slate-800">
                        <svg class="h-4 w-4 shrink-0 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M15 17l5-5-5-5" />
                            <path d="M20 12H9" />
                            <path d="M12 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h6" />
                        </svg>
                        Log out
                    </button>
                </form>
            </div>
        </div>
    </div>
</nav>

<script>
    (function () {
        var root = document.getElementById('sidebar-account-menu');

        if (! root) {
            return;
        }

        var trigger = document.getElementById('sidebar-account-trigger');
        var dropdown = document.getElementById('sidebar-account-dropdown');
        var chevron = document.getElementById('sidebar-account-chevron');

        var isOpen = false;

        function open() {
            isOpen = true;

            dropdown.classList.remove('hidden');
            trigger.setAttribute('aria-expanded', 'true');
            chevron.classList.add('rotate-180');
        }

        function close(returnFocus) {
            if (! isOpen) {
                return;
            }

            isOpen = false;

            dropdown.classList.add('hidden');
            trigger.setAttribute('aria-expanded', 'false');
            chevron.classList.remove('rotate-180');

            if (returnFocus) {
                trigger.focus();
            }
        }

        trigger.addEventListener('click', function () {
            if (isOpen) {
                close(false);
            } else {
                open();
            }
        });

        // Escape closes the menu and puts the focus back on the button that
        // opened it, so the keyboard never loses its place.
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && isOpen) {
                close(true);
            }
        });

        // A click anywhere outside the account section closes the menu.
        document.addEventListener('click', function (event) {
            if (! isOpen) {
                return;
            }

            if (! root.contains(event.target)) {
                close(false);
            }
        });

        // Focus that leaves the account section altogether also closes the
        // menu, so that a keyboard user tabbing onwards is not left with an
        // open menu behind them.
        root.addEventListener('focusout', function (event) {
            if (! isOpen) {
                return;
            }

            if (! root.contains(event.relatedTarget)) {
                close(false);
            }
        });
    })();
</script>
