<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketComment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TicketCommentController extends Controller
{
    /**
     * Store a new comment on a ticket.
     */
    public function store(Request $request, Ticket $ticket): RedirectResponse
    {
        $validated = $request->validate([
            'content' => ['required', 'string', 'max:5000'],
            'type' => ['nullable', 'in:internal,public'],
        ]);

        TicketComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => Auth::id(),
            'content' => $validated['content'],
            'type' => $validated['type'] ?? 'internal',
            'is_public' => ($validated['type'] ?? 'internal') === 'public',
        ]);

        return redirect()->route('tickets.show', $ticket)
            ->with('success', 'Comment added.');
    }

    /**
     * Update a comment.
     */
    public function update(Request $request, Ticket $ticket, TicketComment $comment): RedirectResponse
    {
        $validated = $request->validate([
            'content' => ['required', 'string', 'max:5000'],
        ]);

        $comment->update([
            'content' => $validated['content'],
            'edited_at' => now(),
            'edited_by' => Auth::id(),
        ]);

        return redirect()->route('tickets.show', $ticket)
            ->with('success', 'Comment updated.');
    }

    /**
     * Delete a comment.
     */
    public function destroy(Ticket $ticket, TicketComment $comment): RedirectResponse
    {
        $comment->delete();

        return redirect()->route('tickets.show', $ticket)
            ->with('success', 'Comment removed.');
    }
}
