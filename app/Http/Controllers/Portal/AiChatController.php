<?php

namespace App\Http\Controllers\Portal;

use App\Enums\PlanFeature;
use App\Exceptions\OpenAiException;
use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Tenant;
use App\Services\AiAssistantService;
use App\Services\PlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AiChatController extends Controller
{
    /** Per-tenant daily cap on portal user messages (cost guard). */
    private const DAILY_MESSAGE_CAP = 200;

    public function __construct(
        private PlanService $planService,
        private AiAssistantService $assistant,
    ) {}

    public function message(Request $request, string $slug): JsonResponse
    {
        $tenant = $this->resolveTenant($slug);

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'session_token' => ['nullable', 'string', 'max:64'],
        ]);

        $conversation = $this->conversationFor($tenant, $validated['session_token'] ?? null);

        if ($this->overDailyCap($tenant)) {
            return response()->json([
                'reply' => "The assistant has reached today's message limit. Please submit a ticket and our team will help you.",
                'session_token' => $conversation->session_token,
            ]);
        }

        try {
            $reply = $this->assistant->replyToPortalMessage($tenant, $conversation, $validated['message']);
        } catch (OpenAiException $e) {
            report($e);

            return response()->json([
                'reply' => "Sorry, I'm having trouble right now. You can submit a ticket and our team will follow up.",
                'session_token' => $conversation->session_token,
            ]);
        }

        return response()->json([
            'reply' => $reply,
            'session_token' => $conversation->session_token,
        ]);
    }

    /**
     * Return the prior messages for a conversation so the widget can restore it on reload.
     */
    public function history(Request $request, string $slug): JsonResponse
    {
        $tenant = $this->resolveTenant($slug);
        $token = (string) $request->query('session_token', '');

        if ($token === '') {
            return response()->json(['messages' => []]);
        }

        $conversation = ChatConversation::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('channel', ChatConversation::CHANNEL_PORTAL)
            ->where('session_token', $token)
            ->first();

        if (! $conversation) {
            return response()->json(['messages' => []]);
        }

        $messages = $conversation->messages()
            ->whereIn('role', [ChatMessage::ROLE_USER, ChatMessage::ROLE_ASSISTANT])
            ->whereNotNull('content')
            ->get()
            ->map(fn (ChatMessage $m) => ['role' => $m->role, 'text' => (string) $m->content])
            ->values();

        return response()->json(['messages' => $messages]);
    }

    private function resolveTenant(string $slug): Tenant
    {
        $tenant = Tenant::where('slug', $slug)
            ->where('is_active', true)
            ->whereNull('suspended_at')
            ->firstOrFail();

        // Feature plan + explicit opt-in toggles (default off to control cost).
        if (! $this->planService->tenantHasFeature($tenant, PlanFeature::AiChatbot)) {
            abort(404);
        }

        if ($this->aiSetting($tenant, 'ai_enabled') !== '1' || $this->aiSetting($tenant, 'ai_portal_widget_enabled') !== '1') {
            abort(404);
        }

        return $tenant;
    }

    private function aiSetting(Tenant $tenant, string $key): ?string
    {
        return AppSetting::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('key', $key)
            ->value('value');
    }

    private function conversationFor(Tenant $tenant, ?string $token): ChatConversation
    {
        if ($token) {
            $existing = ChatConversation::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('channel', ChatConversation::CHANNEL_PORTAL)
                ->where('session_token', $token)
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        return ChatConversation::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'channel' => ChatConversation::CHANNEL_PORTAL,
            'session_token' => Str::random(64),
            'status' => 'active',
        ]);
    }

    private function overDailyCap(Tenant $tenant): bool
    {
        $count = ChatMessage::query()
            ->where('role', ChatMessage::ROLE_USER)
            ->whereDate('created_at', now()->toDateString())
            ->whereHas('conversation', fn ($q) => $q->where('tenant_id', $tenant->id)->where('channel', ChatConversation::CHANNEL_PORTAL))
            ->count();

        return $count >= self::DAILY_MESSAGE_CAP;
    }
}
