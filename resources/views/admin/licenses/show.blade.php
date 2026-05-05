@extends('layouts.admin')

@section('title', 'License Details')

@section('content')
    <div class="bg-white shadow overflow-hidden rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-medium text-gray-900">License Information</h3>
                <div class="space-x-2" x-data="{ showReactivate: false }">
                    <a href="{{ route('admin.licenses.edit', $license) }}" class="inline-flex items-center px-3 py-1 border border-gray-300 rounded-md text-sm text-gray-700 hover:bg-gray-50">Edit</a>
                    @if($license->status !== 'revoked' && !$license->isFullyExpired())
                        <form action="{{ route('admin.licenses.revoke', $license) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure?')">
                            @csrf
                            <button type="submit" class="inline-flex items-center px-3 py-1 border border-red-300 rounded-md text-sm text-red-700 hover:bg-red-50">Revoke</button>
                        </form>
                    @endif

                    @if($license->status === 'pending' && $license->tenant_id === null)
                        <form action="{{ route('admin.licenses.destroy', $license) }}" method="POST" class="inline" onsubmit="return confirm('Permanently delete this unactivated license? This cannot be undone.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="inline-flex items-center px-3 py-1 border border-red-600 bg-red-600 rounded-md text-sm text-white hover:bg-red-700">Delete</button>
                        </form>
                    @endif

                    @if($license->status !== 'pending')
                        @if($license->isFullyExpired() || $license->status === 'revoked' || $license->isInGracePeriod())
                            <button type="button" @click="showReactivate = true" style="background-color:#4f46e5;color:#ffffff;" class="inline-flex items-center px-3 py-1 rounded-md text-sm font-semibold shadow-sm hover:opacity-90">
                                Reactivate
                            </button>
                        @endif
                    @endif

                    {{-- Reactivate modal --}}
                    <div x-show="showReactivate" x-cloak
                         x-transition.opacity
                         class="fixed inset-0 z-50 flex items-center justify-center p-4"
                         style="background-color: rgba(17,24,39,0.5);"
                         @keydown.escape.window="showReactivate = false"
                         @click.self="showReactivate = false">
                        <div class="w-full max-w-md rounded-xl bg-white shadow-xl" @click.stop>
                            <form method="POST" action="{{ route('admin.licenses.reactivate', $license) }}">
                                @csrf
                                <div class="p-6">
                                    <h3 class="text-base font-semibold text-gray-900">Reactivate License</h3>
                                    <p class="mt-1 text-sm text-gray-500">Set a new expiration date. The tenant's access will be restored immediately, and the action will be recorded in the activity log.</p>

                                    <div class="mt-5 space-y-4">
                                        <div>
                                            <label for="expires_at" class="block text-xs font-medium text-gray-700">New Expiration Date <span class="text-red-500">*</span></label>
                                            <input type="date" name="expires_at" id="expires_at" required
                                                value="{{ now()->addYear()->format('Y-m-d') }}"
                                                min="{{ now()->addDay()->format('Y-m-d') }}"
                                                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        </div>

                                        <div>
                                            <label for="grace_days_react" class="block text-xs font-medium text-gray-700">Grace Period (days)</label>
                                            <input type="number" name="grace_days" id="grace_days_react" min="0" max="90"
                                                value="{{ $license->grace_days }}"
                                                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        </div>

                                        <div>
                                            <label for="note" class="block text-xs font-medium text-gray-700">Note (optional)</label>
                                            <textarea name="note" id="note" rows="2" maxlength="500"
                                                placeholder="e.g. Annual renewal — invoice #1234"
                                                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center justify-end gap-2 border-t border-gray-100 bg-gray-50 px-6 py-3">
                                    <button type="button" @click="showReactivate = false" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">Cancel</button>
                                    <button type="submit" style="background-color:#4f46e5;color:#ffffff;" class="rounded-md px-3 py-1.5 text-sm font-semibold shadow-sm hover:opacity-90">Reactivate License</button>
                                </div>
                            </form>
                        </div>
                    </div>
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
                    <dd class="mt-1 text-sm text-gray-900">@localdt($license->issued_at, 'M d, Y H:i')</dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-gray-500">Activated At</dt>
                    <dd class="mt-1 text-sm text-gray-900">
                        {{ $license->activated_at ? \App\Support\TenantTime::format($license->activated_at, 'M d, Y H:i') : 'Not activated' }}
                    </dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-gray-500">Expires At</dt>
                    <dd class="mt-1 text-sm text-gray-900">
                        @localdt($license->expires_at, 'M d, Y')
                        @if(!$license->isExpired())
                            <span class="text-gray-500">({{ $license->daysUntilExpiry() }} days remaining)</span>
                        @endif
                    </dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-gray-500">Grace Period</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $license->grace_days }} days</dd>
                </div>

                @if($license->reactivated_at)
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Last Reactivated</dt>
                        <dd class="mt-1 text-sm text-gray-900">@localdt($license->reactivated_at, 'M d, Y H:i')</dd>
                    </div>
                @endif

                @if($license->revoked_at)
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Revoked At</dt>
                        <dd class="mt-1 text-sm text-gray-900">@localdt($license->revoked_at, 'M d, Y H:i')</dd>
                    </div>
                @endif
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

    @php
        $licenseLogs = \App\Models\ActivityLog::query()
            ->withoutGlobalScopes()
            ->where('subject_type', $license->getMorphClass())
            ->where('subject_id', $license->id)
            ->latest()
            ->limit(20)
            ->get();
    @endphp

    @if($licenseLogs->isNotEmpty())
        <div class="bg-white shadow rounded-lg mt-6 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Activity Log</h3>
            </div>
            <ul class="divide-y divide-gray-100">
                @foreach($licenseLogs as $log)
                    <li class="px-6 py-3">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm text-gray-900">{{ $log->description ?? $log->action }}</p>
                                <p class="mt-0.5 text-xs text-gray-400">
                                    {{ $log->action }} · {{ $log->created_at->format('M d, Y H:i') }}
                                </p>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="mt-4">
        <a href="{{ route('admin.licenses.index') }}" class="text-indigo-600 hover:text-indigo-900">&larr; Back to Licenses</a>
    </div>
@endsection
