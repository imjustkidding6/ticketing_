<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Subscription Expired — {{ config('app.name') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('cliqueha-logo.png') }}">
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-gray-50 antialiased">
<div class="flex min-h-screen items-center justify-center px-4">
    <div class="w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-lg">
        <div class="bg-gradient-to-r from-red-500 to-red-600 px-8 py-10 text-center">
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-white/20">
                <svg class="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                </svg>
            </div>
            <h1 class="mt-4 text-2xl font-bold text-white">Subscription Expired</h1>
            <p class="mt-1 text-sm text-red-100">Your access has been suspended.</p>
        </div>

        <div class="px-8 py-8">
            <p class="text-gray-700">
                Your subscription for <strong>{{ $tenant->name }}</strong> has expired.
            </p>

            @if($license?->expires_at)
                <dl class="mt-5 grid grid-cols-2 gap-4 rounded-lg bg-gray-50 px-4 py-3 text-sm">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wider text-gray-500">Expired On</dt>
                        <dd class="mt-1 text-gray-900">{{ $license->expires_at->format('F j, Y') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wider text-gray-500">Status</dt>
                        <dd class="mt-1 font-medium text-red-700">
                            @if($license->status === \App\Models\License::STATUS_REVOKED)
                                Revoked
                            @elseif($license->status === \App\Models\License::STATUS_EXPIRED)
                                Expired
                            @else
                                Inactive
                            @endif
                        </dd>
                    </div>
                </dl>
            @endif

            <div class="mt-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                <p class="font-medium">Don't worry — your data is safe.</p>
                <p class="mt-1">All tickets, clients, and settings are preserved. Access will be restored as soon as your subscription is reactivated.</p>
            </div>

            <div class="mt-6">
                <p class="text-sm font-medium text-gray-700">To restore access:</p>
                <ol class="mt-2 list-decimal list-inside space-y-1 text-sm text-gray-600">
                    <li>Contact your administrator or distributor</li>
                    <li>Request subscription renewal</li>
                    <li>Access will be restored automatically</li>
                </ol>
            </div>

            <div class="mt-8 flex items-center justify-between border-t border-gray-100 pt-5">
                <span class="text-xs text-gray-400">Signed in as {{ auth()->user()?->email }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">Sign out</button>
                </form>
            </div>
        </div>
    </div>
</div>
</body>
</html>
