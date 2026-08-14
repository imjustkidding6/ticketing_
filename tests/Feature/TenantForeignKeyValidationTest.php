<?php

namespace Tests\Feature;

use App\Enums\PlanFeature;
use App\Models\Client;
use App\Models\Department;
use App\Models\License;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantForeignKeyValidationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Tenant $otherTenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::factory()->create([
            'slug' => 'start',
            'features' => PlanFeature::forPlan('start'),
        ]);

        $this->tenant = Tenant::factory()->create([
            'license_id' => License::factory()->active()->forPlan($plan)->create()->id,
        ]);
        $this->otherTenant = Tenant::factory()->create([
            'license_id' => License::factory()->active()->forPlan($plan)->create()->id,
        ]);

        $this->owner = User::factory()->create();
        $this->tenant->addUser($this->owner, 'owner');

        $this->actingAs($this->owner)
            ->withTenant($this->tenant)
            ->withSession(['current_tenant_id' => $this->tenant->id]);
    }

    public function test_ticket_accepts_same_tenant_foreign_keys(): void
    {
        [$client, $department, $category, $product, $assignee] = $this->ticketRelations($this->tenant);

        $this->post($this->tenantUrl('/tickets'), [
            'client_id' => $client->id,
            'department_id' => $department->id,
            'category_id' => $category->id,
            'product_ids' => [$product->id],
            'assigned_to' => $assignee->id,
            'subject' => 'Same tenant ticket',
            'description' => 'All related IDs belong to the active tenant.',
            'priority' => 'medium',
        ])->assertRedirect();

        $ticket = Ticket::where('subject', 'Same tenant ticket')->firstOrFail();
        $this->assertSame($client->id, $ticket->client_id);
        $this->assertSame($department->id, $ticket->department_id);
        $this->assertSame($category->id, $ticket->category_id);
        $this->assertSame($assignee->id, $ticket->assigned_to);
        $this->assertTrue($ticket->products()->whereKey($product->id)->exists());
    }

    public function test_ticket_rejects_every_cross_tenant_foreign_key_without_persisting(): void
    {
        [$client, $department, $category, $product, $assignee] = $this->ticketRelations($this->tenant);
        [$otherClient, $otherDepartment, $otherCategory, $otherProduct, $otherAssignee] = $this->ticketRelations($this->otherTenant);

        $valid = [
            'client_id' => $client->id,
            'department_id' => $department->id,
            'category_id' => $category->id,
            'product_ids' => [$product->id],
            'assigned_to' => $assignee->id,
            'subject' => 'Must never persist',
            'description' => 'Cross-tenant association attempt.',
            'priority' => 'medium',
        ];

        foreach ([
            'client_id' => $otherClient->id,
            'department_id' => $otherDepartment->id,
            'category_id' => $otherCategory->id,
            'product_ids.0' => $otherProduct->id,
            'assigned_to' => $otherAssignee->id,
        ] as $field => $foreignId) {
            $payload = $valid;
            data_set($payload, $field, $foreignId);

            $this->from($this->tenantUrl('/tickets/create'))
                ->post($this->tenantUrl('/tickets'), $payload)
                ->assertRedirect($this->tenantUrl('/tickets/create'))
                ->assertSessionHasErrors($field);

            $this->assertDatabaseMissing('tickets', ['subject' => 'Must never persist']);
        }
    }

    public function test_category_rejects_a_department_from_another_tenant(): void
    {
        $foreignDepartment = Department::factory()->create(['tenant_id' => $this->otherTenant->id]);

        $this->post($this->tenantUrl('/categories'), [
            'department_id' => $foreignDepartment->id,
            'name' => 'Cross tenant category',
            'color' => '#123456',
            'is_active' => true,
        ])->assertSessionHasErrors('department_id');

        $this->assertDatabaseMissing('ticket_categories', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Cross tenant category',
        ]);
    }

    public function test_task_rejects_an_assignee_from_another_tenant(): void
    {
        $ticket = Ticket::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->owner->id,
        ]);
        $foreignAssignee = User::factory()->create();
        $this->otherTenant->addUser($foreignAssignee, 'member');

        $this->post($this->tenantUrl("/tickets/{$ticket->id}/tasks"), [
            'description' => 'Cross tenant assigned task',
            'assigned_to' => $foreignAssignee->id,
        ])->assertSessionHasErrors('assigned_to');

        $this->assertDatabaseMissing('ticket_tasks', [
            'ticket_id' => $ticket->id,
            'description' => 'Cross tenant assigned task',
        ]);
    }

    public function test_ticket_accepts_nullable_relations_and_rejects_nonexistent_or_nonmember_users(): void
    {
        $client = Client::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->post($this->tenantUrl('/tickets'), [
            'client_id' => $client->id,
            'category_id' => null,
            'department_id' => null,
            'assigned_to' => null,
            'subject' => 'Nullable relations ticket',
            'description' => 'Optional tenant relationships are omitted.',
            'priority' => 'low',
        ])->assertRedirect();

        $this->assertDatabaseHas('tickets', [
            'tenant_id' => $this->tenant->id,
            'subject' => 'Nullable relations ticket',
            'category_id' => null,
            'department_id' => null,
            'assigned_to' => null,
        ]);

        $nonmember = User::factory()->create();
        foreach ([$nonmember->id, PHP_INT_MAX] as $invalidUserId) {
            $this->post($this->tenantUrl('/tickets'), [
                'client_id' => $client->id,
                'assigned_to' => $invalidUserId,
                'subject' => 'Invalid assignee must not persist',
                'description' => 'Assignee is not an active tenant member.',
                'priority' => 'low',
            ])->assertSessionHasErrors('assigned_to');
        }

        $this->assertDatabaseMissing('tickets', ['subject' => 'Invalid assignee must not persist']);
    }

    /**
     * @return array{Client, Department, TicketCategory, Product, User}
     */
    private function ticketRelations(Tenant $tenant): array
    {
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $department = Department::factory()->create(['tenant_id' => $tenant->id]);
        $category = TicketCategory::factory()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $department->id,
        ]);
        $product = Product::factory()->create([
            'tenant_id' => $tenant->id,
            'category_id' => $category->id,
        ]);
        $assignee = User::factory()->create();
        $tenant->addUser($assignee, 'member');

        return [$client, $department, $category, $product, $assignee];
    }
}
