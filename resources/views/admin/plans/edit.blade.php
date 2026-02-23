@extends('layouts.admin')

@section('title', 'Edit Plan')

@section('content')
    <div class="max-w-2xl">
        <form action="{{ route('admin.plans.update', $plan) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="bg-white shadow rounded-lg p-6 space-y-6">
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700">Name</label>
                    <input type="text" name="name" id="name" value="{{ old('name', $plan->name) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                    @error('name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="slug" class="block text-sm font-medium text-gray-700">Slug</label>
                    <input type="text" name="slug" id="slug" value="{{ old('slug', $plan->slug) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                    @error('slug')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                    <textarea name="description" id="description" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('description', $plan->description) }}</textarea>
                </div>

                <div>
                    <label for="max_users" class="block text-sm font-medium text-gray-700">Max Users</label>
                    <input type="number" name="max_users" id="max_users" min="1" value="{{ old('max_users', $plan->max_users) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <p class="mt-1 text-sm text-gray-500">Leave empty for unlimited.</p>
                </div>

                <div>
                    <label for="max_tickets_per_month" class="block text-sm font-medium text-gray-700">Max Tickets per Month</label>
                    <input type="number" name="max_tickets_per_month" id="max_tickets_per_month" min="1" value="{{ old('max_tickets_per_month', $plan->max_tickets_per_month) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <p class="mt-1 text-sm text-gray-500">Leave empty for unlimited.</p>
                </div>

                <div class="flex items-center">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', $plan->is_active) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    <label for="is_active" class="ml-2 text-sm text-gray-700">Active</label>
                </div>

                <div class="flex justify-end space-x-3">
                    <a href="{{ route('admin.plans.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50">
                        Cancel
                    </a>
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                        Update Plan
                    </button>
                </div>
            </div>
        </form>
    </div>
@endsection
