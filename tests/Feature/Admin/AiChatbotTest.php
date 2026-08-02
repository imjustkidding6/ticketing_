<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiChatbotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_unauthenticated_user_cannot_access_chat_page(): void
    {
        $response = $this->get(route('admin.ai.chat-page'));
        $response->assertRedirect(route('login'));
    }

    public function test_admin_can_access_chat_page(): void
    {
        $admin = User::where('email', 'admin@example.com')->first()
            ?? User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->get(route('admin.ai.chat-page'));
        $response->assertOk()
            ->assertViewIs('admin.ai.chat');
    }

    public function test_admin_can_start_and_fetch_conversations(): void
    {
        $admin = User::where('email', 'admin@example.com')->first()
            ?? User::factory()->create(['is_admin' => true]);

        $startRes = $this->actingAs($admin)->postJson(route('admin.ai.chatbot.start'), [
            'title' => 'Test Conversation',
        ]);

        $startRes->assertOk()
            ->assertJson(['success' => true]);

        $getRes = $this->actingAs($admin)->getJson(route('admin.ai.chatbot.conversations'));
        $getRes->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_admin_can_send_message_and_receive_assistant_reply(): void
    {
        $admin = User::where('email', 'admin@example.com')->first()
            ?? User::factory()->create(['is_admin' => true]);

        $tenant = \App\Models\Tenant::first();

        $conv = ChatConversation::create([
            'tenant_id' => $tenant->id,
            'user_id' => $admin->id,
            'channel' => ChatConversation::CHANNEL_AGENT,
            'title' => 'System Query',
            'status' => ChatConversation::STATUS_ACTIVE,
        ]);

        $response = $this->actingAs($admin)->postJson(route('admin.ai.chatbot.send', $conv), [
            'message' => 'How many active tenants exist?',
        ]);

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'userMessage',
                'assistantMessage',
            ]);

        $this->assertDatabaseHas('chat_messages', [
            'chat_conversation_id' => $conv->id,
            'role' => ChatMessage::ROLE_USER,
            'content' => 'How many active tenants exist?',
        ]);
    }

    public function test_admin_can_export_conversation_using_export_service(): void
    {
        $admin = User::where('email', 'admin@example.com')->first()
            ?? User::factory()->create(['is_admin' => true]);

        $tenant = \App\Models\Tenant::first();

        $conv = ChatConversation::create([
            'tenant_id' => $tenant->id,
            'user_id' => $admin->id,
            'channel' => ChatConversation::CHANNEL_AGENT,
            'title' => 'Exportable Conversation',
            'status' => ChatConversation::STATUS_ACTIVE,
        ]);

        ChatMessage::create([
            'chat_conversation_id' => $conv->id,
            'role' => ChatMessage::ROLE_USER,
            'content' => 'Export test message',
        ]);

        $jsonRes = $this->actingAs($admin)->get(route('admin.ai.chatbot.export', ['conversation' => $conv, 'format' => 'json']));
        $jsonRes->assertOk()
            ->assertJsonStructure(['conversation_id', 'messages']);

        $csvRes = $this->actingAs($admin)->get(route('admin.ai.chatbot.export', ['conversation' => $conv, 'format' => 'csv']));
        $csvRes->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=utf-8');
    }
}
