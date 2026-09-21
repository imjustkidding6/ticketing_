<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PlanFeature;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\License;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantRoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Started as a QA evidence probe that asserted the leak existed, and was
 * inverted once the hole was closed — per the convention in QaScenarioProbeTest.
 *
 * ChatConversation::resolveRouteBinding() calls withoutGlobalScopes() with no
 * tenant filter, because the anonymous portal has no session to scope by. That
 * makes the tenant check every caller's own job, and showConversation() was not
 * doing it: a workspace admin could read another workspace's AI conversation —
 * and its message contents — by id.
 *
 * The control test matters as much as the probe. Before the fixture granted the
 * permission correctly, BOTH cases returned 403 and the leak looked closed.
 */
class AiConversationCrossTenantProbeTest extends TestCase
{
    use RefreshDatabase;

    private function enterpriseTenant(string $name): Tenant
    {
        // Unique slug per call: one test builds two workspaces and plans.slug
        // is unique.
        $plan = Plan::factory()->create([
            'slug' => 'enterprise-'.uniqid(),
            'features' => PlanFeature::forPlan('enterprise'),
        ]);
        $license = License::factory()->active()->forPlan($plan)->create();

        return Tenant::factory()->create(['name' => $name, 'license_id' => $license->id]);
    }

    private function adminOf(Tenant $tenant): User
    {
        $user = User::factory()->create();
        $tenant->addUser($user, 'member');

        $roles = app(TenantRoleService::class);
        $roles->setTenantContext($tenant);
        $roles->setupDefaultRoles($tenant);
        $roles->syncRole($user, 'admin', $tenant);

        $this->actingAs($user)->withTenant($tenant)->withSession(['current_tenant_id' => $tenant->id]);

        return $user;
    }

    public function test_a_tenant_admin_cannot_open_another_workspaces_ai_conversation(): void
    {
        $victim = $this->enterpriseTenant('VictimCorp');
        $attacker = $this->enterpriseTenant('AttackerCorp');

        $conversation = ChatConversation::withoutGlobalScopes()->create([
            'tenant_id' => $victim->id,
            'channel' => ChatConversation::CHANNEL_AGENT,
            'title' => 'Victim internal discussion',
            'status' => ChatConversation::STATUS_ACTIVE,
        ]);

        ChatMessage::create([
            'chat_conversation_id' => $conversation->id,
            'role' => ChatMessage::ROLE_USER,
            'content' => 'CONFIDENTIAL-VICTIM-PAYLOAD',
        ]);

        $intruder = $this->adminOf($attacker);

        $response = $this->get($this->tenantUrl('settings/ai/conversations/'.$conversation->id));

        $response->assertNotFound();
        $response->assertDontSee('CONFIDENTIAL-VICTIM-PAYLOAD');
    }

    public function test_control_a_workspace_admin_can_open_their_own_conversation(): void
    {
        // Without this control a 403 on the cross-tenant case is ambiguous: it
        // could mean the boundary holds, or that the fixture never granted the
        // permission in the first place. This proves the permission works.
        $attacker = $this->enterpriseTenant('AttackerCorp');
        $user = $this->adminOf($attacker);

        $own = ChatConversation::withoutGlobalScopes()->create([
            'tenant_id' => $attacker->id,
            'channel' => ChatConversation::CHANNEL_AGENT,
            'title' => 'Own discussion',
            'status' => ChatConversation::STATUS_ACTIVE,
        ]);
        ChatMessage::create([
            'chat_conversation_id' => $own->id,
            'role' => ChatMessage::ROLE_USER,
            'content' => 'OWN-PAYLOAD',
        ]);

        $response = $this->get($this->tenantUrl('settings/ai/conversations/'.$own->id));

        // Proves the fixture really grants the permission, so a 404 in the test
        // above means the boundary held rather than the setup having failed.
        $this->assertTrue($user->can('manage ai settings'));

        $response->assertOk();
        $response->assertSee('OWN-PAYLOAD');
    }
}
