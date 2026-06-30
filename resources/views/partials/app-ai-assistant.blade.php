{{-- In-app AI assistant for logged-in team members. Per-user memory with a conversation history (ChatGPT-style). --}}
<div x-data="appAiChat()" x-cloak class="fixed bottom-24 right-6 z-40">
    {{-- Panel --}}
    <div x-show="open"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         :style="'width:' + width + 'px; height:' + height + 'px'"
         :class="resizing ? 'select-none' : ''"
         class="relative mb-3 flex max-h-[85vh] max-w-[calc(100vw-3rem)] flex-col overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-black/5">
        {{-- Drag handle (left edge) to resize width --}}
        <div @pointerdown="startResize($event, 'x')" class="group absolute inset-y-0 left-0 z-20 flex w-2 cursor-ew-resize items-center justify-center" title="{{ __('Drag to resize') }}">
            <div class="h-10 w-1 rounded-full bg-white/30 group-hover:bg-white/70"></div>
        </div>
        {{-- Drag handle (top edge) to resize height --}}
        <div @pointerdown="startResize($event, 'y')" class="group absolute inset-x-0 top-0 z-20 flex h-2 cursor-ns-resize items-center justify-center" title="{{ __('Drag to resize') }}">
            <div class="h-1 w-10 rounded-full bg-white/30 group-hover:bg-white/70"></div>
        </div>
        {{-- Drag handle (top-left corner) to resize both --}}
        <div @pointerdown="startResize($event, 'both')" class="absolute left-0 top-0 z-30 h-3.5 w-3.5 cursor-nwse-resize" title="{{ __('Drag to resize') }}"></div>
        <div class="flex items-center justify-between bg-gradient-to-r from-indigo-600 via-indigo-600 to-violet-600 px-4 py-3 pl-5 text-white">
            <div class="flex items-center gap-2.5">
                <span class="relative flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-white/15 ring-1 ring-white/25">
                    <svg class="h-[18px] w-[18px]" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z" /></svg>
                    <span class="absolute -bottom-0.5 -right-0.5 h-2.5 w-2.5 rounded-full bg-emerald-400 ring-2 ring-indigo-600"></span>
                </span>
                <div class="leading-tight">
                    <p class="text-sm font-semibold" x-text="view === 'list' ? @js(__('Your conversations')) : @js(__('Nexus AI'))"></p>
                    <p x-show="view !== 'list'" class="text-[11px] text-white/70">{{ __('Your AI teammate · online') }}</p>
                </div>
            </div>
            <div class="flex items-center gap-0.5">
                <button type="button" @click="openHistory()" class="rounded p-1 text-white/80 hover:text-white" :class="view === 'list' ? 'bg-white/20' : ''" aria-label="{{ __('History') }}" title="{{ __('History') }}">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </button>
                <button type="button" @click="newChat()" class="rounded p-1 text-white/80 hover:text-white" aria-label="{{ __('New chat') }}" title="{{ __('New chat') }}">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zM19.5 7.125L16.875 4.5" /></svg>
                </button>
                <button type="button" @click="open = false" class="rounded p-1 text-white/80 hover:text-white" aria-label="{{ __('Close') }}">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>
        </div>

        {{-- History list view --}}
        <div x-show="view === 'list'" class="flex-1 overflow-y-auto bg-gray-50 p-2">
            <button type="button" @click="newChat()" class="mb-1 flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm font-medium text-indigo-700 hover:bg-indigo-50">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                {{ __('New chat') }}
            </button>
            <template x-for="c in conversations" :key="c.id">
                <button type="button" @click="openConversation(c.id)"
                        class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-gray-700 hover:bg-white"
                        :class="c.id === currentId ? 'bg-white ring-1 ring-indigo-200' : ''">
                    <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" /></svg>
                    <span class="truncate" x-text="c.title"></span>
                </button>
            </template>
            <p x-show="!conversations.length" class="px-3 py-6 text-center text-xs text-gray-500">{{ __('No past conversations yet.') }}</p>
        </div>

        {{-- Chat view --}}
        <div x-show="view === 'chat'" x-ref="scroll" class="flex-1 space-y-3 overflow-y-auto bg-gray-50 px-4 py-4">
            <template x-for="(m, i) in messages" :key="m.uid ?? i">
                <div :class="m.role === 'user' ? 'flex justify-end' : 'flex items-end justify-start gap-2'">
                    <template x-if="m.role !== 'user'">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-indigo-500 to-violet-500 text-white shadow-sm">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z" /></svg>
                        </span>
                    </template>
                    <div :class="m.role === 'user' ? 'rounded-2xl rounded-br-sm bg-indigo-600 px-3 py-2 text-sm text-white' : (m.system ? 'rounded-2xl rounded-bl-sm bg-indigo-50 px-3 py-2 text-sm text-indigo-900 ring-1 ring-indigo-200' : 'rounded-2xl rounded-bl-sm bg-white px-3 py-2 text-sm text-gray-800 ring-1 ring-gray-200')"
                         class="max-w-[85%]">
                        <template x-if="m.image">
                            <img :src="m.image" alt="attachment" class="mb-1 max-h-48 max-w-full rounded-lg object-contain">
                        </template>
                        <template x-if="m.role === 'user'">
                            <div x-show="m.text" class="whitespace-pre-line break-words" x-text="m.text"></div>
                        </template>
                        <template x-if="m.role !== 'user'">
                            <div x-show="m.text" class="nx-prose break-words" x-html="md(m.text)"></div>
                        </template>
                        {{-- Rich chart, rendered with ApexCharts (axes, tooltips, legend) --}}
                        <template x-if="m.chart">
                            <div class="mt-2 w-full">
                                <p x-show="m.chart.title" class="mb-1 text-xs font-semibold text-gray-700" x-text="m.chart.title"></p>
                                <div class="overflow-hidden rounded-lg bg-white p-1 ring-1 ring-gray-100"
                                     :style="'height:' + chartH(m.chart) + 'px'"
                                     x-init="$nextTick(() => renderChart($el, m.chart))"></div>
                            </div>
                        </template>
                        {{-- Save a helpful answer to learned knowledge --}}
                        <template x-if="m.role !== 'user' && learnEnabled && m.text">
                            <div class="mt-1.5 flex items-center gap-2 border-t border-gray-100 pt-1.5">
                                <button type="button" x-show="!m.saved" @click="saveToKnowledge(i)" :disabled="m.saving"
                                        class="inline-flex items-center gap-1 text-[11px] font-medium text-gray-400 hover:text-indigo-600 disabled:opacity-50">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0111.186 0z" /></svg>
                                    <span x-text="m.saving ? @js(__('Saving…')) : @js(__('Save to knowledge'))"></span>
                                </button>
                                <span x-show="m.saved" x-cloak class="inline-flex items-center gap-1 text-[11px] font-medium text-emerald-600">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                                    {{ __('Saved to knowledge') }}
                                </span>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
            <div x-show="loading" class="flex items-end justify-start gap-2">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-indigo-500 to-violet-500 text-white shadow-sm">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z" /></svg>
                </span>
                <div class="flex gap-1 rounded-2xl rounded-bl-sm bg-white px-3 py-2.5 ring-1 ring-gray-200">
                    <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-gray-400" style="animation-delay: 0ms"></span>
                    <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-gray-400" style="animation-delay: 150ms"></span>
                    <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-gray-400" style="animation-delay: 300ms"></span>
                </div>
            </div>

            {{-- Quick-start chips, shown only on a fresh chat (just the greeting) --}}
            <div x-show="!loading && messages.length <= 1" x-cloak class="space-y-2 pl-9">
                <p class="text-[11px] font-medium uppercase tracking-wide text-gray-400">{{ __('Try asking') }}</p>
                <div class="flex flex-wrap gap-1.5">
                    <template x-for="(s, i) in suggestions" :key="i">
                        <button type="button" @click="suggest(s.prompt)"
                                class="group flex items-center gap-1.5 rounded-full border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 shadow-sm transition hover:border-indigo-300 hover:bg-indigo-50 hover:text-indigo-700">
                            <span x-text="s.icon"></span>
                            <span x-text="s.label"></span>
                        </button>
                    </template>
                </div>
            </div>
        </div>

        <form x-show="view === 'chat'" @submit.prevent="send()" class="border-t border-gray-200 bg-white px-3 py-2.5">
            <input type="file" x-ref="file" class="hidden" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.txt,.csv,.json" @change="file = $event.target.files[0] || null">
            <div x-show="file" x-cloak class="mb-2 flex items-center gap-2 rounded-md bg-indigo-50 px-2 py-1 text-xs text-indigo-700">
                <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13" /></svg>
                <span class="truncate" x-text="file?.name"></span>
                <button type="button" @click="file = null; $refs.file.value = ''" class="ml-auto shrink-0 text-indigo-400 hover:text-indigo-700" aria-label="{{ __('Remove file') }}">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>
            <div class="flex items-end gap-2">
                <button type="button" @click="$refs.file.click()" :disabled="loading"
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 disabled:opacity-40" aria-label="{{ __('Attach file') }}" title="{{ __('Attach a file or image') }}">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13" /></svg>
                </button>
                <textarea x-model="input" @keydown.enter="if (! $event.shiftKey) { $event.preventDefault(); send(); }" rows="1" :disabled="loading"
                          placeholder="{{ __('Ask the assistant... (Shift+Enter for a new line)') }}"
                          class="max-h-24 flex-1 resize-none rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                <button type="submit" :disabled="loading || (!input.trim() && !file)"
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-indigo-600 to-violet-600 text-white shadow-sm transition hover:from-indigo-500 hover:to-violet-500 disabled:opacity-40" aria-label="{{ __('Send') }}">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" /></svg>
                </button>
            </div>
            <p class="mt-1.5 text-center text-[10px] text-gray-400">{{ __('Nexus AI can make mistakes — double-check important details.') }}</p>
        </form>
    </div>

    <button type="button" @click="toggle()"
            class="group relative flex h-14 w-14 items-center justify-center rounded-full bg-gradient-to-br from-indigo-600 to-violet-600 text-white shadow-lg shadow-indigo-500/30 transition hover:scale-105 hover:shadow-indigo-500/50"
            aria-label="{{ __('Open AI assistant') }}">
        <span x-show="!open" class="absolute inline-flex h-full w-full animate-ping rounded-full bg-indigo-400 opacity-20"></span>
        <svg x-show="!open" class="relative h-7 w-7 transition group-hover:rotate-12" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z" /></svg>
        <svg x-show="open" x-cloak class="relative h-7 w-7" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
        <span x-show="!open" class="absolute -right-0.5 -top-0.5 h-3 w-3 rounded-full bg-emerald-400 ring-2 ring-white"></span>
        {{-- Alert dot when there's a bug-status update to read --}}
        <span x-show="!open && hasBugUpdates" x-cloak class="absolute -left-0.5 -top-0.5 flex h-3.5 w-3.5">
            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-amber-400 opacity-75"></span>
            <span class="relative inline-flex h-3.5 w-3.5 rounded-full bg-amber-500 ring-2 ring-white"></span>
        </span>
    </button>
</div>

<script>
    function appAiChat() {
        return {
            open: false,
            view: 'chat',
            messages: [],
            _uidSeq: 0,
            conversations: [],
            currentId: null,
            input: '',
            file: null,
            loading: false,
            booted: false,
            suggestions: [
                { icon: '🎫', label: @js(__('My open tickets')), prompt: @js(__('What tickets are assigned to me that are still open?')) },
                { icon: '📊', label: @js(__('Tickets by status')), prompt: @js(__('Give me a pie chart of tickets by status.')) },
                { icon: '🧭', label: @js(__('Set up an SLA')), prompt: @js(__('How do I set up an SLA policy in this system?')) },
                { icon: '✨', label: @js(__('What can you do?')), prompt: @js(__('What can you help me with?')) },
            ],
            width: parseInt(localStorage.getItem('ai_chat_width')) || 352,
            height: parseInt(localStorage.getItem('ai_chat_height')) || 512,
            resizing: false,
            learnEnabled: {{ (bool) \App\Models\AppSetting::get('ai_learn_from_chat', false) ? 'true' : 'false' }},
            pendingBugUpdates: [],
            hasBugUpdates: false,
            messageUrl: '{{ route('assistant.message') }}',
            learnUrl: '{{ route('assistant.learn') }}',
            bugUpdatesUrl: '{{ route('assistant.bug-updates') }}',
            bugAckUrl: '{{ route('assistant.bug-updates.ack') }}',
            listUrl: '{{ route('assistant.conversations') }}',
            conversationUrl: '{{ route('assistant.conversation', ['conversation' => 'CONV_ID']) }}',
            csrf: document.querySelector('meta[name="csrf-token"]')?.content || '',

            init() {
                // Check for bug-status updates so the launcher can show an alert dot.
                this.fetchBugUpdates();
            },

            async fetchBugUpdates() {
                try {
                    const res = await fetch(this.bugUpdatesUrl, { headers: { 'Accept': 'application/json' } });
                    const data = await res.json();
                    this.pendingBugUpdates = data.updates || [];
                    this.hasBugUpdates = this.pendingBugUpdates.length > 0;
                } catch (e) { /* ignore */ }
            },

            // Surface reported-bug updates as assistant messages, then acknowledge them.
            async surfaceBugUpdates() {
                if (! this.pendingBugUpdates.length) return;
                for (const u of this.pendingBugUpdates) {
                    this.messages.push(this.tag({ role: 'assistant', text: u.message, system: true }));
                }
                this.pendingBugUpdates = [];
                this.hasBugUpdates = false;
                this.$nextTick(() => this.scrollToEnd());
                try {
                    await fetch(this.bugAckUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json' } });
                } catch (e) { /* ignore */ }
            },

            async toggle() {
                this.open = !this.open;
                if (this.open && !this.booted) {
                    this.booted = true;
                    await this.boot();
                }
                if (this.open) {
                    await this.surfaceBugUpdates();
                    if (this.view === 'chat') this.$nextTick(() => this.scrollToEnd());
                }
            },

            async boot() {
                await this.loadConversations();
                if (this.conversations.length) {
                    await this.openConversation(this.conversations[0].id);
                } else {
                    this.startNew();
                }
            },

            greet() {
                this.messages.push(this.tag({ role: 'assistant', text: @js(__('Hi :name! 👋 I\'m Nexus AI, your AI teammate. I can dig through your tickets, draw charts, draft replies, and learn from how your team has solved problems before. What can I help you with?', ['name' => Auth::user()->name])) }));
            },

            suggest(prompt) {
                if (this.loading) return;
                this.input = prompt;
                this.send();
            },

            startNew() {
                this.currentId = null;
                this.messages = [];
                this.input = '';
                this.greet();
                this.view = 'chat';
                this.$nextTick(() => this.scrollToEnd());
            },

            newChat() { this.startNew(); },

            async loadConversations() {
                try {
                    const res = await fetch(this.listUrl, { headers: { 'Accept': 'application/json' } });
                    const data = await res.json();
                    this.conversations = data.conversations || [];
                } catch (e) { /* ignore */ }
            },

            async openHistory() {
                await this.loadConversations();
                this.view = 'list';
            },

            async openConversation(id) {
                try {
                    const res = await fetch(this.conversationUrl.replace('CONV_ID', id), { headers: { 'Accept': 'application/json' } });
                    const data = await res.json();
                    this.currentId = data.conversation_id;
                    this.messages = this.hydrate(data.messages);
                } catch (e) {
                    this.startNew();
                    return;
                }
                this.view = 'chat';
                this.$nextTick(() => this.scrollToEnd());
            },

            async send() {
                const text = this.input.trim();
                const file = this.file;
                if ((!text && !file) || this.loading) return;
                const wasNew = !this.currentId;
                const isImage = file && (file.type || '').startsWith('image/');
                const preview = isImage ? URL.createObjectURL(file) : null;
                this.messages.push(this.tag({ role: 'user', text: text + (file && !isImage ? '\n📎 ' + file.name : ''), image: preview }));
                this.input = '';
                this.file = null;
                if (this.$refs.file) this.$refs.file.value = '';
                this.loading = true;
                this.$nextTick(() => this.scrollToEnd());
                try {
                    let res;
                    if (file) {
                        const fd = new FormData();
                        fd.append('message', text || 'Please read this file and tell me what it contains.');
                        if (this.currentId) fd.append('conversation_id', this.currentId);
                        fd.append('page_path', window.location.pathname);
                        fd.append('file', file);
                        res = await fetch(this.messageUrl, {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json' },
                            body: fd,
                        });
                    } else {
                        res = await fetch(this.messageUrl, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json' },
                            body: JSON.stringify({ message: text, conversation_id: this.currentId, page_path: window.location.pathname }),
                        });
                    }
                    const data = await res.json();
                    if (data.conversation_id) this.currentId = data.conversation_id;
                    this.pushAssistant(data.reply || @js(__('Sorry, something went wrong.')));
                    if (wasNew) this.loadConversations();
                } catch (e) {
                    this.messages.push(this.tag({ role: 'assistant', text: @js(__('Sorry, I could not reach the assistant. Please try again.')) }));
                } finally {
                    this.loading = false;
                    this.$nextTick(() => this.scrollToEnd());
                }
            },

            scrollToEnd() { const el = this.$refs.scroll; if (el) el.scrollTop = el.scrollHeight; },

            // Save a helpful assistant answer (with its preceding question) to learned knowledge.
            async saveToKnowledge(i) {
                const m = this.messages[i];
                if (!m || m.role === 'user' || m.saving || m.saved || !m.text) return;
                let question = '';
                for (let k = i - 1; k >= 0; k--) {
                    if (this.messages[k].role === 'user' && this.messages[k].text) { question = this.messages[k].text; break; }
                }
                if (!question) return;
                m.saving = true;
                try {
                    const res = await fetch(this.learnUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json' },
                        body: JSON.stringify({ question, answer: m.text }),
                    });
                    if (res.ok) m.saved = true;
                } catch (e) { /* ignore */ } finally {
                    m.saving = false;
                }
            },

            // Pull a chart spec out of the assistant text so its JSON never shows to the user.
            // Handles a ```chart / ```json fenced block, or a bare chart-shaped JSON object
            // (models sometimes forget the fence). Accepts single-series (data) or multi-series (series).
            parseChart(text) {
                text = text || '';
                const fence = text.match(/```(?:chart|json)?\s*([\s\S]*?)```/i);
                if (fence) {
                    const chart = this.tryChart(fence[1]);
                    if (chart) return { text: text.replace(fence[0], '').trim(), chart };
                }
                for (let i = 0; i < text.length; i++) {
                    if (text[i] !== '{') continue;
                    const block = this.balancedObject(text, i);
                    if (!block) break;
                    const chart = this.tryChart(block);
                    if (chart) return { text: (text.slice(0, i) + text.slice(i + block.length)).trim(), chart };
                }
                return { text: text.trim(), chart: null };
            },
            // Parse a candidate string; return it only if it has a chart shape.
            tryChart(raw) {
                try {
                    const c = JSON.parse((raw || '').trim());
                    if (c && c.type && (Array.isArray(c.data) || Array.isArray(c.series))) return c;
                } catch (e) { /* not a chart */ }
                return null;
            },
            // Return the balanced {...} substring beginning at `start` (brace-aware, string-aware).
            balancedObject(text, start) {
                let depth = 0, inStr = false, esc = false;
                for (let i = start; i < text.length; i++) {
                    const ch = text[i];
                    if (inStr) {
                        if (esc) esc = false;
                        else if (ch === '\\') esc = true;
                        else if (ch === '"') inStr = false;
                    } else if (ch === '"') inStr = true;
                    else if (ch === '{') depth++;
                    else if (ch === '}' && --depth === 0) return text.slice(start, i + 1);
                }
                return null;
            },
            pushAssistant(text) {
                const p = this.parseChart(text || '');
                this.messages.push(this.tag({ role: 'assistant', text: p.text, chart: p.chart }));
            },
            hydrate(messages) {
                return (messages || []).map((m) => {
                    if (m.role === 'assistant') { const p = this.parseChart(m.text || ''); return this.tag({ role: 'assistant', text: p.text, chart: p.chart }); }
                    return this.tag({ role: m.role, text: m.text });
                });
            },
            // Markdown + chart rendering via the shared window.NexusChat helpers.
            md(t) { return window.NexusChat ? window.NexusChat.renderMarkdown(t) : (t || ''); },
            chartH(c) { return window.NexusChat ? window.NexusChat.chartHeight(c) : 220; },
            renderChart(el, c) { if (window.NexusChat) window.NexusChat.renderApexChart(el, c); },
            // Stable per-message id so Alpine recreates chart nodes when switching conversations.
            tag(m) { m.uid = ++this._uidSeq; return m; },

            startResize(e, axis = 'x') {
                this.resizing = true;
                const startX = e.clientX, startY = e.clientY;
                const startWidth = this.width, startHeight = this.height;
                const maxWidth = () => Math.min(760, window.innerWidth - 48);
                const maxHeight = () => Math.min(900, window.innerHeight - 120);
                const onMove = (ev) => {
                    // Anchored bottom-right: drag the left edge to widen, the top edge to grow taller.
                    if (axis === 'x' || axis === 'both') {
                        this.width = Math.max(320, Math.min(startWidth + (startX - ev.clientX), maxWidth()));
                    }
                    if (axis === 'y' || axis === 'both') {
                        this.height = Math.max(320, Math.min(startHeight + (startY - ev.clientY), maxHeight()));
                    }
                };
                const onUp = () => {
                    this.resizing = false;
                    localStorage.setItem('ai_chat_width', this.width);
                    localStorage.setItem('ai_chat_height', this.height);
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
