<div class="space-y-8">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h2 class="text-2xl font-semibold tracking-tight">Spaces</h2>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                Each space is a dedicated page where you collect testimonials.
            </p>
        </div>

        <a href="{{ route('spaces.create') }}"
            class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-500">
            Create space
        </a>
    </div>

    @if (session('status') === 'space-updated')
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">
            The space has been updated.
        </div>
    @endif

    @if (session('status') === 'space-deleted')
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">
            The space has been deleted.
        </div>
    @endif

    @if (session('status') === 'fields-updated')
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">
            The collection fields have been updated.
        </div>
    @endif

    @if (session('error'))
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">
            {{ session('error') }}
        </div>
    @endif

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <x-app.stat-card label="Total Spaces" :value="$spacesCount" :footnote="'of '.$maxSpaces.' included in your plan'" />
        <x-app.stat-card label="Spaces Remaining" :value="$spacesRemaining" />
    </div>

    @if ($spaces->isEmpty())
        <x-app.empty-state
            title="No spaces yet"
            description="Spaces let you collect testimonials on a dedicated page. Create your first space to start collecting."
        />
    @else
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($spaces as $space)
                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h3 class="text-base font-semibold tracking-tight text-slate-900 dark:text-slate-100">{{ $space->name }}</h3>
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ $space->title }}</p>

                    <dl class="mt-4 space-y-2 text-sm">
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-slate-500 dark:text-slate-400">Testimonials</dt>
                            <dd class="font-medium text-slate-900 dark:text-slate-100">{{ $space->testimonials_count }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-slate-500 dark:text-slate-400">Theme</dt>
                            <dd class="font-medium text-slate-900 dark:text-slate-100">{{ $space->themeEnum()->label() }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-slate-500 dark:text-slate-400">Link</dt>
                            <dd class="truncate font-medium text-slate-900 dark:text-slate-100">/s/{{ $space->slug }}</dd>
                        </div>
                    </dl>

                    <div class="mt-5 flex flex-wrap items-center gap-3 border-t border-slate-200 pt-4 dark:border-slate-800">
                        <a href="{{ route('spaces.edit', ['space' => $space]) }}"
                            class="text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">
                            Edit
                        </a>
                        <a href="{{ route('spaces.fields', ['space' => $space]) }}"
                            class="text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">
                            Fields
                        </a>
                        <a href="{{ route('spaces.public.show', ['space' => $space->slug]) }}"
                            class="text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">
                            View
                        </a>
                        <form method="POST" action="{{ route('spaces.destroy', ['space' => $space]) }}"
                            onsubmit="return confirm('Delete this space? The testimonials it collected are kept but the space will no longer accept new ones.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                class="text-sm font-medium text-red-600 hover:text-red-500 dark:text-red-400">
                                Delete
                            </button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
