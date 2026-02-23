@extends('layouts.admin')

@section('title', 'Distributor Details')

@section('content')
    <div class="bg-white shadow overflow-hidden rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
            <h3 class="text-lg font-medium text-gray-900">{{ $distributor->name }}</h3>
            <a href="{{ route('admin.distributors.edit', $distributor) }}" class="text-indigo-600 hover:text-indigo-900 text-sm">Edit</a>
        </div>

        <div class="px-6 py-4">
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <dt class="text-sm font-medium text-gray-500">Email</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $distributor->email }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Contact Person</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $distributor->contact_person ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Phone</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $distributor->phone ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Status</dt>
                    <dd class="mt-1">
                        @if($distributor->is_active)
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Active</span>
                        @else
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">Inactive</span>
                        @endif
                    </dd>
                </div>
                <div class="md:col-span-2">
                    <dt class="text-sm font-medium text-gray-500">API Key</dt>
                    <dd class="mt-1"><code class="text-sm font-mono bg-gray-100 px-2 py-1 rounded">{{ $distributor->api_key }}</code></dd>
                </div>
            </dl>
        </div>

        <div class="px-6 py-4 border-t border-gray-200">
            <div class="flex justify-between items-center mb-4">
                <h4 class="text-lg font-medium text-gray-900">Licenses ({{ $distributor->licenses->count() }})</h4>
                <a href="{{ route('admin.licenses.create') }}?distributor_id={{ $distributor->id }}" class="text-indigo-600 hover:text-indigo-900 text-sm">Create License</a>
            </div>

            @if($distributor->licenses->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">License Key</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Plan</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tenant</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @foreach($distributor->licenses as $license)
                                <tr>
                                    <td class="px-4 py-2">
                                        <a href="{{ route('admin.licenses.show', $license) }}" class="text-indigo-600 hover:text-indigo-900 font-mono text-sm">{{ $license->license_key }}</a>
                                    </td>
                                    <td class="px-4 py-2 text-sm">{{ $license->plan->name }}</td>
                                    <td class="px-4 py-2 text-sm">{{ $license->tenant?->name ?? '-' }}</td>
                                    <td class="px-4 py-2">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                            @if($license->status === 'active') bg-green-100 text-green-800
                                            @elseif($license->status === 'pending') bg-yellow-100 text-yellow-800
                                            @else bg-red-100 text-red-800 @endif">
                                            {{ ucfirst($license->status) }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-gray-500 text-sm">No licenses yet.</p>
            @endif
        </div>
    </div>

    <div class="mt-4">
        <a href="{{ route('admin.distributors.index') }}" class="text-indigo-600 hover:text-indigo-900">&larr; Back to Distributors</a>
    </div>
@endsection
