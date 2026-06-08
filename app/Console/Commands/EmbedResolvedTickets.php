<?php

namespace App\Console\Commands;

use App\Models\Ticket;
use App\Services\OpenAiService;
use Illuminate\Console\Command;

/**
 * Embeds resolved (closed) tickets so the AI assistant can learn from past
 * resolutions. Runs on a schedule; each newly-closed ticket becomes a learned
 * example the assistant can semantically retrieve.
 */
class EmbedResolvedTickets extends Command
{
    protected $signature = 'ai:embed-tickets {--limit=200}';

    protected $description = 'Embed resolved tickets so the AI assistant learns from past resolutions';

    public function handle(OpenAiService $openAi): int
    {
        if (! $openAi->isConfigured()) {
            $this->warn('OpenAI is not configured; skipping.');

            return self::SUCCESS;
        }

        $tickets = Ticket::withoutGlobalScopes()
            ->where('status', 'closed')
            ->whereNull('solution_embedded_at')
            ->notMerged()
            ->notSpam()
            ->with(['comments' => fn ($q) => $q->where('is_public', true)->orderBy('id')])
            ->limit((int) $this->option('limit'))
            ->get();

        $done = 0;
        foreach ($tickets as $ticket) {
            try {
                $vector = $openAi->embed($this->solutionText($ticket));
                if ($vector !== []) {
                    $ticket->forceFill([
                        'solution_embedding' => json_encode($vector),
                        'solution_embedded_at' => now(),
                    ])->saveQuietly();
                    $done++;
                }
            } catch (\Throwable $e) {
                $this->error($ticket->ticket_number.': '.$e->getMessage());
            }
        }

        $this->info("Embedded {$done} resolved ticket(s).");

        return self::SUCCESS;
    }

    private function solutionText(Ticket $ticket): string
    {
        $parts = [$ticket->subject, $ticket->description, (string) $ticket->closing_remarks];
        foreach ($ticket->comments as $comment) {
            if ($comment->user_id) { // agent replies describe the resolution
                $parts[] = $comment->content;
            }
        }

        return mb_substr(trim(implode("\n", array_filter($parts))), 0, 8000);
    }
}
