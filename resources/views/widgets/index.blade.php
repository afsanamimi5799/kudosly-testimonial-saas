<div class="space-y-8">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h2 class="text-2xl font-semibold tracking-tight">Widgets</h2>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                The embeddable widgets that display your testimonials on your own site.
            </p>
        </div>

        <a href="{{ route('widgets.create') }}"
            class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-500">
            Create widget
        </a>
    </div>

    @if (session('status') === 'widget-created')
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">
            The widget has been created.
        </div>
    @endif

    @if (session('status') === 'widget-updated')
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">
            The widget has been updated.
        </div>
    @endif

    @if (session('status') === 'widget-deleted')
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">
            The widget has been deleted. Its embed no longer shows any testimonials.
        </div>
    @endif

    @if (session('error'))
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">
            {{ session('error') }}
        </div>
    @endif

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <x-app.stat-card label="Total Widgets" :value="$widgetsCount" />
    </div>

    @if ($widgets->isEmpty())
        <x-app.empty-state
            title="No widgets yet"
            description="Widgets embed your testimonials on your own website. Create a widget to get your embed code."
        />
    @else
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <ul class="divide-y divide-slate-200 dark:divide-slate-800">
                @foreach ($widgets as $widget)
                    <li class="px-6 py-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-slate-900 dark:text-slate-100">
                                    {{ $widget->space?->name ?? 'Unknown space' }}
                                </p>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                    {{ $widget->layoutEnum()->label() }} layout &middot; {{ $widget->themeModeEnum()->label() }} theme
                                </p>
                            </div>

                            <span class="shrink-0 rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                Shows up to {{ $widget->displayLimit() }}
                            </span>
                        </div>

                        <div class="mt-4 flex flex-wrap items-center gap-3 border-t border-slate-200 pt-4 dark:border-slate-800">
                            <a href="{{ route('widgets.edit', ['widget' => $widget]) }}"
                                class="text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">
                                Edit
                            </a>
                            <a href="{{ route('widgets.preview', ['widget' => $widget]) }}"
                                class="text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">
                                Preview
                            </a>
                            <a href="{{ $widget->embedUrl() }}" target="_blank" rel="noopener"
                                class="text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">
                                Open embed
                            </a>
                            <form method="POST" action="{{ route('widgets.destroy', ['widget' => $widget]) }}"
                                onsubmit="return confirm('Delete this widget? Its embed will stop working. The testimonial data is not affected.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                    class="text-sm font-medium text-red-600 hover:text-red-500 dark:text-red-400">
                                    Delete
                                </button>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
