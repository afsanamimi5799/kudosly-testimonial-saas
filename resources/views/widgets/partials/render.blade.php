@php
    /**
     * The shared widget renderer.
     *
     * The editor preview, the iframe embed and the JavaScript embed all use
     * this partial, so what the owner sees in the editor is exactly what the
     * embed shows on their website.
     *
     * The variables it expects are:
     *   $widget        the widget being rendered
     *   $testimonials  the testimonials the widget is allowed to show
     */
    $theme = $widget->themeModeEnum();
    $layout = $widget->layoutEnum();
    $photoBase = \Illuminate\Support\Facades\Storage::disk('public')->url('');
    $animation = $widget->animation_enabled ? 'kudosly-widget-animate' : '';
@endphp

<div class="kudosly-widget {{ $theme->bodyClasses() }}"
    data-kudosly-widget-root="{{ $widget->public_id }}"
    data-kudosly-layout="{{ $layout->value }}"
    data-kudosly-theme="{{ $theme->value }}"
    @if ($widget->backgroundColor()) style="{{ $widget->backgroundStyle() }}" @endif>
    @if ($testimonials->isEmpty())
        <p class="kudosly-widget-empty {{ $theme->mutedClasses() }}">
            There are no testimonials to show yet.
        </p>
    @elseif ($layout === \App\Enums\WidgetLayout::Carousel)
        <div class="kudosly-widget-carousel" @if ($animation) data-animate="{{ $animation }}" @endif>
            @foreach ($testimonials as $testimonial)
                <figure class="kudosly-widget-card {{ $theme->cardClasses() }}">
                    <div class="kudosly-widget-head">
                        @if ($testimonial->profile_photo_path)
                            <img src="{{ $photoBase.$testimonial->profile_photo_path }}" alt="" class="kudosly-widget-avatar" loading="lazy">
                        @else
                            <span class="kudosly-widget-avatar kudosly-widget-initials">
                                {{ Str::upper(Str::substr($testimonial->customer_name, 0, 1)) }}
                            </span>
                        @endif

                        <div class="kudosly-widget-person">
                            <figcaption class="kudosly-widget-name">{{ $testimonial->customer_name }}</figcaption>

                            @if ($testimonial->company_name)
                                <p class="kudosly-widget-company {{ $theme->mutedClasses() }}">{{ $testimonial->company_name }}</p>
                            @endif
                        </div>
                    </div>

                    @if ($testimonial->rating !== null)
                        <div class="kudosly-widget-rating" aria-label="Rated {{ $testimonial->rating }} out of 5">
                            @for ($star = 1; $star <= 5; $star++)
                                <span class="kudosly-widget-star @if ($star <= $testimonial->rating) kudosly-widget-star-on @endif" aria-hidden="true">&#9733;</span>
                            @endfor
                        </div>
                    @endif

                    <blockquote class="kudosly-widget-text {{ $theme->mutedClasses() }}">
                        {{ $testimonial->testimonial_text }}
                    </blockquote>
                </figure>
            @endforeach
        </div>
    @else
        <div class="kudosly-widget-masonry" @if ($animation) data-animate="{{ $animation }}" @endif>
            @foreach ($testimonials as $testimonial)
                <figure class="kudosly-widget-card {{ $theme->cardClasses() }}">
                    <div class="kudosly-widget-head">
                        @if ($testimonial->profile_photo_path)
                            <img src="{{ $photoBase.$testimonial->profile_photo_path }}" alt="" class="kudosly-widget-avatar" loading="lazy">
                        @else
                            <span class="kudosly-widget-avatar kudosly-widget-initials">
                                {{ Str::upper(Str::substr($testimonial->customer_name, 0, 1)) }}
                            </span>
                        @endif

                        <div class="kudosly-widget-person">
                            <figcaption class="kudosly-widget-name">{{ $testimonial->customer_name }}</figcaption>

                            @if ($testimonial->company_name)
                                <p class="kudosly-widget-company {{ $theme->mutedClasses() }}">{{ $testimonial->company_name }}</p>
                            @endif
                        </div>
                    </div>

                    @if ($testimonial->rating !== null)
                        <div class="kudosly-widget-rating" aria-label="Rated {{ $testimonial->rating }} out of 5">
                            @for ($star = 1; $star <= 5; $star++)
                                <span class="kudosly-widget-star @if ($star <= $testimonial->rating) kudosly-widget-star-on @endif" aria-hidden="true">&#9733;</span>
                            @endfor
                        </div>
                    @endif

                    <blockquote class="kudosly-widget-text {{ $theme->mutedClasses() }}">
                        {{ $testimonial->testimonial_text }}
                    </blockquote>
                </figure>
            @endforeach
        </div>
    @endif
</div>
