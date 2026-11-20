@extends('layouts.app')

@section('content')
    <div class="mx-auto max-w-3xl space-y-8">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Collection fields</h1>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                Choose what your customers are asked for when they share a testimonial on
                <span class="font-medium text-slate-900 dark:text-slate-100">{{ $space->name }}</span>.
            </p>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="rounded-lg border border-indigo-100 bg-indigo-50 p-4 dark:border-indigo-900 dark:bg-indigo-950">
                <p class="text-sm font-medium text-indigo-900 dark:text-indigo-200">Always collected</p>
                <p class="mt-1 text-xs text-indigo-700 dark:text-indigo-300">
                    Name, email and the testimonial itself are always required. Email addresses are never displayed publicly.
                </p>
            </div>

            <form method="POST" action="{{ route('spaces.fields.update', ['space' => $space]) }}" class="mt-6 space-y-4">
                @csrf
                @method('PUT')

                @foreach (App\Enums\SpaceFieldKey::cases() as $field)
                    @php
                        $existing = $fields->get($field->value);
                        $enabled = (bool) old("fields.{$field->value}.enabled", $existing?->enabled ?? true);
                        $required = (bool) old("fields.{$field->value}.required", $existing?->required ?? false);
                    @endphp

                    <div class="rounded-lg border border-slate-200 p-4 dark:border-slate-800">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ $field->label() }}</p>
                                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                    @if ($field === App\Enums\SpaceFieldKey::ProfilePhoto)
                                        An image the customer can upload with their testimonial.
                                    @elseif ($field === App\Enums\SpaceFieldKey::SocialUrl)
                                        A link to a profile on a social network.
                                    @else
                                        The company or organisation the customer represents.
                                    @endif
                                </p>
                            </div>

                            <div class="flex shrink-0 items-center gap-4">
                                <label class="flex items-center gap-2 text-xs font-medium text-slate-600 dark:text-slate-400">
                                    <input type="hidden" name="fields[{{ $field->value }}][enabled]" value="0">
                                    <input type="checkbox" name="fields[{{ $field->value }}][enabled]" value="1"
                                        @checked($enabled)
                                        class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-950">
                                    Show
                                </label>

                                <label class="flex items-center gap-2 text-xs font-medium text-slate-600 dark:text-slate-400">
                                    <input type="hidden" name="fields[{{ $field->value }}][required]" value="0">
                                    <input type="checkbox" name="fields[{{ $field->value }}][required]" value="1"
                                        @checked($required)
                                        class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-950">
                                    Required
                                </label>
                            </div>
                        </div>
                    </div>
                @endforeach

                @error('fields')
                    <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror

                <div class="flex items-center gap-3 pt-2">
                    <button type="submit"
                        class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-500">
                        Save fields
                    </button>
                    <a href="{{ route('spaces.edit', ['space' => $space]) }}"
                        class="text-sm font-medium text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
@endsection
