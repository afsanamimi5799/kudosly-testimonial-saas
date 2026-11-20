@php
    $isEdit = $widget !== null;
    $action = $isEdit ? route('widgets.update', ['widget' => $widget]) : route('widgets.store');

    $currentLayout = old('layout', $widget?->layout ?? 'masonry');
    $currentThemeMode = old('theme_mode', $widget?->theme_mode ?? 'light');
    $currentBackground = old('background_color', $widget?->background_color ?? '');
    $currentAnimation = old('animation_enabled', $widget?->animation_enabled ?? true);
    $currentLimit = old('display_limit', $widget?->display_limit ?? 10);
@endphp

<form method="POST" action="{{ $action }}" class="space-y-6" id="widget-form">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="space-y-6">
            @unless ($isEdit)
                <div>
                    <label for="space_id" class="block text-sm font-medium text-slate-900 dark:text-slate-100">
                        Space
                    </label>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">The space whose testimonials this widget shows.</p>
                    <select name="space_id" id="space_id" required
                        class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                        <option value="">Choose a space</option>
                        @foreach ($spaces as $spaceOption)
                            <option value="{{ $spaceOption->id }}" @selected((int) old('space_id') === $spaceOption->id)>
                                {{ $spaceOption->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('space_id')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>
            @endunless

            <fieldset>
                <legend class="block text-sm font-medium text-slate-900 dark:text-slate-100">Layout</legend>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">How the testimonials are arranged inside the widget.</p>

                <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2" role="radiogroup" aria-label="Layout">
                    @foreach ($layouts as $layout)
                        <label class="flex cursor-pointer flex-col rounded-lg border p-4 transition hover:border-indigo-400 dark:hover:border-indigo-500"
                            data-widget-layout-option="{{ $layout->value }}">
                            <input type="radio" name="layout" value="{{ $layout->value }}"
                                @checked($currentLayout === $layout->value)
                                class="sr-only">
                            <span class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ $layout->label() }}</span>
                            <span class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $layout->description() }}</span>
                        </label>
                    @endforeach
                </div>

                @error('layout')
                    <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </fieldset>

            <fieldset>
                <legend class="block text-sm font-medium text-slate-900 dark:text-slate-100">Colour mode</legend>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">The base appearance of the widget.</p>

                <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2" role="radiogroup" aria-label="Colour mode">
                    @foreach ($themeModes as $themeMode)
                        <label class="flex cursor-pointer items-center gap-3 rounded-lg border p-4 transition hover:border-indigo-400 dark:hover:border-indigo-500"
                            data-widget-theme-option="{{ $themeMode->value }}">
                            <input type="radio" name="theme_mode" value="{{ $themeMode->value }}"
                                @checked($currentThemeMode === $themeMode->value)
                                class="sr-only">
                            <span class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ $themeMode->label() }}</span>
                        </label>
                    @endforeach
                </div>

                @error('theme_mode')
                    <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </fieldset>

            <div>
                <span class="block text-sm font-medium text-slate-900 dark:text-slate-100">Background colour</span>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Optional. Pick a preset or enter your own hex value.</p>

                <div class="mt-3 flex flex-wrap items-center gap-2">
                    @foreach ($backgroundColors as $backgroundColor)
                        <label class="cursor-pointer" title="{{ $backgroundColor }}">
                            <input type="radio" name="background_color" value="{{ $backgroundColor }}"
                                @checked(strtolower((string) $currentBackground) === $backgroundColor)
                                class="sr-only">
                            <span class="block h-8 w-8 rounded-md border border-slate-200 dark:border-slate-700"
                                style="background-color: {{ $backgroundColor }}"></span>
                        </label>
                    @endforeach
                </div>

                <div class="mt-3 flex items-stretch">
                    <span class="inline-flex items-center rounded-l-lg border border-r-0 border-slate-300 bg-slate-50 px-3 text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-400">
                        #
                    </span>
                    <input type="text" name="background_color" id="background_color"
                        value="{{ $currentBackground !== '' ? ltrim((string) $currentBackground, '#') : '' }}"
                        maxlength="6" placeholder="eef2ff" pattern="[0-9a-fA-F]{6}"
                        class="block w-full rounded-r-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                </div>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Leave blank to use the default background.</p>

                @error('background_color')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="display_limit" class="block text-sm font-medium text-slate-900 dark:text-slate-100">
                    Number of testimonials
                </label>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Between {{ $displayLimitMin }} and {{ $displayLimitMax }} testimonials are shown.</p>
                <input type="number" name="display_limit" id="display_limit" required
                    min="{{ $displayLimitMin }}" max="{{ $displayLimitMax }}" step="1"
                    value="{{ $currentLimit }}"
                    class="mt-2 block w-40 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                @error('display_limit')
                    <p class="mt-1 text-sm text-red-600 dark:text-slate-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="animation_enabled" class="flex items-start gap-3">
                    <input type="hidden" name="animation_enabled" value="0">
                    <input type="checkbox" name="animation_enabled" id="animation_enabled" value="1"
                        @checked((bool) $currentAnimation)
                        class="mt-0.5 h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-950">
                    <span>
                        <span class="block text-sm font-medium text-slate-900 dark:text-slate-100">Animate testimonials</span>
                        <span class="mt-1 block text-xs text-slate-500 dark:text-slate-400">
                            Turn this off for a completely static widget.
                        </span>
                    </span>
                </label>
            </div>
        </div>
    </div>

    <div class="flex items-center gap-3">
        <button type="submit"
            class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-500">
            {{ $isEdit ? 'Save changes' : 'Create widget' }}
        </button>

        <a href="{{ route('widgets.index') }}"
            class="text-sm font-medium text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200">
            Cancel
        </a>
    </div>
</form>
