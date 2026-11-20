@php
    $isEdit = $space !== null;
    $action = $isEdit ? route('spaces.update', ['space' => $space]) : route('spaces.store');

    $currentTheme = old('theme', $space?->theme ?? 'minimal-light');
    $currentRating = old('rating_enabled', $space?->rating_enabled ?? true);
@endphp

<form method="POST" action="{{ $action }}" class="space-y-6">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="space-y-6">
            <div>
                <label for="name" class="block text-sm font-medium text-slate-900 dark:text-slate-100">
                    Space name
                </label>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Only visible to you. Used to identify the space in your dashboard.</p>
                <input type="text" name="name" id="name" value="{{ old('name', $space?->name) }}" required
                    maxlength="255"
                    class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                @error('name')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="slug" class="block text-sm font-medium text-slate-900 dark:text-slate-100">
                    Public link
                </label>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    Leave blank to generate one from the space name.
                </p>
                <div class="mt-2 flex items-stretch">
                    <span class="inline-flex items-center rounded-l-lg border border-r-0 border-slate-300 bg-slate-50 px-3 text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-400">
                        /s/
                    </span>
                    <input type="text" name="slug" id="slug" value="{{ old('slug', $space?->slug) }}"
                        maxlength="255" placeholder="your-space"
                        class="block w-full rounded-r-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                </div>
                @error('slug')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="title" class="block text-sm font-medium text-slate-900 dark:text-slate-100">
                    Page title
                </label>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">The headline customers see on your collection page.</p>
                <input type="text" name="title" id="title" value="{{ old('title', $space?->title) }}" required
                    maxlength="255"
                    class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                @error('title')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="subtitle" class="block text-sm font-medium text-slate-900 dark:text-slate-100">
                    Subtitle <span class="font-normal text-slate-500 dark:text-slate-400">(optional)</span>
                </label>
                <input type="text" name="subtitle" id="subtitle" value="{{ old('subtitle', $space?->subtitle) }}"
                    maxlength="255"
                    class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                @error('subtitle')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="ask" class="block text-sm font-medium text-slate-900 dark:text-slate-100">
                    Testimonial request
                </label>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">The question you ask your customers, for example &ldquo;What do you love about us?&rdquo;</p>
                <textarea name="ask" id="ask" rows="3" required maxlength="1000"
                    class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">{{ old('ask', $space?->ask) }}</textarea>
                @error('ask')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="rating_enabled" class="flex items-start gap-3">
                    <input type="hidden" name="rating_enabled" value="0">
                    <input type="checkbox" name="rating_enabled" id="rating_enabled" value="1"
                        @checked((bool) old('rating_enabled', $currentRating))
                        class="mt-0.5 h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-950">
                    <span>
                        <span class="block text-sm font-medium text-slate-900 dark:text-slate-100">Ask for a star rating</span>
                        <span class="mt-1 block text-xs text-slate-500 dark:text-slate-400">
                            When off, the rating is hidden and no rating is collected.
                        </span>
                    </span>
                </label>
            </div>
        </div>
    </div>

    {{-- Theme selection with a live preview. The preview swaps the panel
         styling client side; the stored value is the theme slug. --}}
    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <h2 class="text-base font-semibold tracking-tight text-slate-900 dark:text-slate-100">Theme</h2>
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Choose how your collection page looks.</p>

        <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3" role="radiogroup" aria-label="Theme">
            @foreach ($themes as $theme)
                <label class="relative flex cursor-pointer flex-col rounded-lg border p-4 transition hover:border-indigo-400 dark:hover:border-indigo-500"
                    data-theme-option="{{ $theme->value }}"
                    data-theme-body="{{ $theme->bodyClasses() }}"
                    data-theme-panel="{{ $theme->panelClasses() }}"
                    data-theme-button="{{ $theme->buttonClasses() }}"
                    data-theme-muted="{{ $theme->mutedClasses() }}">
                    <input type="radio" name="theme" value="{{ $theme->value }}"
                        @checked($currentTheme === $theme->value)
                        class="sr-only">
                    <span class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ $theme->label() }}</span>
                    <span class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $theme->description() }}</span>
                    <span class="mt-3 block h-8 rounded-md border border-slate-200 bg-gradient-to-r from-slate-100 to-slate-200 dark:border-slate-700 dark:from-slate-800 dark:to-slate-700"></span>
                </label>
            @endforeach
        </div>

        @error('theme')
            <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <h2 class="text-base font-semibold tracking-tight text-slate-900 dark:text-slate-100">Preview</h2>
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">A preview of your collection page.</p>

        <div class="mt-4 overflow-hidden rounded-xl border border-slate-200 dark:border-slate-800">
            <div id="theme-preview" class="px-6 py-8">
                <div id="theme-preview-panel" class="mx-auto max-w-md rounded-xl border p-6">
                    <p id="theme-preview-muted" class="text-sm"></p>
                    <h3 id="theme-preview-title" class="mt-1 text-lg font-semibold tracking-tight">
                        {{ old('title', $space?->title ?? 'What do you love about us?') }}
                    </h3>
                    <p id="theme-preview-ask" class="mt-2 text-sm">
                        {{ old('ask', $space?->ask ?? 'We would love to hear what you think.') }}
                    </p>
                    <button type="button" id="theme-preview-button"
                        class="mt-4 inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium">
                        Share your thoughts
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="flex items-center gap-3">
        <button type="submit"
            class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-500">
            {{ $isEdit ? 'Save changes' : 'Create space' }}
        </button>
    </div>
</form>

<script>
    (function () {
        const options = Array.from(document.querySelectorAll('[data-theme-option]'));
        const preview = document.getElementById('theme-preview');
        const panel = document.getElementById('theme-preview-panel');
        const muted = document.getElementById('theme-preview-muted');
        const button = document.getElementById('theme-preview-button');

        if (!options.length || !preview) {
            return;
        }

        function apply(theme) {
            if (!theme) {
                return;
            }

            preview.className = theme.dataset.themeBody;
            panel.className = 'mx-auto max-w-md rounded-xl border p-6 ' + theme.dataset.themePanel;
            muted.className = 'text-sm ' + theme.dataset.themeMuted;
            button.className = 'mt-4 inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium ' + theme.dataset.themeButton;
        }

        function sync() {
            const selected = options.find((option) => option.querySelector('input:checked'));

            if (selected) {
                apply(selected);
            }
        }

        options.forEach((option) => {
            option.querySelector('input').addEventListener('change', sync);
        });

        sync();
    })();
</script>
