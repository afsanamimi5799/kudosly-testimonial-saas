{{-- The edit form for a testimonial. The moderation status is not edited
     here. The approve and hide actions own the moderation status. --}}
<form method="POST" action="{{ route('testimonials.update', ['testimonial' => $testimonial]) }}"
    class="space-y-6">
    @csrf
    @method('PUT')

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
        <div>
            <label for="customer_name" class="block text-sm font-medium text-slate-700 dark:text-slate-300">
                Customer name
            </label>
            <input id="customer_name" type="text" name="customer_name" value="{{ old('customer_name', $testimonial->customer_name) }}"
                required
                class="mt-1.5 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
            @error('customer_name')
                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="customer_email" class="block text-sm font-medium text-slate-700 dark:text-slate-300">
                Customer email
            </label>
            <input id="customer_email" type="email" name="customer_email" value="{{ old('customer_email', $testimonial->customer_email) }}"
                required
                class="mt-1.5 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                The email address is never shown on a public page.
            </p>
            @error('customer_email')
                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="company_name" class="block text-sm font-medium text-slate-700 dark:text-slate-300">
                Company
            </label>
            <input id="company_name" type="text" name="company_name" value="{{ old('company_name', $testimonial->company_name) }}"
                class="mt-1.5 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
            @error('company_name')
                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="social_url" class="block text-sm font-medium text-slate-700 dark:text-slate-300">
                Social profile URL
            </label>
            <input id="social_url" type="url" name="social_url" value="{{ old('social_url', $testimonial->social_url) }}"
                placeholder="https://"
                class="mt-1.5 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
            @error('social_url')
                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div>
        <label for="testimonial_text" class="block text-sm font-medium text-slate-700 dark:text-slate-300">
            Testimonial
        </label>
        <textarea id="testimonial_text" name="testimonial_text" rows="5" required
            class="mt-1.5 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">{{ old('testimonial_text', $testimonial->testimonial_text) }}</textarea>
        @error('testimonial_text')
            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="rating" class="block text-sm font-medium text-slate-700 dark:text-slate-300">
            Rating
        </label>
        <select id="rating" name="rating"
            class="mt-1.5 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100 focus:outline-none sm:w-48 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
            <option value="">No rating</option>
            @foreach (range(1, 5) as $value)
                <option value="{{ $value }}" @selected(old('rating', $testimonial->rating) === $value)>{{ $value }} star{{ $value === 1 ? '' : 's' }}</option>
            @endforeach
        </select>
        @error('rating')
            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
        @enderror
    </div>

    <div class="flex items-center gap-3">
        <button type="submit"
            class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-500">
            Save changes
        </button>

        <a href="{{ route('inbox.index') }}"
            class="text-sm font-medium text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100">
            Cancel
        </a>
    </div>
</form>
