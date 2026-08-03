<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ChatConversation;
use App\Models\ChatMessageFeedback;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.openai.api_key', 'sk-test-key-12345');
        Config::set('services.openai.model', 'gpt-5');
    }

    public function test_admin_can_view_and_update_ai_settings(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->get('/admin/ai/settings');
        $response->assertStatus(200)->assertSee('AI Platform Settings');

        $updateResponse = $this->actingAs($admin)->post('/admin/ai/settings', [
            'openai_model' => 'gpt-5-turbo',
            'embedding_model' => 'text-embedding-3-large',
            'temperature' => 0.5,
            'max_tokens' => 3000,
            'top_p' => 0.9,
            'frequency_penalty' => 0.1,
            'presence_penalty' => 0.1,
            'feature_portal_ai' => 1,
            'feature_agent_copilot' => 1,
        ]);

        $updateResponse->assertRedirect();
        $updateResponse->assertSessionHas('success');
    }

    public function test_admin_can_create_system_prompt_template(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->post('/admin/ai/prompts', [
            'type' => 'global',
            'name' => 'Support Assistant Prompt v1',
            'prompt' => 'You are a helpful SaaS support AI.',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('ai_prompt_templates', [
            'type' => 'global',
            'name' => 'Support Assistant Prompt v1',
            'version' => 1,
        ]);
    }

    public function test_admin_can_view_and_export_conversations(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $tenant = Tenant::factory()->create();

        ChatConversation::factory()->create([
            'tenant_id' => $tenant->id,
            'channel' => 'portal',
        ]);

        $response = $this->actingAs($admin)->get('/admin/ai/conversations');
        $response->assertStatus(200);

        $exportResponse = $this->actingAs($admin)->get('/admin/ai/conversations/export');
        $exportResponse->assertStatus(200);
        $this->assertTrue(str_contains($exportResponse->headers->get('content-type'), 'text/csv'));
    }

    public function test_user_can_submit_ai_response_feedback(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $tenant = Tenant::factory()->create();

        $response = $this->actingAs($admin)->postJson('/admin/ai/feedback', [
            'tenant_id' => $tenant->id,
            'rating' => ChatMessageFeedback::RATING_THUMBS_UP,
            'comment' => 'Very accurate answer!',
            'question' => 'How do I reset password?',
            'response' => 'Click reset password link.',
        ]);

        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertDatabaseHas('chat_message_feedbacks', [
            'rating' => 'thumbs_up',
            'comment' => 'Very accurate answer!',
        ]);
    }

    public function test_prompt_playground_execution(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'Playground response output test.',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($admin)->postJson('/admin/ai/playground/run', [
            'system_prompt' => 'You are a test AI.',
            'user_message' => 'Hello!',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'reply' => 'Playground response output test.',
            ]);
    }
}
