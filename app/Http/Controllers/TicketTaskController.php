<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketTask;
use App\Services\TicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TicketTaskController extends Controller
{
    public function __construct(
        private TicketService $ticketService,
    ) {}

    /**
     * Store a new task for a ticket.
     */
    public function store(Request $request, Ticket $ticket): RedirectResponse
    {
        $validated = $request->validate([
            'description' => ['required', 'string', 'max:1000'],
            'assigned_to' => ['nullable', \Illuminate\Validation\Rule::exists('users', 'id')->whereNull('deleted_at')],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $ticket->tasks()->create($validated);

        $this->ticketService->addHistory($ticket, 'task_added', null, null, null, 'Task added: '.$validated['description']);

        return redirect()->route('tickets.show', $ticket)
            ->with('success', 'Task added successfully.');
    }

    /**
     * Update a task.
     */
    public function update(Request $request, Ticket $ticket, TicketTask $task): RedirectResponse
    {
        $validated = $request->validate([
            'description' => ['required', 'string', 'max:1000'],
            'assigned_to' => ['nullable', \Illuminate\Validation\Rule::exists('users', 'id')->whereNull('deleted_at')],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $task->update($validated);

        $this->ticketService->addHistory($ticket, 'task_updated', null, null, null, 'Task updated: '.$validated['description']);

        return redirect()->route('tickets.show', $ticket)
            ->with('success', 'Task updated successfully.');
    }

    /**
     * Update task status with history tracking.
     */
    public function updateStatus(Request $request, Ticket $ticket, TicketTask $task): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:pending,in_progress,completed,cancelled'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $oldStatus = $task->status;
        $task->updateStatus($validated['status'], Auth::user(), $validated['notes'] ?? null);

        $this->ticketService->addHistory($ticket, 'task_status_changed', 'task_status', $oldStatus, $validated['status'], "Task '{$task->description}' changed from {$oldStatus} to {$validated['status']}");

        return redirect()->route('tickets.show', $ticket)
            ->with('success', 'Task status updated.');
    }

    /**
     * Bulk update tasks.
     */
    public function bulkUpdate(Request $request, Ticket $ticket): RedirectResponse
    {
        $validated = $request->validate([
            'tasks' => ['required', 'array'],
            'tasks.*.id' => ['required', 'exists:ticket_tasks,id'],
            'tasks.*.description' => ['required', 'string', 'max:1000'],
            'tasks.*.assigned_to' => ['nullable', 'exists:users,id'],
            'tasks.*.notes' => ['nullable', 'string', 'max:2000'],
        ]);

        foreach ($validated['tasks'] as $taskData) {
            $task = TicketTask::findOrFail($taskData['id']);
            $task->update([
                'description' => $taskData['description'],
                'assigned_to' => $taskData['assigned_to'] ?? null,
                'notes' => $taskData['notes'] ?? null,
            ]);
        }

        return redirect()->route('tickets.show', $ticket)
            ->with('success', 'Tasks updated.');
    }

    /**
     * Bulk update task statuses.
     */
    public function bulkStatusUpdate(Request $request, Ticket $ticket): RedirectResponse
    {
        $validated = $request->validate([
            'task_ids' => ['required', 'array'],
            'task_ids.*' => ['exists:ticket_tasks,id'],
            'status' => ['required', 'in:pending,in_progress,completed,cancelled'],
        ]);

        foreach ($validated['task_ids'] as $taskId) {
            $task = TicketTask::findOrFail($taskId);

            if ($task->canChangeStatus()) {
                $task->updateStatus($validated['status'], Auth::user());
            }
        }

        return redirect()->route('tickets.show', $ticket)
            ->with('success', 'Task statuses updated.');
    }

    /**
     * Get task status history.
     */
    public function history(Ticket $ticket, TicketTask $task): \Illuminate\Http\JsonResponse
    {
        $history = $task->statusHistory()
            ->with('user')
            ->latest()
            ->get()
            ->map(fn ($h) => [
                'id' => $h->id,
                'description' => $h->getStatusChangeDescription(),
                'user' => $h->user?->name ?? 'System',
                'notes' => $h->notes,
                'created_at' => $h->created_at->diffForHumans(),
            ]);

        return response()->json($history);
    }

    /**
     * Delete a task.
     */
    public function destroy(Ticket $ticket, TicketTask $task): RedirectResponse
    {
        $this->ticketService->addHistory($ticket, 'task_deleted', null, null, null, 'Task removed: '.$task->description);

        $task->delete();

        return redirect()->route('tickets.show', $ticket)
            ->with('success', 'Task removed.');
    }
}
