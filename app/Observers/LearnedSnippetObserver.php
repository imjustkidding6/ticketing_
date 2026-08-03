<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\GenerateSnippetEmbeddingJob;
use App\Models\LearnedSnippet;

class LearnedSnippetObserver
{
    public function saved(LearnedSnippet $snippet): void
    {
        if ($snippet->wasChanged('embedding')) {
            return;
        }

        GenerateSnippetEmbeddingJob::dispatch($snippet, force: true);
    }
}
