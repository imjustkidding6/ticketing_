@extends('layouts.admin')

@section('title', 'Tenant Details')

@section('content')
    <div class="bg-white shadow overflow-hidden rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
            <h3 class="text-lg font-medium text-gray-900">{{ $tenant->name }}</h3>
            <div class="flex items-center space-x-2">
                @if($tenant->isSuspended())
                    <form action="{{ route('admin.tenants.unsuspend', $tenant) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="inline-flex items-center px-3 py-1 border border-green-300 rounded-md text-sm text-green-700 hover:bg-green-50">Unsuspend</button>
                    </form>
                @else
                    <form action="{{ route('admin.tenants.suspend', $tenant) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure?')">
                        @csrf
                        <button type="submit" class="inline-flex items-center px-3 py-1 border border-red-300 rounded-md text-sm text-red-700 hover:bg-red-50">Suspend</button>
                    </form>
                @endif
            </div>
        </div>

        <div class="px-6 py-4">
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <dt class="text-sm font-medium text-gray-500">Slug</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $tenant->slug }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Status</dt>
                    <dd class="mt-1">
                        @if($tenant->isSuspended())
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Suspended</span>
                            <span class="text-sm text-gray-500 ml-2">since {{ $tenant->suspended_at->format('M d, Y') }}</span>
                        @elseif($tenant->is_active)
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Active</span>
                        @else
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">Inactive</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Created At</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $tenant->created_at->format('M d, Y H:i') }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Description</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $tenant->description ?? '-' }}</dd>
                </div>
            </dl>
        </div>

        {{-- Usage Monitoring --}}
        <div class="px-6 py-4 border-t border-gray-200">
            <h4 class="text-lg font-medium text-gray-900 mb-4">Usage Monitoring</h4>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-gray-50 rounded-lg p-4 text-center">
                    <p class="text-2xl font-bold text-gray-900">{{ $ticketStats['total'] }}</p>
                    <p class="text-sm text-gray-500">Total Tickets</p>
                </div>
                <div class="bg-gray-50 rounded-lg p-4 text-center">
                    <p class="text-2xl font-bold text-blue-600">{{ $ticketStats['open'] }}</p>
                    <p class="text-sm text-gray-500">Open Tickets</p>
                </div>
                <div class="bg-gray-50 rounded-lg p-4 text-center">
                    <p class="text-2xl font-bold text-green-600">{{ $ticketStats['closed'] }}</p>
                    <p class="text-sm text-gray-500">Closed Tickets</p>
                </div>
                <div class="bg-gray-50 rounded-lg p-4 text-center">
                    <p class="text-2xl font-bold {{ $maxTicketsPerMonth && $ticketStats['this_month'] >= $maxTicketsPerMonth ? 'text-red-600' : 'text-gray-900' }}">
                        {{ $ticketStats['this_month'] }}
                        @if($maxTicketsPerMonth)
                            <span class="text-sm font-normal text-gray-500">/ {{ $maxTicketsPerMonth }}</span>
                        @endif
                    </p>
                    <p class="text-sm text-gray-500">Tickets This Month</p>
                    @if($maxTicketsPerMonth)
                        @php
                            $usagePercent = min(100, round(($ticketStats['this_month'] / $maxTicketsPerMonth) * 100));
                        @endphp
                        <div class="mt-2 w-full bg-gray-200 rounded-full h-2">
                            <div class="h-2 rounded-full {{ $usagePercent >= 90 ? 'bg-red-500' : ($usagePercent >= 70 ? 'bg-yellow-500' : 'bg-green-500') }}" style="width: {{ $usagePercent }}%"></div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Seats Usage --}}
            @if($tenant->license)
                <div class="mt-4">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-sm font-medium text-gray-700">Seat Usage</span>
                        <span class="text-sm text-gray-500">{{ $tenant->users->count() }} / {{ $tenant->license->seats }} seats</span>
                    </div>
                    @php
                        $seatPercent = min(100, round(($tenant->users->count() / max(1, $tenant->license->seats)) * 100));
                    @endphp
                    <div class="w-full bg-gray-200 rounded-full h-2.5">
                        <div class="h-2.5 rounded-full {{ $seatPercent >= 90 ? 'bg-red-500' : ($seatPercent >= 70 ? 'bg-yellow-500' : 'bg-indigo-500') }}" style="width: {{ $seatPercent }}%"></div>
                    </div>
                    <form action="{{ route('admin.tenants.update-seats', $tenant) }}" method="POST" class="mt-3 flex items-center gap-2">
                        @csrf
                        <label for="seats" class="text-sm font-medium text-gray-700">Max Users:</label>
                        <input type="number" name="seats" id="seats" value="{{ $tenant->license->seats }}" min="1" class="w-24 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <button type="submit" class="inline-flex items-center px-3 py-1.5 border border-indigo-300 rounded-md text-sm font-medium text-indigo-700 hover:bg-indigo-50">
                            Update
                        </button>
                    </form>
                </div>
            @endif
        </div>

        @if($tenant->license)
            <div class="px-6 py-4 border-t border-gray-200">
                <h4 class="text-lg font-medium text-gray-900 mb-4">License Information</h4>
                <div class="bg-gray-50 rounded-lg p-4">
                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">License Key</dt>
                            <dd class="mt-1"><code class="text-sm font-mono bg-gray-100 px-2 py-1 rounded">{{ $tenant->license->license_key }}</code></dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Plan</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $tenant->license->plan->name }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Distributor</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                <a href="{{ route('admin.distributors.show', $tenant->license->distributor) }}" class="text-indigo-600 hover:text-indigo-900">
                                    {{ $tenant->license->distributor->name }}
                                </a>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Expires At</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                {{ $tenant->license->expires_at->format('M d, Y') }}
                                @if($tenant->license->isExpired())
                                    <span class="text-red-600">(Expired)</span>
                                @else
                                    <span class="text-gray-500">({{ $tenant->license->daysUntilExpiry() }} days remaining)</span>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">License Status</dt>
                            <dd class="mt-1">
                                @if($tenant->license->status === 'active')
                                    @if($tenant->license->isFullyExpired())
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Expired</span>
                                    @elseif($tenant->license->isInGracePeriod())
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-orange-100 text-orange-800">Grace Period</span>
                                    @else
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Active</span>
                                    @endif
                                @else
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">{{ ucfirst($tenant->license->status) }}</span>
                                @endif
                            </dd>
                        </div>
                    </dl>
                    <div class="mt-4 flex items-center justify-between">
                        <a href="{{ route('admin.licenses.show', $tenant->license) }}" class="text-indigo-600 hover:text-indigo-900 text-sm">View License Details</a>

                        <form action="{{ route('admin.tenants.change-plan', $tenant) }}" method="POST" class="flex items-center gap-2">
                            @csrf
                            <label for="plan_id" class="text-sm font-medium text-gray-700">Change Plan:</label>
                            <select name="plan_id" id="plan_id" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @foreach($plans as $plan)
                                    <option value="{{ $plan->id }}" @selected($tenant->license->plan_id === $plan->id)>
                                        {{ $plan->name }}
                                    </option>
                                @endforeach
                            </select>
                            <button type="submit" class="inline-flex items-center px-3 py-1.5 border border-indigo-300 rounded-md text-sm font-medium text-indigo-700 hover:bg-indigo-50">
                                Update
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @endif

        <div class="px-6 py-4 border-t border-gray-200">
            <h4 class="text-lg font-medium text-gray-900 mb-4">Users ({{ $tenant->users->count() }})</h4>
            @if($tenant->users->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Role</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Joined</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @foreach($tenant->users as $user)
                                <tr>
                                    <td class="px-4 py-2 text-sm text-gray-900">{{ $user->name }}</td>
                                    <td class="px-4 py-2 text-sm text-gray-500">{{ $user->email }}</td>
                                    <td class="px-4 py-2">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                            @if($user->pivot->role === 'owner') bg-purple-100 text-purple-800
                                            @elseif($user->pivot->role === 'admin') bg-blue-100 text-blue-800
                                            @else bg-gray-100 text-gray-800 @endif">
                                            {{ ucfirst($user->pivot->role) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-500">{{ $user->pivot->joined_at ? \Carbon\Carbon::parse($user->pivot->joined_at)->format('M d, Y') : '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-gray-500 text-sm">No users yet.</p>
            @endif
        </div>
    </div>

    <div class="mt-4">
        <a href="{{ route('admin.tenants.index') }}" class="text-indigo-600 hover:text-indigo-900">&larr; Back to Tenants</a>
    </div>
@endsection
