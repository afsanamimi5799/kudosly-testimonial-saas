@props([
    'label' => '',
    'value' => '0',
    'footnote' => null,
])

<div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition duration-200 hover:border-slate-300 hover:shadow-md dark:border-slate-800 dark:bg-slate-900 dark:hover:border-slate-700">
    <p class="text-sm font-medium tracking-tight text-slate-600 dark:text-slate-400">{{ $label }}</p>
    <p class="mt-2.5 text-3xl font-semibold tracking-tight tabular-nums text-slate-900 dark:text-slate-100">{{ $value }}</p>

    @if ($footnote)
        <p class="mt-4 border-t border-slate-100 pt-3 text-xs font-medium text-slate-500 dark:border-slate-800/80 dark:text-slate-400">{{ $footnote }}</p>
    @endif
</div>
