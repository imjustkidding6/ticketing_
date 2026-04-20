<x-guest-layout>
    <h2 class="text-lg font-semibold text-gray-900 mb-4">{{ __('Select Organization') }}</h2>

    @if($tenants->isEmpty())
        <p class="text-gray-600 text-center">{{ __('No organizations available. Please contact your administrator.') }}</p>
    @else
        <p class="mb-4 text-sm text-gray-600">{{ __('Choose an organization to continue:') }}</p>

        <div class="space-y-3">
            @foreach($tenants as $tenant)
                <form method="POST" action="{{ route('tenant.switch') }}">
                    @csrf
                    <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
                    <button type="submit" class="w-full text-left rounded-lg border border-gray-200 p-4 hover:ring-2 hover:ring-indigo-500 hover:border-indigo-500 transition-all">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-sm font-semibold text-gray-900">{{ $tenant->name }}</h3>
                                <p class="text-xs text-gray-500">{{ url('/' . $tenant->slug) }}</p>
                            </div>
                            <div class="flex flex-col items-end gap-1">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                    @if($tenant->pivot->role === 'owner') bg-indigo-100 text-indigo-800
                                    @elseif($tenant->pivot->role === 'admin') bg-blue-100 text-blue-800
                                    @else bg-gray-100 text-gray-800
                                    @endif">
                                    {{ ucfirst($tenant->pivot->role) }}
                                </span>
                                @if($tenant->license?->plan)
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-emerald-100 text-emerald-800">
                                        {{ $tenant->license->plan->name }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </button>
                </form>
            @endforeach
        </div>
    @endif

    <div class="mt-4 text-center">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="text-sm text-gray-600 hover:text-gray-900 underline">{{ __('Log Out') }}</button>
        </form>
    </div>
</x-guest-layout>
