@php
    $theme = $space->themeEnum();
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Thank you &middot; {{ $space->title }}</title>

        @fonts

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="min-h-screen {{ $theme->bodyClasses() }} antialiased">
        <main class="mx-auto flex min-h-screen w-full max-w-xl flex-col items-center justify-center px-4 py-12 text-center sm:px-6">
            <div class="text-5xl" aria-hidden="true">&#127881;</div>

            <h1 class="mt-6 text-3xl font-bold tracking-tight sm:text-4xl">
                You&rsquo;re officially awesome! &#127881;
            </h1>

            <p class="mt-4 text-lg {{ $theme->mutedClasses() }}">
                Your kind words just made someone&rsquo;s day.
            </p>

            <p class="mt-2 text-sm {{ $theme->mutedClasses() }}">
                We&rsquo;ve received your testimonial for {{ $space->name }}. It will appear publicly once it has been approved.
            </p>

            <div class="mt-10 flex flex-wrap items-center justify-center gap-3">
                <a href="{{ route('spaces.public.show', ['space' => $space->slug]) }}"
                    class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium transition {{ $theme->buttonClasses() }}">
                    Share another testimonial
                </a>
            </div>

            <p class="mt-8 text-xs {{ $theme->mutedClasses() }}">
                Powered by <a href="/" class="font-medium underline">Kudosly</a>
            </p>
        </main>
    </body>
</html>
