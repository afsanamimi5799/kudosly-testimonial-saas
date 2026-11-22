<section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
    <h2 class="text-lg font-semibold tracking-tight text-slate-900 dark:text-slate-100">Profile Photo</h2>
    <p class="mt-1.5 text-sm text-slate-600 dark:text-slate-400">
        Your picture appears next to your name in the sidebar. A JPG, PNG or WEBP image of up to 2 MB.
    </p>

    <div class="mt-6 flex items-center gap-4">
        <span class="inline-flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-full bg-indigo-100 text-xl font-semibold text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300">
            @if ($user->profilePhotoUrl())
                <img src="{{ $user->profilePhotoUrl() }}" alt="" class="h-full w-full object-cover">
            @else
                {{ $user->profileInitial() }}
            @endif
        </span>

        <div class="min-w-0 flex-1">
            @if ($user->profilePhotoUrl())
                <p class="text-sm text-slate-600 dark:text-slate-400">A picture is set for this account.</p>
            @else
                <p class="text-sm text-slate-600 dark:text-slate-400">No picture yet. Your initial is shown instead.</p>
            @endif
        </div>
    </div>

    @error('profile_photo')
        <p class="mt-4 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror

    <form method="POST" action="{{ route('profile.photo.update') }}" enctype="multipart/form-data" class="mt-5 space-y-4">
        @csrf

        <div>
            <label for="profile_photo" class="block text-sm font-medium text-slate-700 dark:text-slate-300">
                {{ $user->profilePhotoUrl() ? 'Replace photo' : 'Upload a photo' }}
            </label>
            <input id="profile_photo" name="profile_photo" type="file"
                accept="image/jpeg,image/png,image/webp"
                class="mt-1 block w-full text-sm text-slate-700 file:mr-4 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:text-slate-300 dark:file:bg-indigo-950/60 dark:file:text-indigo-300 dark:hover:file:bg-indigo-950 dark:focus:ring-offset-slate-900">
        </div>

        <div class="flex items-center gap-3">
            <button type="submit"
                class="rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm shadow-indigo-600/20 transition hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                {{ $user->profilePhotoUrl() ? 'Replace photo' : 'Upload photo' }}
            </button>
        </div>
    </form>

    @if ($user->profilePhotoUrl())
        <form method="POST" action="{{ route('profile.photo.destroy') }}" class="mt-4 border-t border-slate-100 pt-4 dark:border-slate-800/80">
            @csrf
            @method('DELETE')
            <button type="submit"
                class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-400 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                Remove photo
            </button>
        </form>
    @endif
</section>
