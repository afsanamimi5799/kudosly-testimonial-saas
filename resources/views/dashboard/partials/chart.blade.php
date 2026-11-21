{{-- The daily testimonial submission chart.
     The chart is rendered on the server as an SVG so that it works without
     a JavaScript charting library and can be asserted in tests. --}}
@php
    $maxCount = max($chart['counts']);
    $barWidth = $chart['range'] > 0 ? (\App\Dashboard\SubmissionChart::WIDTH / $chart['range']) : 0;
    $labelEvery = $chart['range'] > 45 ? 15 : ($chart['range'] > 14 ? 5 : 1);
@endphp

<section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-7 dark:border-slate-800 dark:bg-slate-900">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h3 class="text-lg font-semibold tracking-tight text-slate-900 dark:text-slate-100">Testimonial submissions</h3>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                {{ $chart['total'] }} testimonial{{ $chart['total'] === 1 ? '' : 's' }} in the last {{ $chart['range'] }} days.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-1">
            @foreach ($chartRanges as $option)
                <a href="{{ route('dashboard', ['range' => $option]) }}"
                    class="rounded-lg px-3 py-1.5 text-xs font-medium transition {{ $chartRange === $option ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' }}">
                    {{ $option }} days
                </a>
            @endforeach
        </div>
    </div>

    @if ($maxCount === 0)
        <div class="mt-6 rounded-xl border border-dashed border-slate-300 px-6 py-12 text-center dark:border-slate-700">
            <p class="text-sm font-medium text-slate-900 dark:text-slate-100">No submissions in this period</p>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                Once customers start sending testimonials, the daily totals appear here.
            </p>
        </div>
    @else
        <div class="mt-6 overflow-x-auto">
            <svg viewBox="-{{ \App\Dashboard\SubmissionChart::LABEL_PADDING }} 0 {{ \App\Dashboard\SubmissionChart::WIDTH + (\App\Dashboard\SubmissionChart::LABEL_PADDING * 2) }} {{ \App\Dashboard\SubmissionChart::HEIGHT + 30 }}"
                class="h-48 w-full min-w-[36rem]"
                role="img"
                aria-label="Daily testimonial submissions for the last {{ $chart['range'] }} days"
                data-testid="submission-chart"
                data-chart-range="{{ $chart['range'] }}"
                data-chart-total="{{ $chart['total'] }}">
                @foreach ($chart['counts'] as $index => $count)
                    @php
                        $height = $count > 0
                            ? max(3, (int) round(($count / $maxCount) * \App\Dashboard\SubmissionChart::HEIGHT))
                            : 0;
                        $x = $index * $barWidth;
                        $y = \App\Dashboard\SubmissionChart::HEIGHT - $height;
                    @endphp

                    <rect
                        x="{{ number_format($x + 1, 2) }}"
                        y="{{ number_format($y, 2) }}"
                        width="{{ number_format(max(1, $barWidth - 2), 2) }}"
                        height="{{ $height }}"
                        rx="2"
                        class="fill-indigo-500 {{ $height === 0 ? 'fill-slate-200 dark:fill-slate-800' : '' }}"
                        data-count="{{ $count }}"
                        data-date="{{ $chart['labels'][$index] }}">
                        <title>{{ $chart['labels'][$index] }}: {{ $count }}</title>
                    </rect>

                    @if ($index % $labelEvery === 0 || $index === count($chart['counts']) - 1)
                        <text
                            x="{{ number_format($x + ($barWidth / 2), 2) }}"
                            y="{{ \App\Dashboard\SubmissionChart::HEIGHT + 18 }}"
                            text-anchor="middle"
                            class="fill-slate-500 text-[10px] dark:fill-slate-400">
                            {{ $chart['labels'][$index] }}
                        </text>
                    @endif
                @endforeach
            </svg>
        </div>
    @endif
</section>
