@props([
    'label' => '',
    'value' => '0',
    'footnote' => null,
])

<div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
    <p class="text-sm font-medium text-slate-600 dark:text-slate-400">{{ $label }}</p>
    <p class="mt-2 text-3xl font-semibold tracking-tight text-slate-900 dark:text-slate-100">{{ $value }}</p>

    @if ($footnote)
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $footnote }}</p>
    @endif
</div>
