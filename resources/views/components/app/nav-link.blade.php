@props([
    'href' => null,
    'active' => false,
    'label' => '',
    'badge' => null,
])

@php
    $classes = 'group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition';

    if ($href === null) {
        $classes .= ' cursor-default text-slate-400 dark:text-slate-500';
    } elseif ($active) {
        $classes .= ' bg-indigo-50 text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-300';
    } else {
        $classes .= ' text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100';
    }
@endphp

@if ($href === null)
    <span class="{{ $classes }}" aria-disabled="true">
        @isset($icon)
            <span class="shrink-0 text-slate-400 transition group-hover:text-slate-500 dark:text-slate-500">
                {{ $icon }}
            </span>
        @endisset

        <span class="flex-1">{{ $label }}</span>

        @if ($badge)
            <span class="rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                {{ $badge }}
            </span>
        @endif
    </span>
@else
    <a href="{{ $href }}" class="{{ $classes }}" @if ($active) aria-current="page" @endif>
        @isset($icon)
            <span class="shrink-0 {{ $active ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400 transition group-hover:text-slate-600 dark:text-slate-500 dark:group-hover:text-slate-300' }}">
                {{ $icon }}
            </span>
        @endisset

        <span class="flex-1">{{ $label }}</span>

        @if ($badge)
            <span class="rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                {{ $badge }}
            </span>
        @endif
    </a>
@endif
