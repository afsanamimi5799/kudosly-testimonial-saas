@php
    $theme = $space->themeEnum();
    $photoBase = \Illuminate\Support\Facades\Storage::disk('public')->url('');
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Wall of Love &middot; {{ $space->name }}</title>

        @fonts

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="min-h-screen {{ $theme->bodyClasses() }} antialiased">
        <main class="mx-auto w-full max-w-6xl px-4 py-12 sm:px-6 lg:px-8">
            {{-- Space branding --}}
            <header class="text-center">
                <p class="text-sm font-semibold uppercase tracking-widest {{ $theme->mutedClasses() }}">
                    Wall of Love
                </p>

                <h1 class="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">
                    {{ $space->title }}
                </h1>

                @if ($space->subtitle)
                    <p class="mx-auto mt-3 max-w-2xl text-lg {{ $theme->mutedClasses() }}">
                        {{ $space->subtitle }}
                    </p>
                @endif

                <p class="mt-4 text-sm {{ $theme->mutedClasses() }}">
                    {{ $testimonials->count() }} kind word{{ $testimonials->count() === 1 ? '' : 's' }} from our customers
                </p>
            </header>

            @if ($testimonials->isEmpty())
                <div class="mx-auto mt-16 max-w-md text-center">
                    <p class="text-lg font-semibold">No testimonials on the wall yet.</p>

                    <p class="mt-2 text-sm {{ $theme->mutedClasses() }}">
                        Check back soon. Our customers&rsquo; kind words will appear here.
                    </p>
                </div>
            @else
                <div class="mt-12 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($testimonials as $testimonial)
                        <figure class="flex h-full flex-col rounded-2xl border p-6 shadow-sm transition {{ $theme->panelClasses() }}">
                            <div class="flex items-center gap-3">
                                @if ($testimonial->profile_photo_path)
                                    <img src="{{ $photoBase.$testimonial->profile_photo_path }}" alt=""
                                        class="h-11 w-11 shrink-0 rounded-full object-cover" loading="lazy">
                                @else
                                    <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-sm font-semibold text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300">
                                        {{ Str::upper(Str::substr($testimonial->customer_name, 0, 1)) }}
                                    </span>
                                @endif

                                <div class="min-w-0">
                                    <figcaption class="truncate text-sm font-semibold">
                                        {{ $testimonial->customer_name }}
                                    </figcaption>

                                    @if ($testimonial->company_name)
                                        <p class="truncate text-sm {{ $theme->mutedClasses() }}">
                                            {{ $testimonial->company_name }}
                                        </p>
                                    @endif
                                </div>
                            </div>

                            @if ($testimonial->rating !== null)
                                <div class="mt-4 flex items-center gap-0.5" aria-label="Rated {{ $testimonial->rating }} out of 5">
                                    @for ($star = 1; $star <= 5; $star++)
                                        <svg class="h-4 w-4 {{ $star <= $testimonial->rating ? 'text-amber-500' : 'text-slate-300 dark:text-slate-600' }}"
                                            viewBox="0 0 24 24" fill="{{ $star <= $testimonial->rating ? 'currentColor' : 'none' }}"
                                            stroke="currentColor" stroke-width="1.2" aria-hidden="true">
                                            <path d="m12 3 2.7 5.5 6 .9-4.35 4.2 1.03 6L12 16.8 6.62 19.6l1.03-6L3.3 9.4l6-.9L12 3Z" />
                                        </svg>
                                    @endfor
                                </div>
                            @endif

                            <blockquote class="mt-4 flex-1 text-sm leading-relaxed {{ $theme->mutedClasses() }}">
                                {{ $testimonial->testimonial_text }}
                            </blockquote>
                        </figure>
                    @endforeach
                </div>
            @endif

            <footer class="mt-16 text-center">
                <p class="text-xs {{ $theme->mutedClasses() }}">
                    Powered by <a href="/" class="font-medium underline">Kudosly</a>
                </p>
            </footer>
        </main>
    </body>
</html>
