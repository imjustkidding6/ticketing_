@extends('layouts.admin')

@section('title', 'AI Assistant Chat')

@section('content')
<div x-data="aiChatbotApp()" class="h-[calc(100vh-140px)] min-h-[550px] rounded-2xl border border-[var(--border-soft)] bg-[var(--bg-card)] shadow-xl flex flex-col md:flex-row overflow-hidden" x-cloak>
    
    <!-- Left Conversations Sidebar -->
    <div class="w-full md:w-80 bg-[var(--bg-sidebar)] border-r border-[var(--border-soft)] flex flex-col flex-shrink-0">
        
        <!-- Sidebar Top Bar: New Chat & Search -->
        <div class="p-4 border-b border-[var(--border-soft)] space-y-3">
            <button @click="createNewChat()" 
                    type="button"
                    class="w-full h-11 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-xs flex items-center justify-center gap-2 shadow-md shadow-indigo-500/20 transition-all cursor-pointer">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                <span>New Chat</span>
            </button>

            <div class="relative">
                <input x-model="searchQuery" 
                       type="text" 
                       placeholder="Search conversations..." 
                       class="w-full bg-[var(--bg-app)] text-[var(--text-primary)] placeholder-[var(--text-secondary)] text-xs rounded-xl pl-9 pr-3 py-2 border border-[var(--border-soft)] focus:outline-none focus:border-indigo-500 transition-all">
                <svg class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                </svg>
            </div>
        </div>

        <!-- Conversations List -->
        <div class="flex-1 overflow-y-auto p-2 space-y-1">
            <template x-for="conv in filteredConversations" :key="conv.id">
                <div @click="selectConversation(conv)"
                     class="group p-3 rounded-xl cursor-pointer transition-all flex items-center justify-between"
                     :class="activeConversation && activeConversation.id === conv.id ? 'bg-indigo-600/10 text-indigo-600 dark:text-indigo-400 font-semibold border border-indigo-500/20' : 'hover:bg-[var(--bg-hover)] text-[var(--text-primary)]'">
                    
                    <div class="flex items-center gap-3 overflow-hidden min-w-0">
                        <svg class="w-4 h-4 flex-shrink-0" :class="activeConversation && activeConversation.id === conv.id ? 'text-indigo-600 dark:text-indigo-400' : 'text-[var(--text-secondary)]'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" />
                        </svg>

                        <div class="truncate text-xs">
                            <span x-text="conv.title || 'Untitled Conversation'"></span>
                        </div>
                    </div>

                    <!-- Actions dropdown / quick delete -->
                    <div class="opacity-0 group-hover:opacity-100 flex items-center gap-1 transition-opacity">
                        <button @click.stop="promptRename(conv)" type="button" class="p-1 text-slate-400 hover:text-indigo-600 transition-colors" title="Rename">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487zm0 0L19.5 7.125" /></svg>
                        </button>
                        <button @click.stop="confirmDelete(conv)" type="button" class="p-1 text-slate-400 hover:text-rose-600 transition-colors" title="Delete">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
                        </button>
                    </div>
                </div>
            </template>

            <template x-if="filteredConversations.length === 0">
                <div class="p-6 text-center text-xs text-[var(--text-secondary)]">
                    No conversations found.
                </div>
            </template>
        </div>
    </div>

    <!-- Right Main Chat Workspace -->
    <div class="flex-1 flex flex-col bg-[var(--bg-card)] min-w-0">
        
        <!-- Main Workspace Header -->
        <div class="px-6 py-3.5 border-b border-[var(--border-soft)] bg-[var(--bg-header)] flex items-center justify-between flex-shrink-0">
            <div class="flex items-center gap-3 min-w-0">
                <div class="h-9 w-9 rounded-xl bg-gradient-to-tr from-indigo-600 to-indigo-500 flex items-center justify-center text-white font-bold text-sm shadow-md shadow-indigo-500/20">
                    AI
                </div>
                <div class="min-w-0">
                    <h2 class="text-xs font-bold text-[var(--text-primary)] truncate" x-text="activeConversation ? activeConversation.title : 'AI Chatbot Assistant'"></h2>
                    <p class="text-[11px] text-[var(--text-secondary)]">Enterprise Multi-Tenant AI Workspace</p>
                </div>
            </div>

            <!-- Export & Conversation Actions -->
            <template x-if="activeConversation">
                <div class="flex items-center gap-2">
                    <!-- Export Dropdown -->
                    <div class="relative" x-data="{ openExport: false }">
                        <button @click="openExport = !openExport" 
                                type="button" 
                                class="h-9 px-3 rounded-xl border border-[var(--border-soft)] bg-[var(--bg-app)] hover:bg-[var(--bg-hover)] text-xs font-semibold text-[var(--text-primary)] flex items-center gap-1.5 transition-all cursor-pointer">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                            <span>Export</span>
                        </button>

                        <div x-show="openExport" 
                             @click.outside="openExport = false" 
                             x-transition 
                             class="absolute right-0 mt-2 w-36 bg-[var(--bg-card)] border border-[var(--border-soft)] rounded-xl shadow-xl p-1 z-50 text-xs">
                            <a :href="'/admin/ai/chatbot/conversations/' + activeConversation.id + '/export/json'" target="_blank" class="block px-3 py-2 rounded-lg hover:bg-[var(--bg-hover)] text-[var(--text-primary)] font-medium">Export JSON</a>
                            <a :href="'/admin/ai/chatbot/conversations/' + activeConversation.id + '/export/csv'" target="_blank" class="block px-3 py-2 rounded-lg hover:bg-[var(--bg-hover)] text-[var(--text-primary)] font-medium">Export CSV</a>
                            <a :href="'/admin/ai/chatbot/conversations/' + activeConversation.id + '/export/html'" target="_blank" class="block px-3 py-2 rounded-lg hover:bg-[var(--bg-hover)] text-[var(--text-primary)] font-medium">Export HTML</a>
                        </div>
                    </div>

                    <!-- Rename Button -->
                    <button @click="promptRename(activeConversation)" 
                            type="button" 
                            class="h-9 px-3 rounded-xl border border-[var(--border-soft)] bg-[var(--bg-app)] hover:bg-[var(--bg-hover)] text-xs font-semibold text-[var(--text-primary)] flex items-center gap-1.5 transition-all cursor-pointer">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487zm0 0L19.5 7.125" /></svg>
                        <span>Rename</span>
                    </button>

                    <!-- Delete Button -->
                    <button @click="confirmDelete(activeConversation)" 
                            type="button" 
                            class="h-9 px-3 rounded-xl border border-rose-500/20 bg-rose-500/10 hover:bg-rose-500/20 text-xs font-semibold text-rose-500 flex items-center gap-1.5 transition-all cursor-pointer">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
                        <span>Delete</span>
                    </button>
                </div>
            </template>
        </div>

        <!-- Messages Feed Container -->
        <div x-ref="messagesFeed" class="flex-1 overflow-y-auto p-6 space-y-6 bg-[var(--bg-app)]/40 scroll-smooth">
            
            <!-- Empty State -->
            <template x-if="messages.length === 0">
                <div class="h-full flex flex-col items-center justify-center text-center p-8 space-y-4">
                    <div class="w-16 h-16 rounded-2xl bg-indigo-600/10 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shadow-lg shadow-indigo-500/10">
                        <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-[var(--text-primary)]">How can I help you today?</h3>
                        <p class="text-xs text-[var(--text-secondary)] mt-1 max-w-sm">Ask questions, generate system diagnostics, create code snippets, or analyze platform metrics.</p>
                    </div>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-w-lg w-full pt-4">
                        <button @click="sendSuggestedMessage('How many active tenants do we have?')" type="button" class="p-3 rounded-xl bg-[var(--bg-card)] hover:border-indigo-500 border border-[var(--border-soft)] text-left text-xs text-[var(--text-primary)] transition-all shadow-xs cursor-pointer">
                            <strong>Tenant Stats</strong>
                            <p class="text-[11px] text-[var(--text-secondary)]">How many active tenants do we have?</p>
                        </button>
                        <button @click="sendSuggestedMessage('Show license expiration report.')" type="button" class="p-3 rounded-xl bg-[var(--bg-card)] hover:border-indigo-500 border border-[var(--border-soft)] text-left text-xs text-[var(--text-primary)] transition-all shadow-xs cursor-pointer">
                            <strong>Licenses</strong>
                            <p class="text-[11px] text-[var(--text-secondary)]">Show license expiration report.</p>
                        </button>
                    </div>
                </div>
            </template>

            <!-- Messages Loop -->
            <template x-for="msg in messages" :key="msg.id || msg.timestamp">
                <div class="space-y-1">
                    <!-- User Message -->
                    <template x-if="msg.role === 'user'">
                        <div class="flex items-start justify-end gap-3">
                            <div class="max-w-[80%] space-y-1">
                                <div class="p-4 rounded-2xl rounded-tr-none bg-indigo-600 text-white text-xs leading-relaxed shadow-md shadow-indigo-600/10">
                                    <p class="whitespace-pre-wrap" x-text="msg.content"></p>
                                </div>
                                <span class="text-[10px] text-slate-400 block text-right" x-text="formatTime(msg.created_at || msg.timestamp)"></span>
                            </div>
                            <div class="h-8 w-8 rounded-xl bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-200 flex items-center justify-center font-bold text-xs flex-shrink-0">
                                Admin
                            </div>
                        </div>
                    </template>

                    <!-- Assistant Message -->
                    <template x-if="msg.role === 'assistant'">
                        <div class="flex items-start gap-3">
                            <div class="h-8 w-8 rounded-xl bg-indigo-600/10 dark:bg-indigo-500/20 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-bold text-xs flex-shrink-0">
                                AI
                            </div>
                            <div class="max-w-[85%] space-y-1">
                                <div class="p-4 rounded-2xl rounded-tl-none bg-[var(--bg-card)] border border-[var(--border-soft)] text-xs leading-relaxed text-[var(--text-primary)] shadow-sm space-y-3">
                                    <div x-html="renderMarkdown(msg.content)"></div>
                                </div>
                                <span class="text-[10px] text-slate-400 block" x-text="formatTime(msg.created_at || msg.timestamp)"></span>
                            </div>
                        </div>
                    </template>
                </div>
            </template>

            <!-- Typing Indicator -->
            <template x-if="isTyping">
                <div class="flex items-start gap-3">
                    <div class="h-8 w-8 rounded-xl bg-indigo-600/10 dark:bg-indigo-500/20 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-bold text-xs flex-shrink-0">
                        AI
                    </div>
                    <div class="p-4 rounded-2xl rounded-tl-none bg-[var(--bg-card)] border border-[var(--border-soft)] shadow-sm flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-indigo-500 animate-bounce" style="animation-delay: 0ms;"></span>
                        <span class="w-2 h-2 rounded-full bg-indigo-500 animate-bounce" style="animation-delay: 150ms;"></span>
                        <span class="w-2 h-2 rounded-full bg-indigo-500 animate-bounce" style="animation-delay: 300ms;"></span>
                    </div>
                </div>
            </template>
        </div>

        <!-- Bottom ChatGPT-Style Input Bar -->
        <div class="p-4 border-t border-[var(--border-soft)] bg-[var(--bg-card)] flex-shrink-0">
            <!-- Listening Status Indicator -->
            <div x-show="isListening" 
                 x-transition 
                 class="mb-2 px-3 py-1.5 rounded-lg bg-rose-500/10 border border-rose-500/20 flex items-center justify-between text-xs text-rose-500 font-medium">
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-rose-500 animate-ping"></span>
                    <span>Listening (Web Speech API)... Speak now</span>
                </div>
                <button @click="stopListening()" type="button" class="text-[11px] underline font-semibold cursor-pointer">Stop</button>
            </div>

            <form @submit.prevent="sendMessage()" class="relative flex items-end gap-2">
                <!-- Action Buttons: Attachment (Placeholder) & Microphone -->
                <div class="flex items-center gap-1 pb-1.5">
                    <!-- Attachment Placeholder -->
                    <button type="button" 
                            title="Attach File (UI Placeholder)"
                            class="p-2 rounded-xl text-[var(--text-secondary)] hover:bg-[var(--bg-hover)] transition-colors cursor-pointer">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13" /></svg>
                    </button>

                    <!-- Microphone Voice Input -->
                    <button @click="toggleVoiceInput()" 
                            type="button" 
                            :title="isListening ? 'Stop Listening' : 'Voice Input (Web Speech API)'"
                            class="p-2 rounded-xl transition-all cursor-pointer"
                            :class="isListening ? 'bg-rose-500 text-white animate-pulse shadow-md shadow-rose-500/30' : 'text-[var(--text-secondary)] hover:bg-[var(--bg-hover)] hover:text-indigo-600'">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 18.75a6 6 0 006-6v-1.5m-6 7.5a6 6 0 01-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 003-3V4.5a3 3 0 00-3-3 3 3 0 00-3 3v8.25a3 3 0 003 3z" /></svg>
                    </button>
                </div>

                <!-- Text Input Area -->
                <textarea x-model="input" 
                          x-ref="inputTextarea"
                          @keydown.enter.exact.prevent="sendMessage()"
                          rows="1"
                          placeholder="Send a message to AI Assistant (Shift+Enter for newline)..." 
                          :disabled="isTyping"
                          class="flex-1 bg-[var(--bg-app)] text-[var(--text-primary)] text-xs rounded-xl p-3 border border-[var(--border-soft)] focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-all resize-none max-h-32 min-h-[44px]"></textarea>

                <!-- Send Button -->
                <button type="submit" 
                        :disabled="!input.trim() || isTyping"
                        class="h-11 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-700 disabled:opacity-40 text-white transition-all cursor-pointer flex items-center justify-center flex-shrink-0 shadow-md shadow-indigo-600/20">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" /></svg>
                </button>
            </form>
        </div>
    </div>
</div>

<script>
function aiChatbotApp() {
    return {
        conversations: [],
        activeConversation: null,
        messages: [],
        input: '',
        searchQuery: '',
        isTyping: false,
        isListening: false,
        recognition: null,

        get filteredConversations() {
            if (!this.searchQuery.trim()) return this.conversations;
            const q = this.searchQuery.toLowerCase();
            return this.conversations.filter(c => (c.title || '').toLowerCase().includes(q));
        },

        init() {
            this.fetchConversations();
            this.initSpeechRecognition();
        },

        async fetchConversations() {
            try {
                const res = await fetch('{{ route('admin.ai.chatbot.conversations') }}');
                const data = await res.json();
                if (data.success) {
                    this.conversations = data.data;
                    if (this.conversations.length > 0 && !this.activeConversation) {
                        this.selectConversation(this.conversations[0]);
                    }
                }
            } catch (e) {
                console.error('Failed to fetch conversations:', e);
            }
        },

        async selectConversation(conv) {
            this.activeConversation = conv;
            this.messages = [];
            try {
                const res = await fetch('/admin/ai/chatbot/conversations/' + conv.id);
                const data = await res.json();
                if (data.success) {
                    this.messages = data.messages;
                    this.$nextTick(() => this.scrollToBottom());
                }
            } catch (e) {
                console.error('Failed to fetch messages:', e);
            }
        },

        async createNewChat() {
            try {
                const res = await fetch('{{ route('admin.ai.chatbot.start') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    },
                    body: JSON.stringify({ title: 'New AI Conversation' })
                });
                const data = await res.json();
                if (data.success) {
                    this.conversations.unshift(data.conversation);
                    this.selectConversation(data.conversation);
                }
            } catch (e) {
                console.error('Failed to create new chat:', e);
            }
        },

        sendSuggestedMessage(text) {
            this.input = text;
            this.sendMessage();
        },

        async sendMessage() {
            const text = this.input.trim();
            if (!text || this.isTyping) return;

            if (!this.activeConversation) {
                await this.createNewChat();
            }

            const currentConvId = this.activeConversation.id;

            // Push User message locally
            const userMsg = { role: 'user', content: text, created_at: new Date().toISOString() };
            this.messages.push(userMsg);
            this.input = '';
            this.isTyping = true;
            this.$nextTick(() => this.scrollToBottom());

            try {
                const res = await fetch('/admin/ai/chatbot/conversations/' + currentConvId + '/send', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    },
                    body: JSON.stringify({ message: text })
                });

                const data = await res.json();
                if (data.success) {
                    this.messages.push(data.assistantMessage);
                    if (data.conversation) {
                        const idx = this.conversations.findIndex(c => c.id === data.conversation.id);
                        if (idx !== -1) {
                            this.conversations[idx] = data.conversation;
                        }
                        this.activeConversation = data.conversation;
                    }
                }
            } catch (e) {
                console.error('Send message failed:', e);
            } finally {
                this.isTyping = false;
                this.$nextTick(() => this.scrollToBottom());
            }
        },

        async promptRename(conv) {
            const newTitle = prompt('Enter new conversation title:', conv.title || '');
            if (!newTitle || newTitle.trim() === '') return;

            try {
                const res = await fetch('/admin/ai/chatbot/conversations/' + conv.id + '/rename', {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    },
                    body: JSON.stringify({ title: newTitle.trim() })
                });
                const data = await res.json();
                if (data.success) {
                    conv.title = newTitle.trim();
                    if (this.activeConversation && this.activeConversation.id === conv.id) {
                        this.activeConversation.title = newTitle.trim();
                    }
                }
            } catch (e) {
                console.error('Rename conversation failed:', e);
            }
        },

        async confirmDelete(conv) {
            if (!confirm('Are you sure you want to delete this conversation?')) return;

            try {
                const res = await fetch('/admin/ai/chatbot/conversations/' + conv.id, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    }
                });
                const data = await res.json();
                if (data.success) {
                    this.conversations = this.conversations.filter(c => c.id !== conv.id);
                    if (this.activeConversation && this.activeConversation.id === conv.id) {
                        this.activeConversation = this.conversations[0] || null;
                        if (this.activeConversation) {
                            this.selectConversation(this.activeConversation);
                        } else {
                            this.messages = [];
                        }
                    }
                }
            } catch (e) {
                console.error('Delete conversation failed:', e);
            }
        },

        initSpeechRecognition() {
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            if (SpeechRecognition) {
                this.recognition = new SpeechRecognition();
                this.recognition.continuous = false;
                this.recognition.interimResults = true;
                this.recognition.lang = 'en-US';

                this.recognition.onresult = (event) => {
                    let transcript = '';
                    for (let i = event.resultIndex; i < event.results.length; i++) {
                        transcript += event.results[i][0].transcript;
                    }
                    this.input = transcript;
                };

                this.recognition.onerror = () => { this.isListening = false; };
                this.recognition.onend = () => { this.isListening = false; };
            }
        },

        toggleVoiceInput() {
            if (!this.recognition) {
                alert('Voice input (Web Speech API) is not supported in this browser.');
                return;
            }
            if (this.isListening) {
                this.stopListening();
            } else {
                try {
                    this.isListening = true;
                    this.recognition.start();
                } catch (e) {
                    this.isListening = false;
                }
            }
        },

        stopListening() {
            if (this.recognition && this.isListening) {
                this.recognition.stop();
                this.isListening = false;
            }
        },

        scrollToBottom() {
            const feed = this.$refs.messagesFeed;
            if (feed) feed.scrollTop = feed.scrollHeight;
        },

        formatTime(dateStr) {
            if (!dateStr) return '';
            try {
                return new Date(dateStr).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            } catch (e) {
                return '';
            }
        },

        renderMarkdown(content) {
            if (!content) return '';

            let formatted = content;

            // Code blocks ```code```
            formatted = formatted.replace(/```([\s\S]*?)```/g, (match, p1) => {
                const cleanCode = p1.replace(/^([a-zA-Z]+)\n/, '');
                const escaped = cleanCode.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                return `<div class="my-2 rounded-xl bg-slate-900 text-slate-100 p-3 overflow-x-auto text-[11px] font-mono relative group">
                    <button onclick="navigator.clipboard.writeText(\`${cleanCode.replace(/`/g, '\\`')}\`); alert('Code copied to clipboard!');" type="button" class="absolute top-2 right-2 px-2 py-1 rounded bg-slate-800 hover:bg-slate-700 text-[10px] text-slate-300 transition-colors">Copy</button>
                    <pre><code>${escaped}</code></pre>
                </div>`;
            });

            // Bold & Italics
            formatted = formatted.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
            formatted = formatted.replace(/\*(.*?)\*/g, '<em>$1</em>');

            // Inline code `code`
            formatted = formatted.replace(/`([^`]+)`/g, '<code class="bg-[var(--bg-hover)] px-1.5 py-0.5 rounded text-[11px] font-mono text-indigo-600 dark:text-indigo-400">$1</code>');

            // Line breaks
            formatted = formatted.replace(/\n/g, '<br>');

            return formatted;
        }
    };
}
</script>
@endsection
