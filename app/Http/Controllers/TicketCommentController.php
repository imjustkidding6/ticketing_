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
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketCommentController extends Controller
{
    private const DANGEROUS_EXTENSIONS = ['php', 'exe', 'sh', 'bat', 'cmd', 'js', 'py', 'rb'];

    private const ALLOWED_COMMENT_ATTACHMENT_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain',
        'application/zip',
        'application/x-zip-compressed',
    ];

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
        $attachments = $this->storeAttachments($request, $ticket);

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
    private function storeAttachments(Request $request, Ticket $ticket, string $field = 'attachments', string $directory = 'comment-attachments'): array
    {
        if (! $request->hasFile($field)) {
            return [];
        }

        $request->validate([
            "{$field}.*" => [
                'file',
                'max:10240',
                'mimes:jpg,jpeg,png,gif,pdf,doc,docx,xls,xlsx,txt,zip',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value instanceof UploadedFile && $this->hasDangerousExtension($value)) {
                        $fail('File type not allowed for security reasons');
                    }
                },
            ],
        ]);

        $attachments = [];
        foreach ($request->file($field) as $index => $file) {
            $detectedMime = $this->validateSafeAttachment($file, "{$field}.{$index}", self::ALLOWED_COMMENT_ATTACHMENT_MIME_TYPES);
            $attachments[] = [
                'name' => $file->getClientOriginalName(),
                'path' => $file->store('tenants/' . $ticket->tenant_id . '/' . $directory, 'public'),
                'size' => $file->getSize(),
                'mime' => $detectedMime,
            ];
        }

        return $attachments;
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

    private function validateSafeAttachment(UploadedFile $file, string $field, array $allowedMimeTypes): string
    {
        if ($this->hasDangerousExtension($file)) {
            throw ValidationException::withMessages([
                $field => 'File type not allowed for security reasons',
            ]);
        }

        $detectedMime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getPathname());
        $declaredMime = $file->getClientMimeType();

        if (! is_string($detectedMime)) {
            throw ValidationException::withMessages([
                $field => 'The uploaded file content type is not allowed.',
            ]);
        }

        $normalizedDetectedMime = $this->normalizeMimeType($detectedMime);
        $normalizedDeclaredMime = is_string($declaredMime) ? $this->normalizeMimeType($declaredMime) : null;

        if (
            ! in_array($normalizedDetectedMime, $allowedMimeTypes, true)
            || ! is_string($normalizedDeclaredMime)
            || ! in_array($normalizedDeclaredMime, $allowedMimeTypes, true)
            || $normalizedDeclaredMime !== $normalizedDetectedMime
        ) {
            throw ValidationException::withMessages([
                $field => 'The uploaded file content does not match its declared type.',
            ]);
        }

        return $normalizedDetectedMime;
    }

    private function hasDangerousExtension(UploadedFile $file): bool
    {
        $segments = array_filter(
            array_map('trim', explode('.', strtolower($file->getClientOriginalName()))),
            static fn (string $segment): bool => $segment !== ''
        );

        foreach ($segments as $segment) {
            if (in_array($segment, self::DANGEROUS_EXTENSIONS, true)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeMimeType(string $mimeType): string
    {
        return match (strtolower(trim($mimeType))) {
            'application/x-zip-compressed' => 'application/zip',
            'image/jpg' => 'image/jpeg',
            default => strtolower(trim($mimeType)),
        };
    }
}
