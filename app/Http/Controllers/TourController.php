<?php

namespace App\Http\Controllers;

use App\Services\HelpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Serves guided tour definitions.
 *
 * Tours live inside their help topic in config/help.php and are filtered by
 * HelpService, so a topic and its tour can never disagree about who may see
 * them. They are fetched by key rather than embedded in the page because a
 * tour resumes on pages that know nothing about the help centre — and serving
 * them puts the permission check on the server, where a curious reader cannot
 * delete it from the DOM.
 */
class TourController extends Controller
{
    public function __construct(private HelpService $help) {}

    /**
     * Tours the current user is allowed to take, for the launcher and cards.
     */
    public function index(Request $request): JsonResponse
    {
        $tours = collect($this->help->toursFor($request->user()))
            ->map(fn (array $tour) => [
                'key' => $tour['key'],
                'title' => $tour['title'],
                'summary' => $tour['summary'] ?? null,
                'minutes' => $tour['minutes'] ?? null,
                'steps' => count($tour['steps']),
                'topic' => $tour['topic'],
                'category' => $tour['category'] ?? null,
                'icon' => $tour['icon'] ?? 'academic-cap',
                'start_url' => $tour['start_url'] ?? null,
                'start_hint' => $tour['start_hint'] ?? null,
            ])
            ->values();

        return response()->json(['tours' => $tours]);
    }

    /**
     * One tour by key.
     *
     * Returns 403 rather than an empty tour when the user may not take it: an
     * empty tour looks like a broken feature, a 403 is an answer.
     */
    public function show(Request $request, string $key): JsonResponse
    {
        if (! is_array(config("help.topics.{$key}"))) {
            return response()->json(['message' => 'That tour does not exist.'], 404);
        }

        $tour = $this->help->tourFor($request->user(), $key);

        if ($tour === null) {
            return response()->json([
                'message' => 'You do not have access to the steps this tour covers.',
            ], 403);
        }

        // The runner re-fetches on every resume, so logging here unconditionally
        // would record a start per page load and make completion rates
        // meaningless. Only an explicit start counts.
        if ($request->boolean('start')) {
            Log::info('Guided tour started', [
                'tour' => $key,
                'user_id' => $request->user()?->id,
                'tenant_id' => session('current_tenant_id'),
            ]);
        }

        return response()->json(['tour' => $tour]);
    }
}
