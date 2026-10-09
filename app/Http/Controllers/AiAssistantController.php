<?php

namespace App\Http\Controllers;

use App\Enums\PlanFeature;
use App\Exceptions\OpenAiException;
use App\Http\Controllers\Concerns\ScopesTicketsToDepartments;
use App\Http\Requests\StructureTicketDraftRequest;
use App\Models\AppSetting;
use App\Models\BugReport;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\LearnedSnippet;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use App\Services\AI\DraftSuggestionService;
use App\Services\AiAssistantService;
use App\Services\PageContextResolver;
use App\Services\PlanService;
use App\Support\FileParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Authenticated "agent copilot" endpoints. Gated by the ai_chatbot feature
 * (route middleware); the ticket is tenant-scoped by the global TenantScope.
 */
class AiAssistantController extends Controller
{
    use ScopesTicketsToDepartments;

    public function __construct(private AiAssistantService $assistant) {}

    public function draftReply(Ticket $ticket): JsonResponse
    {
        $this->checkPermission('use ai assistant');
        // Department scoping must hold on every ticket-bound action, not only the
        // pages: without this a restricted agent refused /tickets/{id} could still POST
        // here and read the ticket back in the response. Placed AFTER checkPermission so
        // a missing permission stays observable instead of being masked by a 403 here.
        $this->authorizeTicketAccess($ticket);

        try {
            $text = $this->assistant->draftAgentReply($ticket);
        } catch (OpenAiException $e) {
            report($e);

            return response()->json(['error' => 'The AI assistant is unavailable right now.'], 503);
        }

        return response()->json(['text' => $text]);
    }

    public function summarize(Ticket $ticket): JsonResponse
    {
        $this->checkPermission('use ai assistant');
        // Department scoping must hold on every ticket-bound action, not only the
        // pages: without this a restricted agent refused /tickets/{id} could still POST
        // here and read the ticket back in the response. Placed AFTER checkPermission so
        // a missing permission stays observable instead of being masked by a 403 here.
        $this->authorizeTicketAccess($ticket);

        try {
            $text = $this->assistant->summarizeTicket($ticket);
        } catch (OpenAiException $e) {
            report($e);

            return response()->json(['error' => 'The AI assistant is unavailable right now.'], 503);
        }

        return response()->json(['text' => $text]);
    }

    /**
     * Polish a single rough task/checklist line on the ticket view into one
     * clean, actionable resolution task.
     */
    public function polishTask(Request $request, Ticket $ticket): JsonResponse
    {
        $this->checkPermission('use ai assistant');
        // Department scoping must hold on every ticket-bound action, not only the
        // pages: without this a restricted agent refused /tickets/{id} could still POST
        // here and read the ticket back in the response. Placed AFTER checkPermission so
        // a missing permission stays observable instead of being masked by a 403 here.
        $this->authorizeTicketAccess($ticket);
        abort_unless((bool) AppSetting::get('ai_enabled', false), 404);

        $validated = $request->validate([
            'description' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $text = $this->assistant->polishTask($ticket, $validated['description']);
        } catch (OpenAiException $e) {
            report($e);

            return response()->json(['error' => 'The AI assistant is unavailable right now.'], 503);
        }

        return response()->json(['text' => $text]);
    }

    /**
     * Preview an AI-refined version of a ticket's OPEN task checklist (reworded,
     * with missing steps added and redundant ones dropped). Read-only — nothing
     * is saved; the agent reviews/edits and applies via TicketTaskController.
     */
    public function polishTaskList(Ticket $ticket): JsonResponse
    {
        $this->checkPermission('use ai assistant');
        // Department scoping must hold on every ticket-bound action, not only the
        // pages: without this a restricted agent refused /tickets/{id} could still POST
        // here and read the ticket back in the response. Placed AFTER checkPermission so
        // a missing permission stays observable instead of being masked by a 403 here.
        $this->authorizeTicketAccess($ticket);
        abort_unless((bool) AppSetting::get('ai_enabled', false), 404);

        $open = $ticket->tasks()->whereIn('status', ['pending', 'in_progress'])
            ->orderBy('sort_order')->pluck('description')->all();
        $done = $ticket->tasks()->whereIn('status', ['completed', 'cancelled'])
            ->orderBy('sort_order')->pluck('description')->all();

        try {
            $tasks = $this->assistant->polishTaskList($ticket, $open, $done);
        } catch (OpenAiException $e) {
            report($e);

            return response()->json(['error' => 'The AI assistant is unavailable right now.'], 503);
        }

        return response()->json(['tasks' => $tasks, 'kept' => count($done)]);
    }

    /**
     * Clean up & structure a rough ticket draft (subject, description, tasks) on the
     * create/edit ticket form, and — on the create form — suggest a PRIORITY the
     * workspace can actually honour.
     *
     * One route, one OpenAI call, additive nullable keys. The edit form and the public
     * portal's own polish cannot drift from this one if there is only one of it.
     */
    public function structureDraft(StructureTicketDraftRequest $request, DraftSuggestionService $drafts): JsonResponse
    {
        $this->checkPermission('use ai assistant');
        abort_unless((bool) AppSetting::get('ai_enabled', false), 404);

        $tenant = Tenant::findOrFail(session('current_tenant_id'));

        // The agent enrichment's own two gates, HERE rather than on the route: adding
        // `feature:ai_agents` + `ai_agents_enabled` to `tickets-ai/structure` would delete
        // a working Polish button for any Enterprise workspace that has agents switched
        // off. Gate off means no agent block and a prompt byte-identical to the one this
        // surface sent before P7. Session-reading forms are correct here — this is a web
        // request whose session IS the workspace in the URL; they are forbidden in the
        // queued intake analysis for exactly that reason.
        $withAgents = app(PlanService::class)->currentTenantHasFeature(PlanFeature::AiAgents)
            && (bool) AppSetting::get('ai_agents_enabled', false);

        try {
            $suggestion = $drafts->suggest(
                $tenant,
                $request->user(),
                (string) ($request->validated('subject') ?? ''),
                (string) $request->validated('description'),
                $request->validated('client_id') === null ? null : (int) $request->validated('client_id'),
                $request->validated('department_id') === null ? null : (int) $request->validated('department_id'),
                (string) ($request->validated('context') ?? DraftSuggestionService::CONTEXT_CREATE),
                $withAgents,
                // The reporter's own declared technical facts, read off the half-filled form.
                // Validated nullable, so a mid-draft Polish carries whatever is answered so
                // far and nothing is inferred for what is not.
                $request->validated('impact_scope'),
                $request->validated('workaround'),
                $request->validated('environment'),
                $request->validated('steps_to_reproduce'),
                $request->validated('error_text'),
            );
        } catch (OpenAiException $e) {
            report($e);

            return response()->json(['error' => 'The AI assistant is unavailable right now.'], 503);
        }

        // A refused draft is shown to the engineer who wrote it, rather than silently
        // returning their own text back as though the AI had agreed with it.
        if ($suggestion->refused()) {
            return response()->json(['error' => $suggestion->refusal], 422);
        }

        return response()->json($suggestion->toArray());
    }

    // ─────────── In-app assistant (per-user, multi-conversation) ───────────

    public function message(Request $request): JsonResponse
    {
        $tenant = Tenant::findOrFail(session('current_tenant_id'));
        abort_unless((bool) AppSetting::get('ai_enabled', false), 404);

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'conversation_id' => ['nullable', 'integer'],
            'page_path' => ['nullable', 'string', 'max:2048'],
            'file' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,gif,webp,pdf,txt,csv,json'],
        ]);

        $conversation = $this->resolveUserConversation($tenant, $request->user(), $validated['conversation_id'] ?? null);

        $pageContext = app(PageContextResolver::class)->describe($tenant, $validated['page_path'] ?? null);

        $message = $validated['message'];
        $imageDataUrl = null;
        if ($request->hasFile('file')) {
            $parsed = FileParser::parse($request->file('file'));
            if ($parsed['type'] === 'image') {
                $imageDataUrl = $parsed['data_url'];
                $message .= "\n\n[Attached image: {$parsed['name']}]";
            } else {
                $message .= "\n\n[Attached file: {$parsed['name']}]\n".$parsed['content'];
            }
        }

        try {
            $reply = $this->assistant->replyToAppMessage($tenant, $conversation, $request->user(), $message, $imageDataUrl, $pageContext);
        } catch (OpenAiException $e) {
            report($e);

            return response()->json(['reply' => "Sorry, I'm having trouble right now. Please try again.", 'conversation_id' => $conversation->id]);
        }

        return response()->json(['reply' => $reply, 'conversation_id' => $conversation->id]);
    }

    /**
     * Save a confirmed-helpful assistant answer to the tenant's learned knowledge
     * (opt-in via ai_learn_from_chat), so it can be reused for similar questions.
     */
    public function learn(Request $request): JsonResponse
    {
        $this->checkPermission('manage ai knowledge');
        abort_unless(
            (bool) AppSetting::get('ai_enabled', false) && (bool) AppSetting::get('ai_learn_from_chat', false),
            404
        );

        $validated = $request->validate([
            'question' => ['required', 'string', 'max:2000'],
            'answer' => ['required', 'string', 'max:8000'],
        ]);

        $tenant = Tenant::findOrFail(session('current_tenant_id'));
        $this->assistant->learnFromExchange($tenant, $request->user(), $validated['question'], $validated['answer']);

        return response()->json(['ok' => true]);
    }

    /**
     * Review screen for the saved (learned) answers — so admins can see and prune
     * what the assistant has been taught.
     */
    public function knowledge(): View
    {
        $this->checkPermission('manage ai knowledge');

        $snippets = LearnedSnippet::with('creator')->latest('id')->paginate(20);

        return view('settings.ai-knowledge', compact('snippets'));
    }

    public function deleteKnowledge(LearnedSnippet $snippet): RedirectResponse
    {
        $this->checkPermission('manage ai knowledge');

        $snippet->delete(); // tenant-scoped by the global scope

        return back()->with('success', 'Learned answer removed.');
    }

    /**
     * Status updates on bugs THIS user reported, to surface inside the assistant.
     * Non-consuming: returns updates whose status hasn't been acknowledged yet.
     */
    public function bugUpdates(Request $request): JsonResponse
    {
        $tenant = Tenant::findOrFail(session('current_tenant_id'));

        $bugs = BugReport::where('tenant_id', $tenant->id)
            ->where('reported_by', $request->user()->id)
            ->whereIn('status', BugReport::USER_FACING_STATUSES)
            ->where(fn ($q) => $q->whereNull('user_notified_status')->orWhereColumn('user_notified_status', '!=', 'status'))
            ->latest('updated_at')
            ->limit(10)
            ->get();

        return response()->json([
            'updates' => $bugs->map(fn (BugReport $b) => [
                'reference' => $b->reference(),
                'status' => $b->status,
                'message' => $b->userFacingMessage(),
            ])->values(),
        ]);
    }

    /**
     * Acknowledge the surfaced bug updates so they aren't shown again.
     *
     * Scoped to the references the client actually displayed. Acknowledging
     * every user-facing bug silently swallowed any update beyond the ten
     * bugUpdates() returns — the user would never be told about those.
     */
    public function ackBugUpdates(Request $request): JsonResponse
    {
        $tenant = Tenant::findOrFail(session('current_tenant_id'));

        $validated = $request->validate([
            'references' => ['sometimes', 'array', 'max:50'],
            'references.*' => ['string', 'max:64'],
        ]);

        $query = BugReport::where('tenant_id', $tenant->id)
            ->where('reported_by', $request->user()->id)
            ->whereIn('status', BugReport::USER_FACING_STATUSES);

        // No references given (older client): fall back to the same window
        // bugUpdates() surfaces, rather than the whole history.
        if (filled($validated['references'] ?? null)) {
            // BugReport::reference() is "BUG-{year}-{id padded to 6}"; the id is
            // the trailing segment, not the year.
            $ids = collect($validated['references'])
                ->map(fn (string $ref) => preg_match('/-(\d+)$/', $ref, $m) === 1 ? (int) $m[1] : 0)
                ->filter()
                ->all();
            $query->whereIn('id', $ids);
        } else {
            $query->where(fn ($q) => $q->whereNull('user_notified_status')->orWhereColumn('user_notified_status', '!=', 'status'))
                ->latest('updated_at')
                ->limit(10);
        }

        $query->get()->each(fn (BugReport $b) => $b->update(['user_notified_status' => $b->status]));

        return response()->json(['ok' => true]);
    }

    /**
     * The logged-in user's own past conversations (for the widget history list).
     */
    public function myConversations(Request $request): JsonResponse
    {
        $tenant = Tenant::findOrFail(session('current_tenant_id'));

        $list = ChatConversation::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $request->user()->id)
            ->where('channel', ChatConversation::CHANNEL_AGENT)
            ->whereHas('messages', fn ($q) => $q->where('role', ChatMessage::ROLE_USER))
            ->latest('id')
            ->limit(30)
            ->get()
            ->map(fn (ChatConversation $c) => ['id' => $c->id, 'title' => $this->conversationTitle($c)]);

        return response()->json(['conversations' => $list]);
    }

    /**
     * Messages of one of the user's own conversations.
     */
    public function myConversationMessages(Request $request, ChatConversation $conversation): JsonResponse
    {
        $tenant = Tenant::findOrFail(session('current_tenant_id'));
        abort_if(
            $conversation->tenant_id !== $tenant->id
            || $conversation->user_id !== $request->user()->id
            || $conversation->channel !== ChatConversation::CHANNEL_AGENT,
            404
        );

        $messages = $conversation->messages()
            ->whereIn('role', [ChatMessage::ROLE_USER, ChatMessage::ROLE_ASSISTANT])
            ->whereNotNull('content')
            ->get()
            ->map(fn (ChatMessage $m) => ['role' => $m->role, 'text' => (string) $m->content]);

        return response()->json(['conversation_id' => $conversation->id, 'messages' => $messages]);
    }

    // ─────────── Saved chat history (admin viewer) ───────────

    public function conversations(): View
    {
        $this->checkPermission('manage ai settings');

        $conversations = ChatConversation::with(['user', 'client'])
            ->withCount('messages')
            ->latest('id')
            ->paginate(20);

        return view('settings.ai-conversations', compact('conversations'));
    }

    public function showConversation(ChatConversation $conversation): View
    {
        $this->checkPermission('manage ai settings');

        // ChatConversation::resolveRouteBinding() deliberately bypasses the tenant
        // scope, because the anonymous portal has no session to scope by. That
        // makes the tenant check the caller's job, and this caller was not doing
        // it: any workspace admin could read another workspace's conversation by
        // id. Mirrors the check myConversationMessages() already performs.
        $tenant = Tenant::findOrFail(session('current_tenant_id'));
        abort_if($conversation->tenant_id !== $tenant->id, 404);

        $conversation->load(['user', 'client']);
        $messages = $conversation->messages()
            ->whereIn('role', [ChatMessage::ROLE_USER, ChatMessage::ROLE_ASSISTANT])
            ->whereNotNull('content')
            ->get();

        return view('settings.ai-conversation', compact('conversation', 'messages'));
    }

    /**
     * Resolve the conversation to append to: an existing one owned by the user,
     * or a brand-new conversation when no (valid) id is given (i.e. "New chat").
     */
    private function resolveUserConversation(Tenant $tenant, User $user, ?int $id): ChatConversation
    {
        if ($id) {
            $existing = ChatConversation::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('user_id', $user->id)
                ->where('channel', ChatConversation::CHANNEL_AGENT)
                ->where('id', $id)
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        return ChatConversation::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'channel' => ChatConversation::CHANNEL_AGENT,
            'status' => 'active',
        ]);
    }

    private function conversationTitle(ChatConversation $conversation): string
    {
        $first = $conversation->messages()
            ->where('role', ChatMessage::ROLE_USER)
            ->whereNotNull('content')
            ->orderBy('id')
            ->value('content');

        return $first ? Str::limit(trim($first), 40) : 'New conversation';
    }
}
