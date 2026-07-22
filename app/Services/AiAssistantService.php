<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AppSetting;
use App\Models\BugReport;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Client;
use App\Models\Department;
use App\Models\KbArticle;
use App\Models\LearnedSnippet;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use App\Notifications\BugReportFiled;
use App\Support\SystemGuide;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Tenant-aware orchestrator for the AI assistant.
 *
 * - Portal bot: a multi-turn, tool-calling conversation (search KB, create ticket,
 *   look up ticket status). Conversation history is persisted.
 * - Agent copilot: stateless one-shot generations (draft a reply, summarize a ticket).
 */
class AiAssistantService
{
    private const MAX_TOOL_ITERATIONS = 8;

    public function __construct(
        private OpenAiService $openAi,
        private TicketService $ticketService,
    ) {}

    // ───────────────────────── Portal bot ─────────────────────────

    /**
     * Handle one user message in a portal conversation and return the assistant's reply.
     */
    public function replyToPortalMessage(Tenant $tenant, ChatConversation $conversation, string $userMessage): string
    {
        return $this->converse($tenant, $conversation, $userMessage, $this->portalSystemPrompt($tenant), $this->portalTools());
    }

    /**
     * Handle one message from a logged-in team member (in-app assistant, per-user memory).
     */
    public function replyToAppMessage(Tenant $tenant, ChatConversation $conversation, User $user, string $userMessage, ?string $imageDataUrl = null, ?string $pageContext = null): string
    {
        return $this->converse($tenant, $conversation, $userMessage, $this->appSystemPrompt($tenant, $user, $pageContext), $this->appTools(), $imageDataUrl);
    }

    /**
     * Run the persisted tool-calling loop for a conversation and return the assistant reply.
     *
     * @param  array<int, array<string, mixed>>  $tools
     */
    private function converse(Tenant $tenant, ChatConversation $conversation, string $userMessage, string $systemPrompt, array $tools, ?string $imageDataUrl = null): string
    {
        if (app(PromptInjectionService::class)->isInjection($userMessage, $tenant->id, $conversation->user_id)) {
            $reply = 'Your message could not be processed due to security policy guidelines.';
            ChatMessage::create([
                'chat_conversation_id' => $conversation->id,
                'role' => ChatMessage::ROLE_ASSISTANT,
                'content' => $reply,
            ]);

            return $reply;
        }

        if (app(ModerationService::class)->isFlagged($userMessage, 'balanced', $tenant->id, $conversation->user_id)) {
            $reply = 'Your message contains content that violates our community safety guidelines.';
            ChatMessage::create([
                'chat_conversation_id' => $conversation->id,
                'role' => ChatMessage::ROLE_ASSISTANT,
                'content' => $reply,
            ]);

            return $reply;
        }

        ChatMessage::create([
            'chat_conversation_id' => $conversation->id,
            'role' => ChatMessage::ROLE_USER,
            'content' => $userMessage,
        ]);

        $messages = array_merge(
            [['role' => 'system', 'content' => $systemPrompt]],
            $this->history($conversation),
        );

        // Attach an uploaded image to the current (last) user message for vision.
        if ($imageDataUrl !== null && $messages !== []) {
            $messages[array_key_last($messages)] = ['role' => 'user', 'content' => [
                ['type' => 'text', 'text' => $userMessage],
                ['type' => 'image_url', 'image_url' => ['url' => $imageDataUrl]],
            ]];
        }

        for ($i = 0; $i < self::MAX_TOOL_ITERATIONS; $i++) {
            $response = $this->openAi->chat($messages, $tools);
            $message = $response['choices'][0]['message'] ?? [];
            $usage = $response['usage'] ?? null;
            $toolCalls = $message['tool_calls'] ?? [];

            if ($toolCalls === []) {
                $content = (string) ($message['content'] ?? '');
                ChatMessage::create([
                    'chat_conversation_id' => $conversation->id,
                    'role' => ChatMessage::ROLE_ASSISTANT,
                    'content' => $content,
                    'metadata' => $usage ? ['usage' => $usage] : null,
                ]);

                return $content;
            }

            // The model asked to call one or more tools — record the request, then run them.
            $messages[] = ['role' => 'assistant', 'content' => $message['content'] ?? null, 'tool_calls' => $toolCalls];
            ChatMessage::create([
                'chat_conversation_id' => $conversation->id,
                'role' => ChatMessage::ROLE_ASSISTANT,
                'content' => $message['content'] ?? null,
                'metadata' => ['tool_calls' => $toolCalls, 'usage' => $usage],
            ]);

            foreach ($toolCalls as $call) {
                $name = $call['function']['name'] ?? '';
                $args = json_decode($call['function']['arguments'] ?? '{}', true) ?: [];
                $result = $this->executeTool($tenant, $conversation, $name, is_array($args) ? $args : []);
                $resultJson = (string) json_encode($result);

                $messages[] = ['role' => 'tool', 'tool_call_id' => $call['id'] ?? '', 'content' => $resultJson];
                ChatMessage::create([
                    'chat_conversation_id' => $conversation->id,
                    'role' => ChatMessage::ROLE_TOOL,
                    'tool_name' => $name,
                    'content' => $resultJson,
                    'metadata' => ['tool_call_id' => $call['id'] ?? null, 'args' => $args],
                ]);
            }
        }

        // Safety net if the model kept requesting tools without answering.
        $fallback = 'Sorry, I was not able to complete that. Would you like me to open a support ticket for you?';
        ChatMessage::create([
            'chat_conversation_id' => $conversation->id,
            'role' => ChatMessage::ROLE_ASSISTANT,
            'content' => $fallback,
        ]);

        return $fallback;
    }

    /**
     * Clean conversational history for replay (skips intermediate tool-call rows).
     *
     * @return array<int, array{role: string, content: string}>
     */
    private function history(ChatConversation $conversation): array
    {
        return $conversation->messages()
            ->whereIn('role', [ChatMessage::ROLE_USER, ChatMessage::ROLE_ASSISTANT])
            ->whereNotNull('content')
            ->get()
            ->map(fn (ChatMessage $m) => ['role' => $m->role, 'content' => (string) $m->content])
            ->all();
    }

    // ─────────────────────── Agent copilot ───────────────────────

    public function draftAgentReply(Ticket $ticket): string
    {
        $kb = $this->knowledgeBaseExcerpts($ticket->tenant, $ticket->subject);
        $system = 'You are an expert support agent for '.$ticket->tenant->displayName().'. '
            .'Draft a professional, empathetic reply to the customer for the ticket below, using the knowledge base excerpts where relevant. '
            .'Be concise and actionable. Do not invent facts; if information is missing, ask the customer for it. '
            .'Write only the reply body — no subject line and no signature.';
        $user = "TICKET\nSubject: {$ticket->subject}\nDescription: {$ticket->description}\n\n"
            ."CONVERSATION SO FAR:\n".$this->ticketContext($ticket)."\n\n"
            ."KNOWLEDGE BASE:\n".($kb ?: '(none found)');

        return $this->oneShot($system, $user, 600);
    }

    public function summarizeTicket(Ticket $ticket): string
    {
        $system = 'You summarize support tickets for agents. Produce a tight summary: the customer\'s issue, '
            .'what has happened so far, and the recommended next action. Use short bullet points.';
        $user = "TICKET\nSubject: {$ticket->subject}\nDescription: {$ticket->description}\n\n"
            ."CONVERSATION SO FAR:\n".$this->ticketContext($ticket);

        return $this->oneShot($system, $user, 400);
    }

    /**
     * Clean up and structure a rough ticket draft so the saved ticket — and the
     * dataset later used for self-learning (embeddings) — is consistent and tidy.
     * Returns a polished subject, a well-formatted description, and suggested
     * resolution tasks. Never invents facts the notes don't support.
     *
     * @return array{subject: string, description: string, tasks: array<int, string>}
     */
    public function structureTicketDraft(Tenant $tenant, string $subject, string $description): array
    {
        $system = 'You are a support-ticket editor for '.$tenant->displayName().'. '
            .'An agent has typed rough notes for a NEW ticket. Rewrite them into a clean, professional, well-structured ticket. '
            .'Do NOT invent facts, names, numbers, or causes that are not stated or clearly implied by the notes. '
            ."Return STRICT JSON with exactly these keys:\n"
            .'"subject": a concise, specific one-line title (max ~80 chars, no trailing period).'."\n"
            .'"description": a clear, well-formatted description. When the notes support it, organize it under short bold-style labelled sections such as "Issue", "Steps to Reproduce", "Expected", "Actual", and "Environment". Keep it factual, fix grammar and spelling, and remove filler. Do not pad it out.'."\n"
            .'"tasks": an array of 2 to 6 short, actionable resolution steps an agent should follow to investigate and fix this issue. Each task is a plain imperative phrase (e.g. "Reproduce the error on a test account"), no numbering, no trailing period.'."\n"
            .'If the notes are too vague for a field, keep the original wording rather than fabricating. Respond with JSON only.';

        $user = "ROUGH NOTES\nSubject: ".trim($subject)."\n\nDescription:\n".trim($description);

        $response = $this->openAi->chat(
            [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $user]],
            [],
            ['max_tokens' => 800, 'temperature' => 0.2, 'response_format' => ['type' => 'json_object']],
        );

        $data = json_decode((string) ($response['choices'][0]['message']['content'] ?? ''), true);
        if (! is_array($data)) {
            return ['subject' => $subject, 'description' => $description, 'tasks' => []];
        }

        $tasks = [];
        foreach ((array) ($data['tasks'] ?? []) as $task) {
            $task = trim((string) (is_array($task) ? ($task['title'] ?? $task['task'] ?? '') : $task));
            if ($task !== '') {
                $tasks[] = Str::limit($task, 250, '');
            }
        }

        return [
            'subject' => trim((string) ($data['subject'] ?? '')) ?: $subject,
            'description' => trim((string) ($data['description'] ?? '')) ?: $description,
            'tasks' => array_slice($tasks, 0, 8),
        ];
    }

    private function oneShot(string $system, string $user, int $maxTokens): string
    {
        $response = $this->openAi->chat(
            [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $user]],
            [],
            ['max_tokens' => $maxTokens],
        );

        return (string) ($response['choices'][0]['message']['content'] ?? '');
    }

    private function ticketContext(Ticket $ticket): string
    {
        $comments = $ticket->comments()->where('is_public', true)->orderBy('id')->get();

        if ($comments->isEmpty()) {
            return '(no replies yet)';
        }

        return $comments->map(function (TicketComment $c) {
            $who = $c->user_id ? 'Agent' : 'Customer';

            return $who.': '.$c->content;
        })->implode("\n");
    }

    // ───────────────────────── Tools ─────────────────────────

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function executeTool(Tenant $tenant, ChatConversation $conversation, string $name, array $args): array
    {
        return match ($name) {
            'search_knowledge_base' => $this->searchKnowledgeBase($tenant, (string) ($args['query'] ?? '')),
            'search_system_guide' => SystemGuide::search((string) ($args['query'] ?? '')),
            'search_web' => $this->searchWeb((string) ($args['query'] ?? '')),
            'create_ticket' => $this->createTicketTool($tenant, $conversation, $args),
            'lookup_ticket_status' => $this->lookupTicketStatus($tenant, $args),
            'query_tickets' => $this->queryTickets($conversation, $args),
            'query_clients' => $this->queryClients($conversation, $args),
            'ticket_stats' => $this->ticketStats($conversation, $args),
            'search_resolved_tickets' => $this->searchResolvedTickets($conversation, (string) ($args['query'] ?? '')),
            'report_bug' => $this->createBugReport($conversation, $args),
            default => ['error' => 'unknown_tool'],
        };
    }

    /**
     * @return array{articles: array<int, array<string, mixed>>}
     */
    private function searchKnowledgeBase(Tenant $tenant, string $query): array
    {
        $query = trim($query);
        if (strlen($query) < 2) {
            return ['articles' => []];
        }

        $articles = KbArticle::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->published()
            ->where(function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                    ->orWhere('excerpt', 'like', "%{$query}%")
                    ->orWhere('content', 'like', "%{$query}%");
            })
            ->with('category')
            ->ordered()
            ->limit(5)
            ->get()
            ->map(fn (KbArticle $a) => [
                'title' => $a->title,
                'excerpt' => $a->excerpt,
                'content' => Str::limit(strip_tags((string) $a->content), 1200),
                'url' => $a->category ? route('portal.knowledge-base.article', [
                    'slug' => $tenant->slug,
                    'categorySlug' => $a->category->slug,
                    'articleSlug' => $a->slug,
                ]) : null,
            ])
            ->all();

        return ['articles' => $articles];
    }

    private function knowledgeBaseExcerpts(Tenant $tenant, string $query): string
    {
        return collect($this->searchKnowledgeBase($tenant, $query)['articles'])
            ->map(fn ($a) => "### {$a['title']}\n".($a['excerpt'] ?? ''))
            ->implode("\n\n");
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function createTicketTool(Tenant $tenant, ChatConversation $conversation, array $args): array
    {
        $name = trim((string) ($args['name'] ?? ''));
        $email = trim((string) ($args['email'] ?? ''));
        $subject = trim((string) ($args['subject'] ?? ''));
        $description = trim((string) ($args['description'] ?? ''));

        if ($name === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL) || $subject === '' || $description === '') {
            return ['error' => 'missing_fields', 'message' => 'I need the full name, a valid email, a subject, and a description before opening a ticket.'];
        }

        $department = $this->resolveDepartment($tenant, $args['department_id'] ?? null);
        if (! $department) {
            return ['error' => 'no_department', 'message' => 'No department is available to route this ticket.'];
        }

        $client = $this->findOrCreateGuestClient($tenant, $email, $name);
        $trackingToken = Str::random(64);

        $ticket = $this->ticketService->createTicket([
            'tenant_id' => $tenant->id,
            'subject' => $subject,
            'description' => $description,
            'priority' => null,
            'department_id' => $department->id,
            'category_id' => null,
            'client_id' => $client->id,
            'created_by' => null,
            'tracking_token' => $trackingToken,
            'attachments' => null,
        ]);

        $conversation->forceFill(['client_id' => $client->id, 'ticket_id' => $ticket->id])->save();

        return [
            'ticket_number' => $ticket->ticket_number,
            'status' => $ticket->status,
            'tracking_url' => route('tenant.track-ticket.token', ['slug' => $tenant->slug, 'token' => $trackingToken]),
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function lookupTicketStatus(Tenant $tenant, array $args): array
    {
        $number = trim((string) ($args['ticket_number'] ?? ''));
        $email = trim((string) ($args['email'] ?? ''));

        if ($number === '' || $email === '') {
            return ['error' => 'missing_fields', 'message' => 'I need both the ticket number and the email used to submit it.'];
        }

        $ticket = Ticket::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('ticket_number', $number)
            ->whereHas('client', fn ($q) => $q->withoutGlobalScopes()->where('email', $email))
            ->first();

        if (! $ticket) {
            return ['error' => 'not_found', 'message' => 'I could not find a ticket matching that number and email.'];
        }

        return [
            'ticket_number' => $ticket->ticket_number,
            'subject' => $ticket->subject,
            'status' => $ticket->status,
            'last_update' => optional($ticket->updated_at)->toDayDateTimeString(),
            'tracking_url' => $ticket->tracking_token
                ? route('tenant.track-ticket.token', ['slug' => $tenant->slug, 'token' => $ticket->tracking_token])
                : null,
        ];
    }

    /**
     * General, safe ticket query for the in-app (agent) assistant. The model composes
     * the filters; this code always forces tenant scope, stays read-only, and caps rows.
     *
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function queryTickets(ChatConversation $conversation, array $args): array
    {
        $query = Ticket::withoutGlobalScopes()
            ->where('tenant_id', $conversation->tenant_id)
            ->notMerged()
            ->notSpam()
            ->with(['client', 'assignee']);

        $assignedTo = strtolower(trim((string) ($args['assigned_to'] ?? '')));
        if ($assignedTo === 'me') {
            if (! $conversation->user_id) {
                return ['error' => 'not_available', 'message' => '"assigned to me" is only available to a signed-in team member.'];
            }
            $query->where('assigned_to', $conversation->user_id);
        } elseif ($assignedTo === 'unassigned') {
            $query->whereNull('assigned_to');
        }

        if (! empty($args['status'])) {
            $query->where('status', $args['status']);
        }
        if (! empty($args['priority'])) {
            $query->where('priority', $args['priority']);
        }

        if (! empty($args['client'])) {
            $client = (string) $args['client'];
            $query->whereHas('client', fn ($q) => $q->withoutGlobalScopes()
                ->where(fn ($w) => $w->where('email', 'like', "%{$client}%")->orWhere('name', 'like', "%{$client}%")));
        }

        if (! empty($args['search'])) {
            $search = (string) $args['search'];
            $query->where(fn ($q) => $q->where('subject', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")
                ->orWhere('ticket_number', 'like', "%{$search}%"));
        }

        foreach (['created_after' => '>=', 'created_before' => '<='] as $key => $op) {
            if (! empty($args[$key])) {
                try {
                    $query->whereDate('created_at', $op, Carbon::parse((string) $args[$key]));
                } catch (\Throwable $e) {
                    // ignore unparseable dates
                }
            }
        }

        $limit = max(1, min((int) ($args['limit'] ?? 15), 25));

        $tickets = $query->latest()->limit($limit)->get()->map(fn (Ticket $t) => [
            'ticket_number' => $t->ticket_number,
            'subject' => $t->subject,
            'status' => $t->status,
            'priority' => $t->priority,
            'assigned_to' => $t->assignee?->name,
            'client' => $t->client?->name,
            'created' => optional($t->created_at)->toDateString(),
        ])->all();

        return ['count' => count($tickets), 'tickets' => $tickets];
    }

    /**
     * Safe, tenant-scoped client search for the in-app assistant.
     *
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function queryClients(ChatConversation $conversation, array $args): array
    {
        $query = Client::withoutGlobalScopes()->where('tenant_id', $conversation->tenant_id);

        if (! empty($args['search'])) {
            $search = (string) $args['search'];
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('contact_person', 'like', "%{$search}%"));
        }
        if (! empty($args['tier'])) {
            $query->where('tier', $args['tier']);
        }
        if (! empty($args['status'])) {
            $query->where('status', $args['status']);
        }

        $limit = max(1, min((int) ($args['limit'] ?? 15), 25));

        $clients = $query
            ->withCount(['tickets' => fn ($q) => $q->withoutGlobalScopes()->where('tenant_id', $conversation->tenant_id)])
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (Client $c) => [
                'name' => $c->name,
                'email' => $c->email,
                'contact_person' => $c->contact_person,
                'tier' => $c->tier,
                'status' => $c->status,
                'tickets' => $c->tickets_count,
            ])->all();

        return ['count' => count($clients), 'clients' => $clients];
    }

    /**
     * Accurate ticket counts by status (optionally mine / within a date range).
     *
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function ticketStats(ChatConversation $conversation, array $args): array
    {
        $base = Ticket::withoutGlobalScopes()
            ->where('tenant_id', $conversation->tenant_id)
            ->notMerged()
            ->notSpam();

        if (strtolower((string) ($args['assigned_to'] ?? '')) === 'me' && $conversation->user_id) {
            $base->where('assigned_to', $conversation->user_id);
        }

        foreach (['created_after' => '>=', 'created_before' => '<='] as $key => $op) {
            if (! empty($args[$key])) {
                try {
                    $base->whereDate('created_at', $op, Carbon::parse((string) $args[$key]));
                } catch (\Throwable $e) {
                    // ignore unparseable dates
                }
            }
        }

        $byStatus = (clone $base)->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status')->all();

        return ['total' => array_sum($byStatus), 'by_status' => $byStatus];
    }

    /**
     * Self-learning: semantically find how similar PAST problems were resolved,
     * using embeddings of the workspace's closed tickets. The more tickets the
     * team resolves (and the scheduled embed command processes), the smarter this gets.
     *
     * @return array<string, mixed>
     */
    private function searchResolvedTickets(ChatConversation $conversation, string $query): array
    {
        $query = trim($query);
        if (strlen($query) < 3) {
            return ['tickets' => []];
        }

        try {
            $queryVector = $this->openAi->embed($query);
        } catch (\Throwable $e) {
            return ['tickets' => [], 'error' => 'unavailable'];
        }

        if ($queryVector === []) {
            return ['tickets' => []];
        }

        $candidates = Ticket::withoutGlobalScopes()
            ->where('tenant_id', $conversation->tenant_id)
            ->where('status', 'closed')
            ->whereNotNull('solution_embedded_at')
            ->with(['comments' => fn ($q) => $q->where('is_public', true)->orderBy('id')])
            ->latest('closed_at')
            ->limit(500)
            ->get();

        $scored = [];
        foreach ($candidates as $ticket) {
            $vector = json_decode((string) $ticket->solution_embedding, true);
            if (! is_array($vector) || $vector === []) {
                continue;
            }
            $scored[] = ['score' => $this->cosineSimilarity($queryVector, $vector), 'ticket' => $ticket];
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        $tickets = array_map(fn ($s) => [
            'ticket_number' => $s['ticket']->ticket_number,
            'problem' => mb_substr(trim($s['ticket']->subject.' — '.$s['ticket']->description), 0, 300),
            'resolution' => $this->ticketResolution($s['ticket']),
            'similarity' => round($s['score'], 3),
        ], array_slice($scored, 0, 5));

        $learned = $this->searchLearnedSnippets($conversation->tenant_id, $queryVector);

        return ['count' => count($tickets), 'tickets' => $tickets, 'learned' => $learned];
    }

    /**
     * Semantically match the query against confirmed-helpful Q&A snippets the team
     * saved from past chats (opt-in self-learning).
     *
     * @param  array<int, float>  $queryVector
     * @return array<int, array<string, mixed>>
     */
    private function searchLearnedSnippets(int $tenantId, array $queryVector): array
    {
        $snippets = LearnedSnippet::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('embedding')
            ->latest('id')
            ->limit(500)
            ->get();

        $scored = [];
        foreach ($snippets as $snippet) {
            $vector = json_decode((string) $snippet->embedding, true);
            if (! is_array($vector) || $vector === []) {
                continue;
            }
            $score = $this->cosineSimilarity($queryVector, $vector);
            if ($score < 0.3) { // ignore weak matches so we don't surface noise
                continue;
            }
            $scored[] = ['score' => $score, 'snippet' => $snippet];
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_map(fn ($x) => [
            'question' => mb_substr((string) $x['snippet']->question, 0, 200),
            'answer' => mb_substr((string) $x['snippet']->answer, 0, 600),
            'similarity' => round($x['score'], 3),
        ], array_slice($scored, 0, 3));
    }

    /**
     * Store a confirmed-helpful question/answer pair so the assistant can reuse it.
     * Embeds the question for later semantic retrieval.
     */
    public function learnFromExchange(Tenant $tenant, ?User $user, string $question, string $answer): bool
    {
        $question = trim($question);
        $answer = trim($answer);
        if ($question === '' || $answer === '') {
            return false;
        }

        $embedding = null;
        try {
            $vector = $this->openAi->embed(mb_substr($question, 0, 2000));
            if ($vector !== []) {
                $embedding = json_encode($vector);
            }
        } catch (\Throwable $e) {
            // Persist without an embedding; it simply won't be semantically retrievable.
        }

        LearnedSnippet::create([
            'tenant_id' => $tenant->id,
            'created_by' => $user?->id,
            'question' => mb_substr($question, 0, 2000),
            'answer' => mb_substr($answer, 0, 8000),
            'embedding' => $embedding,
        ]);

        return true;
    }

    /**
     * Record a product bug the user reported in chat, and notify internal staff.
     * Reuses the conversation's tenant + user as the reporter.
     *
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function createBugReport(ChatConversation $conversation, array $args): array
    {
        $title = trim((string) ($args['title'] ?? ''));
        $description = trim((string) ($args['description'] ?? ''));
        if ($title === '' || $description === '') {
            return ['error' => 'need_more_info', 'message' => 'A short title and a description are required to file the bug.'];
        }

        $severity = in_array($args['severity'] ?? '', BugReport::SEVERITIES, true) ? (string) $args['severity'] : 'medium';

        $bug = BugReport::create([
            'tenant_id' => $conversation->tenant_id,
            'reported_by' => $conversation->user_id,
            'chat_conversation_id' => $conversation->id,
            'title' => mb_substr($title, 0, 200),
            'description' => mb_substr($description, 0, 5000),
            'steps_to_reproduce' => filled($args['steps_to_reproduce'] ?? null) ? mb_substr(trim((string) $args['steps_to_reproduce']), 0, 3000) : null,
            'area' => filled($args['area'] ?? null) ? mb_substr(trim((string) $args['area']), 0, 120) : null,
            'severity' => $severity,
            'status' => BugReport::STATUS_NEW,
        ]);

        // Notify internal CliqueHA staff (cross-tenant) that a new bug was filed.
        $admins = User::where('is_admin', true)->get();
        if ($admins->isNotEmpty()) {
            Notification::send($admins, new BugReportFiled($bug));
        }

        return ['bug_reference' => $bug->reference(), 'status' => 'received'];
    }

    /**
     * @param  array<int, float>  $a
     * @param  array<int, float>  $b
     */
    private function cosineSimilarity(array $a, array $b): float
    {
        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;
        $n = min(count($a), count($b));
        for ($i = 0; $i < $n; $i++) {
            $x = (float) $a[$i];
            $y = (float) $b[$i];
            $dot += $x * $y;
            $normA += $x * $x;
            $normB += $y * $y;
        }

        return ($normA > 0 && $normB > 0) ? $dot / (sqrt($normA) * sqrt($normB)) : 0.0;
    }

    private function ticketResolution(Ticket $ticket): string
    {
        $resolution = trim((string) $ticket->closing_remarks);
        if ($resolution === '') {
            $agentReply = $ticket->comments->first(fn ($c) => $c->user_id !== null);
            $resolution = (string) ($agentReply?->content ?? '');
        }

        return mb_substr($resolution !== '' ? $resolution : '(no resolution notes recorded)', 0, 500);
    }

    private function resolveDepartment(Tenant $tenant, mixed $departmentId): ?Department
    {
        $base = Department::withoutGlobalScopes()->where('tenant_id', $tenant->id)->active();

        if ($departmentId) {
            $match = (clone $base)->where('id', $departmentId)->first();
            if ($match) {
                return $match;
            }
        }

        return (clone $base)->ordered()->first();
    }

    private function findOrCreateGuestClient(Tenant $tenant, string $email, string $name): Client
    {
        return Client::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('email', $email)
            ->first()
            ?? Client::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'name' => $name,
                'email' => $email,
                'contact_person' => $name,
                'tier' => Client::TIER_BASIC,
                'status' => Client::STATUS_ACTIVE,
            ]);
    }

    // ─────────────────── Prompt + tool schema ───────────────────

    private function departmentList(Tenant $tenant): string
    {
        return Department::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->active()
            ->ordered()
            ->get(['id', 'name'])
            ->map(fn ($d) => "- {$d->id}: {$d->name}")
            ->implode("\n");
    }

    private function tenantCustomPrompt(Tenant $tenant): ?string
    {
        return AppSetting::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('key', 'ai_system_prompt')
            ->value('value');
    }

    /**
     * Tell the model the current date/time (in the tenant's timezone) — models otherwise
     * guess their training cutoff. Reads the tenant timezone directly so it also works
     * on the public portal where there is no session.
     */
    private function currentDateLine(Tenant $tenant): string
    {
        $tz = AppSetting::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('key', 'timezone')
            ->value('value') ?: 'UTC';

        try {
            $now = now()->setTimezone($tz);
        } catch (\Throwable $e) {
            $now = now();
            $tz = 'UTC';
        }

        return 'The current date and time is '.$now->format('l, F j, Y, g:i A').' ('.$tz.'). '
            .'Use this whenever the user asks about the date or time.';
    }

    private function appSystemPrompt(Tenant $tenant, User $user, ?string $pageContext = null): string
    {
        $deptList = $this->departmentList($tenant);
        $custom = $this->tenantCustomPrompt($tenant);

        $prompt = "You are the AI assistant for the {$tenant->displayName()} support team, chatting with {$user->name} (a logged-in team member). "
            ."Your primary purpose is to help support agents resolve tickets quickly and well. Keep the conversation focused on support, tickets, and resolving customer problems.\n\n"
            .$this->currentDateLine($tenant)."\n\n"
            ."Guidelines:\n"
            ."- This is an ongoing conversation. Remember details the user shared earlier and refer back to them.\n"
            ."- When an agent describes a problem to solve, OR asks a question the team may have handled before, FIRST call search_resolved_tickets. It returns both past ticket resolutions and answers agents previously confirmed as helpful (the \"learned\" list) — prefer those proven answers and cite that it worked before. This is how you learn from the team's history.\n"
            ."- For questions about how to USE this app (how do I…, where is…, e.g. how to create a ticket or change a setting), call search_system_guide and answer with the steps and the exact menu location.\n"
            ."- For questions about the company's own products/services or help content, call search_knowledge_base and cite the article title (and link).\n"
            ."- For general questions (comparisons, concepts, definitions, industry knowledge — e.g. how this differs from another tool), answer directly and confidently from your own knowledge. Do NOT tell the user something is 'not in the knowledge base' — the knowledge base only holds the company's own help articles, not general knowledge.\n"
            ."- For any question about tickets (mine, by status or priority, for a client, searching, or recent), call query_tickets with just the filters you need.\n"
            ."- For ticket counts or workload (\"how many open tickets\", \"my closed this week\"), call ticket_stats. For questions about clients, call query_clients.\n"
            ."- When the user asks for a chart, graph, or any visualization, first gather the real numbers (e.g. via ticket_stats or query_tickets), then append ONE chart to your reply as a fenced ```chart block containing strict JSON. Two shapes are allowed:\n"
            ."  Single series — ```chart\n{\"type\":\"bar\",\"title\":\"Tickets by status\",\"data\":[{\"label\":\"Open\",\"value\":4},{\"label\":\"Closed\",\"value\":10}]}\n```\n"
            ."  Multi series  — ```chart\n{\"type\":\"line\",\"title\":\"Tickets per day\",\"labels\":[\"Mon\",\"Tue\",\"Wed\"],\"series\":[{\"name\":\"Opened\",\"data\":[5,3,8]},{\"name\":\"Closed\",\"data\":[2,6,4]}]}\n```\n"
            ."Choose \"type\" from: \"bar\" (horizontal categories), \"column\" (vertical categories), \"line\" or \"area\" (a trend over ordered points), \"pie\" or \"donut\" (parts of a whole). Pick the type that best fits the data; use multi-series only when comparing two or more measures. Optionally add \"stacked\":true for stacked bars/columns.\n"
            ."Write a one-line summary before the block. Put the chart data ONLY inside the fenced ```chart block — never print the raw JSON as visible text and never use a ```json block for it. Use real values only — never fabricate data to fill a chart — and include a chart only when a visualization helps.\n"
            ."- Use create_ticket to open a ticket on a client's behalf; confirm the client's name, email, subject, and description first.\n"
            ."- If the user reports that something in THIS system is broken or erroring (a defect/bug in the app itself — not a how-to question and not their own customers' tickets), gather a clear title, description, reproduction steps, and the affected area, confirm with the user, then call report_bug and tell them the bug reference. Let them know the team has been notified and they'll get an update here when it's addressed.\n"
            .($this->webSearchConfigured() ? "- For general questions that are NOT about this system or the company's products, use search_web and cite the source link.\n" : '')
            ."- Be concise and helpful, and never invent policies, prices, or facts.\n\n"
            ."Departments available for new tickets (id: name):\n".($deptList ?: '(none)');

        if (filled($pageContext)) {
            $prompt .= "\n\nCURRENT PAGE CONTEXT (what the user is looking at right now):\n".$pageContext."\n"
                .'When the user says "this page", "this ticket", "here", or asks for help without spelling out the details, use this context to understand what they mean and tailor your help to it. Do not ask them to repeat information that is already in this context.';
        }

        if (filled($custom)) {
            $prompt .= "\n\nAdditional instructions from the company:\n".$custom;
        }

        return $prompt;
    }

    private function portalSystemPrompt(Tenant $tenant): string
    {
        $deptList = $this->departmentList($tenant);
        $custom = $this->tenantCustomPrompt($tenant);

        $prompt = "You are the support assistant for \"{$tenant->displayName()}\". "
            ."You help users with this company's products and support. Stay on topic, but be warm and conversational.\n\n"
            .$this->currentDateLine($tenant)."\n\n"
            ."Guidelines:\n"
            ."- This is an ongoing conversation. Remember and use details the user has shared earlier (their name, email, the problem they described) so the chat feels continuous and personal. If they ask what they told you earlier, answer from the conversation above.\n"
            ."- For any factual product/support question, FIRST call search_knowledge_base, then answer using those articles and cite the article title (and link if available). If nothing relevant is found, say so honestly.\n"
            ."- If the user wants to raise an issue you cannot resolve, offer to open a support ticket. Before calling create_ticket, collect and CONFIRM the user's full name, a valid email, a short subject, and a clear description. After creating it, give them the ticket number and tracking link.\n"
            ."- If the user asks about an existing ticket, use lookup_ticket_status with the ticket number and their email.\n"
            ."- Politely decline only requests that are clearly unrelated to support (jokes, essays, general trivia). Never invent policies, prices, or facts.\n"
            ."- Be concise and friendly.\n\n"
            ."Departments available for new tickets (id: name):\n".($deptList ?: '(none)');

        if (filled($custom)) {
            $prompt .= "\n\nAdditional instructions from the company:\n".$custom;
        }

        return $prompt;
    }

    /**
     * Tools for the in-app (agent) assistant: portal tools plus the product guide.
     *
     * @return array<int, array<string, mixed>>
     */
    private function appTools(): array
    {
        $tools = array_merge($this->portalTools(), [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'search_system_guide',
                    'description' => 'Search the CliqueHA Nexus product guide for how to use the app itself — where features live and how to perform actions (create a ticket, change a setting, run a report, manage clients, etc.). Use this for "how do I…" / "where is…" questions about the system.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => ['type' => 'string', 'description' => 'Keywords about the app action or section'],
                        ],
                        'required' => ['query'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'query_tickets',
                    'description' => 'Search and list this workspace\'s support tickets with flexible filters. Use for any question about tickets — assigned to me, by status or priority, for a client, free-text search, or a date range. Compose only the filters you need.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'assigned_to' => ['type' => 'string', 'enum' => ['me', 'unassigned', 'anyone'], 'description' => 'Whose tickets (default anyone)'],
                            'status' => ['type' => 'string', 'enum' => ['open', 'assigned', 'in_progress', 'on_hold', 'closed', 'cancelled']],
                            'priority' => ['type' => 'string', 'enum' => ['low', 'medium', 'high', 'critical']],
                            'client' => ['type' => 'string', 'description' => 'Filter by client name or email'],
                            'search' => ['type' => 'string', 'description' => 'Text to match in subject, description, or ticket number'],
                            'created_after' => ['type' => 'string', 'description' => 'ISO date, e.g. 2026-06-01'],
                            'created_before' => ['type' => 'string', 'description' => 'ISO date'],
                            'limit' => ['type' => 'integer', 'description' => 'Max rows (1-25, default 15)'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'query_clients',
                    'description' => 'Search and list clients in this workspace by name, email, or contact person; optionally filter by tier or status.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'search' => ['type' => 'string', 'description' => 'Name, email, or contact-person text'],
                            'tier' => ['type' => 'string', 'enum' => ['basic', 'premium', 'enterprise']],
                            'status' => ['type' => 'string', 'enum' => ['active', 'inactive']],
                            'limit' => ['type' => 'integer', 'description' => 'Max rows (1-25, default 15)'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'ticket_stats',
                    'description' => 'Get accurate ticket counts for this workspace, broken down by status. Use for "how many open/closed tickets", "my workload", or counts over a date range.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'assigned_to' => ['type' => 'string', 'enum' => ['me', 'anyone'], 'description' => 'Whose tickets (default anyone)'],
                            'created_after' => ['type' => 'string', 'description' => 'ISO date'],
                            'created_before' => ['type' => 'string', 'description' => 'ISO date'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'search_resolved_tickets',
                    'description' => 'Find how similar PAST problems were resolved — learned from this workspace\'s closed tickets AND from answers agents previously confirmed as helpful — via semantic search. Returns "tickets" (past resolutions) and "learned" (confirmed-helpful Q&A). Call this first whenever an agent describes a problem they need to solve.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => ['type' => 'string', 'description' => 'The problem, error, or symptom to find past resolutions for'],
                        ],
                        'required' => ['query'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'report_bug',
                    'description' => 'File a bug about THIS system (CliqueHA Nexus) when the user reports that something in the app is broken, erroring, or behaving incorrectly. The report goes to the internal team, who can hand it to an AI programmer to fix. Do NOT use this for how-to questions, feature requests, or the user\'s own customers\' support tickets. Confirm the details with the user first, then report the bug number back to them.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'title' => ['type' => 'string', 'description' => 'A concise one-line summary of the bug'],
                            'description' => ['type' => 'string', 'description' => 'What is wrong, including any error message and what the user expected to happen'],
                            'steps_to_reproduce' => ['type' => 'string', 'description' => 'Numbered steps to trigger the bug, if known'],
                            'area' => ['type' => 'string', 'description' => 'The part of the app affected, e.g. "tickets", "reports", "SLA", "login"'],
                            'severity' => ['type' => 'string', 'enum' => ['low', 'medium', 'high', 'critical']],
                        ],
                        'required' => ['title', 'description'],
                    ],
                ],
            ],
        ]);

        if ($this->webSearchConfigured()) {
            $tools[] = $this->webSearchTool();
        }

        return $tools;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function portalTools(): array
    {
        $tools = [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'search_knowledge_base',
                    'description' => "Search this company's knowledge base for help articles relevant to the user's question. Use before answering factual product/support questions.",
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => ['type' => 'string', 'description' => 'Keywords from the user question'],
                        ],
                        'required' => ['query'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'create_ticket',
                    'description' => 'Open a support ticket for the user. Only call AFTER confirming the user\'s full name, a valid email, a subject, and a clear description.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'email' => ['type' => 'string'],
                            'subject' => ['type' => 'string'],
                            'description' => ['type' => 'string'],
                            'department_id' => ['type' => 'integer', 'description' => 'Optional department id from the provided list'],
                        ],
                        'required' => ['name', 'email', 'subject', 'description'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'lookup_ticket_status',
                    'description' => 'Look up the status of an existing ticket by its ticket number and the email used to submit it.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'ticket_number' => ['type' => 'string'],
                            'email' => ['type' => 'string'],
                        ],
                        'required' => ['ticket_number', 'email'],
                    ],
                ],
            ],
        ];

        return $tools;
    }

    private function webSearchConfigured(): bool
    {
        return $this->openAi->isConfigured();
    }

    /**
     * @return array<string, mixed>
     */
    private function webSearchTool(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'search_web',
                'description' => 'Search the public web for general information that is NOT about this app or the company\'s own products (e.g. general definitions, how-tos, or current external facts). Do not use it for anything answerable from the knowledge base or the system guide.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => 'string', 'description' => 'What to search the web for'],
                    ],
                    'required' => ['query'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function searchWeb(string $query): array
    {
        $query = trim($query);
        if ($query === '' || ! $this->webSearchConfigured()) {
            return ['results' => []];
        }

        try {
            $data = $this->openAi->webSearch($query);
            $message = $data['choices'][0]['message'] ?? [];

            $citations = [];
            foreach (($message['annotations'] ?? []) as $annotation) {
                if (($annotation['type'] ?? '') === 'url_citation' && isset($annotation['url_citation'])) {
                    $citations[] = [
                        'title' => $annotation['url_citation']['title'] ?? '',
                        'url' => $annotation['url_citation']['url'] ?? '',
                    ];
                }
            }

            return [
                'answer' => (string) ($message['content'] ?? ''),
                'citations' => $citations,
            ];
        } catch (\Throwable $e) {
            return ['results' => [], 'error' => 'web_search_failed'];
        }
    }
}
