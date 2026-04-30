<x-guest-layout>
    <div class="text-center">
        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-red-100">
            <svg class="h-7 w-7 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
            </svg>
        </div>

        <h1 class="mt-4 text-xl font-semibold text-gray-900">{{ __('Subscription Expired') }}</h1>
        <p class="mt-1 text-sm text-gray-600">{{ __('Your access to :name has been suspended.', ['name' => $tenant->name]) }}</p>
    </div>

    @if($license?->expires_at)
        <dl class="mt-6 grid grid-cols-2 gap-3 rounded-lg bg-gray-50 px-4 py-3 text-sm">
            <div>
                <dt class="text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Expired On') }}</dt>
                <dd class="mt-1 text-gray-900">{{ $license->expires_at->format('F j, Y') }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Status') }}</dt>
                <dd class="mt-1 font-medium text-red-700">
                    @if($license->status === \App\Models\License::STATUS_REVOKED)
                        {{ __('Revoked') }}
                    @elseif($license->status === \App\Models\License::STATUS_EXPIRED)
                        {{ __('Expired') }}
                    @else
                        {{ __('Inactive') }}
                    @endif
                </dd>
            </div>
        </dl>
    @endif

    <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        <p class="font-medium">{{ __("Don't worry — your data is safe.") }}</p>
        <p class="mt-1 text-xs">{{ __('All tickets, clients, and settings are preserved. Access will be restored as soon as your subscription is reactivated.') }}</p>
    </div>

    <div class="mt-5">
        <p class="text-sm font-medium text-gray-700">{{ __('To restore access:') }}</p>
        <ol class="mt-2 list-decimal list-inside space-y-1 text-sm text-gray-600">
            <li>{{ __('Contact your administrator or distributor') }}</li>
            <li>{{ __('Request subscription renewal') }}</li>
            <li>{{ __('Access will be restored automatically') }}</li>
        </ol>
    </div>

    <div class="mt-6 flex items-center justify-between border-t border-gray-100 pt-4">
        <span class="text-xs text-gray-400">{{ __('Signed in as :email', ['email' => auth()->user()?->email]) }}</span>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">{{ __('Sign out') }}</button>
        </form>
    </div>
</x-guest-layout>
