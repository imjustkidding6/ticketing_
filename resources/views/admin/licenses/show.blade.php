@extends('layouts.admin')

@section('title', 'License Details')

@section('content')
    <div class="bg-white shadow overflow-hidden rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-medium text-gray-900">License Information</h3>
                <div class="space-x-2">
                    <a href="{{ route('admin.licenses.edit', $license) }}" class="inline-flex items-center px-3 py-1 border border-gray-300 rounded-md text-sm text-gray-700 hover:bg-gray-50">Edit</a>
                    @if($license->status !== 'revoked')
                        <form action="{{ route('admin.licenses.revoke', $license) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure?')">
                            @csrf
                            <button type="submit" class="inline-flex items-center px-3 py-1 border border-red-300 rounded-md text-sm text-red-700 hover:bg-red-50">Revoke</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        <div class="px-6 py-4">
            <div class="bg-gray-100 rounded-lg p-4 mb-6">
                <p class="text-sm text-gray-500 mb-1">License Key</p>
                <code class="text-2xl font-mono font-bold text-gray-900">{{ $license->license_key }}</code>
            </div>

            <dl class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <dt class="text-sm font-medium text-gray-500">Status</dt>
                    <dd class="mt-1">
                        @if($license->status === 'pending')
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">Pending Activation</span>
                        @elseif($license->status === 'active')
                            @if($license->isFullyExpired())
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Expired</span>
                            @elseif($license->isInGracePeriod())
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-orange-100 text-orange-800">Grace Period ({{ $license->daysUntilFullExpiry() }} days left)</span>
                            @else
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Active</span>
                            @endif
                        @elseif($license->status === 'revoked')
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Revoked</span>
                        @endif
                    </dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-gray-500">Plan</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $license->plan->name }}</dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-gray-500">Distributor</dt>
                    <dd class="mt-1 text-sm text-gray-900">
                        <a href="{{ route('admin.distributors.show', $license->distributor) }}" class="text-indigo-600 hover:text-indigo-900">
                            {{ $license->distributor->name }}
                        </a>
                    </dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-gray-500">Seats</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $license->seats }} users</dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-gray-500">Issued At</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $license->issued_at->format('M d, Y H:i') }}</dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-gray-500">Activated At</dt>
                    <dd class="mt-1 text-sm text-gray-900">
                        {{ $license->activated_at ? $license->activated_at->format('M d, Y H:i') : 'Not activated' }}
                    </dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-gray-500">Expires At</dt>
                    <dd class="mt-1 text-sm text-gray-900">
                        {{ $license->expires_at->format('M d, Y') }}
                        @if(!$license->isExpired())
                            <span class="text-gray-500">({{ $license->daysUntilExpiry() }} days remaining)</span>
                        @endif
                    </dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-gray-500">Grace Period</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $license->grace_days }} days</dd>
                </div>
            </dl>
        </div>

        @if($license->tenant)
            <div class="px-6 py-4 border-t border-gray-200">
                <h4 class="text-lg font-medium text-gray-900 mb-4">Linked Tenant</h4>
                <div class="bg-gray-50 rounded-lg p-4">
                    <div class="flex justify-between items-center">
                        <div>
                            <p class="font-medium text-gray-900">{{ $license->tenant->name }}</p>
                            <p class="text-sm text-gray-500">{{ $license->tenant->users->count() }} / {{ $license->seats }} users</p>
                        </div>
                        <a href="{{ route('admin.tenants.show', $license->tenant) }}" class="text-indigo-600 hover:text-indigo-900 text-sm">View Tenant</a>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <div class="mt-4">
        <a href="{{ route('admin.licenses.index') }}" class="text-indigo-600 hover:text-indigo-900">&larr; Back to Licenses</a>
    </div>
@endsection
