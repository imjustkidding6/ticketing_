<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BugReport;
use App\Services\GitHubService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Reconciles bug reports whose GitHub PR status never arrived by webhook.
 *
 * The webhook (GitHubWebhookController) is the primary path and is instant.
 * This is the fallback it always implied but never had: without it, a bug whose
 * webhook delivery failed — or a deployment where GITHUB_WEBHOOK_SECRET was
 * never set — sits at "escalated" permanently, and the reporter is never told
 * their fix shipped.
 */
class ReconcileBugPullRequests extends Command
{
    protected $signature = 'bugs:reconcile-prs {--limit=25 : Maximum bugs to check in one pass}';

    protected $description = 'Back-fill PR status for bug reports the GitHub webhook never reported on.';

    public function handle(GitHubService $github): int
    {
        if (! $github->isConfigured()) {
            $this->info('GitHub is not configured; nothing to reconcile.');

            return self::SUCCESS;
        }

        $bugs = BugReport::withoutGlobalScopes()
            ->whereIn('status', [BugReport::STATUS_ESCALATED, BugReport::STATUS_PR_OPENED])
            ->whereNotNull('github_issue_number')
            ->orderBy('updated_at')
            ->limit((int) $this->option('limit'))
            ->get();

        if ($bugs->isEmpty()) {
            $this->info('No bug reports awaiting PR status.');

            return self::SUCCESS;
        }

        $updated = 0;

        foreach ($bugs as $bug) {
            try {
                $pr = $github->findPullForIssue((int) $bug->github_issue_number);
            } catch (Throwable $e) {
                // One bad lookup must not abort the sweep.
                Log::warning('Bug PR reconcile failed', ['bug' => $bug->id, 'error' => $e->getMessage()]);
                $this->warn("  #{$bug->id}: lookup failed ({$e->getMessage()})");

                continue;
            }

            if ($pr === null) {
                continue;
            }

            // The search API reports a merged PR as state=closed with
            // pull_request.merged_at set; an unmerged close leaves it null.
            $merged = filled($pr['pull_request']['merged_at'] ?? null);
            $status = $merged ? BugReport::STATUS_MERGED : BugReport::STATUS_PR_OPENED;

            if ($bug->status === $status) {
                continue;
            }

            $bug->update([
                'status' => $status,
                'github_pr_url' => $pr['html_url'] ?? $bug->github_pr_url,
            ]);

            $updated++;
            $this->line("  #{$bug->id}: {$status} ({$pr['html_url']})");
        }

        $this->info("Reconciled {$updated} of {$bugs->count()} checked.");

        return self::SUCCESS;
    }
}
