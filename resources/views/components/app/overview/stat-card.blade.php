@props([
    'label' => '',
    'value' => '0',
    'footnote' => null,
    'icon' => null,
])

<div class="group relative flex flex-col justify-between overflow-hidden rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md dark:border-slate-800 dark:bg-slate-900 dark:hover:border-slate-700 dark:hover:bg-slate-900/80">
    <div>
        <div class="flex items-start justify-between gap-3">
            <p class="text-sm font-medium tracking-tight text-slate-600 dark:text-slate-400">{{ $label }}</p>

            @if ($icon)
                <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 transition duration-200 group-hover:bg-indigo-100 dark:bg-indigo-950/60 dark:text-indigo-300 dark:group-hover:bg-indigo-950">
                    {{ $icon }}
                </span>
            @endif
        </div>

        <p class="mt-3 text-3xl font-semibold tracking-tight text-slate-900 tabular-nums dark:text-slate-100">{{ $value }}</p>
    </div>

    @if ($footnote)
        <p class="mt-4 border-t border-slate-100 pt-3 text-xs font-medium text-slate-500 dark:border-slate-800/80 dark:text-slate-400">{{ $footnote }}</p>
    @endif
</div>
