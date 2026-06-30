{{-- Public AI chat widget. Expects $tenant. Mounted from client-portal/layout when enabled. --}}
<div x-data="aiChat()" x-cloak class="fixed bottom-6 right-6 z-50">
    {{-- Chat panel --}}
    <div x-show="open"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         :style="'width:' + width + 'px; height:' + height + 'px'"
         :class="resizing ? 'select-none' : ''"
         class="relative mb-3 flex max-h-[85vh] max-w-[calc(100vw-3rem)] flex-col overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-black/5">
        {{-- Drag handle (left edge) to resize width --}}
        <div @pointerdown="startResize($event, 'x')" class="group absolute inset-y-0 left-0 z-20 flex w-2 cursor-ew-resize items-center justify-center" title="{{ __('Drag to resize') }}">
            <div class="h-10 w-1 rounded-full bg-gray-300 group-hover:bg-gray-400"></div>
        </div>
        {{-- Drag handle (top edge) to resize height --}}
        <div @pointerdown="startResize($event, 'y')" class="group absolute inset-x-0 top-0 z-20 flex h-2 cursor-ns-resize items-center justify-center" title="{{ __('Drag to resize') }}">
            <div class="h-1 w-10 rounded-full bg-white/30 group-hover:bg-white/70"></div>
        </div>
        {{-- Drag handle (top-left corner) to resize both --}}
        <div @pointerdown="startResize($event, 'both')" class="absolute left-0 top-0 z-30 h-3.5 w-3.5 cursor-nwse-resize" title="{{ __('Drag to resize') }}"></div>
        {{-- Header --}}
        <div class="flex items-center justify-between px-4 py-3 pl-5 text-white" style="background-color: var(--portal-primary);">
            <div class="flex items-center gap-2">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" /></svg>
                <span class="text-sm font-semibold">{{ __('Support Assistant') }}</span>
            </div>
            <div class="flex items-center gap-0.5">
                <button type="button" @click="newChat()" class="rounded p-1 text-white/80 hover:text-white" aria-label="{{ __('New chat') }}" title="{{ __('New chat') }}">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zM19.5 7.125L16.875 4.5" /></svg>
                </button>
                <button type="button" @click="open = false" class="rounded p-1 text-white/80 hover:text-white" aria-label="{{ __('Close') }}">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>
        </div>

        {{-- Messages --}}
        <div x-ref="scroll" class="flex-1 space-y-3 overflow-y-auto bg-gray-50 px-4 py-4">
            <template x-for="(m, i) in messages" :key="i">
                <div :class="m.role === 'user' ? 'flex justify-end' : 'flex justify-start'">
                    <div :class="m.role === 'user' ? 'rounded-2xl rounded-br-sm px-3 py-2 text-sm text-white' : 'rounded-2xl rounded-bl-sm bg-white px-3 py-2 text-sm text-gray-800 ring-1 ring-gray-200'"
                         :style="m.role === 'user' ? 'background-color: var(--portal-primary)' : ''"
                         class="max-w-[85%] break-words">
                        <template x-if="m.role === 'user'">
                            <div class="whitespace-pre-line" x-text="m.text"></div>
                        </template>
                        <template x-if="m.role !== 'user'">
                            <div class="nx-prose" x-html="md(m.text)"></div>
                        </template>
                    </div>
                </div>
            </template>
            {{-- Typing indicator --}}
            <div x-show="loading" class="flex justify-start">
                <div class="flex gap-1 rounded-2xl rounded-bl-sm bg-white px-3 py-2.5 ring-1 ring-gray-200">
                    <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-gray-400" style="animation-delay: 0ms"></span>
                    <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-gray-400" style="animation-delay: 150ms"></span>
                    <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-gray-400" style="animation-delay: 300ms"></span>
                </div>
            </div>
        </div>

        {{-- Input --}}
        <form @submit.prevent="send()" class="flex items-end gap-2 border-t border-gray-200 bg-white px-3 py-2.5">
            <textarea x-model="input" @keydown.enter="if (! $event.shiftKey) { $event.preventDefault(); send(); }" rows="1"
                      :disabled="loading"
                      placeholder="{{ __('Type your message... (Shift+Enter for a new line)') }}"
                      class="max-h-24 flex-1 resize-none rounded-lg border-gray-300 text-sm focus:border-gray-400 focus:ring-0"></textarea>
            <button type="submit" :disabled="loading || !input.trim()"
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-white disabled:opacity-40"
                    style="background-color: var(--portal-primary);" aria-label="{{ __('Send') }}">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" /></svg>
            </button>
        </form>
    </div>

    {{-- Floating bubble --}}
    <button type="button" @click="toggle()"
            class="flex h-14 w-14 items-center justify-center rounded-full text-white shadow-lg transition hover:scale-105"
            style="background-color: var(--portal-primary);" aria-label="{{ __('Open support assistant') }}">
        <svg x-show="!open" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z" /></svg>
        <svg x-show="open" x-cloak class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
    </button>
</div>

<script>
    function aiChat() {
        return {
            open: false,
            messages: [],
            input: '',
            loading: false,
            width: parseInt(localStorage.getItem('portal_chat_width_{{ $tenant->slug }}')) || 352,
            height: parseInt(localStorage.getItem('portal_chat_height_{{ $tenant->slug }}')) || 512,
            resizing: false,
            storageKey: 'ai_chat_token_{{ $tenant->slug }}',
            sessionToken: localStorage.getItem('ai_chat_token_{{ $tenant->slug }}') || null,
            endpoint: '{{ route('tenant.ai-chat', ['slug' => $tenant->slug]) }}',
            historyEndpoint: '{{ route('tenant.ai-chat.history', ['slug' => $tenant->slug]) }}',
            csrf: document.querySelector('meta[name="csrf-token"]')?.content || '',

            init() {
                if (this.sessionToken) {
                    this.loadHistory();
                }
            },

            greet() {
                this.messages.push({ role: 'assistant', text: @js(__('Hi! I can answer questions, check a ticket\'s status, or open a support ticket for you. How can I help?')) });
            },

            async loadHistory() {
                try {
                    const res = await fetch(this.historyEndpoint + '?session_token=' + encodeURIComponent(this.sessionToken), { headers: { 'Accept': 'application/json' } });
                    const data = await res.json();
                    if (data.messages && data.messages.length) {
                        this.messages = data.messages;
                        this.$nextTick(() => this.scrollToEnd());
                    }
                } catch (e) { /* ignore — start fresh */ }
            },

            newChat() {
                this.sessionToken = null;
                localStorage.removeItem(this.storageKey);
                this.messages = [];
                this.input = '';
                this.greet();
                this.$nextTick(() => this.scrollToEnd());
            },

            toggle() {
                this.open = !this.open;
                if (this.open && this.messages.length === 0) {
                    this.greet();
                }
                if (this.open) {
                    this.$nextTick(() => this.scrollToEnd());
                }
            },

            async send() {
                const text = this.input.trim();
                if (!text || this.loading) return;

                this.messages.push({ role: 'user', text });
                this.input = '';
                this.loading = true;
                this.$nextTick(() => this.scrollToEnd());

                try {
                    const res = await fetch(this.endpoint, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json' },
                        body: JSON.stringify({ message: text, session_token: this.sessionToken }),
                    });
                    const data = await res.json();
                    if (data.session_token) {
                        this.sessionToken = data.session_token;
                        localStorage.setItem(this.storageKey, data.session_token);
                    }
                    this.messages.push({ role: 'assistant', text: data.reply || @js(__('Sorry, something went wrong.')) });
                } catch (e) {
                    this.messages.push({ role: 'assistant', text: @js(__('Sorry, I could not reach the assistant. Please try again.')) });
                } finally {
                    this.loading = false;
                    this.$nextTick(() => this.scrollToEnd());
                }
            },

            // Render an assistant reply as safe Markdown HTML (shared helper).
            md(t) { return window.NexusChat ? window.NexusChat.renderMarkdown(t) : (t || ''); },

            scrollToEnd() {
                const el = this.$refs.scroll;
                if (el) el.scrollTop = el.scrollHeight;
            },

            startResize(e, axis = 'x') {
                this.resizing = true;
                const startX = e.clientX, startY = e.clientY;
                const startWidth = this.width, startHeight = this.height;
                const maxWidth = () => Math.min(760, window.innerWidth - 48);
                const maxHeight = () => Math.min(900, window.innerHeight - 120);
                const onMove = (ev) => {
                    // Anchored bottom-right: drag the left edge to widen, the top edge to grow taller.
                    if (axis === 'x' || axis === 'both') {
                        this.width = Math.max(300, Math.min(startWidth + (startX - ev.clientX), maxWidth()));
                    }
                    if (axis === 'y' || axis === 'both') {
                        this.height = Math.max(320, Math.min(startHeight + (startY - ev.clientY), maxHeight()));
                    }
                };
                const onUp = () => {
                    this.resizing = false;
                    localStorage.setItem('portal_chat_width_{{ $tenant->slug }}', this.width);
                    localStorage.setItem('portal_chat_height_{{ $tenant->slug }}', this.height);
                    window.removeEventListener('pointermove', onMove);
                    window.removeEventListener('pointerup', onUp);
                };
                window.addEventListener('pointermove', onMove);
                window.addEventListener('pointerup', onUp);
                e.preventDefault();
            },
        };
    }
</script>
