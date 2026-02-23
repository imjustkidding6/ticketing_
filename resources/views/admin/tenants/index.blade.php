@extends('layouts.admin')

@section('title', 'Tenants')

@section('content')
    <div class="mb-4">
        <p class="text-gray-600">View and manage all registered tenants</p>
    </div>

    <div class="bg-white shadow overflow-hidden rounded-lg">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Plan</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Users</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($tenants as $tenant)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900">{{ $tenant->name }}</div>
                            <div class="text-sm text-gray-500">{{ $tenant->slug }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $tenant->license?->plan?->name ?? 'No license' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $tenant->users_count }} / {{ $tenant->license?->seats ?? '-' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($tenant->isSuspended())
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Suspended</span>
                            @elseif($tenant->is_active)
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Active</span>
                            @else
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">Inactive</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $tenant->created_at->format('M d, Y') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                            <a href="{{ route('admin.tenants.show', $tenant) }}" class="text-indigo-600 hover:text-indigo-900">View</a>
                            @if($tenant->isSuspended())
                                <form action="{{ route('admin.tenants.unsuspend', $tenant) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" class="text-green-600 hover:text-green-900">Unsuspend</button>
                                </form>
                            @else
                                <form action="{{ route('admin.tenants.suspend', $tenant) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to suspend this tenant?')">
                                    @csrf
                                    <button type="submit" class="text-red-600 hover:text-red-900">Suspend</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-4 text-center text-gray-500">No tenants found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $tenants->links() }}
    </div>
@endsection
