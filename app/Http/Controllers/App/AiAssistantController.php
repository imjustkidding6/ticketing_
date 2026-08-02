<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Exceptions\OpenAiException;
use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AiAssistantService;
use App\Services\PageContextResolver;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Throwable;

/**
 * Controller for authenticated staff/agent AI assistant workspace interactions.
 */
class AiAssistantController extends Controller
{
    public function __construct(
        private readonly AiAssistantService $assistant,
    ) {}

    /**
     * Start a new agent AI conversation.
     * POST /app/ai/start
     */
    public function startConversation(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        try {
            $tenant = $this->resolveTenant($request, $user);

            $conversation = ChatConversation::create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'channel' => ChatConversation::CHANNEL_AGENT,
                'status' => ChatConversation::STATUS_ACTIVE,
            ]);

            return response()->json([
                'success' => true,
                'conversation_id' => $conversation->id,
                'messages' => [],
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
                'message' => 'Failed to initialize agent conversation.',
            ], 500);
        }
    }

    /**
     * Send a message in an agent conversation, with optional image upload and page context.
     * POST /app/ai/message
     */
    public function sendMessage(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'conversation_id' => ['nullable', 'integer'],
            'page_context' => ['nullable', 'string', 'max:4000'],
            'page_path' => ['nullable', 'string', 'max:2048'],
            'image' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp'],
            'file' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp'],
        ]);

        try {
            $tenant = $this->resolveTenant($request, $user);
            $conversation = $this->resolveConversation($tenant, $user, $validated['conversation_id'] ?? null);

            $pageContext = $validated['page_context'] ?? null;
            if ($pageContext === null && filled($validated['page_path'] ?? null)) {
                $pageContext = app(PageContextResolver::class)->describe($tenant, $validated['page_path']);
            }

            $messageText = $validated['message'];
            $imageDataUrl = null;

            $uploadedFile = $request->file('image') ?? $request->file('file');
            if ($uploadedFile instanceof UploadedFile && $uploadedFile->isValid()) {
                $imageDataUrl = $this->convertImageToBase64DataUrl($uploadedFile);
            }

            $reply = $this->assistant->replyToAppMessage(
                $tenant,
                $conversation,
                $user,
                $messageText,
                $imageDataUrl,
                $pageContext
            );

            $messages = $this->formattedMessages($conversation);

            return response()->json([
                'success' => true,
                'conversation_id' => $conversation->id,
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
     * Load conversation history for authenticated agent.
     * GET /app/ai/{conversation}
     */
    public function history(Request $request, int|string $conversation): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        try {
            $tenant = $this->resolveTenant($request, $user);

            $conversationModel = ChatConversation::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('user_id', $user->id)
                ->where('channel', ChatConversation::CHANNEL_AGENT)
                ->where('id', $conversation)
                ->first();

            if (! $conversationModel) {
                return response()->json([
                    'success' => false,
                    'message' => 'Conversation not found or unauthorized access.',
                ], 404);
            }

            $messages = $this->formattedMessages($conversationModel);

            return response()->json([
                'success' => true,
                'conversation_id' => $conversationModel->id,
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
     * Convert uploaded image file into Base64 Data URL and safely remove temporary file.
     */
    private function convertImageToBase64DataUrl(UploadedFile $file): ?string
    {
        $path = $file->getRealPath();
        if (! $path || ! file_exists($path)) {
            return null;
        }

        $mime = $file->getMimeType() ?? 'image/png';
        $contents = file_get_contents($path);

        if ($contents === false) {
            return null;
        }

        $base64 = base64_encode($contents);

        // Delete temporary upload after reading into memory
        @unlink($path);

        return "data:{$mime};base64,{$base64}";
    }

    /**
     * Resolve current tenant for authenticated user.
     */
    private function resolveTenant(Request $request, User $user): Tenant
    {
        $tenantId = session('current_tenant_id') ?? $request->input('tenant_id') ?? $user->tenant_id;
        $slug = $request->route('slug') ?? $request->route('tenant') ?? $request->input('slug');

        if ($slug) {
            $tenant = Tenant::where('slug', (string) $slug)
                ->where('is_active', true)
                ->first();

            if ($tenant && $user->belongsToTenant($tenant)) {
                return $tenant;
            }
        }

        if ($tenantId) {
            $tenant = Tenant::where('id', (int) $tenantId)
                ->where('is_active', true)
                ->first();

            if ($tenant && $user->belongsToTenant($tenant)) {
                return $tenant;
            }
        }

        // Default to user's first active tenant
        $tenant = $user->tenants()->where('is_active', true)->first();
        if ($tenant) {
            return $tenant;
        }

        return Tenant::where('is_active', true)->firstOrFail();
    }

    /**
     * Resolve existing agent conversation or create a new one.
     */
    private function resolveConversation(Tenant $tenant, User $user, ?int $conversationId): ChatConversation
    {
        if ($conversationId) {
            $conversation = ChatConversation::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('user_id', $user->id)
                ->where('channel', ChatConversation::CHANNEL_AGENT)
                ->where('id', $conversationId)
                ->first();

            if ($conversation) {
                return $conversation;
            }
        }

        return ChatConversation::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'channel' => ChatConversation::CHANNEL_AGENT,
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
