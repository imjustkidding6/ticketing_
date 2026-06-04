{{-- In-app AI assistant for logged-in team members. Per-user memory with a conversation history (ChatGPT-style). --}}
<div x-data="appAiChat()" x-cloak class="fixed bottom-24 right-6 z-40">
    {{-- Panel --}}
    <div x-show="open"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         class="mb-3 flex h-[32rem] max-h-[75vh] w-[22rem] max-w-[calc(100vw-3rem)] flex-col overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-black/5">
        <div class="flex items-center justify-between bg-indigo-600 px-4 py-3 text-white">
            <div class="flex items-center gap-2">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z" /></svg>
                <span class="text-sm font-semibold" x-text="view === 'list' ? @js(__('Your conversations')) : @js(__('AI Assistant'))"></span>
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
            <template x-for="(m, i) in messages" :key="i">
                <div :class="m.role === 'user' ? 'flex justify-end' : 'flex justify-start'">
                    <div :class="m.role === 'user' ? 'rounded-2xl rounded-br-sm bg-indigo-600 px-3 py-2 text-sm text-white' : 'rounded-2xl rounded-bl-sm bg-white px-3 py-2 text-sm text-gray-800 ring-1 ring-gray-200'"
                         class="max-w-[85%] whitespace-pre-line break-words" x-text="m.text"></div>
                </div>
            </template>
            <div x-show="loading" class="flex justify-start">
                <div class="flex gap-1 rounded-2xl rounded-bl-sm bg-white px-3 py-2.5 ring-1 ring-gray-200">
                    <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-gray-400" style="animation-delay: 0ms"></span>
                    <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-gray-400" style="animation-delay: 150ms"></span>
                    <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-gray-400" style="animation-delay: 300ms"></span>
                </div>
            </div>
        </div>

        <form x-show="view === 'chat'" @submit.prevent="send()" class="flex items-end gap-2 border-t border-gray-200 bg-white px-3 py-2.5">
            <textarea x-model="input" @keydown.enter.prevent="send()" rows="1" :disabled="loading"
                      placeholder="{{ __('Ask the assistant...') }}"
                      class="max-h-24 flex-1 resize-none rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
            <button type="submit" :disabled="loading || !input.trim()"
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-indigo-600 text-white hover:bg-indigo-500 disabled:opacity-40" aria-label="{{ __('Send') }}">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" /></svg>
            </button>
        </form>
    </div>

    <button type="button" @click="toggle()"
            class="flex h-14 w-14 items-center justify-center rounded-full bg-indigo-600 text-white shadow-lg transition hover:scale-105 hover:bg-indigo-500"
            aria-label="{{ __('Open AI assistant') }}">
        <svg x-show="!open" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z" /></svg>
        <svg x-show="open" x-cloak class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
    </button>
</div>

<script>
    function appAiChat() {
        return {
            open: false,
            view: 'chat',
            messages: [],
            conversations: [],
            currentId: null,
            input: '',
            loading: false,
            booted: false,
            messageUrl: '{{ route('assistant.message') }}',
            listUrl: '{{ route('assistant.conversations') }}',
            conversationUrl: '{{ route('assistant.conversation', ['conversation' => 'CONV_ID']) }}',
            csrf: document.querySelector('meta[name="csrf-token"]')?.content || '',

            async toggle() {
                this.open = !this.open;
                if (this.open && !this.booted) {
                    this.booted = true;
                    await this.boot();
                }
                if (this.open && this.view === 'chat') this.$nextTick(() => this.scrollToEnd());
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
                this.messages.push({ role: 'assistant', text: @js(__('Hi :name! I can find answers in your knowledge base, check a ticket\'s status, or open a ticket. What do you need?', ['name' => Auth::user()->name])) });
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
                    this.messages = data.messages || [];
                } catch (e) {
                    this.startNew();
                    return;
                }
                this.view = 'chat';
                this.$nextTick(() => this.scrollToEnd());
            },

            async send() {
                const text = this.input.trim();
                if (!text || this.loading) return;
                const wasNew = !this.currentId;
                this.messages.push({ role: 'user', text });
                this.input = '';
                this.loading = true;
                this.$nextTick(() => this.scrollToEnd());
                try {
                    const res = await fetch(this.messageUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json' },
                        body: JSON.stringify({ message: text, conversation_id: this.currentId }),
                    });
                    const data = await res.json();
                    if (data.conversation_id) this.currentId = data.conversation_id;
                    this.messages.push({ role: 'assistant', text: data.reply || @js(__('Sorry, something went wrong.')) });
                    if (wasNew) this.loadConversations();
                } catch (e) {
                    this.messages.push({ role: 'assistant', text: @js(__('Sorry, I could not reach the assistant. Please try again.')) });
                } finally {
                    this.loading = false;
                    this.$nextTick(() => this.scrollToEnd());
                }
            },

            scrollToEnd() { const el = this.$refs.scroll; if (el) el.scrollTop = el.scrollHeight; },
        };
    }
</script>
