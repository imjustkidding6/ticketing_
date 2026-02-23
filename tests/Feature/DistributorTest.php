<?php

namespace Tests\Feature;

use App\Models\Distributor;
use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DistributorTest extends TestCase
{
    use RefreshDatabase;

    public function test_distributor_can_be_created(): void
    {
        $distributor = Distributor::factory()->create([
            'name' => 'Test Distributor',
            'email' => 'test@distributor.com',
        ]);

        $this->assertDatabaseHas('distributors', [
            'name' => 'Test Distributor',
            'email' => 'test@distributor.com',
        ]);
    }

    public function test_slug_is_auto_generated_from_name(): void
    {
        $distributor = Distributor::create([
            'name' => 'Test Company Inc',
            'email' => 'test@example.com',
        ]);

        $this->assertEquals('test-company-inc', $distributor->slug);
    }

    public function test_api_key_is_auto_generated(): void
    {
        $distributor = Distributor::create([
            'name' => 'Test Company',
            'email' => 'test@example.com',
        ]);

        $this->assertNotNull($distributor->api_key);
        $this->assertStringStartsWith('dk_', $distributor->api_key);
    }

    public function test_api_key_can_be_set_manually(): void
    {
        $distributor = Distributor::factory()->create([
            'api_key' => 'dk_custom_key_12345',
        ]);

        $this->assertEquals('dk_custom_key_12345', $distributor->api_key);
    }

    public function test_scope_active_returns_only_active_distributors(): void
    {
        Distributor::factory()->count(2)->create(['is_active' => true]);
        Distributor::factory()->count(3)->inactive()->create();

        $activeDistributors = Distributor::active()->get();

        $this->assertCount(2, $activeDistributors);
    }

    public function test_generate_license_creates_license_with_correct_attributes(): void
    {
        $distributor = Distributor::factory()->create();
        $plan = Plan::factory()->start()->create();

        $license = $distributor->generateLicense($plan, [
            'seats' => 15,
            'expires_at' => now()->addMonths(6),
        ]);

        $this->assertEquals($distributor->id, $license->distributor_id);
        $this->assertEquals($plan->id, $license->plan_id);
        $this->assertEquals(15, $license->seats);
        $this->assertEquals('pending', $license->status);
        $this->assertNotNull($license->license_key);
    }

    public function test_generate_license_uses_plan_max_users_as_default_seats(): void
    {
        $distributor = Distributor::factory()->create();
        $plan = Plan::factory()->create(['max_users' => 20]);

        $license = $distributor->generateLicense($plan);

        $this->assertEquals(20, $license->seats);
    }

    public function test_active_licenses_returns_correct_count(): void
    {
        $distributor = Distributor::factory()->create();
        $plan = Plan::factory()->create();

        $distributor->licenses()->createMany([
            ['license_key' => 'KEY1-KEY1-KEY1-KEY1-KEY1', 'plan_id' => $plan->id, 'seats' => 5, 'status' => 'active', 'issued_at' => now(), 'expires_at' => now()->addYear()],
            ['license_key' => 'KEY2-KEY2-KEY2-KEY2-KEY2', 'plan_id' => $plan->id, 'seats' => 5, 'status' => 'active', 'issued_at' => now(), 'expires_at' => now()->addYear()],
            ['license_key' => 'KEY3-KEY3-KEY3-KEY3-KEY3', 'plan_id' => $plan->id, 'seats' => 5, 'status' => 'pending', 'issued_at' => now(), 'expires_at' => now()->addYear()],
        ]);

        $this->assertEquals(2, $distributor->activeLicenses());
    }

    public function test_distributor_has_many_licenses(): void
    {
        $distributor = Distributor::factory()->create();

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $distributor->licenses());
    }
}
