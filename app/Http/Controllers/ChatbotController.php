<?php

namespace App\Http\Controllers;

use App\Enums\PlanFeature;
use App\Http\Requests\ChatbotEscalateRequest;
use App\Http\Requests\ChatbotMessageRequest;
use App\Http\Requests\ChatbotTokenRequest;
use App\Models\AppSetting;
use App\Models\ChatbotMessage;
use App\Models\ChatbotSession;
use App\Models\ChatbotUsage;
use App\Models\Department;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Notifications\ChatbotFallbackNotification;
use App\Services\ChatbotAiService;
use App\Services\ChatbotEscalationService;
use App\Services\ChatbotTokenService;
use App\Services\PlanService;
use App\Services\TenantUrlHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class ChatbotController extends Controller
{
    public function __construct(
        private ChatbotTokenService $tokenService,
        private ChatbotAiService $aiService,
        private ChatbotEscalationService $escalationService,
        private PlanService $planService,
    ) {}

    public function token(ChatbotTokenRequest $request, string $slug): JsonResponse
    {
        $tenant = $this->resolveTenant($slug);
        $this->abortIfChatbotDisabled($tenant);

        $sessionId = $request->validated('session_id') ?? (string) Str::uuid();

        ChatbotSession::withoutGlobalScopes()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'session_id' => $sessionId],
            ['status' => 'active', 'last_seen_at' => now()]
        );

        return response()->json($this->tokenService->issueToken($tenant, $sessionId));
    }

    public function message(ChatbotMessageRequest $request, string $slug): JsonResponse
    {
        $tenant = $this->resolveTenant($slug);
        $this->abortIfChatbotDisabled($tenant);

        $tokenPayload = $this->validateToken($request->bearerToken(), $slug);
        if (! $tokenPayload) {
            return $this->errorResponse('Invalid token.', 401);
        }

        if ($tokenPayload['session_id'] !== $request->validated('session_id')) {
            return $this->errorResponse('Session mismatch.', 403);
        }

        $session = ChatbotSession::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('session_id', $request->validated('session_id'))
            ->first();

        if (! $session) {
            return $this->errorResponse('Session not found.', 404);
        }

        if (! $this->allowMessage($tenant)) {
            return $this->errorResponse('Daily chatbot limit reached. Please try again tomorrow.', 429);
        }

        $userMessage = $request->validated('message');

        ChatbotMessage::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'chatbot_session_id' => $session->id,
            'role' => 'user',
            'content' => $userMessage,
        ]);

        $conversation = $this->buildConversation($session);
        $aiResponse = $this->aiService->generateReply($tenant, $conversation);

        ChatbotMessage::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'chatbot_session_id' => $session->id,
            'role' => 'assistant',
            'content' => $aiResponse['reply'],
            'confidence' => $aiResponse['confidence'],
            'prompt_tokens' => $aiResponse['prompt_tokens'],
            'completion_tokens' => $aiResponse['completion_tokens'],
            'model' => $aiResponse['model'],
            'metadata' => ['fallback' => $aiResponse['fallback']],
        ]);

        $this->trackUsage($tenant->id, $aiResponse['prompt_tokens'] + $aiResponse['completion_tokens']);
        $session->update(['last_seen_at' => now()]);

        $threshold = (float) ($this->getChatbotSetting('chatbot_confidence_threshold') ?? 0.6);
        $escalate = $this->shouldEscalate($userMessage, $aiResponse['confidence'], $threshold) || $aiResponse['fallback'];

        if ($aiResponse['fallback']) {
            $this->notifyFallback($tenant, 'AI provider error or empty response.');
        }

        if ($escalate) {
            return $this->handleEscalation($tenant, $session, $conversation);
        }

        return response()->json([
            'reply' => $aiResponse['reply'],
            'confidence' => $aiResponse['confidence'],
            'next_action' => 'none',
        ]);
    }

    public function escalate(ChatbotEscalateRequest $request, string $slug): JsonResponse
    {
        $tenant = $this->resolveTenant($slug);
        $this->abortIfChatbotDisabled($tenant);

        $tokenPayload = $this->validateToken($request->bearerToken(), $slug);
        if (! $tokenPayload) {
            return $this->errorResponse('Invalid token.', 401);
        }

        $session = ChatbotSession::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('session_id', $request->validated('session_id'))
            ->first();

        if (! $session) {
            return $this->errorResponse('Session not found.', 404);
        }

        if ($session->escalated_ticket_id) {
            return response()->json([
                'message' => 'Ticket already created.',
            ]);
        }

        $session->update([
            'contact_name' => $request->validated('name'),
            'contact_email' => $request->validated('email'),
            'contact_phone' => $request->validated('phone'),
        ]);

        $conversation = $this->buildConversation($session);
        $departmentNames = Department::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->orderBy('name')
            ->pluck('name')
            ->all();

        $draft = $this->aiService->generateTicketDraft($tenant, $conversation, $departmentNames);
        $ticket = $this->escalationService->createTicket(
            $tenant,
            $session,
            $draft
        );

        $session->update([
            'status' => 'escalated',
            'escalated_ticket_id' => $ticket->id,
            'escalated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Ticket created.',
            'ticket_number' => $ticket->ticket_number,
            'tracking_url' => app(TenantUrlHelper::class)->tenantUrl($tenant, "/track-ticket/{$ticket->tracking_token}"),
        ]);
    }

    private function resolveTenant(string $slug): Tenant
    {
        return Tenant::where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();
    }

    private function abortIfChatbotDisabled(Tenant $tenant): void
    {
        if ($tenant->isSuspended()) {
            abort(404);
        }

        if (! $this->planService->tenantHasFeature($tenant, PlanFeature::AiChatbot)) {
            abort(404);
        }
    }

    private function errorResponse(string $message, int $status): JsonResponse
    {
        return response()->json(['message' => $message], $status);
    }

    /**
     * @return array<int, array{role: string, content: string}>
     */
    private function buildConversation(ChatbotSession $session): array
    {
        return $session->messages()
            ->orderByDesc('id')
            ->limit(12)
            ->get()
            ->reverse()
            ->map(fn (ChatbotMessage $message) => [
                'role' => $message->role,
                'content' => $message->content,
            ])
            ->values()
            ->all();
    }

    private function shouldEscalate(string $message, float $confidence, float $threshold): bool
    {
        $needsHuman = (bool) preg_match('/\b(agent|human|support|representative|call me|help me)\b/i', $message);

        return $needsHuman || $confidence < $threshold;
    }

    private function allowMessage(Tenant $tenant): bool
    {
        $usage = ChatbotUsage::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'usage_date' => now()->toDateString()],
            ['message_count' => 0, 'tokens_used' => 0]
        );

        return $usage->message_count < 2000;
    }

    private function trackUsage(int $tenantId, int $tokens): void
    {
        ChatbotUsage::withoutGlobalScopes()->updateOrCreate(
            ['tenant_id' => $tenantId, 'usage_date' => now()->toDateString()],
            ['message_count' => 0, 'tokens_used' => 0]
        );

        ChatbotUsage::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('usage_date', now()->toDateString())
            ->increment('message_count');

        if ($tokens > 0) {
            ChatbotUsage::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('usage_date', now()->toDateString())
                ->increment('tokens_used', $tokens);
        }
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $conversation
     */
    private function handleEscalation(Tenant $tenant, ChatbotSession $session, array $conversation): JsonResponse
    {
        if (! $session->contact_email || ! $session->contact_name) {
            return response()->json([
                'reply' => 'I can get a human to help. What is your name and email address?',
                'next_action' => 'collect_contact',
            ]);
        }

        $departmentNames = Department::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->orderBy('name')
            ->pluck('name')
            ->all();

        $draft = $this->aiService->generateTicketDraft($tenant, $conversation, $departmentNames);
        $ticket = $this->escalationService->createTicket(
            $tenant,
            $session,
            $draft
        );

        $session->update([
            'status' => 'escalated',
            'escalated_ticket_id' => $ticket->id,
            'escalated_at' => now(),
        ]);

        return response()->json([
            'reply' => 'Thanks! I created a ticket and our team will follow up shortly.',
            'next_action' => 'ticket_created',
            'ticket_number' => $ticket->ticket_number,
            'tracking_url' => app(TenantUrlHelper::class)->tenantUrl($tenant, "/track-ticket/{$ticket->tracking_token}"),
        ]);
    }

    private function validateToken(?string $token, string $slug): ?array
    {
        if (! $token) {
            return null;
        }

        try {
            $payload = $this->tokenService->parseToken($token);
        } catch (\Throwable) {
            return null;
        }

        return $payload['tenant_slug'] === $slug ? $payload : null;
    }

    private function notifyFallback(Tenant $tenant, string $reason): void
    {
        $adminEmail = AppSetting::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('key', 'admin_notification_email')
            ->first()?->getTypedValue();

        if ($adminEmail) {
            Notification::route('mail', $adminEmail)
                ->notify(new ChatbotFallbackNotification($tenant, $reason));
        }
    }

    private function getChatbotSetting(string $key): mixed
    {
        return SystemSetting::get($key);
    }
}
