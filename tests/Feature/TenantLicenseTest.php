<?php

namespace Tests\Feature;

use App\Models\License;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantLicenseTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_can_have_license(): void
    {
        $license = License::factory()->active()->create();
        $tenant = Tenant::factory()->create(['license_id' => $license->id]);

        $this->assertEquals($license->id, $tenant->license_id);
        $this->assertInstanceOf(License::class, $tenant->license);
    }

    public function test_plan_returns_plan_through_license(): void
    {
        $plan = Plan::factory()->start()->create();
        $license = License::factory()->active()->create(['plan_id' => $plan->id]);
        $tenant = Tenant::factory()->create(['license_id' => $license->id]);

        $this->assertInstanceOf(Plan::class, $tenant->plan());
        $this->assertEquals($plan->id, $tenant->plan()->id);
    }

    public function test_plan_returns_null_when_no_license(): void
    {
        $tenant = Tenant::factory()->create(['license_id' => null]);

        $this->assertNull($tenant->plan());
    }

    public function test_is_license_valid_returns_true_for_valid_license(): void
    {
        $license = License::factory()->active()->create([
            'expires_at' => now()->addMonth(),
        ]);
        $tenant = Tenant::factory()->create(['license_id' => $license->id]);

        $this->assertTrue($tenant->isLicenseValid());
    }

    public function test_is_license_valid_returns_false_when_no_license(): void
    {
        $tenant = Tenant::factory()->create(['license_id' => null]);

        $this->assertFalse($tenant->isLicenseValid());
    }

    public function test_is_license_valid_returns_false_for_expired_license(): void
    {
        $license = License::factory()->expired()->create();
        $tenant = Tenant::factory()->create(['license_id' => $license->id]);

        $this->assertFalse($tenant->isLicenseValid());
    }

    public function test_is_suspended_returns_true_when_suspended_at_is_set(): void
    {
        $tenant = Tenant::factory()->suspended()->create();

        $this->assertTrue($tenant->isSuspended());
    }

    public function test_is_suspended_returns_false_when_suspended_at_is_null(): void
    {
        $tenant = Tenant::factory()->create(['suspended_at' => null]);

        $this->assertFalse($tenant->isSuspended());
    }

    public function test_suspend_sets_suspended_at(): void
    {
        $tenant = Tenant::factory()->create();

        $tenant->suspend();

        $this->assertNotNull($tenant->fresh()->suspended_at);
        $this->assertTrue($tenant->isSuspended());
    }

    public function test_unsuspend_clears_suspended_at(): void
    {
        $tenant = Tenant::factory()->suspended()->create();

        $tenant->unsuspend();

        $this->assertNull($tenant->fresh()->suspended_at);
        $this->assertFalse($tenant->isSuspended());
    }

    public function test_can_add_users_returns_true_when_under_seat_limit(): void
    {
        $license = License::factory()->active()->create(['seats' => 5]);
        $tenant = Tenant::factory()->create(['license_id' => $license->id]);

        $this->assertTrue($tenant->canAddUsers());
    }

    public function test_can_add_users_returns_false_when_at_seat_limit(): void
    {
        $license = License::factory()->active()->create(['seats' => 2]);
        $tenant = Tenant::factory()->create(['license_id' => $license->id]);

        $tenant->addUser(User::factory()->create(), 'owner');
        $tenant->addUser(User::factory()->create(), 'member');

        $this->assertFalse($tenant->canAddUsers());
    }

    public function test_can_add_users_returns_false_when_no_license(): void
    {
        $tenant = Tenant::factory()->create(['license_id' => null]);

        $this->assertFalse($tenant->canAddUsers());
    }

    public function test_available_user_slots_returns_correct_count(): void
    {
        $license = License::factory()->active()->create(['seats' => 10]);
        $tenant = Tenant::factory()->create(['license_id' => $license->id]);

        $tenant->addUser(User::factory()->create(), 'owner');
        $tenant->addUser(User::factory()->create(), 'member');
        $tenant->addUser(User::factory()->create(), 'member');

        $this->assertEquals(7, $tenant->availableUserSlots());
    }

    public function test_available_user_slots_returns_zero_when_no_license(): void
    {
        $tenant = Tenant::factory()->create(['license_id' => null]);

        $this->assertEquals(0, $tenant->availableUserSlots());
    }

    public function test_change_plan_updates_license_plan(): void
    {
        $oldPlan = Plan::factory()->start()->create();
        $newPlan = Plan::factory()->business()->create();
        $license = License::factory()->active()->create(['plan_id' => $oldPlan->id]);
        $tenant = Tenant::factory()->create(['license_id' => $license->id]);

        $result = $tenant->changePlan($newPlan);

        $this->assertTrue($result);
        $this->assertEquals($newPlan->id, $tenant->license->fresh()->plan_id);
    }

    public function test_change_plan_returns_false_when_no_license(): void
    {
        $tenant = Tenant::factory()->create(['license_id' => null]);
        $plan = Plan::factory()->create();

        $result = $tenant->changePlan($plan);

        $this->assertFalse($result);
    }

    public function test_settings_are_cast_to_array(): void
    {
        $tenant = Tenant::factory()->create([
            'settings' => ['theme' => 'dark', 'notifications' => true],
        ]);

        $this->assertIsArray($tenant->settings);
        $this->assertEquals('dark', $tenant->settings['theme']);
        $this->assertTrue($tenant->settings['notifications']);
    }
}
