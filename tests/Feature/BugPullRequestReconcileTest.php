<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BugReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The GitHub webhook is the primary way a bug learns its PR was opened or
 * merged. These cover the fallback sweep, which is what keeps a bug from
 * sitting at "escalated" forever when the webhook was never delivered.
 */
class BugPullRequestReconcileTest extends TestCase
{
    use RefreshDatabase;

    private function configureGitHub(): void
    {
        config([
            'services.github.token' => 'ghp_test',
            'services.github.repo' => 'acme/ticketing',
            'services.github.api_url' => 'https://api.github.com',
        ]);
    }

    private function searchReturns(array $items): void
    {
        Http::fake([
            'https://api.github.com/search/issues*' => Http::response(['items' => $items]),
        ]);
    }

    public function test_it_does_nothing_when_github_is_not_configured(): void
    {
        config(['services.github.token' => null, 'services.github.repo' => null]);
        Http::fake();

        $bug = BugReport::factory()->create([
            'status' => BugReport::STATUS_ESCALATED,
            'github_issue_number' => 42,
        ]);

        $this->artisan('bugs:reconcile-prs')->assertSuccessful();

        Http::assertNothingSent();
        $this->assertSame(BugReport::STATUS_ESCALATED, $bug->fresh()->status);
    }

    public function test_an_escalated_bug_advances_to_pr_opened(): void
    {
        $this->configureGitHub();
        $this->searchReturns([[
            'title' => 'Fix the thing',
            'body' => 'Closes #42',
            'html_url' => 'https://github.com/acme/ticketing/pull/99',
            'pull_request' => ['merged_at' => null],
        ]]);

        $bug = BugReport::factory()->create([
            'status' => BugReport::STATUS_ESCALATED,
            'github_issue_number' => 42,
        ]);

        $this->artisan('bugs:reconcile-prs')->assertSuccessful();

        $bug->refresh();
        $this->assertSame(BugReport::STATUS_PR_OPENED, $bug->status);
        $this->assertSame('https://github.com/acme/ticketing/pull/99', $bug->github_pr_url);
    }

    public function test_a_merged_pull_request_advances_the_bug_to_merged(): void
    {
        $this->configureGitHub();
        $this->searchReturns([[
            'title' => 'Fix the thing',
            'body' => 'Closes #42',
            'html_url' => 'https://github.com/acme/ticketing/pull/99',
            'pull_request' => ['merged_at' => '2026-09-01T10:00:00Z'],
        ]]);

        $bug = BugReport::factory()->create([
            'status' => BugReport::STATUS_PR_OPENED,
            'github_issue_number' => 42,
        ]);

        $this->artisan('bugs:reconcile-prs')->assertSuccessful();

        $this->assertSame(BugReport::STATUS_MERGED, $bug->fresh()->status);
    }

    public function test_an_incidental_substring_match_is_not_treated_as_a_reference(): void
    {
        $this->configureGitHub();
        // GitHub's free-text search matches "42" inside "1425"; only a real
        // "#42" token should count, or a bug gets attached to a stranger's PR.
        $this->searchReturns([[
            'title' => 'Bump dependency to 1425',
            'body' => 'No issue reference here.',
            'html_url' => 'https://github.com/acme/ticketing/pull/7',
            'pull_request' => ['merged_at' => '2026-09-01T10:00:00Z'],
        ]]);

        $bug = BugReport::factory()->create([
            'status' => BugReport::STATUS_ESCALATED,
            'github_issue_number' => 42,
        ]);

        $this->artisan('bugs:reconcile-prs')->assertSuccessful();

        $this->assertSame(BugReport::STATUS_ESCALATED, $bug->fresh()->status);
        $this->assertNull($bug->fresh()->github_pr_url);
    }

    public function test_bugs_outside_the_escalated_window_are_left_alone(): void
    {
        $this->configureGitHub();
        Http::fake();

        $new = BugReport::factory()->create(['status' => BugReport::STATUS_NEW, 'github_issue_number' => 1]);
        $merged = BugReport::factory()->create(['status' => BugReport::STATUS_MERGED, 'github_issue_number' => 2]);

        $this->artisan('bugs:reconcile-prs')->assertSuccessful();

        Http::assertNothingSent();
        $this->assertSame(BugReport::STATUS_NEW, $new->fresh()->status);
        $this->assertSame(BugReport::STATUS_MERGED, $merged->fresh()->status);
    }

    public function test_one_failed_lookup_does_not_abort_the_sweep(): void
    {
        $this->configureGitHub();

        $first = BugReport::factory()->create(['status' => BugReport::STATUS_ESCALATED, 'github_issue_number' => 11]);
        $second = BugReport::factory()->create(['status' => BugReport::STATUS_ESCALATED, 'github_issue_number' => 22]);

        $calls = 0;
        Http::fake(function () use (&$calls) {
            $calls++;

            return $calls === 1
                ? Http::response('boom', 500)
                : Http::response(['items' => [[
                    'title' => 'Fix', 'body' => 'Closes #22',
                    'html_url' => 'https://github.com/acme/ticketing/pull/5',
                    'pull_request' => ['merged_at' => null],
                ]]]);
        });

        $this->artisan('bugs:reconcile-prs')->assertSuccessful();

        $this->assertSame(BugReport::STATUS_ESCALATED, $first->fresh()->status);
        $this->assertSame(BugReport::STATUS_PR_OPENED, $second->fresh()->status);
    }
}
