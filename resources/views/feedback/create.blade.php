<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Send Feedback</h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">

            @if(session('success'))
                <div class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                    {{ session('success') }}
                </div>
            @endif

            <div class="overflow-hidden rounded-xl bg-white p-6 shadow-sm">
                <p class="text-sm text-gray-500 mb-6">
                    Share a bug report, suggestion, or any other feedback about the system. Our team reviews every submission.
                </p>

                <form method="POST" action="{{ route('feedback.store') }}" class="space-y-6">
                    @csrf

                    <div>
                        <label for="type" class="block text-sm font-medium text-gray-700">Type <span class="text-red-500">*</span></label>
                        <select name="type" id="type" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            @foreach($types as $type)
                                <option value="{{ $type }}" {{ old('type') === $type ? 'selected' : '' }}>{{ ucfirst($type) }}</option>
                            @endforeach
                        </select>
                        @error('type')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="subject" class="block text-sm font-medium text-gray-700">Subject <span class="text-gray-400 font-normal">(optional)</span></label>
                        <input type="text" name="subject" id="subject" maxlength="255"
                               value="{{ old('subject') }}"
                               placeholder="Brief summary"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        @error('subject')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="body" class="block text-sm font-medium text-gray-700">Message <span class="text-red-500">*</span></label>
                        <textarea name="body" id="body" rows="6" required maxlength="3000"
                                  placeholder="Describe your feedback in detail..."
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">{{ old('body') }}</textarea>
                        @error('body')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
                        <button type="submit"
                                class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 transition-colors">
                            Submit Feedback
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
