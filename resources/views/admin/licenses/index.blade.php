@extends('layouts.admin')

@section('title', 'Licenses')

@section('content')
    <!-- Desktop Header (640px and above) -->
    <div class="mb-4 hidden sm:flex justify-between items-center">
        <p class="text-gray-600">Manage all licenses</p>
        <a href="{{ route('admin.licenses.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
            Create License
        </a>
    </div>

    <!-- Mobile Header (Below 640px) -->
    <div class="mb-6 block sm:hidden space-y-3">
        <p class="text-slate-500 dark:text-slate-400 text-sm">Manage all licenses</p>
        <a href="{{ route('admin.licenses.create') }}" class="w-full inline-flex items-center justify-center px-4 py-2.5 bg-indigo-600 border border-transparent rounded-xl font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 transition duration-150">
            Create License
        </a>
    </div>

    <!-- Desktop Table View (640px and above) -->
    <div class="hidden sm:block bg-white shadow overflow-hidden rounded-lg">
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
                            @if($license->expires_at)
                                @localdt($license->expires_at, 'M d, Y')
                            @else
                                <span class="text-gray-400">{{ $license->duration_days }}d from activation</span>
                            @endif
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
                            @if($license->status === 'pending' && $license->tenant_id === null)
                                <form action="{{ route('admin.licenses.destroy', $license) }}" method="POST" class="inline" onsubmit="return confirm('Permanently delete this unactivated license? This cannot be undone.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-900 font-semibold">Delete</button>
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

    <!-- Mobile Stacked Cards View (Below 640px) -->
    <div class="block sm:hidden space-y-4">
        @forelse($licenses as $license)
            <div class="bg-white dark:bg-[var(--bg-card)] rounded-2xl border border-slate-100 dark:border-[var(--border-soft)] p-5 shadow-sm space-y-4">
                <!-- LICENSE KEY -->
                <div>
                    <span class="block uppercase tracking-wider text-xs text-slate-400 dark:text-slate-500 font-semibold">LICENSE KEY</span>
                    <code class="text-base font-mono font-bold text-slate-900 dark:text-[var(--text-primary)] bg-slate-100 dark:bg-[var(--bg-app)] px-2.5 py-1 rounded-lg mt-1 inline-block">{{ $license->license_key }}</code>
                </div>

                <!-- DISTRIBUTOR -->
                <div>
                    <span class="block uppercase tracking-wider text-xs text-slate-400 dark:text-slate-500 font-semibold">DISTRIBUTOR</span>
                    <span class="text-base font-semibold text-slate-900 dark:text-[var(--text-primary)] mt-0.5 block">{{ $license->distributor->name }}</span>
                </div>

                <!-- PLAN -->
                <div>
                    <span class="block uppercase tracking-wider text-xs text-slate-400 dark:text-slate-500 font-semibold">PLAN</span>
                    <span class="text-base font-semibold text-slate-900 dark:text-[var(--text-primary)] mt-0.5 block">{{ $license->plan->name }}</span>
                </div>

                <!-- TENANT -->
                <div>
                    <span class="block uppercase tracking-wider text-xs text-slate-400 dark:text-slate-500 font-semibold">TENANT</span>
                    @if($license->tenant)
                        <a href="{{ route('admin.tenants.show', $license->tenant) }}" class="text-base font-semibold text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 mt-0.5 block">
                            {{ $license->tenant->name }}
                        </a>
                    @else
                        <span class="text-base font-semibold text-slate-400 dark:text-slate-500 mt-0.5 block">No Tenant Assigned</span>
                    @endif
                </div>

                <!-- STATUS -->
                <div>
                    <span class="block uppercase tracking-wider text-xs text-slate-400 dark:text-slate-500 font-semibold">STATUS</span>
                    <div class="mt-1">
                        @if($license->status === 'pending')
                            <span class="px-2.5 py-0.5 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">Pending</span>
                        @elseif($license->status === 'active')
                            @if($license->isFullyExpired())
                                <span class="px-2.5 py-0.5 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Expired</span>
                            @elseif($license->isInGracePeriod())
                                <span class="px-2.5 py-0.5 inline-flex text-xs leading-5 font-semibold rounded-full bg-orange-100 text-orange-800">Grace Period</span>
                            @else
                                <span class="px-2.5 py-0.5 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Active</span>
                            @endif
                        @elseif($license->status === 'revoked')
                            <span class="px-2.5 py-0.5 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Revoked</span>
                        @endif
                    </div>
                </div>

                <!-- EXPIRES -->
                <div>
                    <span class="block uppercase tracking-wider text-xs text-slate-400 dark:text-slate-500 font-semibold">EXPIRES</span>
                    <span class="text-base font-semibold text-slate-900 dark:text-[var(--text-primary)] mt-0.5 block">
                        @if($license->expires_at)
                            @localdt($license->expires_at, 'M d, Y')
                        @else
                            <span class="text-slate-400">{{ $license->duration_days }}d from activation</span>
                        @endif
                    </span>
                </div>

                <!-- ACTION BUTTONS -->
                <div class="pt-3 border-t border-slate-100 dark:border-[var(--border-soft)] space-y-2">
                    <div class="grid grid-cols-2 gap-3">
                        <a href="{{ route('admin.licenses.show', $license) }}" class="inline-flex items-center justify-center h-10 px-4 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl transition duration-150 shadow-xs">
                            View
                        </a>
                        <a href="{{ route('admin.licenses.edit', $license) }}" class="inline-flex items-center justify-center h-10 px-4 border border-slate-200 dark:border-[var(--border-soft)] bg-white dark:bg-[var(--bg-card)] text-slate-700 dark:text-[var(--text-primary)] hover:bg-slate-50 text-xs font-semibold rounded-xl transition duration-150">
                            Edit
                        </a>
                    </div>
                    @if($license->status !== 'revoked')
                        <form action="{{ route('admin.licenses.revoke', $license) }}" method="POST" class="w-full" onsubmit="return confirm('Are you sure you want to revoke this license?')">
                            @csrf
                            <button type="submit" class="w-full inline-flex items-center justify-center h-10 px-4 border border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100 text-xs font-semibold rounded-xl transition duration-150">
                                Revoke License
                            </button>
                        </form>
                    @endif
                    @if($license->status === 'pending' && $license->tenant_id === null)
                        <form action="{{ route('admin.licenses.destroy', $license) }}" method="POST" class="w-full" onsubmit="return confirm('Permanently delete this unactivated license? This cannot be undone.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="w-full inline-flex items-center justify-center h-10 px-4 border border-red-300 bg-red-600 text-white hover:bg-red-700 text-xs font-semibold rounded-xl transition duration-150">
                                Delete License
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        @empty
            <div class="bg-white dark:bg-[var(--bg-card)] rounded-2xl border border-slate-100 dark:border-[var(--border-soft)] p-8 shadow-sm flex flex-col items-center justify-center text-center py-16 space-y-3">
                <div class="w-12 h-12 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-400 flex items-center justify-center text-xl">🔑</div>
                <h4 class="text-base font-semibold text-slate-900 dark:text-[var(--text-primary)]">No licenses found</h4>
                <p class="text-xs text-slate-500 dark:text-[var(--text-secondary)]">Create your first license to get started.</p>
            </div>
        @endforelse
    </div>

    <!-- Footer -->
    <div class="mt-4 text-center">
        <p class="text-xs text-slate-500 dark:text-slate-400">
            Showing {{ $licenses->firstItem() ?? 0 }} to {{ $licenses->lastItem() ?? 0 }} of {{ $licenses->total() }} licenses
        </p>
    </div>

    <div class="mt-4">
        {{ $licenses->links() }}
    </div>
@endsection
