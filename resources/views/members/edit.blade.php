<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('Edit User') }}</h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">
            @if($pivotRole === 'owner')
                <div class="rounded-xl bg-white p-6 shadow-sm">
                    <div class="rounded-md bg-yellow-50 border border-yellow-200 p-4">
                        <p class="text-sm text-yellow-700">{{ __('The owner account cannot be edited from user management.') }}</p>
                    </div>
                    <div class="mt-4">
                        <a href="{{ route('members.index') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">{{ __('Back to users') }}</a>
                    </div>
                </div>
            @else
                <div class="overflow-hidden rounded-xl bg-white p-6 shadow-sm" x-data="{ selectedRole: '{{ old('role', $currentRole ?? 'agent') }}' }">
                    <!-- User header -->
                    <div class="mb-6 flex items-center gap-3 border-b border-gray-200 pb-4">
                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-indigo-100 text-sm font-semibold text-indigo-700">
                            {{ strtoupper(substr($member->name, 0, 1)) }}
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-900">{{ $member->name }}</p>
                            <p class="text-sm text-gray-500">{{ $member->email }}</p>
                        </div>
                    </div>

                    @if($currentRole === 'admin')
                        <div class="mb-4 rounded-md bg-yellow-50 border border-yellow-200 p-3">
                            <p class="text-xs text-yellow-700">{{ __('Warning: Changing this user\'s role will revoke their admin privileges.') }}</p>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('members.update', $member) }}">
                        @csrf
                        @method('PUT')

                        <div class="space-y-6">
                            <!-- Name -->
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700">{{ __('Full Name') }}</label>
                                <input type="text" name="name" id="name" value="{{ old('name', $member->name) }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <!-- Email -->
                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700">{{ __('Email Address') }}</label>
                                <input type="email" name="email" id="email" value="{{ old('email', $member->email) }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <!-- Password -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label for="password" class="block text-sm font-medium text-gray-700">{{ __('Password') }}</label>
                                    <input type="password" name="password" id="password" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <p class="mt-1 text-xs text-gray-400">{{ __('Leave blank to keep current password.') }}</p>
                                    @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label for="password_confirmation" class="block text-sm font-medium text-gray-700">{{ __('Confirm Password') }}</label>
                                    <input type="password" name="password_confirmation" id="password_confirmation" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
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

                            <!-- Support Agent Configuration -->
                            <div x-show="selectedRole === 'agent' || selectedRole === 'manager'" x-cloak class="rounded-lg border border-gray-200 bg-gray-50 p-4 space-y-4">
                                <p class="text-sm font-semibold text-gray-700">{{ __('Support Agent Configuration') }}</p>

                                @if($departments->isNotEmpty())
                                @php $memberDeptIds = old('department_ids', $member->departments->pluck('id')->toArray()); @endphp
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">{{ __('Departments') }}</label>
                                    <div class="mt-2 space-y-2 max-h-40 overflow-y-auto rounded-md border border-gray-300 p-2">
                                        @foreach($departments as $dept)
                                            <label class="flex items-center gap-2 cursor-pointer">
                                                <input type="checkbox" name="department_ids[]" value="{{ $dept->id }}" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" {{ in_array($dept->id, $memberDeptIds) ? 'checked' : '' }}>
                                                <span class="text-sm text-gray-700">{{ $dept->name }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                    @error('department_ids') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                                @endif

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    @if(app(\App\Services\PlanService::class)->currentTenantHasFeature(\App\Enums\PlanFeature::AgentEscalation))
                                    @php
                                        $currentTier = match($member->support_tier) {
                                            'tier_1' => '1',
                                            'tier_2' => '2',
                                            'tier_3' => '3',
                                            default => '1',
                                        };
                                    @endphp
                                    <div>
                                        <label for="support_tier" class="block text-sm font-medium text-gray-700">{{ __('Support Tier') }}</label>
                                        <select name="support_tier" id="support_tier" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                            <option value="1" {{ old('support_tier', $currentTier) == '1' ? 'selected' : '' }}>{{ __('Tier 1') }}</option>
                                            <option value="2" {{ old('support_tier', $currentTier) == '2' ? 'selected' : '' }}>{{ __('Tier 2') }}</option>
                                            <option value="3" {{ old('support_tier', $currentTier) == '3' ? 'selected' : '' }}>{{ __('Tier 3') }}</option>
                                        </select>
                                        @error('support_tier') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                    </div>
                                    @endif
                                    <div>
                                        <label for="is_available" class="block text-sm font-medium text-gray-700">{{ __('Availability') }}</label>
                                        <select name="is_available" id="is_available" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                            <option value="1" {{ old('is_available', $member->is_available ? '1' : '0') == '1' ? 'selected' : '' }}>{{ __('Available') }}</option>
                                            <option value="0" {{ old('is_available', $member->is_available ? '1' : '0') == '0' ? 'selected' : '' }}>{{ __('Not available') }}</option>
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
                            <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">{{ __('Update User') }}</button>
                        </div>
                    </form>

                    <div class="mt-4 border-t border-gray-200 pt-4">
                        <form method="POST" action="{{ route('members.destroy', $member) }}" onsubmit="return confirm('{{ __('Are you sure you want to delete this user?') }}')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-sm font-medium text-red-600 hover:text-red-800">{{ __('Delete User') }}</button>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
