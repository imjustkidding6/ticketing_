<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiPromptTemplate;
use App\Models\AiUsageLog;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatMessageFeedback;
use App\Models\KbArticle;
use App\Models\Tenant;
use App\Services\AiConversationExportService;
use App\Services\AiMetricsService;
use App\Services\CircuitBreakerService;
use App\Services\EmbeddingService;
use App\Services\OpenAiService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AiAdminManagerController extends Controller
{
    public function __construct(
        private readonly OpenAiService $openAi,
        private readonly EmbeddingService $embeddingService,
    ) {}

    /**
     * View and configure system AI settings and feature flags.
     */
    public function settings(): View
    {
        $settings = Cache::get('ai_admin_settings', [
            'openai_model' => config('services.openai.model', 'gpt-5'),
            'embedding_model' => config('services.openai.embedding_model', 'text-embedding-3-small'),
            'temperature' => 0.7,
            'max_tokens' => 2000,
            'top_p' => 1.0,
            'frequency_penalty' => 0.0,
            'presence_penalty' => 0.0,
            'feature_portal_ai' => true,
            'feature_agent_copilot' => true,
            'feature_knowledge_search' => true,
            'feature_web_search' => true,
            'feature_vision' => true,
            'feature_self_learning' => true,
            'feature_bug_reporting' => true,
            'feature_charts' => true,
        ]);

        return view('admin.ai.settings', ['settings' => $settings]);
    }

    /**
     * Update AI configuration settings.
     */
    public function updateSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'openai_model' => ['required', 'string'],
            'embedding_model' => ['required', 'string'],
            'temperature' => ['required', 'numeric', 'min:0', 'max:2'],
            'max_tokens' => ['required', 'integer', 'min:100', 'max:16000'],
            'top_p' => ['required', 'numeric', 'min:0', 'max:1'],
            'frequency_penalty' => ['required', 'numeric', 'min:-2', 'max:2'],
            'presence_penalty' => ['required', 'numeric', 'min:-2', 'max:2'],
            'feature_portal_ai' => ['nullable', 'boolean'],
            'feature_agent_copilot' => ['nullable', 'boolean'],
            'feature_knowledge_search' => ['nullable', 'boolean'],
            'feature_web_search' => ['nullable', 'boolean'],
            'feature_vision' => ['nullable', 'boolean'],
            'feature_self_learning' => ['nullable', 'boolean'],
            'feature_bug_reporting' => ['nullable', 'boolean'],
            'feature_charts' => ['nullable', 'boolean'],
        ]);

        $settings = [
            'openai_model' => $validated['openai_model'],
            'embedding_model' => $validated['embedding_model'],
            'temperature' => (float) $validated['temperature'],
            'max_tokens' => (int) $validated['max_tokens'],
            'top_p' => (float) $validated['top_p'],
            'frequency_penalty' => (float) $validated['frequency_penalty'],
            'presence_penalty' => (float) $validated['presence_penalty'],
            'feature_portal_ai' => $request->boolean('feature_portal_ai'),
            'feature_agent_copilot' => $request->boolean('feature_agent_copilot'),
            'feature_knowledge_search' => $request->boolean('feature_knowledge_search'),
            'feature_web_search' => $request->boolean('feature_web_search'),
            'feature_vision' => $request->boolean('feature_vision'),
            'feature_self_learning' => $request->boolean('feature_self_learning'),
            'feature_bug_reporting' => $request->boolean('feature_bug_reporting'),
            'feature_charts' => $request->boolean('feature_charts'),
        ];

        Cache::forever('ai_admin_settings', $settings);

        Log::info('AI System settings updated by admin.', [
            'admin_id' => auth()->id(),
            'settings' => $settings,
        ]);

        return back()->with('success', 'AI settings updated successfully.');
    }

    /**
     * View Prompt Templates list and version history.
     */
    public function prompts(): View
    {
        $prompts = AiPromptTemplate::with(['tenant', 'department', 'author'])
            ->latest('id')
            ->paginate(15);

        return view('admin.ai.prompts', ['prompts' => $prompts]);
    }

    /**
     * Save a new prompt template version.
     */
    public function storePrompt(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tenant_id' => ['nullable', 'integer'],
            'department_id' => ['nullable', 'integer'],
            'type' => ['required', 'string', 'in:global,portal,agent,department'],
            'name' => ['required', 'string', 'max:255'],
            'prompt' => ['required', 'string'],
        ]);

        $latestVersion = AiPromptTemplate::where('type', $validated['type'])
            ->where('tenant_id', $validated['tenant_id'] ?? null)
            ->max('version') ?? 0;

        AiPromptTemplate::create([
            'tenant_id' => $validated['tenant_id'] ?? null,
            'department_id' => $validated['department_id'] ?? null,
            'type' => $validated['type'],
            'name' => $validated['name'],
            'prompt' => $validated['prompt'],
            'version' => $latestVersion + 1,
            'created_by' => auth()->id(),
        ]);

        Log::info("AI Prompt template '{$validated['name']}' created v".($latestVersion + 1));

        return back()->with('success', 'Prompt template saved successfully.');
    }

    /**
     * AI Conversation Manager: Search and filter conversations.
     */
    public function conversations(Request $request): View
    {
        $query = ChatConversation::withoutGlobalScopes()
            ->with(['tenant', 'user', 'messages'])
            ->latest('updated_at');

        if ($request->filled('channel')) {
            $query->where('channel', $request->query('channel'));
        }

        if ($request->filled('tenant_id')) {
            $query->where('tenant_id', $request->query('tenant_id'));
        }

        $conversations = $query->paginate(20);
        $tenants = Tenant::where('is_active', true)->orderBy('name')->get();

        return view('admin.ai.conversations', [
            'conversations' => $conversations,
            'tenants' => $tenants,
        ]);
    }

    /**
     * Export conversation logs as CSV.
     */
    public function exportConversations(Request $request): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="ai_conversations_export_'.date('Y-m-d').'.csv"',
        ];

        $callback = function () use ($request) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Conversation ID', 'Tenant ID', 'Channel', 'User ID', 'Message Count', 'Created At', 'Status']);

            $query = ChatConversation::withoutGlobalScopes()->with('messages');
            if ($request->filled('tenant_id')) {
                $query->where('tenant_id', $request->query('tenant_id'));
            }

            $query->chunk(100, function ($conversations) use ($file) {
                foreach ($conversations as $conv) {
                    fputcsv($file, [
                        $conv->id,
                        $conv->tenant_id,
                        $conv->channel,
                        $conv->user_id ?? 'Guest',
                        $conv->messages->count(),
                        $conv->created_at->toIso8601String(),
                        $conv->status,
                    ]);
                }
            });

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Delete an AI conversation.
     */
    public function deleteConversation(int $id): RedirectResponse
    {
        $conversation = ChatConversation::withoutGlobalScopes()->findOrFail($id);
        $conversation->delete();

        Log::info("AI Conversation #{$id} deleted by admin.");

        return back()->with('success', 'Conversation deleted.');
    }

    /**
     * View user feedback reports.
     */
    public function feedback(): View
    {
        $feedbacks = ChatMessageFeedback::with(['tenant', 'user', 'chatMessage'])
            ->latest('id')
            ->paginate(20);

        return view('admin.ai.feedback', ['feedbacks' => $feedbacks]);
    }

    /**
     * Store response feedback (Thumbs Up / Down).
     */
    public function storeFeedback(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'chat_message_id' => ['nullable', 'integer'],
            'tenant_id' => ['required', 'integer'],
            'rating' => ['required', 'string', 'in:thumbs_up,thumbs_down'],
            'comment' => ['nullable', 'string', 'max:1000'],
            'question' => ['nullable', 'string'],
            'response' => ['nullable', 'string'],
        ]);

        $feedback = ChatMessageFeedback::create([
            'chat_message_id' => $validated['chat_message_id'] ?? null,
            'tenant_id' => $validated['tenant_id'],
            'user_id' => auth()->id(),
            'rating' => $validated['rating'],
            'comment' => $validated['comment'] ?? null,
            'question' => $validated['question'] ?? null,
            'response' => $validated['response'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'feedback_id' => $feedback->id,
        ]);
    }

    /**
     * Interactive Prompt Playground.
     */
    public function playground(): View
    {
        return view('admin.ai.playground');
    }

    /**
     * Execute test prompt in Playground.
     */
    public function runPlaygroundTest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'system_prompt' => ['required', 'string'],
            'user_message' => ['required', 'string'],
            'model' => ['nullable', 'string'],
        ]);

        if (! $this->openAi->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'OpenAI API key is not configured.',
            ], 503);
        }

        $startTime = microtime(true);

        try {
            $response = $this->openAi->chat([
                ['role' => 'system', 'content' => $validated['system_prompt']],
                ['role' => 'user', 'content' => $validated['user_message']],
            ]);

            $duration = round(microtime(true) - $startTime, 2);
            $reply = $response['choices'][0]['message']['content'] ?? '';
            $usage = $response['usage'] ?? ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0];

            return response()->json([
                'success' => true,
                'reply' => $reply,
                'duration_seconds' => $duration,
                'usage' => $usage,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Analytics and Token Usage Dashboard.
     */
    public function analytics(): View
    {
        $metrics = $this->embeddingService->getMetrics();

        $totalConversations = ChatConversation::withoutGlobalScopes()->count();
        $totalMessages = ChatMessage::withoutGlobalScopes()->count();
        $thumbsUpCount = ChatMessageFeedback::where('rating', 'thumbs_up')->count();
        $thumbsDownCount = ChatMessageFeedback::where('rating', 'thumbs_down')->count();

        // Estimated cost based on ~ $0.002 per 1k tokens
        $estimatedCost = round(($totalMessages * 450 / 1000) * 0.002, 2);

        return view('admin.ai.analytics', [
            'metrics' => $metrics,
            'totalConversations' => $totalConversations,
            'totalMessages' => $totalMessages,
            'thumbsUpCount' => $thumbsUpCount,
            'thumbsDownCount' => $thumbsDownCount,
            'estimatedCost' => $estimatedCost,
        ]);
    }

    /**
     * AI Infrastructure Health Monitoring Dashboard.
     */
    public function health(CircuitBreakerService $circuitBreaker, AiMetricsService $metricsService): View
    {
        $openAiAvailable = $this->openAi->isConfigured();
        $circuitStatus = $circuitBreaker->getStatus();
        $metrics = $metricsService->getMetricsSummary();

        $status = 'healthy';
        if (! $openAiAvailable || $circuitStatus['state'] === 'OPEN') {
            $status = 'critical';
        } elseif ($metrics['error_rate_percent'] > 5.0) {
            $status = 'warning';
        }

        return view('admin.ai.health', [
            'status' => $status,
            'openAiAvailable' => $openAiAvailable,
            'circuitStatus' => $circuitStatus,
            'metrics' => $metrics,
            'lastApiRequest' => AiUsageLog::latest('created_at')->value('created_at'),
            'lastEmbeddingRun' => KbArticle::withoutGlobalScopes()->latest('embedded_at')->value('embedded_at'),
        ]);
    }

    /**
     * Export a specific AI conversation in PDF/HTML, CSV, or JSON format.
     */
    public function exportSingleConversation(int $id, string $format, AiConversationExportService $exportService): mixed
    {
        $conversation = ChatConversation::withoutGlobalScopes()->with('messages')->findOrFail($id);

        if ($format === 'json') {
            return response()->json($exportService->exportJson($conversation));
        }

        if ($format === 'csv') {
            return response($exportService->exportCsv($conversation))
                ->header('Content-Type', 'text/csv')
                ->header('Content-Disposition', 'attachment; filename="conversation_'.$id.'.csv"');
        }

        return response($exportService->exportHtml($conversation))
            ->header('Content-Type', 'text/html');
    }
}
