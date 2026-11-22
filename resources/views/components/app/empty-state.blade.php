@props([
    'title' => '',
    'description' => null,
    'icon' => null,
])

<div class="flex flex-col items-center rounded-xl border border-dashed border-slate-300 bg-white px-6 py-8 text-center dark:border-slate-700 dark:bg-slate-900">
    @if ($icon)
        <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950/60 dark:text-indigo-300">
            {{ $icon }}
        </span>
    @endif

    <h3 class="text-base font-semibold tracking-tight text-slate-900 dark:text-slate-100 {{ $icon ? 'mt-4' : '' }}">{{ $title }}</h3>

    @if ($description)
        <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-slate-600 dark:text-slate-400">{{ $description }}</p>
    @endif
</div>
