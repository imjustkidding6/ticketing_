<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\BugReport;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Client;
use App\Models\LearnedSnippet;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AiAssistantDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_conversation_creation_relationships_and_helpers(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $ticket = Ticket::factory()->create(['tenant_id' => $tenant->id]);

        $conversation = ChatConversation::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'client_id' => $client->id,
            'ticket_id' => $ticket->id,
            'status' => ChatConversation::STATUS_ACTIVE,
            'channel' => ChatConversation::CHANNEL_PORTAL,
        ]);

        $this->assertInstanceOf(Tenant::class, $conversation->tenant);
        $this->assertEquals($tenant->id, $conversation->tenant->id);
        $this->assertInstanceOf(User::class, $conversation->user);
        $this->assertEquals($user->id, $conversation->user->id);
        $this->assertInstanceOf(Client::class, $conversation->client);
        $this->assertEquals($client->id, $conversation->client->id);
        $this->assertInstanceOf(Ticket::class, $conversation->ticket);
        $this->assertEquals($ticket->id, $conversation->ticket->id);

        // Test helper methods
        $conversation->markClosed();
        $this->assertEquals(ChatConversation::STATUS_CLOSED, $conversation->status);

        $conversation->markActive();
        $this->assertEquals(ChatConversation::STATUS_ACTIVE, $conversation->status);

        $conversation->touchLastMessage();
        $this->assertNotNull($conversation->last_message_at);
        $this->assertInstanceOf(Carbon::class, $conversation->last_message_at);
    }

    public function test_chat_message_creation_relationships_and_casts(): void
    {
        $conversation = ChatConversation::factory()->create();
        $metadata = ['tokens' => 150, 'model' => 'gpt-5'];

        $message = ChatMessage::factory()->create([
            'chat_conversation_id' => $conversation->id,
            'role' => ChatMessage::ROLE_ASSISTANT,
            'content' => 'Sample response',
            'metadata' => $metadata,
        ]);

        $this->assertInstanceOf(ChatConversation::class, $message->conversation);
        $this->assertEquals($conversation->id, $message->conversation->id);
        $this->assertInstanceOf(ChatConversation::class, $message->chatConversation);
        $this->assertEquals($conversation->id, $message->chatConversation->id);

        // Test cast
        $this->assertIsArray($message->metadata);
        $this->assertEquals(150, $message->metadata['tokens']);
        $this->assertEquals('gpt-5', $message->metadata['model']);

        // Verify constants
        $this->assertEquals('user', ChatMessage::ROLE_USER);
        $this->assertEquals('assistant', ChatMessage::ROLE_ASSISTANT);
        $this->assertEquals('tool', ChatMessage::ROLE_TOOL);
        $this->assertEquals('system', ChatMessage::ROLE_SYSTEM);
    }

    public function test_bug_report_creation_relationships_constants_and_reference_helper(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $conversation = ChatConversation::factory()->create(['tenant_id' => $tenant->id]);

        $triageData = ['confidence' => 0.95, 'suggested_area' => 'auth'];

        $bug = BugReport::factory()->create([
            'tenant_id' => $tenant->id,
            'reported_by' => $user->id,
            'chat_conversation_id' => $conversation->id,
            'title' => 'Login error',
            'description' => 'Cannot log in with SSO',
            'severity' => BugReport::SEVERITY_HIGH,
            'status' => BugReport::STATUS_NEW,
            'ai_triage' => $triageData,
        ]);

        $this->assertInstanceOf(Tenant::class, $bug->tenant);
        $this->assertInstanceOf(User::class, $bug->user);
        $this->assertInstanceOf(User::class, $bug->reporter);
        $this->assertInstanceOf(ChatConversation::class, $bug->conversation);
        $this->assertInstanceOf(ChatConversation::class, $bug->chatConversation);

        // Test cast
        $this->assertIsArray($bug->ai_triage);
        $this->assertEquals('auth', $bug->ai_triage['suggested_area']);

        // Test reference helper method
        $expectedYear = date('Y');
        $this->assertStringStartsWith("BUG-{$expectedYear}-", $bug->reference());
        $this->assertEquals(sprintf('BUG-%s-%06d', $expectedYear, $bug->id), $bug->reference());

        // Test constants
        $this->assertEquals('new', BugReport::STATUS_NEW);
        $this->assertEquals('acknowledged', BugReport::STATUS_ACKNOWLEDGED);
        $this->assertEquals('in_progress', BugReport::STATUS_IN_PROGRESS);
        $this->assertEquals('resolved', BugReport::STATUS_RESOLVED);
        $this->assertEquals('closed', BugReport::STATUS_CLOSED);
        $this->assertEquals('low', BugReport::SEVERITY_LOW);
        $this->assertEquals('medium', BugReport::SEVERITY_MEDIUM);
        $this->assertEquals('high', BugReport::SEVERITY_HIGH);
        $this->assertEquals('critical', BugReport::SEVERITY_CRITICAL);
    }

    public function test_learned_snippet_creation_relationships_and_casts(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $vector = [0.1, 0.2, -0.3, 0.4];

        $snippet = LearnedSnippet::factory()->create([
            'tenant_id' => $tenant->id,
            'created_by' => $user->id,
            'question' => 'How to reset password?',
            'answer' => 'Click forgot password on login screen.',
            'embedding' => $vector,
        ]);

        $this->assertInstanceOf(Tenant::class, $snippet->tenant);
        $this->assertInstanceOf(User::class, $snippet->user);
        $this->assertInstanceOf(User::class, $snippet->creator);

        // Test cast
        $this->assertIsArray($snippet->embedding);
        $this->assertSame($vector, $snippet->embedding);
    }
}
