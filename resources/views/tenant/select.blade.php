<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Select Organization') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            @if($tenants->isEmpty())
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 text-center">
                        <p class="text-lg font-medium">{{ __('No organizations available') }}</p>
                        <p class="mt-2 text-gray-600">{{ __('You are not a member of any active organization. Please contact your administrator.') }}</p>
                    </div>
                </div>
            @else
                <p class="mb-6 text-gray-600">{{ __('Choose an organization to continue:') }}</p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach($tenants as $tenant)
                        <form method="POST" action="{{ route('tenant.switch') }}">
                            @csrf
                            <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
                            <button type="submit" class="w-full text-left bg-white overflow-hidden shadow-sm sm:rounded-lg hover:ring-2 hover:ring-indigo-500 transition-all">
                                <div class="p-6">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <h3 class="text-lg font-semibold text-gray-900">{{ $tenant->name }}</h3>
                                            <p class="text-sm text-gray-500">{{ $tenant->slug }}</p>
                                        </div>
                                        <div class="flex flex-col items-end gap-1">
                                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium
                                                @if($tenant->pivot->role === 'owner') bg-indigo-100 text-indigo-800
                                                @elseif($tenant->pivot->role === 'admin') bg-blue-100 text-blue-800
                                                @else bg-gray-100 text-gray-800
                                                @endif">
                                                {{ ucfirst($tenant->pivot->role) }}
                                            </span>
                                            @if($tenant->license?->plan)
                                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-emerald-100 text-emerald-800">
                                                    {{ $tenant->license->plan->name }}
                                                </span>
                                            @else
                                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-red-100 text-red-800">
                                                    {{ __('No Subscription') }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                    @if($tenant->description)
                                        <p class="mt-2 text-sm text-gray-600">{{ Str::limit($tenant->description, 100) }}</p>
                                    @endif
                                </div>
                            </button>
                        </form>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
