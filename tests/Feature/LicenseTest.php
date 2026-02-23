<?php

namespace Tests\Feature;

use App\Models\Distributor;
use App\Models\License;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LicenseTest extends TestCase
{
    use RefreshDatabase;

    public function test_license_can_be_created(): void
    {
        $license = License::factory()->create();

        $this->assertDatabaseHas('licenses', [
            'id' => $license->id,
        ]);
    }

    public function test_license_key_is_auto_generated(): void
    {
        $distributor = Distributor::factory()->create();
        $plan = Plan::factory()->create();

        $license = License::create([
            'distributor_id' => $distributor->id,
            'plan_id' => $plan->id,
            'seats' => 10,
            'issued_at' => now(),
            'expires_at' => now()->addYear(),
        ]);

        $this->assertNotNull($license->license_key);
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $license->license_key);
    }

    public function test_generate_key_creates_correct_format(): void
    {
        $key = License::generateKey();

        $this->assertMatchesRegularExpression('/^[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $key);
    }

    public function test_license_belongs_to_distributor(): void
    {
        $license = License::factory()->create();

        $this->assertInstanceOf(Distributor::class, $license->distributor);
    }

    public function test_license_belongs_to_plan(): void
    {
        $license = License::factory()->create();

        $this->assertInstanceOf(Plan::class, $license->plan);
    }

    public function test_activate_links_license_to_tenant(): void
    {
        $license = License::factory()->pending()->create();
        $tenant = Tenant::factory()->create();

        $result = $license->activate($tenant);

        $this->assertTrue($result);
        $this->assertEquals($tenant->id, $license->tenant_id);
        $this->assertEquals('active', $license->status);
        $this->assertNotNull($license->activated_at);
        $this->assertEquals($license->id, $tenant->fresh()->license_id);
    }

    public function test_activate_fails_if_license_not_pending(): void
    {
        $license = License::factory()->active()->create();
        $tenant = Tenant::factory()->create();

        $result = $license->activate($tenant);

        $this->assertFalse($result);
    }

    public function test_revoke_sets_status_to_revoked(): void
    {
        $license = License::factory()->active()->create();

        $license->revoke();

        $this->assertEquals('revoked', $license->fresh()->status);
    }

    public function test_is_valid_returns_true_for_active_non_expired_license(): void
    {
        $license = License::factory()->active()->create([
            'expires_at' => now()->addMonth(),
        ]);

        $this->assertTrue($license->isValid());
    }

    public function test_is_valid_returns_false_for_fully_expired_license(): void
    {
        $license = License::factory()->expired()->create();

        $this->assertFalse($license->isValid());
    }

    public function test_is_expired_returns_true_when_past_expires_at(): void
    {
        $license = License::factory()->create([
            'expires_at' => now()->subDay(),
        ]);

        $this->assertTrue($license->isExpired());
    }

    public function test_is_expired_returns_false_when_before_expires_at(): void
    {
        $license = License::factory()->create([
            'expires_at' => now()->addMonth(),
        ]);

        $this->assertFalse($license->isExpired());
    }

    public function test_is_in_grace_period_returns_true_when_expired_but_within_grace_days(): void
    {
        $license = License::factory()->inGracePeriod()->create();

        $this->assertTrue($license->isInGracePeriod());
    }

    public function test_is_in_grace_period_returns_false_when_not_expired(): void
    {
        $license = License::factory()->create([
            'expires_at' => now()->addMonth(),
        ]);

        $this->assertFalse($license->isInGracePeriod());
    }

    public function test_is_fully_expired_returns_true_when_past_grace_period(): void
    {
        $license = License::factory()->expired()->create();

        $this->assertTrue($license->isFullyExpired());
    }

    public function test_is_fully_expired_returns_false_when_in_grace_period(): void
    {
        $license = License::factory()->inGracePeriod()->create();

        $this->assertFalse($license->isFullyExpired());
    }

    public function test_grace_period_ends_at_returns_correct_date(): void
    {
        $expiresAt = now()->addMonth();
        $license = License::factory()->create([
            'expires_at' => $expiresAt,
            'grace_days' => 7,
        ]);

        $gracePeriodEndsAt = $license->gracePeriodEndsAt();

        $this->assertEquals($expiresAt->copy()->addDays(7)->toDateString(), $gracePeriodEndsAt->toDateString());
    }

    public function test_days_until_expiry_returns_correct_count(): void
    {
        $license = License::factory()->create([
            'expires_at' => now()->addDays(30)->startOfDay(),
        ]);

        $daysUntilExpiry = $license->daysUntilExpiry();
        $this->assertTrue($daysUntilExpiry >= 29 && $daysUntilExpiry <= 30);
    }

    public function test_days_until_expiry_returns_zero_when_expired(): void
    {
        $license = License::factory()->create([
            'expires_at' => now()->subDays(5),
        ]);

        $this->assertEquals(0, $license->daysUntilExpiry());
    }

    public function test_change_plan_updates_plan_id(): void
    {
        $oldPlan = Plan::factory()->start()->create();
        $newPlan = Plan::factory()->business()->create();
        $license = License::factory()->create(['plan_id' => $oldPlan->id]);

        $license->changePlan($newPlan);

        $this->assertEquals($newPlan->id, $license->fresh()->plan_id);
    }

    public function test_scope_pending_returns_only_pending_licenses(): void
    {
        License::factory()->count(2)->pending()->create();
        License::factory()->count(3)->active()->create();

        $pendingLicenses = License::pending()->get();

        $this->assertCount(2, $pendingLicenses);
    }

    public function test_scope_active_returns_only_active_licenses(): void
    {
        License::factory()->count(2)->pending()->create();
        License::factory()->count(3)->active()->create();

        $activeLicenses = License::active()->get();

        $this->assertCount(3, $activeLicenses);
    }

    public function test_scope_expired_returns_only_expired_licenses(): void
    {
        License::factory()->count(2)->create(['expires_at' => now()->addMonth()]);
        License::factory()->count(3)->create(['expires_at' => now()->subDay()]);

        $expiredLicenses = License::expired()->get();

        $this->assertCount(3, $expiredLicenses);
    }
}
