{{-- A single testimonial in the inbox, with its moderation actions. --}}
@php
    $status = $testimonial->status;
    $photoUrl = $testimonial->profile_photo_path
        ? \Illuminate\Support\Facades\Storage::disk('public')->url($testimonial->profile_photo_path)
        : null;
@endphp

<article class="px-6 py-5"
    id="testimonial-{{ $testimonial->id }}"
    data-testimonial-id="{{ $testimonial->id }}">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="flex min-w-0 gap-3">
            @if ($photoUrl)
                <img src="{{ $photoUrl }}" alt=""
                    class="h-10 w-10 shrink-0 rounded-full object-cover" loading="lazy">
            @else
                <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-sm font-semibold text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300">
                    {{ Str::upper(Str::substr($testimonial->customer_name, 0, 1)) }}
                </span>
            @endif

            <div class="min-w-0">
                <p class="truncate text-sm font-medium text-slate-900 dark:text-slate-100">
                    {{ $testimonial->customer_name }}
                </p>

                @if ($testimonial->company_name)
                    <p class="truncate text-sm text-slate-600 dark:text-slate-400">{{ $testimonial->company_name }}</p>
                @endif

                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                    {{ $testimonial->space?->name ?? 'Unknown space' }}
                    &middot;
                    {{ $testimonial->created_at?->format('j M Y') }}
                </p>
            </div>
        </div>

        <div class="flex shrink-0 flex-wrap items-center gap-2">
            @if ($testimonial->rating !== null)
                <span class="inline-flex items-center gap-1 rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                    <svg class="h-3 w-3 text-amber-500" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path d="m12 3 2.7 5.5 6 .9-4.35 4.2 1.03 6L12 16.8 6.62 19.6l1.03-6L3.3 9.4l6-.9L12 3Z" />
                    </svg>
                    {{ $testimonial->rating }}
                </span>
            @endif

            <span class="rounded-md px-2 py-0.5 text-xs font-medium {{ $status->badgeClasses() }}">
                {{ $status->label() }}
            </span>

            @if ($testimonial->is_favorite)
                <span class="inline-flex items-center gap-1 rounded-md bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-950/60 dark:text-amber-200">
                    <svg class="h-3 w-3" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path d="m12 3 2.7 5.5 6 .9-4.35 4.2 1.03 6L12 16.8 6.62 19.6l1.03-6L3.3 9.4l6-.9L12 3Z" />
                    </svg>
                    Favourite
                </span>
            @endif

            @if ($testimonial->is_wall_of_love)
                <span class="inline-flex items-center gap-1 rounded-md bg-rose-100 px-2 py-0.5 text-xs font-medium text-rose-800 dark:bg-rose-950/60 dark:text-rose-200">
                    <svg class="h-3 w-3" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path d="M12 20.5S4 15.8 4 10.2A4.2 4.2 0 0 1 12 8a4.2 4.2 0 0 1 8 2.2c0 5.6-8 10.3-8 10.3Z" />
                    </svg>
                    Wall of Love
                </span>
            @endif

            @if (! $testimonial->sharing_consent)
                <span class="rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                    No sharing consent
                </span>
            @endif
        </div>
    </div>

    <blockquote class="mt-3 text-sm leading-relaxed text-slate-700 dark:text-slate-300">
        {{ $testimonial->testimonial_text }}
    </blockquote>

    <div class="mt-4 flex flex-wrap items-center gap-2">
        @if ($status !== \App\Enums\TestimonialStatus::Approved)
            <form method="POST" action="{{ route('testimonials.approve', ['testimonial' => $testimonial]) }}">
                @csrf
                <button type="submit"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-indigo-500">
                    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                    </svg>
                    Approve
                </button>
            </form>
        @endif

        @if ($status !== \App\Enums\TestimonialStatus::Hidden)
            <form method="POST" action="{{ route('testimonials.hide', ['testimonial' => $testimonial]) }}">
                @csrf
                <button type="submit"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M10 3.75a.75.75 0 0 1 .75.75v4.75h4.75a.75.75 0 0 1 0 1.5h-4.75v4.75a.75.75 0 0 1-1.5 0v-4.75H4.75a.75.75 0 0 1 0-1.5h4.75V4.5a.75.75 0 0 1 .75-.75Z" transform="rotate(45 10 10)" />
                    </svg>
                    Hide
                </button>
            </form>
        @endif

        <form method="POST" action="{{ route('testimonials.favorite', ['testimonial' => $testimonial]) }}">
            @csrf
            <button type="submit"
                class="inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-medium transition {{ $testimonial->is_favorite ? 'border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200' : 'border-slate-200 text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800' }}">
                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="{{ $testimonial->is_favorite ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                    <path d="m12 3 2.7 5.5 6 .9-4.35 4.2 1.03 6L12 16.8 6.62 19.6l1.03-6L3.3 9.4l6-.9L12 3Z" />
                </svg>
                {{ $testimonial->is_favorite ? 'Unfavourite' : 'Favourite' }}
            </button>
        </form>

        <form method="POST" action="{{ route('testimonials.wall-of-love', ['testimonial' => $testimonial]) }}">
            @csrf
            <button type="submit"
                class="inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-medium transition {{ $testimonial->is_wall_of_love ? 'border-rose-300 bg-rose-50 text-rose-800 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-200' : 'border-slate-200 text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800' }}">
                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="{{ $testimonial->is_wall_of_love ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                    <path d="M12 20.5S4 15.8 4 10.2A4.2 4.2 0 0 1 12 8a4.2 4.2 0 0 1 8 2.2c0 5.6-8 10.3-8 10.3Z" />
                </svg>
                {{ $testimonial->is_wall_of_love ? 'Remove from wall' : 'Add to wall' }}
            </button>
        </form>

        {{-- Secondary actions live behind an ellipsis menu. --}}
        <details class="relative">
            <summary class="inline-flex h-8 w-8 cursor-pointer list-none items-center justify-center rounded-lg border border-slate-200 text-slate-600 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                <span class="sr-only">More actions for {{ $testimonial->customer_name }}</span>
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path d="M10 6a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3ZM10 13a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3ZM10 20a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3Z" />
                </svg>
            </summary>

            <div class="absolute right-0 z-10 mt-1 w-52 overflow-hidden rounded-lg border border-slate-200 bg-white py-1 shadow-lg dark:border-slate-700 dark:bg-slate-900">
                <a href="{{ route('testimonials.edit', ['testimonial' => $testimonial]) }}"
                    class="block px-4 py-2 text-xs font-medium text-slate-700 transition hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800">
                    Edit testimonial
                </a>

                @if ($testimonial->space?->slug)
                    <a href="{{ route('wall.public.show', ['space' => $testimonial->space->slug]) }}"
                        class="block px-4 py-2 text-xs font-medium text-slate-700 transition hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800">
                        View public wall
                    </a>
                @endif

                <form method="POST" action="{{ route('testimonials.destroy', ['testimonial' => $testimonial]) }}"
                    onsubmit="return confirm('Delete this testimonial? It will be removed from your inbox and from every public page.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                        class="block w-full px-4 py-2 text-left text-xs font-medium text-red-600 transition hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/40">
                        Delete testimonial
                    </button>
                </form>
            </div>
        </details>
    </div>
</article>
