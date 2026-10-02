<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\OpenAiException;
use App\Http\Controllers\Concerns\RequiresAiAgentsEnabled;
use App\Http\Controllers\Concerns\ScopesTicketsToDepartments;
use App\Http\Requests\StoreSuggestionVerdictRequest;
use App\Models\AiSuggestionVerdict;
use App\Models\AiTicketAnalysis;
use App\Models\AppSetting;
use App\Models\Ticket;
use App\Services\AI\AnalyzeTicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * ANALYZE TICKET and the Accept / Edit / Reject capture behind it.
 *
 * Four gates, all four on BOTH actions:
 *   - plan: `feature:ai_chatbot,ai_agents` on the routes (both Enterprise);
 *   - workspace: `ai_enabled` (404, as the copilot endpoints do) and
 *     `ensureAiAgentsEnabled()` (403 — the route exists, the switch is off);
 *   - permission: the existing `use ai assistant`. No new permission: it already means
 *     "may use AI on work I can see" and is already granted to every engineer role;
 *   - department: `authorizeTicketAccess()`. Applying it to only one action is precisely
 *     the same-tenant IDOR CLAUDE.md names, so verdict() calls it too even though it
 *     resolves the ticket through the analysis.
 *
 * Nothing here writes to the ticket. Applying a suggestion is the engineer posting to the
 * existing ticket-update endpoint, which keeps guardSlaPolicy() and the assignSla()
 * recompute exactly where they already are.
 */
class AiTicketAnalysisController extends Controller
{
    use RequiresAiAgentsEnabled, ScopesTicketsToDepartments;

    public function __construct(private readonly AnalyzeTicketService $analyzer) {}

    public function analyze(Ticket $ticket): JsonResponse
    {
        $this->gate($ticket);

        $user = Auth::user();
        abort_unless($user !== null, 403);

        try {
            $analysis = $this->analyzer->analyze($ticket, $user);
        } catch (OpenAiException $e) {
            // Includes a tripped circuit breaker, which is raised before any HTTP.
            report($e);

            return response()->json(['error' => 'The AI assistant is unavailable right now.'], 503);
        }

        if ($analysis->refused()) {
            return response()->json([
                'error' => $analysis->errorMessage,
                'analysis_id' => $analysis->analysisId,
            ], 422);
        }

        if (! $analysis->succeeded()) {
            return response()->json([
                'error' => $analysis->errorMessage ?? 'The AI analysis could not be completed.',
                'analysis_id' => $analysis->analysisId,
            ], 502);
        }

        return response()->json($analysis->toArray());
    }

    /**
     * Record what the engineer did with one suggestion.
     *
     * `accepted` vs `edited` is decided here, by comparing a hash of what was applied
     * against a hash of what the stored analysis suggested — never from the request's own
     * `verdict` field for those two, or the pilot's headline metric would be a number the
     * browser chooses. Only `rejected` is taken from the request, because an explicit
     * Dismiss is the one verdict no comparison can infer (and a closed tab must never
     * count as one).
     */
    public function verdict(StoreSuggestionVerdictRequest $request, AiTicketAnalysis $analysis): JsonResponse
    {
        $ticket = $analysis->ticket;

        abort_if($ticket === null, 404);

        $this->gate($ticket);

        $type = (string) $request->validated('suggestion_type');
        $suggested = $analysis->suggestedValueFor($type);

        // A verdict on a suggestion this analysis never offered is meaningless, and
        // accepting one would let a client invent accept-rate. This is also what keeps
        // the two P6a-2 types (checks, engineer) refused until they are produced.
        if ($suggested === null) {
            return response()->json([
                'error' => 'This analysis did not offer a '.$type.' suggestion.',
            ], 422);
        }

        $isRejection = $request->validated('verdict') === AiSuggestionVerdict::VERDICT_REJECTED;
        $applied = $isRejection ? null : $request->validated('applied_value');
        $appliedHash = AiSuggestionVerdict::hash($applied);
        $suggestedHash = AiSuggestionVerdict::hash($suggested);

        $verdict = match (true) {
            $isRejection => AiSuggestionVerdict::VERDICT_REJECTED,
            $appliedHash === null || $appliedHash === $suggestedHash => AiSuggestionVerdict::VERDICT_ACCEPTED,
            default => AiSuggestionVerdict::VERDICT_EDITED,
        };

        $row = AiSuggestionVerdict::updateOrCreate(
            [
                'ai_ticket_analysis_id' => $analysis->getKey(),
                'suggestion_type' => $type,
            ],
            [
                'tenant_id' => $analysis->tenant_id,
                // Taken from the analysis, never from the request body.
                'ticket_id' => $analysis->ticket_id,
                'decided_by' => Auth::id(),
                'verdict' => $verdict,
                'suggested_hash' => $suggestedHash,
                'applied_hash' => $appliedHash,
                'edit_ratio' => $verdict === AiSuggestionVerdict::VERDICT_REJECTED
                    ? null
                    : AiSuggestionVerdict::editRatio($suggested, $applied ?? $suggested),
                // Copied from config at decision time so revising the estimate later does
                // not rewrite history. Zero for a rejection: nothing was saved.
                'minutes_saved' => $verdict === AiSuggestionVerdict::VERDICT_REJECTED
                    ? 0
                    : (int) config('ai.minutes_saved.'.$type, 0),
                'decided_at' => now(),
            ],
        );

        return response()->json([
            'verdict' => $row->verdict,
            'edit_ratio' => $row->edit_ratio,
            'minutes_saved' => $row->minutes_saved,
        ]);
    }

    /**
     * The workspace opt-in, the permission and the department check, in one place so the
     * two actions cannot drift apart.
     */
    private function gate(Ticket $ticket): void
    {
        abort_unless((bool) AppSetting::get('ai_enabled', false), 404);
        $this->ensureAiAgentsEnabled();
        $this->checkPermission('use ai assistant');
        $this->authorizeTicketAccess($ticket);
    }
}
