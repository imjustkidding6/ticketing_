@extends('layouts.admin')

@section('title', 'Chatbot Settings')

@section('content')
    <div class="mb-4">
        <p class="text-gray-600">Configure the AI provider used across all tenants.</p>
    </div>

    <div class="bg-white shadow overflow-hidden rounded-lg p-6">
        <form method="POST" action="{{ route('admin.chatbot-settings.update') }}">
            @csrf

            <div class="space-y-6">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="chatbot_provider" class="block text-sm font-medium text-gray-700">Provider</label>
                        <select name="chatbot_provider" id="chatbot_provider" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <option value="gemini" {{ ($settings['chatbot_provider'] ?? 'gemini') === 'gemini' ? 'selected' : '' }}>Gemini</option>
                        </select>
                        @error('chatbot_provider') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="chatbot_model" class="block text-sm font-medium text-gray-700">Model</label>
                        <input type="text" name="chatbot_model" id="chatbot_model" value="{{ old('chatbot_model', $settings['chatbot_model'] ?? 'gemini-1.5-flash') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        @error('chatbot_model') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label for="chatbot_api_key" class="block text-sm font-medium text-gray-700">API Key</label>
                    <input type="password" name="chatbot_api_key" id="chatbot_api_key" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="{{ ($settings['chatbot_api_key'] ?? false) ? '••••••••' : '' }}">
                    <p class="mt-1 text-xs text-gray-500">Leave blank to keep the current key.</p>
                    @error('chatbot_api_key') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="chatbot_system_prompt" class="block text-sm font-medium text-gray-700">System Prompt</label>
                    <textarea name="chatbot_system_prompt" id="chatbot_system_prompt" rows="4" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">{{ old('chatbot_system_prompt', $settings['chatbot_system_prompt'] ?? '') }}</textarea>
                    @error('chatbot_system_prompt') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="chatbot_confidence_threshold" class="block text-sm font-medium text-gray-700">Confidence Threshold</label>
                        <input type="number" step="0.01" min="0" max="1" name="chatbot_confidence_threshold" id="chatbot_confidence_threshold" value="{{ old('chatbot_confidence_threshold', $settings['chatbot_confidence_threshold'] ?? '0.6') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        <p class="mt-1 text-xs text-gray-500">Below this score the chatbot will escalate to a ticket.</p>
                        @error('chatbot_confidence_threshold') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-end">
                <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Save Settings</button>
            </div>
        </form>
    </div>
@endsection
