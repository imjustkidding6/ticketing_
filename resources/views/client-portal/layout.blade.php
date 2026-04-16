<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $tenant->name }} - {{ __('Support Portal') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <!-- Tenant Branding -->
        <style>
            :root {
                --portal-primary: {{ $tenant->primary_color ?? '#4f46e5' }};
                --portal-accent: {{ $tenant->accent_color ?? '#4338ca' }};
            }
        </style>
    </head>
    <body class="font-sans antialiased bg-gray-50">
        <div class="min-h-screen flex flex-col">
            <!-- Portal Header -->
            <header class="border-b border-gray-200 shadow-sm text-white" style="background-color: var(--portal-primary);">
                <div class="mx-auto max-w-full px-4 sm:px-4 lg:px-6">
                    <div class="flex h-16 items-center justify-between">
                        <div class="flex items-center gap-4">
                            <a href="{{ route('tenant.landing', ['slug' => $tenant->slug]) }}" class="flex items-center gap-2">
                                @if($tenant->logo_path)
                                    <img src="{{ $tenant->logoUrl() }}" alt="{{ $tenant->name }}" class="h-10 w-auto">
                                @else
                                    <svg class="h-8 w-8 text-white/80" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-9-5.25h5.25M7.5 15h3M3.375 5.25c-.621 0-1.125.504-1.125 1.125v3.026a2.999 2.999 0 010 5.198v3.026c0 .621.504 1.125 1.125 1.125h17.25c.621 0 1.125-.504 1.125-1.125v-3.026a2.999 2.999 0 010-5.198V6.375c0-.621-.504-1.125-1.125-1.125H3.375z" />
                                    </svg>
                                @endif
                                <div>
                                    <span class="text-lg font-semibold text-white">{{ $tenant->name }}</span>
                                    <span class="ml-2 text-sm text-white/70">{{ __('Support Portal') }}</span>
                                </div>
                            </a>
                        </div>

                        @if(empty($hideNav))
                        <nav class="flex items-center gap-4">
                            <a href="{{ route('tenant.landing', ['slug' => $tenant->slug]) }}" class="text-sm font-medium text-white/80 hover:text-white">{{ __('Home') }}</a>
                            <a href="{{ route('tenant.track-ticket', ['slug' => $tenant->slug]) }}" class="text-sm font-medium text-white/80 hover:text-white">{{ __('Track Ticket') }}</a>
                            <a href="{{ route('tenant.submit-ticket', ['slug' => $tenant->slug]) }}" class="inline-flex items-center rounded-md px-3 py-2 text-sm font-semibold text-white shadow-sm" style="background-color: var(--portal-accent);">
                                {{ __('New Ticket') }}
                            </a>
                        </nav>
                        @endif
                    </div>
                </div>
            </header>

            @if(session('success'))
                <div class="mx-auto max-w-full px-4 sm:px-4 lg:px-6 mt-4">
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                        {{ session('success') }}
                    </div>
                </div>
            @endif

            @if(session('error'))
                <div class="mx-auto max-w-full px-4 sm:px-4 lg:px-6 mt-4">
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                        {{ session('error') }}
                    </div>
                </div>
            @endif

            <!-- Page Content -->
            <main class="flex-1 py-8">
                {{ $slot }}
            </main>

            <!-- Footer -->
            <footer class="border-t border-gray-200 bg-white py-4">
                <div class="mx-auto max-w-full px-4 sm:px-4 lg:px-6 text-center text-sm text-gray-500">
                    &copy; {{ date('Y') }} {{ $tenant->name }}. {{ __('All rights reserved.') }}
                </div>
            </footer>
        </div>

        @if($hasChatbot)
        <div id="chatbot-widget" class="fixed bottom-6 right-6 z-50">
            <button id="chatbot-toggle" class="flex items-center gap-2 rounded-full px-4 py-3 text-sm font-semibold text-white shadow-lg" style="background-color: var(--portal-primary);">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3.75h6m-6 3.75h3M3.75 6A2.25 2.25 0 016 3.75h12A2.25 2.25 0 0120.25 6v9a2.25 2.25 0 01-2.25 2.25h-6.75L6 21v-2.75H6A2.25 2.25 0 013.75 16V6z" />
                </svg>
                {{ __('Chat with us') }}
            </button>

            <div id="chatbot-panel" class="hidden mt-3 w-80 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-gray-200 bg-gray-50 px-4 py-3">
                    <div>
                        <p class="text-sm font-semibold text-gray-900">{{ $tenant->name }} {{ __('Support') }}</p>
                        <p class="text-xs text-gray-500">{{ __('AI assistant') }}</p>
                    </div>
                    <button id="chatbot-close" class="text-gray-400 hover:text-gray-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div id="chatbot-messages" class="h-64 space-y-3 overflow-y-auto px-4 py-3 text-sm text-gray-700"></div>

                <div id="chatbot-contact" class="hidden border-t border-gray-200 px-4 py-3">
                    <p class="text-xs text-gray-500 mb-2">{{ __('Provide your contact details so we can open a ticket.') }}</p>
                    <div class="space-y-2">
                        <input id="chatbot-name" type="text" placeholder="{{ __('Name') }}" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <input id="chatbot-email" type="email" placeholder="{{ __('Email') }}" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <input id="chatbot-phone" type="text" placeholder="{{ __('Phone (optional)') }}" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <button id="chatbot-escalate" class="w-full rounded-md px-3 py-2 text-sm font-semibold text-white" style="background-color: var(--portal-primary);">{{ __('Create Ticket') }}</button>
                    </div>
                </div>

                <div class="border-t border-gray-200 px-4 py-3">
                    <div class="flex gap-2">
                        <input id="chatbot-input" type="text" placeholder="{{ __('Ask a question...') }}" class="flex-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <button id="chatbot-send" class="rounded-md px-3 py-2 text-sm font-semibold text-white" style="background-color: var(--portal-accent);">{{ __('Send') }}</button>
                    </div>
                </div>
            </div>
        </div>

        <script>
            (function () {
                const panel = document.getElementById('chatbot-panel');
                const toggle = document.getElementById('chatbot-toggle');
                const closeBtn = document.getElementById('chatbot-close');
                const messagesEl = document.getElementById('chatbot-messages');
                const inputEl = document.getElementById('chatbot-input');
                const sendBtn = document.getElementById('chatbot-send');
                const contactBox = document.getElementById('chatbot-contact');
                const escalateBtn = document.getElementById('chatbot-escalate');
                const nameEl = document.getElementById('chatbot-name');
                const emailEl = document.getElementById('chatbot-email');
                const phoneEl = document.getElementById('chatbot-phone');
                const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

                const tokenUrl = '{{ route('api.public.chatbot.token', ['slug' => $tenant->slug]) }}';
                const messageUrl = '{{ route('api.public.chatbot.message', ['slug' => $tenant->slug]) }}';
                const escalateUrl = '{{ route('api.public.chatbot.escalate', ['slug' => $tenant->slug]) }}';

                const storageKey = 'chatbot_session_{{ $tenant->slug }}';
                const state = JSON.parse(localStorage.getItem(storageKey) || '{}');

                function saveState() {
                    localStorage.setItem(storageKey, JSON.stringify(state));
                }

                function appendMessage(role, text) {
                    const wrapper = document.createElement('div');
                    const bubble = document.createElement('div');
                    bubble.textContent = text;
                    bubble.className = role === 'user'
                        ? 'ml-auto max-w-[80%] rounded-lg px-3 py-2 text-white'
                        : 'mr-auto max-w-[80%] rounded-lg bg-gray-100 px-3 py-2 text-gray-700';
                    if (role === 'user') {
                        bubble.style.backgroundColor = 'var(--portal-primary)';
                    }
                    wrapper.appendChild(bubble);
                    messagesEl.appendChild(wrapper);
                    messagesEl.scrollTop = messagesEl.scrollHeight;
                }

                async function ensureToken() {
                    if (state.token && state.session_id) {
                        return;
                    }

                    const response = await fetch(tokenUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify({ session_id: state.session_id }),
                    });

                    const data = await response.json();
                    state.token = data.token;
                    state.session_id = data.session_id;
                    saveState();
                }

                async function sendMessage() {
                    const text = inputEl.value.trim();
                    if (! text) return;

                    await ensureToken();
                    appendMessage('user', text);
                    inputEl.value = '';

                    const response = await fetch(messageUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Authorization': `Bearer ${state.token}`,
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify({ session_id: state.session_id, message: text }),
                    });

                    const data = await response.json();

                    if (data.reply) {
                        appendMessage('assistant', data.reply);
                    }

                    if (data.next_action === 'collect_contact') {
                        contactBox.classList.remove('hidden');
                    }

                    if (data.next_action === 'ticket_created') {
                        contactBox.classList.add('hidden');
                        appendMessage('assistant', `{{ __('Track your ticket here:') }} ${data.tracking_url}`);
                    }
                }

                async function escalate() {
                    await ensureToken();

                    const response = await fetch(escalateUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Authorization': `Bearer ${state.token}`,
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify({
                            session_id: state.session_id,
                            name: nameEl.value.trim(),
                            email: emailEl.value.trim(),
                            phone: phoneEl.value.trim(),
                        }),
                    });

                    const data = await response.json();
                    if (data.tracking_url) {
                        appendMessage('assistant', `{{ __('Ticket created:') }} ${data.tracking_url}`);
                    }
                }

                toggle.addEventListener('click', async () => {
                    panel.classList.toggle('hidden');
                    if (! panel.classList.contains('hidden') && messagesEl.childElementCount === 0) {
                        appendMessage('assistant', '{{ __('Hi! How can we help you today?') }}');
                    }
                });

                closeBtn.addEventListener('click', () => panel.classList.add('hidden'));
                sendBtn.addEventListener('click', sendMessage);
                inputEl.addEventListener('keydown', (event) => {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        sendMessage();
                    }
                });
                escalateBtn.addEventListener('click', escalate);
            })();
        </script>
        @endif
    </body>
</html>
