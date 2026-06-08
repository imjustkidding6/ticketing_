<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('AI Assistant Settings') }}</h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            <!-- Settings Tabs -->
            <div class="mb-6 flex gap-4 border-b border-gray-200">
                <a href="{{ route('settings.general') }}" class="border-b-2 border-transparent px-4 py-2 text-sm font-medium text-gray-500 hover:border-gray-300 hover:text-gray-700">{{ __('General') }}</a>
                <a href="{{ route('settings.ticket') }}" class="border-b-2 border-transparent px-4 py-2 text-sm font-medium text-gray-500 hover:border-gray-300 hover:text-gray-700">{{ __('Tickets') }}</a>
                @if(app(\App\Services\PlanService::class)->currentTenantHasFeature(\App\Enums\PlanFeature::EmailNotifications))
                <a href="{{ route('settings.notifications') }}" class="border-b-2 border-transparent px-4 py-2 text-sm font-medium text-gray-500 hover:border-gray-300 hover:text-gray-700">{{ __('Notifications') }}</a>
                @endif
                <a href="{{ route('settings.branding') }}" class="border-b-2 border-transparent px-4 py-2 text-sm font-medium text-gray-500 hover:border-gray-300 hover:text-gray-700">{{ __('Branding') }}</a>
                @if(app(\App\Services\PlanService::class)->currentTenantHasFeature(\App\Enums\PlanFeature::ServiceReports))
                <a href="{{ route('settings.service-report') }}" class="border-b-2 border-transparent px-4 py-2 text-sm font-medium text-gray-500 hover:border-gray-300 hover:text-gray-700">{{ __('Service Report') }}</a>
                @endif
                <a href="{{ route('settings.ai') }}" class="border-b-2 border-indigo-500 px-4 py-2 text-sm font-medium text-indigo-600">{{ __('AI Assistant') }}</a>
                @if(auth()->user()?->currentTenant()?->apiAccessEnabled())
                <a href="{{ route('settings.api-tokens') }}" class="border-b-2 border-transparent px-4 py-2 text-sm font-medium text-gray-500 hover:border-gray-300 hover:text-gray-700">{{ __('API') }}</a>
                @endif
            </div>

            @if(session('success'))
                <div class="mb-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
            @endif

            <form method="POST" action="{{ route('settings.ai') }}">
                @csrf

                <div class="rounded-xl bg-white p-6 shadow-sm">
                    <h3 class="text-sm font-semibold text-gray-900">{{ __('AI Assistant') }}</h3>
                    <p class="mt-1 text-xs text-gray-500">{{ __('A smart assistant that answers from your Knowledge Base, opens tickets, and helps your agents. Turn it on where you want it.') }}</p>

                    <div class="mt-4 space-y-4">
                        <label class="flex items-start gap-3">
                            <input type="checkbox" name="ai_enabled" value="1" {{ ($settings['ai_enabled'] ?? false) ? 'checked' : '' }} class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span>
                                <span class="block text-sm font-medium text-gray-700">{{ __('Enable AI Assistant') }}</span>
                                <span class="block text-xs text-gray-500">{{ __('Master switch. When off, nothing AI-related is shown or callable.') }}</span>
                            </span>
                        </label>
                        <label class="flex items-start gap-3">
                            <input type="checkbox" name="ai_portal_widget_enabled" value="1" {{ ($settings['ai_portal_widget_enabled'] ?? false) ? 'checked' : '' }} class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span>
                                <span class="block text-sm font-medium text-gray-700">{{ __('Show the chat widget on the public portal') }}</span>
                                <span class="block text-xs text-gray-500">{{ __('A floating chat bubble for your clients on the support portal.') }}</span>
                            </span>
                        </label>
                        <label class="flex items-start gap-3">
                            <input type="checkbox" name="ai_agent_copilot_enabled" value="1" {{ ($settings['ai_agent_copilot_enabled'] ?? false) ? 'checked' : '' }} class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span>
                                <span class="block text-sm font-medium text-gray-700">{{ __('Enable the agent copilot on tickets') }}</span>
                                <span class="block text-xs text-gray-500">{{ __('Adds "Draft with AI" and "Summarize" buttons for your agents.') }}</span>
                            </span>
                        </label>
                        <label class="flex items-start gap-3">
                            <input type="checkbox" name="ai_learn_from_chat" value="1" {{ ($settings['ai_learn_from_chat'] ?? false) ? 'checked' : '' }} class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span>
                                <span class="block text-sm font-medium text-gray-700">{{ __('Let agents teach the assistant from chat') }}</span>
                                <span class="block text-xs text-gray-500">{{ __('Adds a "Save to knowledge" button on assistant replies. Saved answers are reused for similar future questions. Review and remove them anytime under "Learned answers".') }}</span>
                            </span>
                        </label>
                    </div>
                </div>

                <div class="mt-4 flex items-center justify-end gap-4">
                    <a href="{{ route('settings.ai.knowledge') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">{{ __('Learned answers') }} &rarr;</a>
                    <a href="{{ route('settings.ai.conversations') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">{{ __('View saved conversation history') }} &rarr;</a>
                </div>

                <div class="mt-6 rounded-xl bg-white p-6 shadow-sm">
                    <h3 class="text-sm font-semibold text-gray-900">{{ __('Assistant Instructions') }}</h3>
                    <p class="mt-1 text-xs text-gray-500">{{ __('Optional. Give the assistant extra context about your business, tone, policies, or things it should never say. Added to its base instructions.') }}</p>
                    <textarea name="ai_system_prompt" rows="5" maxlength="2000" class="mt-3 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="{{ __('e.g. We are a hardware repair shop. Always offer the walk-in option. Never quote prices.') }}">{{ old('ai_system_prompt', $settings['ai_system_prompt'] ?? '') }}</textarea>
                    @error('ai_system_prompt') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="mt-6 flex justify-end">
                    <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">{{ __('Save Settings') }}</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
