<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

class EmbedAllCommand extends Command
{
    protected $signature = 'ai:embed:all
                            {--tenant= : Filter by tenant ID}
                            {--force : Re-embed all existing records}
                            {--limit=100 : Maximum records per batch}';

    protected $description = 'Generate embeddings for closed tickets, published KB articles, and learned snippets.';

    public function handle(): int
    {
        $tenant = $this->option('tenant');
        $force = $this->option('force');
        $limit = $this->option('limit');

        $options = [];
        if ($tenant) {
            $options['--tenant'] = $tenant;
        }
        if ($force) {
            $options['--force'] = true;
        }
        if ($limit) {
            $options['--limit'] = $limit;
        }

        $this->info('--- Processing Knowledge Base Articles ---');
        $this->call('ai:embed:knowledge', $options);

        $this->info('--- Processing Closed Tickets ---');
        $this->call('ai:embed:tickets', $options);

        $this->info('--- Processing Learned Snippets ---');
        $this->call('ai:embed:snippets', $options);

        $this->info('All AI embeddings processing completed.');

        return self::SUCCESS;
    }
}
