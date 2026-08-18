                    @php $taskCopilot = app(\App\Services\PlanService::class)->currentTenantHasFeature(\App\Enums\PlanFeature::AiChatbot) && (bool) \App\Models\AppSetting::get('ai_agent_copilot_enabled', false); @endphp
                    <div class="rounded-xl bg-white p-6 shadow-sm" @if($taskCopilot) x-data="taskListPolish(@js(route('tickets.ai.polish-tasks', $ticket)))" @endif>
                        <div class="flex items-center justify-between">
                            <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-400">{{ __('Task Checklist') }}</h4>
                            <div class="flex items-center gap-3">
                                <span class="text-sm text-gray-500">
                                    {{ $ticket->tasks->where('status', 'completed')->count() }}/{{ $ticket->tasks->count() }} {{ __('completed') }}
                                </span>
                                @if($taskCopilot && !in_array($ticket->status, ['closed', 'cancelled']))
                                    <button type="button" @click="preview()" :disabled="loading" title="{{ __('Review the whole checklist with AI') }}" class="inline-flex items-center gap-1 rounded-md bg-white px-2.5 py-1 text-xs font-medium text-indigo-700 ring-1 ring-indigo-200 hover:bg-indigo-50 disabled:opacity-50">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" /></svg>
                                        <span x-show="!loading">{{ __('Polish list') }}</span>
                                        <span x-show="loading" x-cloak>{{ __('Reviewing...') }}</span>
                                    </button>
                                @endif
                            </div>
                        </div>
                        @if($taskCopilot)
                            <p x-show="error" x-cloak class="mt-2 text-xs text-red-600" x-text="error"></p>
                        @endif

                        @if($ticket->tasks->count() > 0)
                            <ul class="mt-4 divide-y divide-gray-200">
                                @foreach($ticket->tasks->sortBy('sort_order') as $task)
                                    <li class="py-3">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="flex items-start gap-3 min-w-0 flex-1">
                                                @if($task->status === 'completed')
                                                    <div class="mt-0.5 h-5 w-5 shrink-0 rounded border-2 border-green-500 bg-green-500 flex items-center justify-center">
                                                        <svg class="h-3 w-3 text-white" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                                        </svg>
                                                    </div>
                                                @elseif($task->status === 'cancelled')
                                                    <div class="mt-0.5 h-5 w-5 shrink-0 rounded border-2 border-gray-300 bg-gray-100 flex items-center justify-center">
                                                        <svg class="h-3 w-3 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                                        </svg>
                                                    </div>
                                                @else
                                                    <div class="mt-0.5 h-5 w-5 shrink-0 rounded border-2 {{ $task->status === 'in_progress' ? 'border-indigo-400 bg-indigo-50' : 'border-gray-300' }}"></div>
                                                @endif
                                                <div class="min-w-0">
                                                    <span class="text-sm {{ $task->isCompleted() ? 'text-gray-400 line-through' : ($task->status === 'cancelled' ? 'text-gray-400 line-through' : 'text-gray-900') }}">{{ $task->description }}</span>
                                                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-0.5">
                                                        @if($task->assignee)
                                                            <span class="text-xs text-gray-400">{{ $task->assignee->name }}</span>
                                                        @endif
                                                        @if($task->completed_at)
                                                            <span class="text-xs text-green-600">{{ __('Completed') }} @localdt($task->completed_at, 'm/d/Y, g:i A')</span>
                                                        @endif
                                                    </div>
                                                    @if($task->notes)
                                                        <p class="mt-0.5 text-xs text-gray-500">{{ $task->notes }}</p>
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-2 shrink-0">
                                                @if(!in_array($ticket->status, ['closed', 'cancelled']) && $task->canChangeStatus())
                                                    <form method="POST" action="{{ route('tickets.tasks.status', [$ticket, $task]) }}" class="flex items-center gap-1">
                                                        @csrf
                                                        <select name="status" onchange="this.form.requestSubmit()" class="rounded-md border-gray-300 text-xs py-1 pl-2 pr-7 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                            @foreach(['pending', 'in_progress', 'completed', 'cancelled'] as $ts)
                                                                <option value="{{ $ts }}" {{ $task->status === $ts ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $ts)) }}</option>
                                                            @endforeach
                                                        </select>
                                                    </form>
                                                @else
                                                    <x-badge :type="$task->status">{{ ucfirst(str_replace('_', ' ', $task->status)) }}</x-badge>
                                                @endif
                                                @if(!in_array($ticket->status, ['closed', 'cancelled']))
                                                    <form method="POST" action="{{ route('tickets.tasks.destroy', [$ticket, $task]) }}">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="text-gray-400 hover:text-red-500" title="{{ __('Remove') }}">
                                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                                            </svg>
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <div class="mt-6 text-center py-8">
                                <svg class="mx-auto h-10 w-10 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z" />
                                </svg>
                                <p class="mt-2 text-sm text-gray-500">{{ __('No tasks created yet.') }}</p>
                            </div>
                        @endif

                        {{-- Add Task Form --}}
                        @if(!in_array($ticket->status, ['closed', 'cancelled']))
                            <form method="POST" action="{{ route('tickets.tasks.store', $ticket) }}" class="mt-4 flex items-end gap-3 border-t border-gray-200 pt-4" @if($taskCopilot) x-data="taskAiPolish(@js(route('tickets.ai.polish-task', $ticket)))" @endif>
                                @csrf
                                <div class="flex-1">
                                    <input type="text" name="description" required placeholder="{{ __('Add a task...') }}" @if($taskCopilot) x-ref="taskInput" @endif class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    @if($taskCopilot)
                                        <p x-show="error" x-cloak class="mt-1 text-xs text-red-600" x-text="error"></p>
                                    @endif
                                </div>
                                @if($taskCopilot)
                                    <button type="button" @click="polish()" :disabled="loading" title="{{ __('Polish this task with AI') }}" class="inline-flex items-center gap-1 rounded-md bg-white px-2.5 py-2 text-sm font-medium text-indigo-700 ring-1 ring-indigo-200 hover:bg-indigo-50 disabled:opacity-50">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" /></svg>
                                        <span x-show="!loading">{{ __('Polish') }}</span>
                                        <span x-show="loading" x-cloak>{{ __('Polishing...') }}</span>
                                    </button>
                                @endif
                                <button type="submit" class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                                    <svg class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                    </svg>
                                    {{ __('Add Task') }}
                                </button>
                            </form>
                            @if($taskCopilot)
                                <script>
                                    function taskAiPolish(url) {
                                        return {
                                            url, loading: false, error: '',
                                            csrf: document.querySelector('meta[name="csrf-token"]')?.content || '',
                                            async polish() {
                                                const input = this.$refs.taskInput;
                                                const text = (input?.value || '').trim();
                                                if (!text) { this.error = @js(__('Type a task first.')); return; }
                                                this.error = ''; this.loading = true;
                                                try {
                                                    const res = await fetch(this.url, {
                                                        method: 'POST',
                                                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json' },
                                                        body: JSON.stringify({ description: text }),
                                                    });
                                                    const data = await res.json().catch(() => ({}));
                                                    if (!res.ok) throw new Error(data.error || @js(__('The AI request failed.')));
                                                    if (data.text && input) { input.value = data.text; input.focus(); }
                                                } catch (e) { this.error = e.message; }
                                                finally { this.loading = false; }
                                            },
                                        };
                                    }
                                </script>
                            @endif
                        @endif

                        @if($taskCopilot)
                            {{-- AI checklist-review modal --}}
                            <div x-show="open" x-cloak @keydown.escape.window="open=false" @click.self="open=false" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background: rgba(0,0,0,0.4);">
                                <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl">
                                    <h3 class="text-lg font-semibold text-gray-900">{{ __('AI-refined checklist') }}</h3>
                                    <p class="mt-1 text-sm text-gray-500">{{ __('Review the suggested open tasks. Edit, add, or remove any before applying.') }}</p>
                                    <p x-show="kept > 0" x-cloak class="mt-1 text-xs text-gray-400"><span x-text="kept"></span> {{ __('completed/cancelled task(s) will be kept.') }}</p>

                                    <form method="POST" action="{{ route('tickets.tasks.polish-apply', $ticket) }}" class="mt-4">
                                        @csrf
                                        <div class="space-y-2 max-h-72 overflow-y-auto">
                                            <template x-for="(item, i) in items" :key="i">
                                                <div class="flex items-center gap-2">
                                                    <input type="text" name="tasks[]" x-model="items[i]" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                    <button type="button" @click="removeItem(i)" title="{{ __('Remove') }}" class="shrink-0 rounded-md p-1.5 text-gray-400 hover:bg-gray-100 hover:text-red-600">
                                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                                                    </button>
                                                </div>
                                            </template>
                                            <p x-show="items.length === 0" x-cloak class="text-sm text-gray-400">{{ __('No tasks — add one below, or cancel.') }}</p>
                                        </div>
                                        <button type="button" @click="addItem()" class="mt-2 inline-flex items-center gap-1 text-sm font-medium text-indigo-600 hover:text-indigo-500">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                                            {{ __('Add task') }}
                                        </button>

                                        <div class="mt-5 flex items-center justify-end gap-2">
                                            <button type="button" @click="open=false" class="rounded-md px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100">{{ __('Cancel') }}</button>
                                            <button type="submit" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">{{ __('Apply changes') }}</button>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <script>
                                function taskListPolish(url) {
                                    return {
                                        url, open: false, loading: false, error: '', kept: 0, items: [],
                                        csrf: document.querySelector('meta[name="csrf-token"]')?.content || '',
                                        async preview() {
                                            this.error = ''; this.loading = true;
                                            try {
                                                const res = await fetch(this.url, { method: 'POST', headers: { 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json' } });
                                                const data = await res.json().catch(() => ({}));
                                                if (!res.ok) throw new Error(data.error || @js(__('The AI request failed.')));
                                                this.items = Array.isArray(data.tasks) ? data.tasks : [];
                                                this.kept = data.kept || 0;
                                                this.open = true;
                                            } catch (e) { this.error = e.message; }
                                            finally { this.loading = false; }
                                        },
                                        addItem() { this.items.push(''); },
                                        removeItem(i) { this.items.splice(i, 1); },
                                    };
                                }
                            </script>
                        @endif
                    </div>
