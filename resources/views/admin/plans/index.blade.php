@extends('layouts.admin')

@section('title', 'Plans')

@section('content')
    <div class="mb-4">
        <p class="text-gray-600">Manage subscription plans</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        @foreach($plans as $plan)
            <div class="bg-white shadow rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                    <h3 class="text-lg font-medium text-gray-900">{{ $plan->name }}</h3>
                    @if($plan->is_active)
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Active</span>
                    @else
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">Inactive</span>
                    @endif
                </div>
                <div class="px-6 py-4">
                    <p class="text-sm text-gray-500 mb-4">{{ $plan->description ?? 'No description' }}</p>

                    <dl class="space-y-2">
                        <div class="flex justify-between">
                            <dt class="text-sm text-gray-500">Max Users</dt>
                            <dd class="text-sm font-medium text-gray-900">{{ $plan->max_users ?? 'Unlimited' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm text-gray-500">Max Tickets/Month</dt>
                            <dd class="text-sm font-medium text-gray-900">{{ $plan->max_tickets_per_month ?? 'Unlimited' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm text-gray-500">Licenses</dt>
                            <dd class="text-sm font-medium text-gray-900">{{ $plan->licenses_count }}</dd>
                        </div>
                    </dl>
                </div>
                <div class="px-6 py-3 bg-gray-50">
                    <a href="{{ route('admin.plans.edit', $plan) }}" class="text-indigo-600 hover:text-indigo-900 text-sm">Edit Plan</a>
                </div>
            </div>
        @endforeach
    </div>
@endsection
