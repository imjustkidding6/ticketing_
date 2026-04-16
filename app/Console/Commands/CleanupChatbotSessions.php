<?php

namespace App\Console\Commands;

use App\Models\ChatbotSession;
use Illuminate\Console\Command;

class CleanupChatbotSessions extends Command
{
    protected $signature = 'chatbot:cleanup';

    protected $description = 'Delete chatbot sessions and messages older than 30 days.';

    public function handle(): int
    {
        $cutoff = now()->subDays(30);

        ChatbotSession::withoutGlobalScopes()
            ->where('created_at', '<', $cutoff)
            ->orderBy('id')
            ->chunkById(200, function ($sessions) {
                foreach ($sessions as $session) {
                    $session->delete();
                }
            });

        $this->info('Chatbot sessions cleanup complete.');

        return Command::SUCCESS;
    }
}
