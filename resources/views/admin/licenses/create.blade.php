@extends('layouts.admin')

@section('title', 'Create License')

@section('content')
    <div class="max-w-2xl">
        <form action="{{ route('admin.licenses.store') }}" method="POST">
            @csrf

            <div class="bg-white shadow rounded-lg p-6 space-y-6">
                <div>
                    <label for="distributor_id" class="block text-sm font-medium text-gray-700">Distributor</label>
                    <select name="distributor_id" id="distributor_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                        <option value="">Select Distributor</option>
                        @foreach($distributors as $distributor)
                            <option value="{{ $distributor->id }}" {{ old('distributor_id') == $distributor->id ? 'selected' : '' }}>
                                {{ $distributor->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('distributor_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="plan_id" class="block text-sm font-medium text-gray-700">Plan</label>
                    <select name="plan_id" id="plan_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                        <option value="">Select Plan</option>
                        @foreach($plans as $plan)
                            <option value="{{ $plan->id }}" {{ old('plan_id') == $plan->id ? 'selected' : '' }}>
                                {{ $plan->name }} ({{ $plan->max_users ? $plan->max_users . ' users' : 'Unlimited' }})
                            </option>
                        @endforeach
                    </select>
                    @error('plan_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="seats" class="block text-sm font-medium text-gray-700">Seats (Max Users)</label>
                    <input type="number" name="seats" id="seats" min="1" value="{{ old('seats', 10) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                    @error('seats')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="expires_at" class="block text-sm font-medium text-gray-700">Expires At</label>
                    <input type="date" name="expires_at" id="expires_at" value="{{ old('expires_at', now()->addYear()->format('Y-m-d')) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                    @error('expires_at')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="grace_days" class="block text-sm font-medium text-gray-700">Grace Period (Days)</label>
                    <input type="number" name="grace_days" id="grace_days" min="0" max="90" value="{{ old('grace_days', 7) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                    <p class="mt-1 text-sm text-gray-500">Days of full access after expiration before suspension.</p>
                    @error('grace_days')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex justify-end space-x-3">
                    <a href="{{ route('admin.licenses.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50">
                        Cancel
                    </a>
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                        Create License
                    </button>
                </div>
            </div>
        </form>
    </div>
@endsection
