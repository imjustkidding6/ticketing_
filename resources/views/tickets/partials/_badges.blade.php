                    @php
                        $completedTasks = $ticket->tasks->where('status', 'completed')->count();
                        $totalTasks = $ticket->tasks->count();
                    @endphp
                    @if($totalTasks > 0)
                        <x-badge :type="$completedTasks === $totalTasks ? 'completed' : 'in_progress'">
                            {{ $completedTasks === $totalTasks ? __('COMPLETE SET') : __('INCOMPLETE SET') }}
                        </x-badge>
                    @endif
                    <x-badge :type="$ticket->status">{{ strtoupper(str_replace('_', ' ', $ticket->status)) }}</x-badge>
                    <x-badge :type="$ticket->priority">{{ strtoupper($ticket->priority) }}</x-badge>
                    @if($ticket->isOverdue())
                        <x-badge type="overdue">{{ __('OVERDUE') }}</x-badge>
                    @endif
