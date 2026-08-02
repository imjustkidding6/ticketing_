<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Distributor;
use App\Models\License;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;

class AdminAiCopilotService
{
    public function __construct(
        private readonly PromptInjectionService $promptInjection,
        private readonly ModerationService $moderation,
        private readonly SemanticCacheService $cache,
        private readonly OpenAiService $openAi
    ) {}

    /**
     * Handle user prompt for the Admin AI Copilot.
     *
     * @return array{message: string, action: array<string, mixed>|null, status: string}
     */
    public function processAdminQuery(string $prompt, User $adminUser): array
    {
        $tenantId = null;
        $userId = $adminUser->id;

        // 1. Security Check: Prompt Injection
        if ($this->promptInjection->isInjection($prompt, $tenantId, $userId)) {
            return [
                'message' => 'Action blocked: Your prompt contains system instructions that violate security policies.',
                'action' => null,
                'status' => 'blocked',
            ];
        }

        // 2. Moderation Check
        if ($this->moderation->isFlagged($prompt, 'strict', $tenantId, $userId)) {
            return [
                'message' => 'Action blocked: Content moderation flagged prohibited language or security triggers.',
                'action' => null,
                'status' => 'flagged',
            ];
        }

        // 3. Destructive Operation Guardrail Check
        if ($this->isDestructiveRequest($prompt)) {
            return [
                'message' => 'This action requires manual confirmation. Automatic deletion, suspension, or database modification is disabled for safety.',
                'action' => [
                    'type' => 'navigate',
                    'label' => 'Open System Settings',
                    'url' => route('admin.settings.index'),
                ],
                'status' => 'manual_confirmation_required',
            ];
        }

        // 4. Check Semantic Cache
        return $this->cache->remember('admin_copilot', $prompt, function () use ($prompt) {
            return $this->generateResponse($prompt);
        });
    }

    /**
     * Check if a request implies destructive data modification.
     */
    private function isDestructiveRequest(string $prompt): bool
    {
        $normalized = strtolower($prompt);
        $destructiveKeywords = [
            'delete tenant',
            'delete user',
            'drop database',
            'drop table',
            'suspend tenant',
            'revoke license',
            'truncate',
            'purge database',
            'wipe system',
            'remove all users',
            'delete all tickets',
        ];

        foreach ($destructiveKeywords as $keyword) {
            if (str_contains($normalized, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate response via OpenAI or local System Context Knowledge Engine fallback.
     *
     * @return array{message: string, action: array<string, mixed>|null, status: string}
     */
    private function generateResponse(string $prompt): array
    {
        $context = $this->gatherSystemContext();
        $safeAction = $this->detectSafeAction($prompt);

        if ($this->openAi->isConfigured()) {
            try {
                $systemPrompt = $this->buildSystemPrompt($context);
                $messages = [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $prompt],
                ];

                $response = $this->openAi->chat($messages, [], [
                    'temperature' => 0.2,
                    'max_tokens' => 500,
                ]);

                $replyText = $response['choices'][0]['message']['content'] ?? null;

                if (filled($replyText)) {
                    return [
                        'message' => trim((string) $replyText),
                        'action' => $safeAction,
                        'status' => 'success',
                    ];
                }
            } catch (Throwable $e) {
                Log::warning('OpenAI call failed for Admin AI Copilot, using intelligent system fallback: '.$e->getMessage());
            }
        }

        // Fallback: Knowledge Engine Context Response
        return $this->generateFallbackResponse($prompt, $context, $safeAction);
    }

    /**
     * Gather dynamic, real-time system metrics and statistics.
     *
     * @return array<string, mixed>
     */
    private function gatherSystemContext(): array
    {
        return [
            'tenants' => [
                'total' => Tenant::count(),
                'active' => Tenant::where('is_active', true)->whereNull('suspended_at')->count(),
                'suspended' => Tenant::whereNotNull('suspended_at')->count(),
            ],
            'distributors' => [
                'total' => Distributor::count(),
                'active' => Distributor::active()->count(),
            ],
            'licenses' => [
                'total' => License::count(),
                'active' => License::where('status', 'active')->count(),
                'expired' => License::where('expires_at', '<', now())->count(),
                'pending' => License::where('status', 'pending')->count(),
            ],
            'tickets' => [
                'total' => Ticket::count(),
                'created_today' => Ticket::whereDate('created_at', now()->today())->count(),
            ],
            'plans' => Plan::all(['name', 'slug', 'max_users', 'max_tickets_per_month'])->toArray(),
        ];
    }

    /**
     * Detect safe navigation or search actions based on user intent.
     *
     * @return array<string, mixed>|null
     */
    private function detectSafeAction(string $prompt): ?array
    {
        $normalized = strtolower($prompt);

        if (str_contains($normalized, 'tenant') || str_contains($normalized, 'company')) {
            return [
                'type' => 'navigate',
                'label' => 'Open Tenants Management',
                'url' => route('admin.tenants.index'),
            ];
        }

        if (str_contains($normalized, 'license') || str_contains($normalized, 'expire')) {
            return [
                'type' => 'navigate',
                'label' => 'View System Licenses',
                'url' => route('admin.licenses.index'),
            ];
        }

        if (str_contains($normalized, 'plan') || str_contains($normalized, 'starter') || str_contains($normalized, 'business') || str_contains($normalized, 'enterprise')) {
            return [
                'type' => 'navigate',
                'label' => 'Manage Subscription Plans',
                'url' => route('admin.plans.index'),
            ];
        }

        if (str_contains($normalized, 'distributor')) {
            return [
                'type' => 'navigate',
                'label' => 'View Distributors',
                'url' => route('admin.distributors.index'),
            ];
        }

        if (str_contains($normalized, 'user') || str_contains($normalized, 'administrator')) {
            return [
                'type' => 'navigate',
                'label' => 'Manage System Users',
                'url' => route('admin.users.index'),
            ];
        }

        if (str_contains($normalized, 'announcement')) {
            return [
                'type' => 'navigate',
                'label' => 'System Announcements',
                'url' => route('admin.announcements.index'),
            ];
        }

        if (str_contains($normalized, 'report') || str_contains($normalized, 'analytic')) {
            return [
                'type' => 'navigate',
                'label' => 'View Reports & Analytics',
                'url' => route('admin.reports.index'),
            ];
        }

        if (str_contains($normalized, 'sla') || str_contains($normalized, 'policy')) {
            return [
                'type' => 'navigate',
                'label' => 'SLA Policies',
                'url' => route('admin.sla.index'),
            ];
        }

        if (str_contains($normalized, 'setting')) {
            return [
                'type' => 'navigate',
                'label' => 'System Settings',
                'url' => route('admin.settings.index'),
            ];
        }

        return null;
    }

    /**
     * Build LLM System Prompt populated with system context.
     */
    private function buildSystemPrompt(array $context): string
    {
        $contextJson = json_encode($context, JSON_PRETTY_PRINT);

        return <<<PROMPT
You are the AI Admin Copilot for the Laravel Multi-Tenant SaaS Ticketing System.
You assist system administrators with insights, statistics, navigation, and system documentation.

System Real-Time Metrics & Context:
{$contextJson}

Guidelines:
1. Provide accurate, professional, and concise answers based on the system context provided above.
2. If asked about tenant counts, expired licenses, ticket statistics, plans, or navigation, answer directly with exact numbers.
3. NEVER perform or offer to perform automatic data modifications or deletions directly.
4. Keep responses friendly, structured, and easy to read.
PROMPT;
    }

    /**
     * Fallback Knowledge Engine when OpenAI API is not active or unavailable.
     *
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>|null  $safeAction
     * @return array{message: string, action: array<string, mixed>|null, status: string}
     */
    private function generateFallbackResponse(string $prompt, array $context, ?array $safeAction): array
    {
        $normalized = strtolower($prompt);

        if (str_contains($normalized, 'tenant') || str_contains($normalized, 'active')) {
            $msg = "There are currently **{$context['tenants']['total']} total tenants** registered in the system ({$context['tenants']['active']} active, {$context['tenants']['suspended']} suspended).";
        } elseif (str_contains($normalized, 'license') || str_contains($normalized, 'expire')) {
            $msg = "The system currently has **{$context['licenses']['total']} total licenses** ({$context['licenses']['active']} active, {$context['licenses']['expired']} expired, {$context['licenses']['pending']} pending).";
        } elseif (str_contains($normalized, 'ticket') || str_contains($normalized, 'today')) {
            $msg = "A total of **{$context['tickets']['total']} tickets** exist across all tenants, with **{$context['tickets']['created_today']} created today**.";
        } elseif (str_contains($normalized, 'plan') || str_contains($normalized, 'starter')) {
            $msg = 'The system offers 3 subscription plans: **Starter** (5 users, 100 tickets/mo), **Business** (10 users, 500 tickets/mo), and **Enterprise** (20 users, unlimited tickets).';
        } elseif (str_contains($normalized, 'user') || str_contains($normalized, 'where can i manage')) {
            $msg = 'You can manage system administrators and global users under the **Users** section in the Admin Panel.';
        } else {
            $msg = "I'm your AI Admin Copilot. System status: **{$context['tenants']['active']} Active Tenants**, **{$context['licenses']['active']} Active Licenses**, and **{$context['tickets']['created_today']} Tickets Today**. How can I help you navigate or manage the system?";
        }

        return [
            'message' => $msg,
            'action' => $safeAction,
            'status' => 'success',
        ];
    }
}
