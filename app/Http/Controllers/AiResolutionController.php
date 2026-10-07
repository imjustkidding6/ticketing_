<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\OpenAiException;
use App\Http\Controllers\Concerns\RequiresAiAgentsEnabled;
use App\Http\Controllers\Concerns\ScopesTicketsToDepartments;
use App\Http\Requests\StoreResolutionDraftRequest;
use App\Models\AppSetting;
use App\Models\Ticket;
use App\Services\AI\ResolutionWriterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * GENERATE RESOLUTION (A2-1) — one POST, two prose drafts, nothing written.
 *
 * Its own controller rather than a third action on `AiTicketAnalysisController`: that
 * class's docblock is a contract about ANALYZE, and the gate here differs by one
 * permission.
 *
 * Five gates:
 *   - plan: `feature:ai_chatbot,ai_agents` on the route (both Enterprise). `ai_chatbot`
 *     owns the AI runtime and the `use ai assistant` grant; `ai_agents` is what makes the
 *     department's agents part of the prompt, and this feature is unexplainable without
 *     them;
 *   - workspace: `ai_enabled` (404, as the copilot endpoints do) and
 *     `ensureAiAgentsEnabled()` (403 — the route exists, the switch is off);
 *   - permission: `use ai assistant` AND **`create ticket comments`**. The second is a
 *     deliberate departure from Analyze: `use ai assistant` means "may use AI on work I
 *     can see", which was right for a read-only triage panel, but this output is text
 *     destined for a ticket and in one case for a customer. A `viewer` — who by design
 *     holds view-only permissions exclusively — must not be handed a reply drafter.
 *     `create ticket comments` is the least permission without which none of the three
 *     copy controls could land anything. **No new permission is added**, so the role
 *     matrix is untouched;
 *   - department: `authorizeTicketAccess()`, on the one action — applying it to only some
 *     actions is the same-tenant IDOR CLAUDE.md names. It is applied **last, deliberately**:
 *     a department refusal raised before the permission checks would MASK them, so a test
 *     asserting 403 for a missing permission would pass with that permission deleted. That
 *     is exactly the masking accident M4 and the round-1 review's F1 both hit.
 *
 * Nothing here writes to the ticket. The endpoint returns JSON; the drafts reach the
 * ticket only through the engineer's own Add Comment or Update Status press, under their
 * own permissions, through controllers this package does not touch. **There is no path in
 * this package that creates a `TicketComment`.**
 */
class AiResolutionController extends Controller
{
    use RequiresAiAgentsEnabled, ScopesTicketsToDepartments;

    public function __construct(private readonly ResolutionWriterService $writer) {}

    public function draft(StoreResolutionDraftRequest $request, Ticket $ticket): JsonResponse
    {
        abort_unless((bool) AppSetting::get('ai_enabled', false), 404);
        $this->ensureAiAgentsEnabled();
        $this->checkPermission('use ai assistant');
        $this->checkPermission('create ticket comments');
        $this->authorizeTicketAccess($ticket);

        $user = Auth::user();
        abort_unless($user !== null, 403);

        try {
            $draft = $this->writer->write(
                $ticket,
                $user,
                (string) $request->validated('notes'),
                (string) ($request->validated('mode') ?? ResolutionWriterService::MODE_RESOLUTION),
            );
        } catch (OpenAiException $e) {
            // Includes a tripped circuit breaker, which is raised before any HTTP.
            report($e);

            return response()->json(['error' => 'The AI assistant is unavailable right now.'], 503);
        }

        // The notes precondition: no run happened, so there is no analysis id to report.
        if ($draft->status === ResolutionWriterService::STATUS_NOTES_TOO_SHORT) {
            return response()->json(['error' => $draft->errorMessage], 422);
        }

        if ($draft->refused()) {
            return response()->json([
                'error' => $draft->errorMessage,
                'analysis_id' => $draft->analysisId,
            ], 422);
        }

        if (! $draft->succeeded()) {
            return response()->json([
                'error' => $draft->errorMessage ?? 'The AI could not write up this resolution.',
                'analysis_id' => $draft->analysisId,
            ], 502);
        }

        return response()->json($draft->toArray());
    }
}
