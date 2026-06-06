<?php

namespace App\Services;

use App\Exceptions\GitHubException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Thin wrapper around the GitHub REST API for the "AI Programmer" bridge.
 * The app files an issue (labelled so the Claude Code Action picks it up); a
 * webhook later reports the resulting PR back. Holds no app/tenant logic.
 */
class GitHubService
{
    public function isConfigured(): bool
    {
        return filled(config('services.github.token')) && filled(config('services.github.repo'));
    }

    /**
     * Create an issue on the configured repo and return its number + html url.
     *
     * @param  array<int, string>  $labels
     * @return array{number: int, url: string}
     *
     * @throws GitHubException
     */
    public function createIssue(string $title, string $body, array $labels = []): array
    {
        if (! $this->isConfigured()) {
            throw new GitHubException('GitHub is not configured.');
        }

        $response = $this->client()->post($this->repoUrl().'/issues', [
            'title' => $title,
            'body' => $body,
            'labels' => $labels,
        ]);

        if ($response->failed()) {
            throw new GitHubException('GitHub issue creation failed ('.$response->status().'): '.$response->body());
        }

        return [
            'number' => (int) $response->json('number'),
            'url' => (string) $response->json('html_url'),
        ];
    }

    /**
     * Fetch a pull request (used by the polling fallback).
     *
     * @return array<string, mixed>
     *
     * @throws GitHubException
     */
    public function getPull(int $number): array
    {
        if (! $this->isConfigured()) {
            throw new GitHubException('GitHub is not configured.');
        }

        $response = $this->client()->get($this->repoUrl().'/pulls/'.$number);

        if ($response->failed()) {
            throw new GitHubException('GitHub pull fetch failed ('.$response->status().'): '.$response->body());
        }

        return $response->json() ?? [];
    }

    private function client(): PendingRequest
    {
        return Http::withToken((string) config('services.github.token'))
            ->acceptJson()
            ->withHeaders(['X-GitHub-Api-Version' => '2022-11-28', 'User-Agent' => 'CliqueHA-Nexus'])
            ->timeout(30)
            ->retry(2, 200, throw: false);
    }

    private function repoUrl(): string
    {
        $base = rtrim((string) config('services.github.api_url', 'https://api.github.com'), '/');
        $repo = trim((string) config('services.github.repo'), '/');

        return "{$base}/repos/{$repo}";
    }
}
