@extends('layouts.admin')

@section('title', 'Edit Tenant')

@section('content')
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Edit Tenant</h1>
            <p class="text-slate-500 dark:text-slate-400 text-sm mt-1">Update tenant settings, licensing, and contact details</p>
        </div>
        <div>
            <a href="{{ route('admin.tenants.index') }}" class="inline-flex items-center px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg font-semibold text-xs text-slate-700 dark:text-slate-300 uppercase tracking-widest hover:bg-slate-50 dark:hover:bg-slate-800 transition">
                &larr; Back to Tenants
            </a>
        </div>
    </div>

    <div class="max-w-4xl">
        <form action="{{ route('admin.tenants.update', $tenant) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="bg-white dark:bg-[var(--bg-card)] shadow-md rounded-2xl border border-slate-100 dark:border-[var(--border-soft)] p-6 space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Tenant Name -->
                    <div>
                        <label for="name" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Tenant Name <span class="text-rose-500">*</span></label>
                        <input type="text" 
                               name="name" 
                               id="name" 
                               value="{{ old('name', $tenant->name) }}" 
                               class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 shadow-xs focus:border-indigo-500 focus:ring-indigo-500 text-sm" 
                               required>
                        @error('name')
                            <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Company Name -->
                    <div>
                        <label for="company_name" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Company Name</label>
                        <input type="text" 
                               name="company_name" 
                               id="company_name" 
                               value="{{ old('company_name', $companyName) }}" 
                               class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 shadow-xs focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                               placeholder="e.g. Acme Corporation">
                        @error('company_name')
                            <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Contact Email -->
                    <div>
                        <label for="contact_email" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Contact Email</label>
                        <input type="email" 
                               name="contact_email" 
                               id="contact_email" 
                               value="{{ old('contact_email', $contactEmail) }}" 
                               class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 shadow-xs focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                               placeholder="admin@company.com">
                        @error('contact_email')
                            <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Status -->
                    <div>
                        <label for="status" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Status <span class="text-rose-500">*</span></label>
                        @php
                            $currentStatus = old('status', $tenant->isSuspended() ? 'suspended' : ($tenant->is_active ? 'active' : 'inactive'));
                        @endphp
                        <select name="status" 
                                id="status" 
                                class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 shadow-xs focus:border-indigo-500 focus:ring-indigo-500 text-sm" 
                                required>
                            <option value="active" {{ $currentStatus === 'active' ? 'selected' : '' }}>Active</option>
                            <option value="inactive" {{ $currentStatus === 'inactive' ? 'selected' : '' }}>Inactive</option>
                            <option value="suspended" {{ $currentStatus === 'suspended' ? 'selected' : '' }}>Suspended</option>
                        </select>
                        @error('status')
                            <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Assigned Plan -->
                    <div>
                        <label for="plan_id" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Assigned Plan</label>
                        <select name="plan_id" 
                                id="plan_id" 
                                class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 shadow-xs focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                {{ !$tenant->license ? 'disabled' : '' }}>
                            @if(!$tenant->license)
                                <option value="">No license attached</option>
                            @else
                                @foreach($plans as $plan)
                                    <option value="{{ $plan->id }}" {{ old('plan_id', $tenant->license?->plan_id) == $plan->id ? 'selected' : '' }}>
                                        {{ $plan->name }}
                                    </option>
                                @endforeach
                            @endif
                        </select>
                        @if(!$tenant->license)
                            <p class="mt-1 text-xs text-slate-400">Tenant must have a license to assign a plan.</p>
                        @endif
                        @error('plan_id')
                            <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Seat Limit -->
                    <div>
                        <label for="seats" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Seat Limit (Max Users)</label>
                        <input type="number" 
                               name="seats" 
                               id="seats" 
                               value="{{ old('seats', $tenant->license?->seats) }}" 
                               min="1" 
                               class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 shadow-xs focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                               {{ !$tenant->license ? 'disabled' : '' }}>
                        @error('seats')
                            <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Distributor -->
                    <div class="md:col-span-2">
                        <label for="distributor_id" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Distributor</label>
                        <select name="distributor_id" 
                                id="distributor_id" 
                                class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 shadow-xs focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                {{ !$tenant->license ? 'disabled' : '' }}>
                            @if(!$tenant->license)
                                <option value="">No license attached</option>
                            @else
                                <option value="">None</option>
                                @foreach($distributors as $distributor)
                                    <option value="{{ $distributor->id }}" {{ old('distributor_id', $tenant->license?->distributor_id) == $distributor->id ? 'selected' : '' }}>
                                        {{ $distributor->name }}
                                    </option>
                                @endforeach
                            @endif
                        </select>
                        @error('distributor_id')
                            <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100 dark:border-[var(--border-soft)]">
                    <a href="{{ route('admin.tenants.index') }}" class="inline-flex items-center px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg font-semibold text-xs text-slate-700 dark:text-slate-300 uppercase tracking-widest hover:bg-slate-50 dark:hover:bg-slate-800 transition">
                        Cancel
                    </a>
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition">
                        Update Tenant
                    </button>
                </div>
            </div>
        </form>
    </div>
@endsection
