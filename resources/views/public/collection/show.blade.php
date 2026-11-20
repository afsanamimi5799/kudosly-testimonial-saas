@php
    $theme = $space->themeEnum();
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ $space->title }}</title>
        <meta name="description" content="{{ $space->subtitle }}">

        @fonts

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="min-h-screen {{ $theme->bodyClasses() }} antialiased">
        <main class="mx-auto flex min-h-screen w-full max-w-2xl flex-col justify-center px-4 py-12 sm:px-6">
            <div class="text-center">
                <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">{{ $space->title }}</h1>

                @if ($space->subtitle)
                    <p class="mt-3 text-lg {{ $theme->mutedClasses() }}">{{ $space->subtitle }}</p>
                @endif

                <p class="mt-4 text-base {{ $theme->mutedClasses() }}">{{ $space->ask }}</p>
            </div>

            <div class="mt-10 rounded-xl border p-6 shadow-sm sm:p-8 {{ $theme->panelClasses() }}">
                <form method="POST" action="{{ route('spaces.public.store', ['space' => $space->slug]) }}"
                    enctype="multipart/form-data" class="space-y-5">
                    @csrf

                    <div>
                        <label for="customer_name" class="block text-sm font-medium">
                            Your name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="customer_name" id="customer_name"
                            value="{{ old('customer_name') }}" required maxlength="255"
                            class="mt-1.5 block w-full rounded-lg border px-3 py-2 text-sm shadow-sm focus:ring-2 {{ $theme->inputClasses() }}">
                        @error('customer_name')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="customer_email" class="block text-sm font-medium">
                            Your email <span class="text-red-500">*</span>
                        </label>
                        <input type="email" name="customer_email" id="customer_email"
                            value="{{ old('customer_email') }}" required maxlength="255" autocomplete="email"
                            class="mt-1.5 block w-full rounded-lg border px-3 py-2 text-sm shadow-sm focus:ring-2 {{ $theme->inputClasses() }}">
                        <p class="mt-1 text-xs {{ $theme->mutedClasses() }}">We will never display your email address publicly.</p>
                        @error('customer_email')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    @foreach ($fields as $field)
                        @php
                            $isRequired = (bool) $field->required;
                            $inputId = 'field_'.$field->field_key;
                        @endphp

                        @if ($field->field_key === \App\Enums\SpaceFieldKey::CompanyName->value)
                            <div>
                                <label for="{{ $inputId }}" class="block text-sm font-medium">
                                    Company name @if ($isRequired)<span class="text-red-500">*</span>@endif
                                </label>
                                <input type="text" name="company_name" id="{{ $inputId }}"
                                    value="{{ old('company_name') }}" maxlength="255" @required($isRequired)
                                    class="mt-1.5 block w-full rounded-lg border px-3 py-2 text-sm shadow-sm focus:ring-2 {{ $theme->inputClasses() }}">
                                @error('company_name')
                                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>
                        @elseif ($field->field_key === \App\Enums\SpaceFieldKey::SocialUrl->value)
                            <div>
                                <label for="{{ $inputId }}" class="block text-sm font-medium">
                                    Social profile link @if ($isRequired)<span class="text-red-500">*</span>@endif
                                </label>
                                <input type="url" name="social_url" id="{{ $inputId }}"
                                    value="{{ old('social_url') }}" maxlength="255" placeholder="https://"
                                    @required($isRequired)
                                    class="mt-1.5 block w-full rounded-lg border px-3 py-2 text-sm shadow-sm focus:ring-2 {{ $theme->inputClasses() }}">
                                @error('social_url')
                                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>
                        @elseif ($field->field_key === \App\Enums\SpaceFieldKey::ProfilePhoto->value)
                            <div>
                                <label for="{{ $inputId }}" class="block text-sm font-medium">
                                    Profile photo @if ($isRequired)<span class="text-red-500">*</span>@endif
                                </label>
                                <input type="file" name="profile_photo" id="{{ $inputId }}" accept="image/*"
                                    @required($isRequired)
                                    class="mt-1.5 block w-full text-sm {{ $theme->mutedClasses() }} file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium dark:file:bg-slate-800">
                                <p class="mt-1 text-xs {{ $theme->mutedClasses() }}">JPG, PNG or WEBP. Up to 2 MB.</p>
                                @error('profile_photo')
                                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>
                        @endif
                    @endforeach

                    <div>
                        <label for="testimonial_text" class="block text-sm font-medium">
                            Your testimonial <span class="text-red-500">*</span>
                        </label>
                        <textarea name="testimonial_text" id="testimonial_text" rows="5" required maxlength="5000"
                            placeholder="Tell us what you think..."
                            class="mt-1.5 block w-full rounded-lg border px-3 py-2 text-sm shadow-sm focus:ring-2 {{ $theme->inputClasses() }}">{{ old('testimonial_text') }}</textarea>
                        @error('testimonial_text')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    @if ($space->rating_enabled)
                        <div>
                            <span class="block text-sm font-medium">Your rating <span class="text-red-500">*</span></span>
                            <div class="mt-2 flex items-center gap-1" role="radiogroup" aria-label="Rating">
                                @for ($star = 5; $star >= 1; $star--)
                                    <label class="cursor-pointer">
                                        <input type="radio" name="rating" value="{{ $star }}"
                                            class="peer sr-only" @checked((int) old('rating') === $star)>
                                        <span class="block px-0.5 text-2xl leading-none text-slate-300 transition peer-checked:text-amber-400 hover:text-amber-300">&#9733;</span>
                                    </label>
                                @endfor
                            </div>
                            <p class="mt-1 text-xs {{ $theme->mutedClasses() }}">Choose between 1 and 5 stars.</p>
                            @error('rating')
                                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>
                    @endif

                    <div class="rounded-lg border p-4 {{ $theme->inputClasses() }}">
                        <label class="flex items-start gap-3">
                            <input type="hidden" name="sharing_consent" value="0">
                            <input type="checkbox" name="sharing_consent" value="1"
                                @checked((bool) old('sharing_consent'))
                                class="mt-0.5 h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="text-sm">
                                I give permission for my testimonial to be publicly displayed and shared on websites or social media.
                            </span>
                        </label>
                        <p class="mt-2 text-xs {{ $theme->mutedClasses() }}">
                            You can still submit without this. Your testimonial stays private and is never shown publicly.
                        </p>
                        @error('sharing_consent')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit"
                        class="inline-flex w-full items-center justify-center rounded-lg px-4 py-2.5 text-sm font-medium transition {{ $theme->buttonClasses() }}">
                        Submit your testimonial
                    </button>
                </form>
            </div>

            <p class="mt-8 text-center text-xs {{ $theme->mutedClasses() }}">
                Powered by <a href="/" class="font-medium underline">Kudosly</a>
            </p>
        </main>
    </body>
</html>
