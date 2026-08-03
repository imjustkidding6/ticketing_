<!-- AI Admin Copilot Floating Button & Interface -->
<div x-data="adminAiCopilot()" class="relative z-[60]" x-cloak>
    
    <!-- Floating Launcher Button -->
    <button @click="toggleCopilot()" 
            type="button"
            class="fixed bottom-6 right-6 z-[60] flex items-center gap-2.5 px-4 py-3 rounded-full bg-gradient-to-r from-indigo-600 to-indigo-700 hover:from-indigo-500 hover:to-indigo-600 text-white font-medium text-sm shadow-xl shadow-indigo-500/25 hover:shadow-indigo-500/40 transition-all duration-300 transform hover:-translate-y-0.5 cursor-pointer border border-indigo-400/30">
        <div class="relative flex items-center justify-center">
            <svg class="w-5 h-5 text-white animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z" />
            </svg>
            <span class="absolute -top-1 -right-1 flex h-2.5 w-2.5">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
            </span>
        </div>
        <span class="font-semibold tracking-tight hidden sm:inline">AI Admin Copilot</span>
    </button>

    <!-- Chat Modal Drawer Container -->
    <div x-show="isOpen" 
         x-transition:enter="transition ease-out duration-300 transform"
         x-transition:enter-start="opacity-0 translate-y-4 scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         x-transition:leave="transition ease-in duration-200 transform"
         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
         x-transition:leave-end="opacity-0 translate-y-4 scale-95"
         class="fixed bottom-20 right-4 sm:right-6 z-[65] w-[calc(100vw-2rem)] sm:w-[420px] h-[580px] max-h-[82vh] rounded-2xl bg-[var(--bg-card)] border border-[var(--border-soft)] shadow-2xl flex flex-col overflow-hidden backdrop-blur-xl">
        
        <!-- Header -->
        <div class="px-5 py-4 bg-[var(--bg-header)] border-b border-[var(--border-soft)] flex items-center justify-between flex-shrink-0">
            <div class="flex items-center gap-3">
                <div class="h-9 w-9 rounded-xl bg-gradient-to-tr from-indigo-600 to-indigo-500 flex items-center justify-center text-white shadow-md shadow-indigo-500/20">
                    <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-[var(--text-primary)] leading-none">AI Admin Copilot</h3>
                    <div class="flex items-center gap-1.5 mt-1">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span class="text-[11px] font-medium text-[var(--text-secondary)]">Online & Context Aware</span>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-1">
                <!-- Clear Chat History Button -->
                <button @click="clearHistory()" 
                        title="Clear Chat History"
                        type="button" 
                        class="p-2 rounded-xl text-[var(--text-secondary)] hover:bg-[var(--bg-hover)] transition-colors cursor-pointer">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                    </svg>
                </button>

                <!-- Close Modal Button -->
                <button @click="isOpen = false" 
                        type="button" 
                        class="p-2 rounded-xl text-[var(--text-secondary)] hover:bg-[var(--bg-hover)] transition-colors cursor-pointer">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Chat Messages Container -->
        <div x-ref="chatContainer" class="flex-1 overflow-y-auto p-4 space-y-4 bg-[var(--bg-app)]/50 scroll-smooth">
            
            <!-- Welcome Message & Suggested Quick Action Chips -->
            <div class="flex items-start gap-3">
                <div class="h-8 w-8 rounded-xl bg-indigo-600/10 dark:bg-indigo-500/20 text-indigo-600 dark:text-indigo-400 flex items-center justify-center flex-shrink-0 font-bold text-xs">
                    AI
                </div>
                <div class="flex-1 space-y-2">
                    <div class="p-3.5 rounded-2xl rounded-tl-none bg-[var(--bg-card)] border border-[var(--border-soft)] text-xs leading-relaxed text-[var(--text-primary)] shadow-sm">
                        Hello Administrator! I am your AI Admin Copilot. Ask me questions about system metrics, tenants, licenses, or ask me to help you navigate.
                    </div>
                    
                    <!-- Quick Suggestion Chips -->
                    <div class="flex flex-wrap gap-1.5 pt-1">
                        <template x-for="(chip, i) in suggestionChips" :key="i">
                            <button @click="sendSuggestedQuery(chip)" 
                                    type="button"
                                    class="text-[11px] font-medium px-2.5 py-1 rounded-lg bg-[var(--bg-card)] hover:bg-indigo-600 hover:text-white text-[var(--text-secondary)] border border-[var(--border-soft)] transition-all cursor-pointer shadow-2xs">
                                <span x-text="chip"></span>
                            </button>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Messages Loop -->
            <template x-for="(msg, index) in messages" :key="index">
                <div>
                    <!-- User Message -->
                    <template x-if="msg.sender === 'user'">
                        <div class="flex items-start justify-end gap-3">
                            <div class="p-3.5 rounded-2xl rounded-tr-none bg-indigo-600 text-white text-xs leading-relaxed max-w-[85%] shadow-md shadow-indigo-600/15">
                                <p x-text="msg.text"></p>
                            </div>
                            <div class="h-8 w-8 rounded-xl bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-200 flex items-center justify-center flex-shrink-0 font-bold text-xs">
                                Admin
                            </div>
                        </div>
                    </template>

                    <!-- AI Message -->
                    <template x-if="msg.sender === 'ai'">
                        <div class="flex items-start gap-3">
                            <div class="h-8 w-8 rounded-xl bg-indigo-600/10 dark:bg-indigo-500/20 text-indigo-600 dark:text-indigo-400 flex items-center justify-center flex-shrink-0 font-bold text-xs">
                                AI
                            </div>
                            <div class="space-y-2 max-w-[85%]">
                                <div class="p-3.5 rounded-2xl rounded-tl-none bg-[var(--bg-card)] border border-[var(--border-soft)] text-xs leading-relaxed text-[var(--text-primary)] shadow-sm space-y-2">
                                    <div x-html="formatMarkdown(msg.text)"></div>

                                    <!-- Safe Action Clickable Button -->
                                    <template x-if="msg.action">
                                        <div class="pt-2">
                                            <a :href="msg.action.url" 
                                               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-[11px] font-semibold transition-all shadow-xs cursor-pointer">
                                                <span x-text="msg.action.label"></span>
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                                                </svg>
                                            </a>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </template>

            <!-- Typing Indicator -->
            <template x-if="isTyping">
                <div class="flex items-start gap-3">
                    <div class="h-8 w-8 rounded-xl bg-indigo-600/10 dark:bg-indigo-500/20 text-indigo-600 dark:text-indigo-400 flex items-center justify-center flex-shrink-0 font-bold text-xs">
                        AI
                    </div>
                    <div class="p-3.5 rounded-2xl rounded-tl-none bg-[var(--bg-card)] border border-[var(--border-soft)] shadow-sm flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-indigo-500 animate-bounce" style="animation-delay: 0ms;"></span>
                        <span class="w-2 h-2 rounded-full bg-indigo-500 animate-bounce" style="animation-delay: 150ms;"></span>
                        <span class="w-2 h-2 rounded-full bg-indigo-500 animate-bounce" style="animation-delay: 300ms;"></span>
                    </div>
                </div>
            </template>
        </div>

        <!-- Input Area (Text & Voice) -->
        <div class="p-3 bg-[var(--bg-card)] border-t border-[var(--border-soft)] flex-shrink-0">
            <!-- Listening Status Indicator Bar -->
            <div x-show="isListening" 
                 x-transition 
                 class="mb-2 px-3 py-1.5 rounded-lg bg-rose-500/10 border border-rose-500/20 flex items-center justify-between text-xs text-rose-500 font-medium">
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-rose-500 animate-ping"></span>
                    <span>Listening to your voice...</span>
                </div>
                <button @click="stopListening()" type="button" class="text-[11px] underline font-semibold hover:text-rose-600 cursor-pointer">Stop</button>
            </div>

            <form @submit.prevent="sendMessage()" class="flex items-center gap-2">
                <!-- Microphone Button -->
                <button @click="toggleVoiceInput()" 
                        type="button" 
                        :title="isListening ? 'Stop Listening' : 'Voice Input (Web Speech API)'"
                        class="p-2.5 rounded-xl transition-all cursor-pointer flex-shrink-0"
                        :class="isListening ? 'bg-rose-500 text-white animate-pulse shadow-md shadow-rose-500/30' : 'bg-[var(--bg-hover)] text-[var(--text-secondary)] hover:text-indigo-600'">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 18.75a6 6 0 006-6v-1.5m-6 7.5a6 6 0 01-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 003-3V4.5a3 3 0 00-3-3 3 3 0 00-3 3v8.25a3 3 0 003 3z" />
                    </svg>
                </button>

                <!-- Input Field -->
                <input x-model="input" 
                       x-ref="inputField"
                       type="text" 
                       placeholder="Ask AI Copilot or click mic..." 
                       :disabled="isTyping"
                       class="flex-1 bg-[var(--bg-app)] text-[var(--text-primary)] text-xs rounded-xl px-3.5 py-2.5 border border-[var(--border-soft)] focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-all">

                <!-- Send Button -->
                <button type="submit" 
                        :disabled="!input.trim() || isTyping"
                        class="p-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 disabled:opacity-40 text-white transition-all cursor-pointer flex-shrink-0 shadow-xs">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
                    </svg>
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Alpine.js Component & Web Speech API Script -->
<script>
function adminAiCopilot() {
    return {
        isOpen: false,
        input: '',
        isTyping: false,
        isListening: false,
        recognition: null,
        messages: [],
        suggestionChips: [
            "How many tenants are active?",
            "Show expired licenses.",
            "How many tickets were created today?",
            "What does Starter plan include?",
            "Where can I manage users?"
        ],

        init() {
            this.initSpeechRecognition();
        },

        toggleCopilot() {
            this.isOpen = !this.isOpen;
            if (this.isOpen) {
                this.$nextTick(() => {
                    this.scrollToBottom();
                    this.$refs.inputField?.focus();
                });
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

                this.recognition.onerror = (event) => {
                    console.warn('Speech recognition error:', event.error);
                    this.isListening = false;
                };

                this.recognition.onend = () => {
                    this.isListening = false;
                };
            }
        },

        toggleVoiceInput() {
            if (!this.recognition) {
                window.dispatchEvent(new CustomEvent('toast', {
                    detail: { message: 'Voice input (Web Speech API) is not supported in this browser.', type: 'info' }
                }));
                return;
            }

            if (this.isListening) {
                this.stopListening();
            } else {
                try {
                    this.isListening = true;
                    this.recognition.start();
                } catch (e) {
                    console.error('Speech recognition start error:', e);
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

        sendSuggestedQuery(chipText) {
            this.input = chipText;
            this.sendMessage();
        },

        async sendMessage() {
            const text = this.input.trim();
            if (!text || this.isTyping) return;

            if (this.isListening) {
                this.stopListening();
            }

            // Append User Message
            this.messages.push({
                sender: 'user',
                text: text,
                timestamp: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
            });

            this.input = '';
            this.isTyping = true;
            this.$nextTick(() => this.scrollToBottom());

            try {
                const response = await fetch('{{ route('admin.ai.chat') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    },
                    body: JSON.stringify({ message: text })
                });

                const data = await response.json();

                if (response.ok && data.success) {
                    this.messages.push({
                        sender: 'ai',
                        text: data.message,
                        action: data.action,
                        timestamp: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
                    });
                } else {
                    this.messages.push({
                        sender: 'ai',
                        text: data.message || 'An error occurred while processing your request.',
                        action: null,
                        timestamp: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
                    });
                }
            } catch (error) {
                console.error('AI Copilot request error:', error);
                this.messages.push({
                    sender: 'ai',
                    text: 'Unable to connect to AI server. Please check your network connection.',
                    action: null,
                    timestamp: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
                });
            } finally {
                this.isTyping = false;
                this.$nextTick(() => this.scrollToBottom());
            }
        },

        clearHistory() {
            this.messages = [];
        },

        scrollToBottom() {
            const container = this.$refs.chatContainer;
            if (container) {
                container.scrollTop = container.scrollHeight;
            }
        },

        formatMarkdown(text) {
            if (!text) return '';
            let formatted = text
                .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                .replace(/\*(.*?)\*/g, '<em>$1</em>')
                .replace(/`([^`]+)`/g, '<code class="bg-[var(--bg-hover)] px-1 py-0.5 rounded text-[11px]">$1</code>')
                .replace(/\n/g, '<br>');
            return formatted;
        }
    };
}
</script>
