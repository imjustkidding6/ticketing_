<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Connect Jude') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <div class="p-6 bg-white shadow sm:rounded-lg space-y-4">
                <p class="text-sm text-gray-600">
                    Generate a token to connect this workspace to the Jude assistant Hub, then in the Hub go to
                    <strong>Connected apps → Add</strong> and enter this app's address + the token. The token acts as
                    <strong>you</strong>, in your current workspace. Treat it like a password.
                </p>

                <div class="rounded-md bg-gray-50 ring-1 ring-gray-200 px-4 py-3 text-sm">
                    <div class="text-gray-500">This app's address (for the Hub connection)</div>
                    <div class="font-mono text-gray-800 break-all">{{ config('app.url') }}</div>
                    <div class="mt-1 text-xs text-gray-400">If the Hub runs in Docker on this machine, use
                        <code>http://host.docker.internal:{{ parse_url(config('app.url'), PHP_URL_PORT) ?: 80 }}</code> instead.</div>
                </div>

                @if($fresh)
                    <div class="rounded-md bg-emerald-50 ring-1 ring-emerald-200 p-4">
                        <div class="text-sm font-medium text-emerald-800">Copy this now — it won't be shown again:</div>
                        <div class="mt-2 flex items-center gap-2">
                            <code id="freshToken" class="flex-1 rounded bg-white px-3 py-2 font-mono text-sm text-gray-800 break-all ring-1 ring-emerald-200">{{ $fresh }}</code>
                            <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('freshToken').innerText)"
                                    class="rounded-md bg-emerald-600 px-3 py-2 text-sm text-white hover:bg-emerald-700">Copy</button>
                        </div>
                    </div>
                @endif

                <form method="POST" action="{{ route('connect-jude.generate') }}" class="flex items-end gap-2">
                    @csrf
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-700">Device / purpose (optional)</label>
                        <input name="name" type="text" placeholder="e.g. My laptop"
                               class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    </div>
                    <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                        Generate token
                    </button>
                </form>
            </div>

            <div class="p-6 bg-white shadow sm:rounded-lg">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Your tokens</h3>
                <div class="divide-y divide-gray-100">
                    @forelse($tokens as $t)
                        <div class="flex items-center justify-between py-3 text-sm">
                            <div>
                                <div class="font-medium text-gray-800">{{ $t->name }}</div>
                                <div class="text-xs text-gray-500">
                                    Created {{ $t->created_at->diffForHumans() }} ·
                                    {{ $t->last_used_at ? 'last used '.$t->last_used_at->diffForHumans() : 'never used' }}
                                </div>
                            </div>
                            <form method="POST" action="{{ route('connect-jude.revoke', $t) }}"
                                  onsubmit="return confirm('Revoke this token? Anything using it will stop working.')">
                                @csrf @method('DELETE')
                                <button class="rounded-md px-3 py-1.5 text-xs text-red-600 hover:bg-red-50">Revoke</button>
                            </form>
                        </div>
                    @empty
                        <p class="py-4 text-center text-sm text-gray-400">No tokens yet.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
