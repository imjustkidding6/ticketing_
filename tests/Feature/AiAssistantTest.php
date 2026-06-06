<?php

namespace Tests\Feature;

use App\Enums\PlanFeature;
use App\Models\AppSetting;
use App\Models\BugReport;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Client;
use App\Models\Department;
use App\Models\KbArticle;
use App\Models\KbCategory;
use App\Models\License;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\BugReportFiled;
use App\Services\PageContextResolver;
use App\Services\TenantRoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AiAssistantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.openai.api_key' => 'sk-test', 'services.openai.model' => 'gpt-4o-mini']);
    }

    private function enterpriseTenant(): Tenant
    {
        $plan = Plan::factory()->create(['slug' => 'enterprise', 'features' => PlanFeature::forPlan('enterprise')]);
        $license = License::factory()->active()->forPlan($plan)->create();

        return Tenant::factory()->create(['license_id' => $license->id]);
    }

    private function starterTenant(): Tenant
    {
        $plan = Plan::factory()->create(['slug' => 'start', 'features' => PlanFeature::forPlan('start')]);
        $license = License::factory()->active()->forPlan($plan)->create();

        return Tenant::factory()->create(['license_id' => $license->id]);
    }

    private function enableAi(Tenant $tenant): void
    {
        foreach (['ai_enabled', 'ai_portal_widget_enabled', 'ai_agent_copilot_enabled'] as $key) {
            AppSetting::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'key' => $key,
                'value' => '1',
                'type' => 'boolean',
                'group' => 'ai',
            ]);
        }
    }

    /** @param  array<int, array<string, mixed>>  $sequence */
    private function fakeOpenAi(array $sequence): void
    {
        $responses = Http::sequence();
        foreach ($sequence as $body) {
            $responses->push($body, 200);
        }
        Http::fake(['api.openai.com/*' => $responses]);
    }

    private function toolCall(string $name, array $args): array
    {
        return ['choices' => [['message' => ['role' => 'assistant', 'content' => null, 'tool_calls' => [
            ['id' => 'call_1', 'type' => 'function', 'function' => ['name' => $name, 'arguments' => json_encode($args)]],
        ]]]], 'usage' => ['total_tokens' => 10]];
    }

    private function assistantText(string $text): array
    {
        return ['choices' => [['message' => ['role' => 'assistant', 'content' => $text]]], 'usage' => ['total_tokens' => 20]];
    }

    /** @param  array<int, float>  $vector */
    private function embedResponse(array $vector): array
    {
        return ['data' => [['embedding' => $vector]]];
    }

    public function test_portal_bot_answers_using_knowledge_base(): void
    {
        $tenant = $this->enterpriseTenant();
        $this->enableAi($tenant);

        $category = KbCategory::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id, 'name' => 'Account', 'slug' => 'account', 'is_active' => true,
        ]);
        KbArticle::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id, 'kb_category_id' => $category->id,
            'title' => 'Reset Password', 'slug' => 'reset-password',
            'content' => 'Go to settings and click reset password.', 'excerpt' => 'How to reset your password.',
            'is_published' => true,
        ]);

        $this->fakeOpenAi([
            $this->toolCall('search_knowledge_base', ['query' => 'reset password']),
            $this->assistantText('To reset your password, see the Reset Password guide.'),
        ]);

        $response = $this->postJson(route('tenant.ai-chat', ['slug' => $tenant->slug]), [
            'message' => 'how do I reset my password?',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['reply', 'session_token']);
        $this->assertStringContainsString('reset your password', $response->json('reply'));

        $this->assertDatabaseHas('chat_conversations', ['tenant_id' => $tenant->id, 'channel' => 'portal']);
        $this->assertDatabaseHas('chat_messages', ['role' => 'user', 'content' => 'how do I reset my password?']);
        $this->assertDatabaseHas('chat_messages', ['role' => 'tool', 'tool_name' => 'search_knowledge_base']);
        Http::assertSentCount(2);
    }

    public function test_portal_bot_creates_ticket(): void
    {
        Notification::fake();

        $tenant = $this->enterpriseTenant();
        $this->enableAi($tenant);
        Department::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);

        $this->fakeOpenAi([
            $this->toolCall('create_ticket', [
                'name' => 'Jane Doe', 'email' => 'jane@example.com',
                'subject' => 'Cannot log in', 'description' => 'I am locked out of my account.',
            ]),
            $this->assistantText('I have opened ticket for you.'),
        ]);

        $response = $this->postJson(route('tenant.ai-chat', ['slug' => $tenant->slug]), [
            'message' => 'I cannot log in, please open a ticket. I am Jane Doe, jane@example.com.',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('tickets', ['tenant_id' => $tenant->id, 'subject' => 'Cannot log in']);
        $this->assertDatabaseHas('clients', ['tenant_id' => $tenant->id, 'email' => 'jane@example.com']);
    }

    public function test_starter_tenant_chat_is_not_found(): void
    {
        $tenant = $this->starterTenant();

        $this->postJson(route('tenant.ai-chat', ['slug' => $tenant->slug]), ['message' => 'hello'])
            ->assertNotFound();
    }

    public function test_chat_is_not_found_when_disabled(): void
    {
        $tenant = $this->enterpriseTenant(); // has feature, but AI not enabled in settings

        $this->postJson(route('tenant.ai-chat', ['slug' => $tenant->slug]), ['message' => 'hello'])
            ->assertNotFound();
    }

    public function test_agent_draft_reply_returns_text(): void
    {
        $tenant = $this->enterpriseTenant();
        $user = $this->setupTenantContext($tenant);
        $ticket = Ticket::factory()->create(['tenant_id' => $tenant->id, 'created_by' => $user->id]);

        $this->fakeOpenAi([$this->assistantText('Hi, thanks for reaching out — here is how to fix it...')]);

        $this->postJson($this->tenantUrl("/tickets/{$ticket->id}/ai/draft-reply"))
            ->assertOk()
            ->assertJsonStructure(['text']);
    }

    public function test_agent_copilot_blocked_without_feature(): void
    {
        // Business plan lacks ai_chatbot → feature middleware returns 403.
        $plan = Plan::factory()->create(['slug' => 'business', 'features' => PlanFeature::forPlan('business')]);
        $license = License::factory()->active()->forPlan($plan)->create();
        $tenant = Tenant::factory()->create(['license_id' => $license->id]);
        $user = $this->setupTenantContext($tenant);
        $ticket = Ticket::factory()->create(['tenant_id' => $tenant->id, 'created_by' => $user->id]);

        $this->postJson($this->tenantUrl("/tickets/{$ticket->id}/ai/summarize"))
            ->assertForbidden();
    }

    public function test_structure_draft_cleans_inputs_and_suggests_tasks(): void
    {
        $tenant = $this->enterpriseTenant();
        $this->enableAi($tenant);
        $this->setupTenantContext($tenant);

        $this->fakeOpenAi([[
            'choices' => [['message' => ['role' => 'assistant', 'content' => json_encode([
                'subject' => 'VPN disconnects every few minutes on Windows 11',
                'description' => "**Issue**\nThe VPN drops repeatedly, blocking the user's work.",
                'tasks' => [
                    'Reproduce the disconnect on a test account',
                    'Check the VPN server logs for drops',
                    'Update the VPN client to the latest version',
                ],
            ])]]],
            'usage' => ['total_tokens' => 30],
        ]]);

        $response = $this->postJson($this->tenantUrl('/tickets-ai/structure'), [
            'subject' => 'vpn keeps dropping',
            'description' => 'users vpn keeps disconnecting every few min on win11 cant work',
        ]);

        $response->assertOk()->assertJsonStructure(['subject', 'description', 'tasks']);
        $this->assertStringContainsString('VPN disconnects', $response->json('subject'));
        $this->assertCount(3, $response->json('tasks'));
    }

    public function test_structure_draft_is_not_found_when_ai_disabled(): void
    {
        $tenant = $this->enterpriseTenant(); // feature present, but ai_enabled never set
        $this->setupTenantContext($tenant);

        $this->postJson($this->tenantUrl('/tickets-ai/structure'), [
            'description' => 'something broke',
        ])->assertNotFound();
    }

    public function test_portal_polish_cleans_customer_wording_without_tasks(): void
    {
        $tenant = $this->enterpriseTenant();
        $this->enableAi($tenant);

        $this->fakeOpenAi([[
            'choices' => [['message' => ['role' => 'assistant', 'content' => json_encode([
                'subject' => 'Cannot log in to my account',
                'description' => 'The user is unable to log in and sees an error message.',
                'tasks' => ['Reset the password'],
            ])]]],
            'usage' => ['total_tokens' => 20],
        ]]);

        $response = $this->postJson(route('tenant.ai-polish', ['slug' => $tenant->slug]), [
            'subject' => 'cant login',
            'description' => 'i cant login to my acount it says error pls help',
        ]);

        $response->assertOk()->assertJsonStructure(['subject', 'description']);
        $this->assertStringContainsString('Cannot log in', $response->json('subject'));
        // Customers must NOT receive internal task suggestions.
        $this->assertNull($response->json('tasks'));
    }

    public function test_portal_polish_is_not_found_when_portal_ai_disabled(): void
    {
        $tenant = $this->enterpriseTenant(); // feature present, no opt-in toggles

        $this->postJson(route('tenant.ai-polish', ['slug' => $tenant->slug]), [
            'description' => 'something broke',
        ])->assertNotFound();
    }

    public function test_agent_can_save_a_learned_answer_when_enabled(): void
    {
        $tenant = $this->enterpriseTenant();
        $this->enableAi($tenant);
        AppSetting::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id, 'key' => 'ai_learn_from_chat', 'value' => '1', 'type' => 'boolean', 'group' => 'ai',
        ]);
        $user = $this->setupTenantContext($tenant);

        $this->fakeOpenAi([$this->embedResponse([0.1, 0.2, 0.3])]);

        $this->postJson($this->tenantUrl('/assistant/learn'), [
            'question' => 'How do I reset a license?',
            'answer' => 'Open Licenses, select it, and click Reset.',
        ])->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseHas('learned_snippets', [
            'tenant_id' => $tenant->id,
            'question' => 'How do I reset a license?',
            'created_by' => $user->id,
        ]);
    }

    public function test_learn_is_not_found_when_learning_disabled(): void
    {
        $tenant = $this->enterpriseTenant();
        $this->enableAi($tenant); // ai_enabled on, but ai_learn_from_chat is off
        $this->setupTenantContext($tenant);

        $this->postJson($this->tenantUrl('/assistant/learn'), [
            'question' => 'q', 'answer' => 'a',
        ])->assertNotFound();
    }

    public function test_report_bug_tool_files_a_bug_and_notifies_staff(): void
    {
        Notification::fake();

        $tenant = $this->enterpriseTenant();
        $this->enableAi($tenant);
        $user = $this->setupTenantContext($tenant);
        $admin = User::factory()->create(['is_admin' => true]);

        $this->fakeOpenAi([
            $this->toolCall('report_bug', [
                'title' => 'Saving a ticket throws a 500',
                'description' => 'Clicking Save on the ticket form returns a 500 error.',
                'severity' => 'high',
            ]),
            $this->assistantText('Thanks — I logged that as BUG-1 and the team has been notified.'),
        ]);

        $this->postJson($this->tenantUrl('/assistant/message'), [
            'message' => 'There is a bug: saving a ticket throws a 500.',
        ])->assertOk();

        $this->assertDatabaseHas('bug_reports', [
            'tenant_id' => $tenant->id,
            'reported_by' => $user->id,
            'title' => 'Saving a ticket throws a 500',
            'severity' => 'high',
            'status' => 'new',
        ]);
        Notification::assertSentTo($admin, BugReportFiled::class);
    }

    public function test_admin_can_send_bug_to_ai_programmer(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $tenant = $this->enterpriseTenant();
        $bug = BugReport::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id, 'title' => 'x', 'description' => 'y', 'severity' => 'medium', 'status' => 'new',
        ]);

        $this->actingAs($admin)->post(route('admin.bugs.fix', $bug->id))->assertRedirect();

        $this->assertEquals('escalated', $bug->fresh()->status);
    }

    public function test_non_admin_cannot_access_bug_queue(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->get('/admin/bugs')->assertForbidden();
    }

    public function test_admin_fix_files_a_github_issue_when_configured(): void
    {
        config(['services.github.token' => 'gh-test', 'services.github.repo' => 'CliqueHA-Information-Services/ticketing']);
        Http::fake(['api.github.com/*' => Http::response(['number' => 77, 'html_url' => 'https://github.com/x/y/issues/77'], 201)]);

        $admin = User::factory()->create(['is_admin' => true]);
        $tenant = $this->enterpriseTenant();
        $bug = BugReport::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id, 'title' => 'x', 'description' => 'y', 'severity' => 'high', 'status' => 'new',
        ]);

        $this->actingAs($admin)->post(route('admin.bugs.fix', $bug->id))->assertRedirect();

        $bug->refresh();
        $this->assertEquals('escalated', $bug->status);
        $this->assertEquals(77, $bug->github_issue_number);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'api.github.com') && str_contains($r->url(), '/issues'));
    }

    public function test_github_webhook_moves_bug_to_pr_opened_then_merged(): void
    {
        config(['services.github.webhook_secret' => 'whsec']);
        $tenant = $this->enterpriseTenant();
        $bug = BugReport::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id, 'title' => 'x', 'description' => 'y', 'severity' => 'medium',
            'status' => 'escalated', 'github_issue_number' => 42,
        ]);

        // PR opened referencing the issue.
        $opened = json_encode(['action' => 'opened', 'pull_request' => [
            'title' => 'Fix export bug', 'body' => 'Closes #42', 'html_url' => 'https://github.com/x/y/pull/9', 'merged' => false,
        ]]);
        $this->postWebhook($opened)->assertOk();
        $this->assertEquals('pr_opened', $bug->fresh()->status);
        $this->assertEquals('https://github.com/x/y/pull/9', $bug->fresh()->github_pr_url);

        // PR merged.
        $merged = json_encode(['action' => 'closed', 'pull_request' => [
            'title' => 'Fix export bug', 'body' => 'Closes #42', 'html_url' => 'https://github.com/x/y/pull/9', 'merged' => true,
        ]]);
        $this->postWebhook($merged)->assertOk();
        $this->assertEquals('merged', $bug->fresh()->status);
    }

    public function test_github_webhook_rejects_a_bad_signature(): void
    {
        config(['services.github.webhook_secret' => 'whsec']);

        $payload = json_encode(['action' => 'opened', 'pull_request' => ['body' => 'Closes #1', 'merged' => false]]);
        $this->call('POST', '/webhooks/github', [], [], [], [
            'HTTP_X_GITHUB_EVENT' => 'pull_request',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256=deadbeef',
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertForbidden();
    }

    /** POST a signed pull_request webhook payload. */
    private function postWebhook(string $payload): TestResponse
    {
        $sig = 'sha256='.hash_hmac('sha256', $payload, 'whsec');

        return $this->call('POST', '/webhooks/github', [], [], [], [
            'HTTP_X_GITHUB_EVENT' => 'pull_request',
            'HTTP_X_HUB_SIGNATURE_256' => $sig,
            'CONTENT_TYPE' => 'application/json',
        ], $payload);
    }

    public function test_bug_status_updates_surface_to_the_reporter(): void
    {
        $tenant = $this->enterpriseTenant();
        $this->enableAi($tenant);
        $user = $this->setupTenantContext($tenant);
        $bug = BugReport::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id, 'reported_by' => $user->id,
            'title' => 'x', 'description' => 'y', 'severity' => 'medium', 'status' => 'pr_opened',
        ]);

        $this->getJson($this->tenantUrl('/assistant/bug-updates'))
            ->assertOk()
            ->assertJsonFragment(['reference' => 'BUG-'.$bug->id, 'status' => 'pr_opened']);

        $this->postJson($this->tenantUrl('/assistant/bug-updates/ack'))->assertOk();
        $this->assertEquals('pr_opened', $bug->fresh()->user_notified_status);

        // Already acknowledged → no longer surfaced.
        $this->getJson($this->tenantUrl('/assistant/bug-updates'))->assertOk()->assertJsonCount(0, 'updates');
    }

    public function test_page_context_resolver_reads_the_current_page(): void
    {
        $tenant = $this->enterpriseTenant();
        $this->setupTenantContext($tenant); // sets session current_tenant_id (for the global scope)
        $ticket = Ticket::factory()->create([
            'tenant_id' => $tenant->id, 'subject' => 'Login is broken', 'status' => 'open', 'priority' => 'high',
        ]);

        $resolver = app(PageContextResolver::class);

        // A ticket detail page surfaces the ticket's own details.
        $ctx = $resolver->describe($tenant, "/{$tenant->slug}/tickets/{$ticket->id}");
        $this->assertStringContainsString($ticket->ticket_number, $ctx);
        $this->assertStringContainsString('Login is broken', $ctx);
        $this->assertStringContainsString('high', $ctx);

        // Generic pages get a friendly label; the slug prefix is stripped.
        $this->assertStringContainsString('Tickets list', $resolver->describe($tenant, "/{$tenant->slug}/tickets"));
        $this->assertStringContainsString('Create Ticket', $resolver->describe($tenant, "/{$tenant->slug}/tickets/create"));
        $this->assertStringContainsString('Dashboard', $resolver->describe($tenant, "/{$tenant->slug}/dashboard"));
        $this->assertNull($resolver->describe($tenant, null));
    }

    public function test_in_app_assistant_keeps_per_user_conversation(): void
    {
        $tenant = $this->enterpriseTenant();
        $this->enableAi($tenant);
        $user = $this->setupTenantContext($tenant);

        $this->fakeOpenAi([
            $this->assistantText('Hello, how can I help?'),
            $this->assistantText('Earlier you greeted me.'),
        ]);

        // First message (no id) creates a conversation and returns its id.
        $first = $this->postJson($this->tenantUrl('/assistant/message'), ['message' => 'hi'])
            ->assertOk()->assertJsonStructure(['reply', 'conversation_id']);
        $cid = $first->json('conversation_id');

        // A follow-up with that id reuses the same conversation (per-user memory).
        $this->postJson($this->tenantUrl('/assistant/message'), ['message' => 'what did I say first?', 'conversation_id' => $cid])
            ->assertOk();

        $this->assertSame(1, ChatConversation::withoutGlobalScopes()
            ->where('user_id', $user->id)->where('channel', 'agent')->count());
        $convo = ChatConversation::withoutGlobalScopes()->where('user_id', $user->id)->first();
        $this->assertSame($cid, $convo->id);
        $this->assertSame(2, $convo->messages()->where('role', 'user')->count());

        // Widget endpoints: the user's own conversation list and a conversation's messages.
        $this->getJson($this->tenantUrl('/assistant/conversations'))
            ->assertOk()->assertJsonStructure(['conversations' => [['id', 'title']]]);
        $this->getJson($this->tenantUrl('/assistant/conversation/'.$cid))
            ->assertOk()->assertJsonStructure(['conversation_id', 'messages' => [['role', 'text']]]);
    }

    public function test_in_app_assistant_queries_tickets(): void
    {
        $tenant = $this->enterpriseTenant();
        $this->enableAi($tenant);
        $user = $this->setupTenantContext($tenant);
        $ticket = Ticket::factory()->create([
            'tenant_id' => $tenant->id, 'assigned_to' => $user->id, 'subject' => 'My assigned issue', 'status' => 'open',
        ]);
        // Another tenant's ticket must never appear in the results.
        $other = Tenant::factory()->create();
        Ticket::factory()->create(['tenant_id' => $other->id, 'subject' => 'Other tenant ticket']);

        $this->fakeOpenAi([
            $this->toolCall('query_tickets', ['assigned_to' => 'me', 'status' => 'open']),
            $this->assistantText('You have 1 open ticket assigned to you.'),
        ]);

        $this->postJson($this->tenantUrl('/assistant/message'), ['message' => 'what tickets are assigned to me?'])
            ->assertOk();

        // The tool ran and returned only the agent's tenant-scoped ticket.
        $toolMessage = ChatMessage::where('role', 'tool')->where('tool_name', 'query_tickets')->first();
        $this->assertNotNull($toolMessage);
        $this->assertStringContainsString($ticket->ticket_number, (string) $toolMessage->content);
        $this->assertStringNotContainsString('Other tenant ticket', (string) $toolMessage->content);
    }

    public function test_in_app_assistant_ticket_stats_and_clients(): void
    {
        $tenant = $this->enterpriseTenant();
        $this->enableAi($tenant);
        $this->setupTenantContext($tenant);
        Ticket::factory()->count(2)->create(['tenant_id' => $tenant->id, 'status' => 'open']);
        Ticket::factory()->create(['tenant_id' => $tenant->id, 'status' => 'closed']);
        Client::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Acme', 'email' => 'acme@example.com']);

        // One sequence for both turns (Http::fake stubs accumulate, so a single sequence is consumed in order).
        $this->fakeOpenAi([
            $this->toolCall('ticket_stats', []), $this->assistantText('2 open, 1 closed.'),
            $this->toolCall('query_clients', ['search' => 'acme']), $this->assistantText('Found Acme.'),
        ]);

        $this->postJson($this->tenantUrl('/assistant/message'), ['message' => 'how many open tickets?'])->assertOk();
        $stats = ChatMessage::where('tool_name', 'ticket_stats')->latest('id')->first();
        $this->assertNotNull($stats);
        $this->assertStringContainsString('open', (string) $stats->content);

        $this->postJson($this->tenantUrl('/assistant/message'), ['message' => 'find client acme'])->assertOk();
        $clients = ChatMessage::where('tool_name', 'query_clients')->latest('id')->first();
        $this->assertNotNull($clients);
        $this->assertStringContainsString('acme@example.com', (string) $clients->content);
    }

    public function test_in_app_assistant_parses_uploaded_text_file(): void
    {
        $tenant = $this->enterpriseTenant();
        $this->enableAi($tenant);
        $this->setupTenantContext($tenant);

        $this->fakeOpenAi([$this->assistantText('The file lists three fruits.')]);

        $file = UploadedFile::fake()->createWithContent('notes.txt', 'SECRET_MARKER apples bananas cherries');

        $this->post($this->tenantUrl('/assistant/message'), [
            'message' => 'What is in this file?',
            'file' => $file,
        ])->assertOk();

        // The extracted file content reached the persisted user message (so the model saw it).
        $userMessage = ChatMessage::where('role', 'user')->latest('id')->first();
        $this->assertNotNull($userMessage);
        $this->assertStringContainsString('SECRET_MARKER', (string) $userMessage->content);
        $this->assertStringContainsString('notes.txt', (string) $userMessage->content);
    }

    public function test_in_app_assistant_learns_from_resolved_tickets(): void
    {
        $tenant = $this->enterpriseTenant();
        $this->enableAi($tenant);
        $this->setupTenantContext($tenant);

        $resolved = Ticket::factory()->create([
            'tenant_id' => $tenant->id, 'status' => 'closed',
            'subject' => 'Printer offline', 'description' => 'Printer will not connect',
        ]);
        $resolved->forceFill([
            'closing_remarks' => 'Restarted the print spooler and reinstalled the driver.',
            'solution_embedding' => json_encode([1.0, 0.0, 0.0]),
            'solution_embedded_at' => now(),
        ])->saveQuietly();

        $this->fakeOpenAi([
            $this->toolCall('search_resolved_tickets', ['query' => 'printer not connecting']),
            $this->embedResponse([1.0, 0.0, 0.0]), // query embedding matches the resolved ticket
            $this->assistantText('A similar issue was resolved by restarting the print spooler.'),
        ]);

        $this->postJson($this->tenantUrl('/assistant/message'), ['message' => 'a printer will not connect, how do I fix it?'])
            ->assertOk();

        $tool = ChatMessage::where('tool_name', 'search_resolved_tickets')->latest('id')->first();
        $this->assertNotNull($tool);
        $this->assertStringContainsString($resolved->ticket_number, (string) $tool->content);
        $this->assertStringContainsString('print spooler', (string) $tool->content);
    }

    public function test_in_app_assistant_new_chat_creates_new_conversation(): void
    {
        $tenant = $this->enterpriseTenant();
        $this->enableAi($tenant);
        $user = $this->setupTenantContext($tenant);

        $this->fakeOpenAi([$this->assistantText('a'), $this->assistantText('b')]);

        $c1 = $this->postJson($this->tenantUrl('/assistant/message'), ['message' => 'first'])->assertOk()->json('conversation_id');
        $c2 = $this->postJson($this->tenantUrl('/assistant/message'), ['message' => 'second'])->assertOk()->json('conversation_id');

        $this->assertNotSame($c1, $c2);
        $this->assertSame(2, ChatConversation::withoutGlobalScopes()
            ->where('user_id', $user->id)->where('channel', 'agent')->count());
    }

    private function setupTenantContext(Tenant $tenant): User
    {
        $user = User::factory()->create();
        $tenant->addUser($user, 'admin');

        $roleService = app(TenantRoleService::class);
        $roleService->setTenantContext($tenant);
        $roleService->setupDefaultRoles($tenant);
        $roleService->syncRole($user, 'admin', $tenant);

        $this->actingAs($user)->withTenant($tenant)->withSession(['current_tenant_id' => $tenant->id]);

        return $user;
    }
}
