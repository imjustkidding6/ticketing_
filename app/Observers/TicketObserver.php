<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\GenerateTicketEmbeddingJob;
use App\Models\Ticket;
use App\Services\EmbeddingService;

class TicketObserver
{
    public function saved(Ticket $ticket): void
    {
        if ($ticket->wasChanged(['solution_embedding', 'solution_embedded_at'])) {
            return;
        }

        if ($ticket->status === 'closed') {
            if ($ticket->wasChanged('closing_remarks') || $ticket->wasChanged('status') || $ticket->solution_embedded_at === null) {
                GenerateTicketEmbeddingJob::dispatch($ticket, force: true);
            }
        } else {
            if ($ticket->solution_embedded_at !== null) {
                app(EmbeddingService::class)->clearTicketEmbedding($ticket);
            }
        }
    }

    public function deleted(Ticket $ticket): void
    {
        app(EmbeddingService::class)->clearTicketEmbedding($ticket);
    }
}
