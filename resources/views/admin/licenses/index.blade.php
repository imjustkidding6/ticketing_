@extends('layouts.admin')

@section('title', 'Licenses')

@section('content')
    <div class="mb-4 flex justify-between items-center">
        <p class="text-gray-600">Manage all licenses</p>
        <a href="{{ route('admin.licenses.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
            Create License
        </a>
    </div>

    <div class="bg-white shadow overflow-hidden rounded-lg">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">License Key</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Distributor</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Plan</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tenant</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expires</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($licenses as $license)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <code class="text-sm font-mono bg-gray-100 px-2 py-1 rounded">{{ $license->license_key }}</code>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            {{ $license->distributor->name }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            {{ $license->plan->name }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            @if($license->tenant)
                                <a href="{{ route('admin.tenants.show', $license->tenant) }}" class="text-indigo-600 hover:text-indigo-900">
                                    {{ $license->tenant->name }}
                                </a>
                            @else
                                <span class="text-gray-400">Not activated</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($license->status === 'pending')
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">Pending</span>
                            @elseif($license->status === 'active')
                                @if($license->isFullyExpired())
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Expired</span>
                                @elseif($license->isInGracePeriod())
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-orange-100 text-orange-800">Grace Period</span>
                                @else
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Active</span>
                                @endif
                            @elseif($license->status === 'revoked')
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Revoked</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $license->expires_at->format('M d, Y') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                            <a href="{{ route('admin.licenses.show', $license) }}" class="text-indigo-600 hover:text-indigo-900">View</a>
                            <a href="{{ route('admin.licenses.edit', $license) }}" class="text-gray-600 hover:text-gray-900">Edit</a>
                            @if($license->status !== 'revoked')
                                <form action="{{ route('admin.licenses.revoke', $license) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to revoke this license?')">
                                    @csrf
                                    <button type="submit" class="text-red-600 hover:text-red-900">Revoke</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-4 text-center text-gray-500">No licenses found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $licenses->links() }}
    </div>
@endsection
