<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Ticket;
use App\Services\EmbeddingService;
use App\Services\OpenAiService;
use Illuminate\Console\Command;

class EmbedTicketsCommand extends Command
{
    protected $signature = 'ai:embed:tickets
                            {--tenant= : Filter by tenant ID}
                            {--force : Re-embed already processed tickets}
                            {--limit=100 : Maximum number of tickets to process}';

    protected $description = 'Generate embeddings for closed support tickets so the AI assistant can reuse past resolutions.';

    public function handle(OpenAiService $openAi, EmbeddingService $embeddingService): int
    {
        if (! $openAi->isConfigured()) {
            $this->warn('OpenAI API key is not configured. Skipping ticket embeddings.');

            return self::FAILURE;
        }

        $tenantId = $this->option('tenant') ? (int) $this->option('tenant') : null;
        $force = (bool) $this->option('force');
        $limit = (int) $this->option('limit');

        $query = Ticket::withoutGlobalScopes()->where('status', 'closed');

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        if (! $force) {
            $query->whereNull('solution_embedded_at');
        }

        $tickets = $query->latest('closed_at')->limit($limit)->get();

        if ($tickets->isEmpty()) {
            $this->info('No closed tickets need embedding.');

            return self::SUCCESS;
        }

        $this->info("Processing embeddings for {$tickets->count()} closed ticket(s)...");
        $bar = $this->output->createProgressBar($tickets->count());
        $bar->start();

        $processed = 0;
        foreach ($tickets as $ticket) {
            if ($embeddingService->embedTicket($ticket, $force)) {
                $processed++;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Successfully embedded {$processed} ticket(s).");

        return self::SUCCESS;
    }
}
