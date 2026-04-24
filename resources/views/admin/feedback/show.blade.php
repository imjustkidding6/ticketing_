@extends('layouts.admin')

@section('title', 'Feedback Detail')

@section('content')
    <div class="mb-6">
        <a href="{{ route('admin.feedback.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Back to feedback</a>
        <h1 class="text-2xl font-semibold text-gray-900 mt-2">Feedback Detail</h1>
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Main content --}}
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white shadow rounded-lg p-6">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">
                            {{ $feedback->subject ?: '(No subject)' }}
                        </h2>
                        <div class="mt-1 flex items-center gap-2">
                            @php
                                $typeColor = match($feedback->type) {
                                    'bug'        => 'bg-red-100 text-red-700',
                                    'suggestion' => 'bg-blue-100 text-blue-700',
                                    'compliment' => 'bg-emerald-100 text-emerald-700',
                                    default      => 'bg-gray-100 text-gray-600',
                                };
                            @endphp
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $typeColor }}">
                                {{ ucfirst($feedback->type) }}
                            </span>
                            <span class="text-xs text-gray-400">{{ $feedback->created_at->format('M d, Y h:i A') }}</span>
                        </div>
                    </div>
                </div>

                <div class="prose prose-sm max-w-none">
                    <p class="text-gray-700 whitespace-pre-line">{{ $feedback->body }}</p>
                </div>
            </div>
        </div>

        {{-- Sidebar --}}
        <div class="space-y-6">

            {{-- Submitter info --}}
            <div class="bg-white shadow rounded-lg p-6">
                <h3 class="text-sm font-medium text-gray-900 mb-3">Submitted by</h3>
                <div class="space-y-2 text-sm">
                    <div>
                        <span class="text-gray-500">Tenant:</span>
                        <span class="ml-1 font-medium text-gray-900">{{ $feedback->tenant?->name ?? '—' }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500">User:</span>
                        <span class="ml-1 text-gray-900">{{ $feedback->user?->name ?? 'Unknown' }}</span>
                    </div>
                    @if($feedback->user?->email)
                        <div>
                            <span class="text-gray-500">Email:</span>
                            <span class="ml-1 text-gray-900">{{ $feedback->user->email }}</span>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Status --}}
            <div class="bg-white shadow rounded-lg p-6">
                <h3 class="text-sm font-medium text-gray-900 mb-3">Status</h3>
                @php
                    $statusColor = match($feedback->status) {
                        'new'      => 'bg-red-100 text-red-700',
                        'read'     => 'bg-yellow-100 text-yellow-700',
                        'resolved' => 'bg-green-100 text-green-700',
                        default    => 'bg-gray-100 text-gray-600',
                    };
                @endphp
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusColor }} mb-4">
                    {{ ucfirst($feedback->status) }}
                </span>

                <form method="POST" action="{{ route('admin.feedback.update', $feedback) }}" class="space-y-3">
                    @csrf
                    @method('PATCH')
                    <select name="status"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        @foreach(\App\Models\TenantFeedback::STATUSES as $s)
                            <option value="{{ $s }}" {{ $feedback->status === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                        @endforeach
                    </select>
                    <button type="submit"
                            class="w-full inline-flex justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-500 transition-colors">
                        Update Status
                    </button>
                </form>
            </div>

            {{-- Delete --}}
            <form method="POST" action="{{ route('admin.feedback.destroy', $feedback) }}"
                  onsubmit="return confirm('Delete this feedback permanently?')">
                @csrf
                @method('DELETE')
                <button type="submit"
                        class="w-full inline-flex justify-center rounded-md border border-red-300 bg-white px-3 py-2 text-sm font-medium text-red-600 hover:bg-red-50 transition-colors">
                    Delete Feedback
                </button>
            </form>

        </div>
    </div>
@endsection
