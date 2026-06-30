<?php

namespace App\Http\Controllers;

use App\Models\BugReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Receives GitHub webhook events for the "AI Programmer" loop and advances the
 * matching BugReport's status (escalated → pr_opened → merged). Authenticates by
 * HMAC signature (X-Hub-Signature-256), not a session/CSRF — it's machine-to-machine.
 */
class GitHubWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $secret = (string) config('services.github.webhook_secret');
        abort_if(blank($secret), 404); // webhook not enabled

        $payload = $request->getContent();
        $expected = 'sha256='.hash_hmac('sha256', $payload, $secret);
        abort_unless(hash_equals($expected, (string) $request->header('X-Hub-Signature-256')), 403);

        if ($request->header('X-GitHub-Event') === 'pull_request') {
            $this->handlePullRequest($request->json()->all());
        }

        return response()->json(['ok' => true]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function handlePullRequest(array $data): void
    {
        $pr = $data['pull_request'] ?? [];
        $action = (string) ($data['action'] ?? '');
        $merged = (bool) ($pr['merged'] ?? false);
        $url = $pr['html_url'] ?? null;

        // Map the PR back to a bug via the issue number(s) it references.
        $issueNumbers = $this->referencedIssues(($pr['title'] ?? '').' '.($pr['body'] ?? ''));
        if ($issueNumbers === []) {
            return;
        }

        $bugs = BugReport::withoutGlobalScopes()
            ->whereIn('github_issue_number', $issueNumbers)
            ->get();

        foreach ($bugs as $bug) {
            if ($action === 'closed' && $merged) {
                $bug->update(['status' => BugReport::STATUS_MERGED, 'github_pr_url' => $url ?? $bug->github_pr_url]);
            } elseif (in_array($action, ['opened', 'reopened', 'ready_for_review'], true)) {
                $bug->update(['status' => BugReport::STATUS_PR_OPENED, 'github_pr_url' => $url]);
            }
        }
    }

    /**
     * Extract referenced issue numbers (e.g. "Closes #123", "#123") from text.
     *
     * @return array<int, int>
     */
    private function referencedIssues(string $text): array
    {
        preg_match_all('/#(\d+)/', $text, $matches);

        return array_values(array_unique(array_map('intval', $matches[1] ?? [])));
    }
}
