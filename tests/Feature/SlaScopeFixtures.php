<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PlanFeature;
use App\Models\Client;
use App\Models\License;
use App\Models\Plan;
use App\Models\SlaPolicy;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Shared fixtures for the /admin/sla scope tests.
 *
 * Kept in one place because all four suites need the same shape — two workspaces,
 * each with its own SLA policy and its own closed ticket — and the whole point of
 * these tests is that the two workspaces are built identically.
 */
trait SlaScopeFixtures
{
    private function businessTenant(string $name): Tenant
    {
        // plans.slug is unique, so every tenant here shares one plan record.
        $plan = Plan::query()->firstWhere('slug', 'business')
            ?? Plan::factory()->create([
                'slug' => 'business',
                'features' => PlanFeature::forPlan('business'),
            ]);

        return Tenant::factory()->create([
            'name' => $name,
            'license_id' => License::factory()->active()->forPlan($plan)->create()->id,
        ]);
    }

    private function catchAllPolicy(Tenant $tenant, string $name, int $responseHours = 4, int $resolutionHours = 24): SlaPolicy
    {
        return SlaPolicy::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'priority' => null,
            'client_tier' => null,
            'response_time_hours' => $responseHours,
            'resolution_time_hours' => $resolutionHours,
            'is_active' => true,
        ]);
    }

    /**
     * An open ticket whose resolution falls due inside the near-breach window.
     */
    private function nearBreachTicket(Tenant $tenant, string $clientName): Ticket
    {
        return Ticket::factory()->create([
            'tenant_id' => $tenant->id,
            'client_id' => Client::factory()->create([
                'tenant_id' => $tenant->id,
                'name' => $clientName,
            ])->id,
            'category_id' => null,
            'department_id' => null,
            'created_by' => User::factory()->create()->id,
            'status' => 'open',
            'priority' => 'high',
            'first_response_at' => Carbon::now('UTC')->subHour(),
            'resolution_due_at' => Carbon::now('UTC')->addHours(2),
        ]);
    }

    /**
     * A closed ticket inside the admin page's 30-day UTC window.
     *
     * $responseHours / $resolutionHours are the actuals; whether they beat the
     * policy's targets is what makes the row a met or a missed.
     */
    private function closedTicket(Tenant $tenant, ?SlaPolicy $policy, float $responseHours, float $resolutionHours, array $overrides = []): Ticket
    {
        $closedAt = Carbon::now('UTC')->subDays(2);
        $createdAt = $closedAt->copy()->subHours((int) ceil($resolutionHours));

        return Ticket::factory()->create(array_merge([
            'tenant_id' => $tenant->id,
            'client_id' => Client::factory()->create(['tenant_id' => $tenant->id])->id,
            'category_id' => null,
            'department_id' => null,
            'created_by' => User::factory()->create()->id,
            'sla_policy_id' => $policy?->id,
            'status' => 'closed',
            'priority' => 'medium',
            'created_at' => $createdAt,
            'first_response_at' => $createdAt->copy()->addHours($responseHours),
            'closed_at' => $closedAt,
        ], $overrides));
    }
}
