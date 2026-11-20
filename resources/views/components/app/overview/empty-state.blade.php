@props([
    'title' => '',
    'description' => null,
    'icon' => null,
])

<div class="flex flex-col items-center rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center shadow-sm transition hover:border-slate-400 dark:border-slate-700 dark:bg-slate-900 dark:hover:border-slate-600">
    @if ($icon)
        <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400 dark:bg-slate-800 dark:text-slate-500">
            {{ $icon }}
        </span>
    @endif

    <h3 class="mt-4 text-base font-semibold tracking-tight text-slate-900 dark:text-slate-100">{{ $title }}</h3>

    @if ($description)
        <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-slate-600 dark:text-slate-400">{{ $description }}</p>
    @endif
</div>
