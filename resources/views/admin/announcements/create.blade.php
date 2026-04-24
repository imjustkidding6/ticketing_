@extends('layouts.admin')

@section('title', 'New Announcement')

@section('content')
    <div class="mb-6">
        <a href="{{ route('admin.announcements.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Back to announcements</a>
        <h1 class="text-2xl font-semibold text-gray-900 mt-2">New System Announcement</h1>
        <p class="text-sm text-gray-600 mt-1">This will be sent to every user in every tenant's notification bell.</p>
    </div>

    <form method="POST" action="{{ route('admin.announcements.store') }}" class="bg-white shadow rounded-lg p-6 space-y-6 max-w-2xl">
        @csrf

        <div>
            <label for="title" class="block text-sm font-medium text-gray-700">Title <span class="text-red-500">*</span></label>
            <input type="text" name="title" id="title" required maxlength="255" value="{{ old('title') }}"
                   placeholder="e.g. v2.1 released — bug fixes &amp; improvements"
                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
            @error('title')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="body" class="block text-sm font-medium text-gray-700">Message <span class="text-red-500">*</span></label>
            <textarea name="body" id="body" required rows="6" maxlength="5000"
                      placeholder="Describe the update, bug fixes, or maintenance window..."
                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">{{ old('body') }}</textarea>
            @error('body')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="severity" class="block text-sm font-medium text-gray-700">Severity <span class="text-red-500">*</span></label>
            <select name="severity" id="severity" required
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                @foreach($severities as $sev)
                    <option value="{{ $sev }}" {{ old('severity', 'update') === $sev ? 'selected' : '' }}>{{ ucfirst($sev) }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-gray-500">Controls the color of the icon users see. "Update" for releases, "Maintenance" for downtime, "Warning" for incidents.</p>
        </div>

        <div class="rounded-md bg-amber-50 border border-amber-200 p-3 text-sm text-amber-800">
            <p class="font-medium">Heads up</p>
            <p class="mt-1 text-xs">Publishing immediately broadcasts to all users. Deleting an announcement later will also revoke it from every user's notification list.</p>
        </div>

        <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-200">
            <a href="{{ route('admin.announcements.index') }}" class="text-sm text-gray-600 hover:text-gray-800">Cancel</a>
            <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                Publish &amp; Broadcast
            </button>
        </div>
    </form>
@endsection
