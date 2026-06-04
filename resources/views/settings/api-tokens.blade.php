<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('API Tokens') }}</h2>
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
                @if(auth()->user()?->currentTenant()?->apiAccessEnabled())
                <a href="{{ route('settings.api-tokens') }}" class="border-b-2 border-indigo-500 px-4 py-2 text-sm font-medium text-indigo-600">{{ __('API') }}</a>
                @endif
            </div>

            @if(session('plain_token'))
                <div class="mb-6 rounded-lg border border-green-200 bg-green-50 p-4">
                    <h3 class="text-sm font-semibold text-green-900">{{ __('Your new API token') }}</h3>
                    <p class="mt-1 text-xs text-green-700">{{ __('Copy this token now — it will not be shown again.') }}</p>
                    <div class="mt-3 flex items-center gap-2 rounded-md bg-white p-3 font-mono text-sm">
                        <code class="flex-1 break-all font-mono text-gray-900">{{ session('plain_token') }}</code>
                        <button type="button" onclick="navigator.clipboard.writeText('{{ session('plain_token') }}')" class="rounded-md bg-gray-100 px-2 py-1 text-xs text-gray-700 hover:bg-gray-200">{{ __('Copy') }}</button>
                    </div>
                </div>
            @endif

            <div class="rounded-xl bg-white p-6 shadow-sm">
                <div class="mb-6">
                    <h3 class="text-base font-semibold text-gray-900">{{ __('Generate API Token') }}</h3>
                    <p class="mt-1 text-sm text-gray-500">{{ __('API tokens allow external systems (chatbots, integrations) to create and read tickets via the public API.') }}</p>

                    <form method="POST" action="{{ route('settings.api-tokens.store') }}" class="mt-4 flex gap-3">
                        @csrf
                        <input type="text" name="name" placeholder="e.g. Pricing Calculator Chatbot"
                               class="flex-1 rounded-md border-gray-300 bg-white text-sm text-gray-900 placeholder-gray-400 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                               required maxlength="100">
                        <input type="number" name="expires_in_days" placeholder="Expires in (days)"
                               min="1" max="3650"
                               class="w-44 rounded-md border-gray-300 bg-white text-sm text-gray-900 placeholder-gray-400 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">{{ __('Generate') }}</button>
                    </form>
                    @error('name')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div class="border-t border-gray-100 pt-6">
                    <h3 class="text-base font-semibold text-gray-900">{{ __('Active Tokens') }}</h3>

                    @if($tokens->isEmpty())
                        <p class="mt-4 text-sm text-gray-500">{{ __('No API tokens yet. Generate one above to get started.') }}</p>
                    @else
                        <table class="mt-4 min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Name') }}</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Created') }}</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Last Used') }}</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Expires') }}</th>
                                    <th class="px-3 py-2"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($tokens as $token)
                                    <tr>
                                        <td class="px-3 py-3 text-sm font-medium text-gray-900">{{ $token->name }}</td>
                                        <td class="px-3 py-3 text-sm text-gray-500">{{ $token->created_at->format('M d, Y') }}</td>
                                        <td class="px-3 py-3 text-sm text-gray-500">{{ $token->last_used_at ? $token->last_used_at->diffForHumans() : __('Never') }}</td>
                                        <td class="px-3 py-3 text-sm text-gray-500">
                                            @if($token->expires_at)
                                                {{ $token->expires_at->format('M d, Y') }}
                                                @if($token->isExpired())<span class="ml-1 text-xs text-red-600">({{ __('Expired') }})</span>@endif
                                            @else
                                                <span class="text-gray-400">{{ __('Never') }}</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-3 text-right">
                                            <form method="POST" action="{{ route('settings.api-tokens.destroy', $token) }}" onsubmit="return confirm('{{ __('Revoke this token? Any integration using it will stop working.') }}')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-sm text-red-600 hover:text-red-700">{{ __('Revoke') }}</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>

                <div class="mt-8 border-t border-gray-100 pt-6">
                    <h3 class="text-base font-semibold text-gray-900">{{ __('API Documentation') }}</h3>
                    <p class="mt-1 text-sm text-gray-500">{{ __('Use the token in the Authorization header as a Bearer token.') }}</p>

                    <div class="mt-4 space-y-4 text-sm">
                        <div>
                            <p class="font-medium text-gray-900">{{ __('Create a ticket') }}</p>
                            <pre class="mt-1 overflow-x-auto rounded-md bg-gray-900 p-3"><code class="block whitespace-pre font-mono text-xs text-gray-100">POST {{ url('/api/v1/tickets') }}
Authorization: Bearer tk_...
Content-Type: application/json

{
  "subject": "Issue with pricing",
  "description": "Customer reported...",
  "client_name": "John Doe",
  "client_email": "john@example.com"
}</code></pre>
                        </div>

                        <div>
                            <p class="font-medium text-gray-900">{{ __('Get a ticket') }}</p>
                            <pre class="mt-1 overflow-x-auto rounded-md bg-gray-900 p-3"><code class="block whitespace-pre font-mono text-xs text-gray-100">GET {{ url('/api/v1/tickets/TKT-XXXXXX-XXXXXXX') }}
Authorization: Bearer tk_...</code></pre>
                        </div>

                        <div>
                            <p class="font-medium text-gray-900">{{ __('List tickets') }}</p>
                            <pre class="mt-1 overflow-x-auto rounded-md bg-gray-900 p-3"><code class="block whitespace-pre font-mono text-xs text-gray-100">GET {{ url('/api/v1/tickets?status=open&priority=high') }}
Authorization: Bearer tk_...</code></pre>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
