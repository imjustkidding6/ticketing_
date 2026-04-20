<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketComment;
use App\Services\TicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketCommentController extends Controller
{
    public function __construct(
        private TicketService $ticketService,
    ) {}

    /**
     * Store a new comment on a ticket.
     */
    public function store(Request $request, Ticket $ticket): RedirectResponse
    {
        $validated = $request->validate([
            'content' => ['required', 'string', 'max:5000'],
            'type' => ['nullable', 'in:internal,public'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,gif,pdf,doc,docx,xls,xlsx,txt,zip'],
        ]);

        $type = $validated['type'] ?? 'internal';
        $attachments = $this->storeAttachments($request);

        $comment = TicketComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => Auth::id(),
            'content' => $validated['content'],
            'type' => $type,
            'is_public' => $type === 'public',
            'attachments' => $attachments ?: null,
        ]);

        $label = $comment->type === 'internal' ? 'Internal note' : 'Public comment';

        $this->ticketService->addHistory(
            $ticket,
            'comment_added',
            description: "{$label} added by ".Auth::user()->name,
        );

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

        $this->ticketService->addHistory(
            $ticket,
            'comment_edited',
            description: 'Comment edited by '.Auth::user()->name,
        );

        return redirect()->route('tickets.show', $ticket)
            ->with('success', 'Comment updated.');
    }

    /**
     * Delete a comment.
     */
    public function destroy(Ticket $ticket, TicketComment $comment): RedirectResponse
    {
        $this->ticketService->addHistory(
            $ticket,
            'comment_deleted',
            description: 'Comment deleted by '.Auth::user()->name,
        );

        $this->deleteAttachments($comment);

        $comment->delete();

        return redirect()->route('tickets.show', $ticket)
            ->with('success', 'Comment removed.');
    }

    /**
     * Download a comment attachment.
     */
    public function downloadAttachment(Ticket $ticket, TicketComment $comment, int $index): StreamedResponse
    {
        $attachments = $comment->attachments ?? [];

        abort_unless(isset($attachments[$index]), 404);

        $attachment = $attachments[$index];

        return Storage::disk('public')->download($attachment['path'], $attachment['name']);
    }

    /**
     * Store uploaded attachments and return their metadata.
     *
     * @return array<int, array{name: string, path: string, size: int|false, mime: string|null}>
     */
    private function storeAttachments(Request $request, string $field = 'attachments', string $directory = 'comment-attachments'): array
    {
        if (! $request->hasFile($field)) {
            return [];
        }

        return array_map(fn (UploadedFile $file) => [
            'name' => $file->getClientOriginalName(),
            'path' => $file->store($directory, 'public'),
            'size' => $file->getSize(),
            'mime' => $file->getMimeType(),
        ], $request->file($field));
    }

    /**
     * Delete stored attachment files for a comment.
     */
    private function deleteAttachments(TicketComment $comment): void
    {
        foreach ($comment->attachments ?? [] as $attachment) {
            Storage::disk('public')->delete($attachment['path'] ?? '');
        }
    }
}
