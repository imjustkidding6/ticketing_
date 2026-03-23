<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('Add User') }}</h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">
            @if(!$canAdd)
                <div class="mb-4 rounded-md bg-yellow-50 border border-yellow-200 p-4">
                    <div class="flex">
                        <svg class="h-5 w-5 text-yellow-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                        </svg>
                        <div class="ml-3">
                            <p class="text-sm text-yellow-700">{{ __('No available seats. Upgrade your plan to add more users.') }}</p>
                        </div>
                    </div>
                </div>
            @endif

            <div class="overflow-hidden rounded-xl bg-white p-6 shadow-sm" x-data="{ selectedRole: '{{ old('role', 'agent') }}' }">
                @if($canAdd)
                    <p class="mb-4 text-sm text-gray-500">{{ $availableSlots }} {{ __('seat(s) available') }}</p>
                @endif

                <form method="POST" action="{{ route('members.store') }}">
                    @csrf

                    <div class="space-y-6">
                        <!-- Name -->
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700">{{ __('Full Name') }}</label>
                            <input type="text" name="name" id="name" value="{{ old('name') }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <!-- Email -->
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700">{{ __('Email Address') }}</label>
                            <input type="email" name="email" id="email" value="{{ old('email') }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <p class="mt-1 text-xs text-gray-400">{{ __('If a user with this email already exists, they will be added to your organization.') }}</p>
                            @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <!-- Password -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="password" class="block text-sm font-medium text-gray-700">{{ __('Password') }}</label>
                                <input type="password" name="password" id="password" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="password_confirmation" class="block text-sm font-medium text-gray-700">{{ __('Confirm Password') }}</label>
                                <input type="password" name="password_confirmation" id="password_confirmation" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            </div>
                        </div>

                        <!-- Role Selection -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-3">{{ __('Role') }}</label>
                            <div class="space-y-3">
                                <label class="flex items-start gap-3 rounded-lg border border-gray-200 p-4 cursor-pointer hover:bg-gray-50" :class="selectedRole === 'admin' && 'ring-2 ring-indigo-500 border-indigo-500'">
                                    <input type="radio" name="role" value="admin" x-model="selectedRole" class="mt-0.5 h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">{{ __('Admin') }}</p>
                                        <p class="text-sm text-gray-500">{{ __('Full system access and user management.') }}</p>
                                    </div>
                                </label>
                                <label class="flex items-start gap-3 rounded-lg border border-gray-200 p-4 cursor-pointer hover:bg-gray-50" :class="selectedRole === 'manager' && 'ring-2 ring-indigo-500 border-indigo-500'">
                                    <input type="radio" name="role" value="manager" x-model="selectedRole" class="mt-0.5 h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">{{ __('Manager') }}</p>
                                        <p class="text-sm text-gray-500">{{ __('Manage tickets, clients, and view reports. Can assign tickets.') }}</p>
                                    </div>
                                </label>
                                <label class="flex items-start gap-3 rounded-lg border border-gray-200 p-4 cursor-pointer hover:bg-gray-50" :class="selectedRole === 'agent' && 'ring-2 ring-indigo-500 border-indigo-500'">
                                    <input type="radio" name="role" value="agent" x-model="selectedRole" class="mt-0.5 h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">{{ __('Agent') }}</p>
                                        <p class="text-sm text-gray-500">{{ __('Handle tickets and respond to clients.') }}</p>
                                    </div>
                                </label>
                            </div>
                            @error('role') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <!-- Support Agent Configuration (shown for agent/manager) -->
                        <div x-show="selectedRole === 'agent' || selectedRole === 'manager'" x-cloak class="rounded-lg border border-gray-200 bg-gray-50 p-4 space-y-4">
                            <p class="text-sm font-semibold text-gray-700">{{ __('Support Agent Configuration') }}</p>

                            @if($departments->isNotEmpty())
                            <div>
                                <label class="block text-sm font-medium text-gray-700">{{ __('Departments') }}</label>
                                <div class="mt-2 space-y-2 max-h-40 overflow-y-auto rounded-md border border-gray-300 p-2">
                                    @foreach($departments as $dept)
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input type="checkbox" name="department_ids[]" value="{{ $dept->id }}" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" {{ in_array($dept->id, old('department_ids', [])) ? 'checked' : '' }}>
                                            <span class="text-sm text-gray-700">{{ $dept->name }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                @error('department_ids') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            @endif

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                @if(app(\App\Services\PlanService::class)->currentTenantHasFeature(\App\Enums\PlanFeature::AgentEscalation))
                                <div>
                                    <label for="support_tier" class="block text-sm font-medium text-gray-700">{{ __('Support Tier') }}</label>
                                    <select name="support_tier" id="support_tier" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        <option value="1" {{ old('support_tier', '1') == '1' ? 'selected' : '' }}>{{ __('Tier 1') }}</option>
                                        <option value="2" {{ old('support_tier') == '2' ? 'selected' : '' }}>{{ __('Tier 2') }}</option>
                                        <option value="3" {{ old('support_tier') == '3' ? 'selected' : '' }}>{{ __('Tier 3') }}</option>
                                    </select>
                                    @error('support_tier') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                                @endif
                                <div>
                                    <label for="is_available" class="block text-sm font-medium text-gray-700">{{ __('Availability') }}</label>
                                    <select name="is_available" id="is_available" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        <option value="1" {{ old('is_available', '1') == '1' ? 'selected' : '' }}>{{ __('Available') }}</option>
                                        <option value="0" {{ old('is_available') == '0' ? 'selected' : '' }}>{{ __('Not available') }}</option>
                                    </select>
                                    @error('is_available') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                            </div>

                            @if(app(\App\Services\PlanService::class)->currentTenantHasFeature(\App\Enums\PlanFeature::AgentEscalation))
                            <div class="rounded-md bg-blue-50 p-3 text-xs text-blue-700">
                                <p class="font-medium">{{ __('Support Tier Info:') }}</p>
                                <ul class="mt-1 list-disc pl-4 space-y-0.5">
                                    <li>{{ __('Tier 1 — Low & Medium priority tickets') }}</li>
                                    <li>{{ __('Tier 2 — Includes High priority tickets') }}</li>
                                    <li>{{ __('Tier 3 — All priorities including Critical & escalations') }}</li>
                                </ul>
                            </div>
                            @endif
                        </div>
                    </div>

                    <div class="mt-6 flex items-center justify-end gap-3">
                        <a href="{{ route('members.index') }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">{{ __('Cancel') }}</a>
                        <button type="submit" {{ !$canAdd ? 'disabled' : '' }} class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed">{{ __('Add User') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
