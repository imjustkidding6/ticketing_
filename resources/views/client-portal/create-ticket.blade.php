<x-client-portal-layout :tenant="$tenant">
    <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">
        <div class="rounded-xl bg-white p-8 shadow-sm">
            <h2 class="text-2xl font-semibold text-gray-900">{{ __('Submit a Support Ticket') }}</h2>
            <p class="mt-2 text-sm text-gray-500">{{ __('Describe your issue and we will get back to you as soon as possible.') }}</p>

            <form method="POST" action="{{ route('portal.tickets.store', ['tenant' => $tenant->slug]) }}" class="mt-8 space-y-6">
                @csrf

                <div x-data="kbSuggestions()">
                    <label for="subject" class="block text-sm font-medium text-gray-700">{{ __('Subject') }}</label>
                    <input type="text" name="subject" id="subject" value="{{ old('subject') }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="{{ __('Brief summary of your issue') }}" x-on:input.debounce.400ms="search($event.target.value)">
                    @error('subject') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror

                    <div x-show="articles.length > 0" x-cloak class="mt-2 rounded-lg border border-indigo-200 bg-indigo-50 p-3">
                        <p class="text-xs font-medium text-indigo-700 mb-2">{{ __('These articles may answer your question:') }}</p>
                        <template x-for="article in articles" :key="article.id">
                            <a :href="article.url" target="_blank" class="block text-sm text-indigo-600 hover:text-indigo-800 py-1">
                                <span x-text="article.title"></span>
                            </a>
                        </template>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="department_id" class="block text-sm font-medium text-gray-700">{{ __('Department') }}</label>
                        <select name="department_id" id="department_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <option value="">{{ __('Select department') }}</option>
                            @foreach($departments as $department)
                                <option value="{{ $department->id }}" {{ old('department_id') == $department->id ? 'selected' : '' }}>{{ $department->name }}</option>
                            @endforeach
                        </select>
                        @error('department_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="category_id" class="block text-sm font-medium text-gray-700">{{ __('Category') }}</label>
                        <select name="category_id" id="category_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <option value="">{{ __('Select category') }}</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" {{ old('category_id') == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                            @endforeach
                        </select>
                        @error('category_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label for="priority" class="block text-sm font-medium text-gray-700">{{ __('Priority') }}</label>
                    <select name="priority" id="priority" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        <option value="low" {{ old('priority', 'medium') === 'low' ? 'selected' : '' }}>{{ __('Low') }}</option>
                        <option value="medium" {{ old('priority', 'medium') === 'medium' ? 'selected' : '' }}>{{ __('Medium') }}</option>
                        <option value="high" {{ old('priority') === 'high' ? 'selected' : '' }}>{{ __('High') }}</option>
                        <option value="critical" {{ old('priority') === 'critical' ? 'selected' : '' }}>{{ __('Critical') }}</option>
                    </select>
                    @error('priority') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700">{{ __('Description') }}</label>
                    <textarea name="description" id="description" rows="6" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="{{ __('Please provide as much detail as possible about your issue...') }}">{{ old('description') }}</textarea>
                    @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="flex items-center justify-end gap-3">
                    <a href="{{ route('portal.dashboard', ['tenant' => $tenant->slug]) }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">{{ __('Cancel') }}</a>
                    <button type="submit" class="rounded-md px-4 py-2 text-sm font-semibold text-white shadow-sm" style="background-color: var(--portal-primary);">{{ __('Submit Ticket') }}</button>
                </div>
            </form>
        </div>
    </div>
    @if($kbSearchUrl)
    <script>
        function kbSuggestions() {
            return {
                articles: [],
                async search(query) {
                    if (query.length < 3) {
                        this.articles = [];
                        return;
                    }
                    try {
                        const response = await fetch(`{{ $kbSearchUrl }}?q=${encodeURIComponent(query)}`);
                        this.articles = await response.json();
                    } catch (e) {
                        this.articles = [];
                    }
                }
            };
        }
    </script>
    @else
    <script>
        function kbSuggestions() {
            return { articles: [], search() {} };
        }
    </script>
    @endif
</x-client-portal-layout>
