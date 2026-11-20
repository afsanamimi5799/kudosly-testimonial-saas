<section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
    <h2 class="text-lg font-semibold tracking-tight">Profile information</h2>
    <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
        Update your account's name and email address.
    </p>

    <form method="POST" action="{{ route('profile.update') }}" class="mt-6 space-y-5">
        @csrf
        @method('PATCH')

        <div>
            <label for="name" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Name</label>
            <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}"
                required
                autocomplete="name"
                class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-slate-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 dark:placeholder-slate-500">
            @error('name')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="email" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Email address</label>
            <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}"
                required
                autocomplete="username"
                class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-slate-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 dark:placeholder-slate-500">
            @error('email')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-center gap-3">
            <button type="submit"
                class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900">
                Save
            </button>
        </div>
    </form>
</section>
