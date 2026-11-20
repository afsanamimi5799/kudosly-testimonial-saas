@props([
    'title' => '',
    'description' => null,
])

<div class="rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center dark:border-slate-700 dark:bg-slate-900">
    <h3 class="text-base font-semibold tracking-tight text-slate-900 dark:text-slate-100">{{ $title }}</h3>

    @if ($description)
        <p class="mx-auto mt-2 max-w-md text-sm text-slate-600 dark:text-slate-400">{{ $description }}</p>
    @endif
</div>
