<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiAssistantControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.openai.api_key', 'sk-test-key-12345');
        Config::set('services.openai.model', 'gpt-5');
    }

    public function test_portal_start_conversation_creates_new_portal_conversation(): void
    {
        $tenant = Tenant::factory()->create();

        $response = $this->postJson('/portal/ai/start', [
            'tenant_id' => $tenant->id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'conversation_id',
                'session_token',
                'messages',
            ]);

        $conversationId = $response->json('conversation_id');

        $this->assertDatabaseHas('chat_conversations', [
            'id' => $conversationId,
            'tenant_id' => $tenant->id,
            'channel' => ChatConversation::CHANNEL_PORTAL,
            'status' => ChatConversation::STATUS_ACTIVE,
        ]);
    }

    public function test_portal_send_message_persists_messages_and_returns_assistant_reply(): void
    {
        $tenant = Tenant::factory()->create();

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'Hello! How can I assist you with your support request today?',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->postJson('/portal/ai/message', [
            'tenant_id' => $tenant->id,
            'message' => 'Hello, I need help resetting my password.',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'assistant_reply' => 'Hello! How can I assist you with your support request today?',
            ])
            ->assertJsonStructure([
                'success',
                'conversation_id',
                'session_token',
                'assistant_reply',
                'messages',
            ]);

        $conversationId = $response->json('conversation_id');

        $this->assertDatabaseHas('chat_messages', [
            'chat_conversation_id' => $conversationId,
            'role' => ChatMessage::ROLE_USER,
            'content' => 'Hello, I need help resetting my password.',
        ]);

        $this->assertDatabaseHas('chat_messages', [
            'chat_conversation_id' => $conversationId,
            'role' => ChatMessage::ROLE_ASSISTANT,
            'content' => 'Hello! How can I assist you with your support request today?',
        ]);
    }

    public function test_portal_load_conversation_returns_history(): void
    {
        $tenant = Tenant::factory()->create();
        $conversation = ChatConversation::factory()->create([
            'tenant_id' => $tenant->id,
            'channel' => ChatConversation::CHANNEL_PORTAL,
            'session_token' => 'test-session-token-123',
        ]);

        ChatMessage::factory()->create([
            'chat_conversation_id' => $conversation->id,
            'role' => ChatMessage::ROLE_USER,
            'content' => 'User question',
        ]);

        ChatMessage::factory()->create([
            'chat_conversation_id' => $conversation->id,
            'role' => ChatMessage::ROLE_ASSISTANT,
            'content' => 'Assistant response',
        ]);

        $response = $this->getJson("/portal/ai/{$conversation->id}?tenant_id={$tenant->id}&session_token=test-session-token-123");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'conversation_id' => $conversation->id,
            ])
            ->assertJsonCount(2, 'messages');
    }

    public function test_agent_start_conversation_requires_authentication(): void
    {
        $response = $this->postJson('/app/ai/start');

        $response->assertStatus(401);
    }

    public function test_agent_start_conversation_creates_agent_conversation_for_user(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $user->tenants()->attach($tenant->id, ['role' => 'agent']);

        $response = $this->actingAs($user)->postJson('/app/ai/start', [
            'tenant_id' => $tenant->id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $conversationId = $response->json('conversation_id');

        $this->assertDatabaseHas('chat_conversations', [
            'id' => $conversationId,
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'channel' => ChatConversation::CHANNEL_AGENT,
        ]);
    }

    public function test_agent_send_message_with_image_upload_and_page_context(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $user->tenants()->attach($tenant->id, ['role' => 'agent']);

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'I see your attached screenshot and error log.',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $image = UploadedFile::fake()->image('screenshot.png', 400, 300);

        $response = $this->actingAs($user)->postJson('/app/ai/message', [
            'tenant_id' => $tenant->id,
            'message' => 'Can you analyze this error screenshot?',
            'page_context' => 'Currently on Ticket #1001 page',
            'image' => $image,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'assistant_reply' => 'I see your attached screenshot and error log.',
            ]);

        $conversationId = $response->json('conversation_id');

        $this->assertDatabaseHas('chat_conversations', [
            'id' => $conversationId,
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_agent_history_enforces_tenant_and_user_isolation(): void
    {
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();

        $user1 = User::factory()->create();
        $user1->tenants()->attach($tenant1->id, ['role' => 'agent']);

        $user2 = User::factory()->create();
        $user2->tenants()->attach($tenant2->id, ['role' => 'agent']);

        $conversation1 = ChatConversation::factory()->create([
            'tenant_id' => $tenant1->id,
            'user_id' => $user1->id,
            'channel' => ChatConversation::CHANNEL_AGENT,
        ]);

        // User 1 accessing own conversation succeeds
        $response1 = $this->actingAs($user1)->getJson("/app/ai/{$conversation1->id}?tenant_id={$tenant1->id}");
        $response1->assertStatus(200)->assertJson(['success' => true]);

        // User 2 accessing User 1's conversation fails (404/403 isolation)
        $response2 = $this->actingAs($user2)->getJson("/app/ai/{$conversation1->id}?tenant_id={$tenant2->id}");
        $response2->assertStatus(404);
    }

    public function test_validation_fails_on_missing_message(): void
    {
        $tenant = Tenant::factory()->create();

        $response = $this->postJson('/portal/ai/message', [
            'tenant_id' => $tenant->id,
            'message' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['message']);
    }
}
