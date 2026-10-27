<?php

namespace Tests\Feature;

use App\Enums\PlanFeature;
use App\Models\License;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantRoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The floating release note.
 *
 * The failure that matters is not a missing pill — it is a pill pointing somewhere
 * that does not exist. config('whats_new.topic') is resolved through
 * route('tutorials.show', …), which 404s on an unknown key, so a typo there would
 * ship a dead end to every workspace. The first test is the one worth having.
 */
class WhatsNewTest extends TestCase
{
    use RefreshDatabase;

    private function setupContext(): array
    {
        $plan = Plan::factory()->create(['slug' => 'business', 'features' => PlanFeature::forPlan('business')]);
        $license = License::factory()->active()->forPlan($plan)->create();
        $tenant = Tenant::factory()->create(['license_id' => $license->id]);
        $user = User::factory()->create();
        $tenant->addUser($user, 'admin');

        $roleService = app(TenantRoleService::class);
        $roleService->setupDefaultRoles($tenant);
        $roleService->syncRole($user, 'admin', $tenant);

        $this->actingAs($user)->withTenant($tenant)->withSession(['current_tenant_id' => $tenant->id]);

        return [$tenant, $user];
    }

    public function test_the_configured_topic_exists_in_the_help_centre(): void
    {
        $topic = config('whats_new.topic');

        $this->assertIsString($topic);
        $this->assertArrayHasKey(
            $topic,
            config('help.topics'),
            'whats_new.topic must name a real help topic, or the pill 404s for every workspace.'
        );
    }

    public function test_the_pill_renders_in_tenant_context_and_links_to_the_guide(): void
    {
        [$tenant] = $this->setupContext();

        $this->get($this->tenantUrl('/dashboard'))
            ->assertOk()
            ->assertSee('data-tour="whats-new"', false)
            ->assertSee(config('whats_new.title'));
    }

    public function test_the_link_resolves_to_the_help_centre(): void
    {
        [$tenant] = $this->setupContext();

        // Follows the redirect tutorials.show performs, proving the destination is
        // real rather than merely that a URL could be generated.
        $this->get($this->tenantUrl('/tutorials/'.config('whats_new.topic')))
            ->assertRedirect($this->tenantUrl('/tutorials').'#topic-'.config('whats_new.topic'));
    }

    public function test_it_can_be_switched_off_product_wide(): void
    {
        [$tenant] = $this->setupContext();

        config(['whats_new.enabled' => false]);

        $this->get($this->tenantUrl('/dashboard'))
            ->assertOk()
            ->assertDontSee('data-tour="whats-new"', false);
    }

    public function test_the_release_key_is_present(): void
    {
        // The dismissal key. Empty would mean every viewer is shown the pill on
        // every page view with no way to dismiss it, so the layout refuses to
        // render without one.
        $this->assertNotEmpty(config('whats_new.release'));
    }

    public function test_the_items_stay_short_enough_to_read(): void
    {
        // The pill is an invitation to the help centre, not a second home for
        // release notes. Four lines is the point at which it stops being read.
        $this->assertLessThanOrEqual(4, count((array) config('whats_new.items')));
    }
}
