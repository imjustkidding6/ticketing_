<?php

namespace Tests\Feature;

use App\Enums\PlanFeature;
use App\Models\License;
use App\Models\Plan;
use App\Models\SystemSetting;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChatbotPublicTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_tenant_can_request_chatbot_token(): void
    {
        $tenant = $this->createBusinessTenant();

        $response = $this->postJson("/{$tenant->slug}/api/public/chatbot/token");

        $response->assertOk()
            ->assertJsonStructure(['token', 'expires_at', 'session_id']);
    }

    public function test_starter_tenant_cannot_access_chatbot(): void
    {
        $tenant = $this->createStarterTenant();

        $this->postJson("/{$tenant->slug}/api/public/chatbot/token")
            ->assertNotFound();
    }

    public function test_chatbot_message_returns_reply(): void
    {
        $tenant = $this->createBusinessTenant();
        $this->setChatbotKey('test-key');

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => '{"reply":"Hello there!","confidence":0.85}'],
                            ],
                        ],
                    ],
                ],
                'usageMetadata' => [
                    'promptTokenCount' => 5,
                    'candidatesTokenCount' => 7,
                ],
            ], 200),
        ]);

        $tokenResponse = $this->postJson("/{$tenant->slug}/api/public/chatbot/token");
        $token = $tokenResponse->json('token');
        $sessionId = $tokenResponse->json('session_id');

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/{$tenant->slug}/api/public/chatbot/message", [
                'session_id' => $sessionId,
                'message' => 'Hi there',
            ]);

        $response->assertOk()
            ->assertJsonFragment(['reply' => 'Hello there!']);
    }

    public function test_chatbot_escalation_prompts_for_contact(): void
    {
        $tenant = $this->createBusinessTenant();
        $this->setChatbotKey('test-key');

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => '{"reply":"Sure, I can help.","confidence":0.9}'],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $tokenResponse = $this->postJson("/{$tenant->slug}/api/public/chatbot/token");
        $token = $tokenResponse->json('token');
        $sessionId = $tokenResponse->json('session_id');

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/{$tenant->slug}/api/public/chatbot/message", [
                'session_id' => $sessionId,
                'message' => 'I need a human agent',
            ]);

        $response->assertOk()
            ->assertJsonFragment(['next_action' => 'collect_contact']);
    }

    private function createBusinessTenant(): Tenant
    {
        $plan = Plan::factory()->create([
            'slug' => 'business',
            'features' => PlanFeature::forPlan('business'),
        ]);
        $license = License::factory()->active()->forPlan($plan)->create();

        return Tenant::factory()->create([
            'license_id' => $license->id,
        ]);
    }

    private function createStarterTenant(): Tenant
    {
        $plan = Plan::factory()->create([
            'slug' => 'start',
            'features' => PlanFeature::forPlan('start'),
        ]);
        $license = License::factory()->active()->forPlan($plan)->create();

        return Tenant::factory()->create([
            'license_id' => $license->id,
        ]);
    }

    private function setChatbotKey(string $key): void
    {
        SystemSetting::set('chatbot_api_key', $key, 'encrypted', 'chatbot');
    }
}
