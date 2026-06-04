<?php

namespace Tests\Feature;

use App\Enums\PlanFeature;
use App\Models\AppSetting;
use App\Models\ChatConversation;
use App\Models\Department;
use App\Models\KbArticle;
use App\Models\KbCategory;
use App\Models\License;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TenantRoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
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

    public function test_in_app_assistant_has_per_user_memory(): void
    {
        $tenant = $this->enterpriseTenant();
        $this->enableAi($tenant);
        $user = $this->setupTenantContext($tenant);

        $this->fakeOpenAi([
            $this->assistantText('Hello, how can I help?'),
            $this->assistantText('Earlier you greeted me.'),
        ]);

        $this->postJson($this->tenantUrl('/assistant/message'), ['message' => 'hi'])
            ->assertOk()->assertJsonStructure(['reply']);
        $this->postJson($this->tenantUrl('/assistant/message'), ['message' => 'what did I say first?'])
            ->assertOk();

        // Memory is keyed to the user account: a single agent-channel conversation, reused.
        $this->assertDatabaseHas('chat_conversations', [
            'tenant_id' => $tenant->id, 'user_id' => $user->id, 'channel' => 'agent',
        ]);
        $this->assertSame(1, ChatConversation::withoutGlobalScopes()
            ->where('user_id', $user->id)->where('channel', 'agent')->count());
        $this->assertDatabaseHas('chat_messages', ['role' => 'user', 'content' => 'hi']);

        // History endpoint returns this user's conversation.
        $this->getJson($this->tenantUrl('/assistant/history'))
            ->assertOk()->assertJsonStructure(['messages' => [['role', 'text']]]);
    }

    public function test_in_app_assistant_new_chat_archives_conversation(): void
    {
        $tenant = $this->enterpriseTenant();
        $this->enableAi($tenant);
        $user = $this->setupTenantContext($tenant);

        $this->fakeOpenAi([$this->assistantText('Hi')]);
        $this->postJson($this->tenantUrl('/assistant/message'), ['message' => 'hi'])->assertOk();

        $this->postJson($this->tenantUrl('/assistant/new'))->assertOk();

        $this->assertSame(0, ChatConversation::withoutGlobalScopes()
            ->where('user_id', $user->id)->where('channel', 'agent')->where('status', 'active')->count());
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
