@extends('layouts.app')

@section('content')
    <div class="mx-auto max-w-2xl space-y-8">
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-6 dark:border-emerald-900 dark:bg-emerald-950">
            <h1 class="text-lg font-semibold tracking-tight text-emerald-900 dark:text-emerald-100">
                Your space is ready
            </h1>
            <p class="mt-1 text-sm text-emerald-800 dark:text-emerald-300">
                Share this link with your customers to start collecting testimonials.
            </p>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-base font-semibold tracking-tight text-slate-900 dark:text-slate-100">
                {{ $space->name }}
            </h2>

            <div class="mt-4">
                <label for="public-link" class="block text-sm font-medium text-slate-900 dark:text-slate-100">
                    Public URL
                </label>
                <div class="mt-2 flex items-stretch">
                    <input type="text" id="public-link" readonly value="{{ $space->publicUrl() }}"
                        class="block w-full rounded-l-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-300">
                    <button type="button" id="copy-link"
                        data-copy-target="public-link"
                        class="inline-flex items-center rounded-r-lg border border-l-0 border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
                        Copy link
                    </button>
                </div>
                <p id="copy-status" class="mt-2 hidden text-xs font-medium text-emerald-600 dark:text-emerald-400">
                    Copied to your clipboard.
                </p>
                @error('slug')
                    <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div class="mt-6 flex flex-wrap items-center gap-3">
                <a href="{{ route('spaces.public.show', ['space' => $space->slug]) }}"
                    class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-500">
                    View space
                </a>
                <a href="{{ route('spaces.index') }}"
                    class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
                    Go to dashboard
                </a>
                <a href="{{ route('spaces.fields', ['space' => $space]) }}"
                    class="text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">
                    Configure collection fields
                </a>
            </div>
        </div>
    </div>
@endsection

<script>
    (function () {
        const button = document.getElementById('copy-link');
        const status = document.getElementById('copy-status');

        if (!button) {
            return;
        }

        button.addEventListener('click', function () {
            const target = document.getElementById(button.dataset.copyTarget);

            if (!target) {
                return;
            }

            target.select();
            target.setSelectionRange(0, 99999);

            navigator.clipboard.writeText(target.value).then(function () {
                status.classList.remove('hidden');
                button.textContent = 'Copied';

                setTimeout(function () {
                    button.textContent = 'Copy link';
                    status.classList.add('hidden');
                }, 2000);
            });
        });
    })();
</script>
