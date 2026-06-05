<?php

namespace App\Http\Controllers;

use App\Exceptions\OpenAiException;
use App\Models\AppSetting;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use App\Services\AiAssistantService;
use App\Services\PageContextResolver;
use App\Support\FileParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Authenticated "agent copilot" endpoints. Gated by the ai_chatbot feature
 * (route middleware); the ticket is tenant-scoped by the global TenantScope.
 */
class AiAssistantController extends Controller
{
    public function __construct(private AiAssistantService $assistant) {}

    public function draftReply(Ticket $ticket): JsonResponse
    {
        $this->checkPermission('view tickets');

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
        $this->checkPermission('view tickets');

        try {
            $text = $this->assistant->summarizeTicket($ticket);
        } catch (OpenAiException $e) {
            report($e);

            return response()->json(['error' => 'The AI assistant is unavailable right now.'], 503);
        }

        return response()->json(['text' => $text]);
    }

    /**
     * Clean up & structure a rough ticket draft (subject, description, tasks)
     * on the create-ticket form, so the stored data — and the self-learning
     * dataset built from it — stays consistent.
     */
    public function structureDraft(Request $request): JsonResponse
    {
        $this->checkPermission('create tickets');
        abort_unless((bool) AppSetting::get('ai_enabled', false), 404);

        $validated = $request->validate([
            'subject' => ['nullable', 'string', 'max:2000'],
            'description' => ['required', 'string', 'max:8000'],
        ]);

        $tenant = Tenant::findOrFail(session('current_tenant_id'));

        try {
            $result = $this->assistant->structureTicketDraft(
                $tenant,
                (string) ($validated['subject'] ?? ''),
                $validated['description'],
            );
        } catch (OpenAiException $e) {
            report($e);

            return response()->json(['error' => 'The AI assistant is unavailable right now.'], 503);
        }

        return response()->json($result);
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
        $this->checkPermission('manage settings');

        $conversations = ChatConversation::with(['user', 'client'])
            ->withCount('messages')
            ->latest('id')
            ->paginate(20);

        return view('settings.ai-conversations', compact('conversations'));
    }

    public function showConversation(ChatConversation $conversation): View
    {
        $this->checkPermission('manage settings');

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
