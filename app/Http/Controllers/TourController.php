<?php

namespace App\Http\Controllers;

use App\Enums\PlanFeature;
use App\Services\PlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Serves guided tour definitions.
 *
 * Tours are fetched by key rather than embedded in the page, because a tour
 * resumes on pages that know nothing about the tour system. Serving them also
 * puts the permission check on the server, where a curious user cannot delete
 * it from the DOM.
 */
class TourController extends Controller
{
    /**
     * Tours the current user is allowed to take, for the launcher.
     */
    public function index(): JsonResponse
    {
        $tours = collect(config('tours', []))
            ->filter(fn (array $tour) => $this->isAvailable($tour))
            ->map(fn (array $tour) => [
                'key' => $tour['key'],
                'title' => $tour['title'],
                'summary' => $tour['summary'] ?? null,
                'minutes' => $tour['minutes'] ?? null,
                'steps' => count($tour['steps'] ?? []),
            ])
            ->values();

        return response()->json(['tours' => $tours]);
    }

    /**
     * One tour by key.
     *
     * Returns 403 rather than an empty tour when the user may not take it:
     * an empty tour looks like a broken feature, a 403 is an answer.
     */
    public function show(Request $request, string $key): JsonResponse
    {
        $tour = config("tours.{$key}");

        if (! is_array($tour)) {
            return response()->json(['message' => 'That tour does not exist.'], 404);
        }

        if (! $this->isAvailable($tour)) {
            return response()->json([
                'message' => 'You do not have access to the steps this tour covers.',
            ], 403);
        }

        // F5: the runner re-fetches on every resume, so logging here
        // unconditionally would record a start per page load and make
        // completion rates meaningless. Only an explicit start counts.
        if ($request->boolean('start')) {
            Log::info('Guided tour started', [
                'tour' => $key,
                'user_id' => $request->user()?->id,
                'tenant_id' => session('current_tenant_id'),
            ]);
        }

        return response()->json(['tour' => $tour]);
    }

    /**
     * Whether the current user can both take and complete this tour.
     *
     * @param  array<string, mixed>  $tour
     */
    private function isAvailable(array $tour): bool
    {
        if (! empty($tour['feature'])) {
            $feature = PlanFeature::tryFrom($tour['feature']);

            if (! $feature || ! app(PlanService::class)->currentTenantHasFeature($feature)) {
                return false;
            }
        }

        // The permission named is the one for the action being taught, not for
        // the page it happens to start on.
        return empty($tour['permission']) || $this->userCan($tour['permission']);
    }
}
