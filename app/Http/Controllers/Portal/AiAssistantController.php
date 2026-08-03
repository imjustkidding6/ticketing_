<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Exceptions\OpenAiException;
use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Tenant;
use App\Services\AiAssistantService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

/**
 * Controller for public/guest AI assistant portal endpoint interactions.
 */
class AiAssistantController extends Controller
{
    public function __construct(
        private readonly AiAssistantService $assistant,
    ) {}

    /**
     * Start a new portal AI conversation.
     * POST /portal/ai/start
     */
    public function startConversation(Request $request): JsonResponse
    {
        try {
            $tenant = $this->resolveTenant($request);
            $sessionToken = Str::random(64);

            $conversation = ChatConversation::create([
                'tenant_id' => $tenant->id,
                'channel' => ChatConversation::CHANNEL_PORTAL,
                'session_token' => $sessionToken,
                'status' => ChatConversation::STATUS_ACTIVE,
            ]);

            return response()->json([
                'success' => true,
                'conversation_id' => $conversation->id,
                'session_token' => $sessionToken,
                'messages' => [],
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant not found or inactive.',
            ], 404);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to initialize conversation.',
            ], 500);
        }
    }

    /**
     * Send a user message in a portal conversation and receive the AI response.
     * POST /portal/ai/message
     */
    public function sendMessage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'conversation_id' => ['nullable', 'integer'],
            'session_token' => ['nullable', 'string', 'max:64'],
            'tenant_id' => ['nullable', 'integer'],
            'slug' => ['nullable', 'string'],
        ]);

        try {
            $tenant = $this->resolveTenant($request);
            $conversation = $this->resolveConversation($tenant, $validated['conversation_id'] ?? null, $validated['session_token'] ?? null);

            $reply = $this->assistant->replyToPortalMessage($tenant, $conversation, $validated['message']);

            $messages = $this->formattedMessages($conversation);

            return response()->json([
                'success' => true,
                'conversation_id' => $conversation->id,
                'session_token' => $conversation->session_token,
                'assistant_reply' => $reply,
                'reply' => $reply,
                'messages' => $messages,
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant or conversation not found.',
            ], 404);
        } catch (OpenAiException $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'The AI assistant service is currently unavailable. Please try again later.',
            ], 503);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while processing your message.',
            ], 500);
        }
    }

    /**
     * Load conversation history for portal user.
     * GET /portal/ai/{conversation}
     */
    public function loadConversation(Request $request, int|string $conversation): JsonResponse
    {
        try {
            $tenant = $this->resolveTenant($request);
            $token = (string) $request->query('session_token', '');

            $conversationModel = ChatConversation::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('channel', ChatConversation::CHANNEL_PORTAL)
                ->where('id', $conversation)
                ->first();

            if (! $conversationModel) {
                return response()->json([
                    'success' => false,
                    'message' => 'Conversation not found.',
                ], 404);
            }

            if (filled($token) && $conversationModel->session_token !== $token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to conversation.',
                ], 403);
            }

            $messages = $this->formattedMessages($conversationModel);

            return response()->json([
                'success' => true,
                'conversation_id' => $conversationModel->id,
                'session_token' => $conversationModel->session_token,
                'messages' => $messages,
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant not found.',
            ], 404);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load conversation history.',
            ], 500);
        }
    }

    /**
     * Resolve current tenant from request attributes, session, or query/body parameters.
     */
    private function resolveTenant(Request $request): Tenant
    {
        $tenantId = $request->input('tenant_id') ?? session('current_tenant_id');
        $slug = $request->route('slug') ?? $request->route('tenant') ?? $request->input('slug') ?? $request->query('slug');

        if ($slug) {
            return Tenant::where('slug', (string) $slug)
                ->where('is_active', true)
                ->firstOrFail();
        }

        if ($tenantId) {
            return Tenant::where('id', (int) $tenantId)
                ->where('is_active', true)
                ->firstOrFail();
        }

        // Fallback to first active tenant if available
        return Tenant::where('is_active', true)->firstOrFail();
    }

    /**
     * Resolve or create portal conversation.
     */
    private function resolveConversation(Tenant $tenant, ?int $conversationId, ?string $token): ChatConversation
    {
        if ($conversationId) {
            $conversation = ChatConversation::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('channel', ChatConversation::CHANNEL_PORTAL)
                ->where('id', $conversationId)
                ->first();

            if ($conversation) {
                return $conversation;
            }
        }

        if ($token) {
            $conversation = ChatConversation::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('channel', ChatConversation::CHANNEL_PORTAL)
                ->where('session_token', $token)
                ->first();

            if ($conversation) {
                return $conversation;
            }
        }

        return ChatConversation::create([
            'tenant_id' => $tenant->id,
            'channel' => ChatConversation::CHANNEL_PORTAL,
            'session_token' => $token ?? Str::random(64),
            'status' => ChatConversation::STATUS_ACTIVE,
        ]);
    }

    /**
     * Format conversation messages for JSON response.
     *
     * @return array<int, array{role: string, content: string, text: string}>
     */
    private function formattedMessages(ChatConversation $conversation): array
    {
        return $conversation->messages()
            ->whereIn('role', [ChatMessage::ROLE_USER, ChatMessage::ROLE_ASSISTANT])
            ->whereNotNull('content')
            ->get()
            ->map(fn (ChatMessage $m) => [
                'role' => $m->role,
                'content' => (string) $m->content,
                'text' => (string) $m->content,
            ])
            ->values()
            ->all();
    }
}
