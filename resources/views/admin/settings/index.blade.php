@extends('layouts.admin')

@section('title', 'Settings')

@section('content')
<div class="max-w-3xl">
    <form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-6 rounded-xl bg-white p-6 shadow-sm">
        @csrf
        @method('PUT')

        <div>
            <h2 class="text-lg font-semibold text-gray-900">System Settings</h2>
            <p class="mt-1 text-sm text-gray-500">Applies to admin-facing screens. Tenants still use their own timezone from their general settings.</p>
        </div>

        <div class="border-t border-gray-200 pt-6">
            <label for="admin_timezone" class="block text-sm font-medium text-gray-700">Admin display timezone</label>
            <p class="mt-1 text-xs text-gray-500">Timestamps on the admin dashboard, announcements list, and tenant list will render in this zone.</p>
            <select name="admin_timezone" id="admin_timezone" class="mt-2 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                @foreach($timezones as $tz)
                    <option value="{{ $tz }}" @selected($adminTimezone === $tz)>{{ $tz }}</option>
                @endforeach
            </select>
            @error('admin_timezone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="flex justify-end border-t border-gray-200 pt-6">
            <button type="submit" class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                Save Settings
            </button>
        </div>
    </form>
</div>
@endsection
