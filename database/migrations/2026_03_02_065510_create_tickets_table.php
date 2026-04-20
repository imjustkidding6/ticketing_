<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('ticket_number')->unique();
            $table->string('subject');
            $table->longText('description');
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->enum('status', ['open', 'assigned', 'in_progress', 'on_hold', 'closed', 'cancelled'])->default('open');

            // Relationships
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('ticket_categories')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();

            // SLA
            $table->unsignedBigInteger('sla_policy_id')->nullable();
            $table->timestamp('response_due_at')->nullable();
            $table->timestamp('resolution_due_at')->nullable();
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('closed_at')->nullable();

            // Billing (Business+)
            $table->boolean('is_billable')->default(false);
            $table->decimal('billable_amount', 10, 2)->nullable();
            $table->string('billable_description')->nullable();
            $table->timestamp('billed_at')->nullable();

            // Parent/Child
            $table->foreignId('parent_ticket_id')->nullable()->constrained('tickets')->nullOnDelete();

            // Merging (Enterprise)
            $table->boolean('is_merged')->default(false);
            $table->unsignedBigInteger('merged_into_ticket_id')->nullable();
            $table->timestamp('merged_at')->nullable();

            // Reopening (Enterprise)
            $table->unsignedInteger('reopened_count')->default(0);

            // Hold tracking
            $table->timestamp('hold_started_at')->nullable();
            $table->unsignedInteger('total_hold_time_minutes')->default(0);

            // Escalation (Enterprise)
            $table->enum('current_tier', ['tier_1', 'tier_2', 'tier_3'])->default('tier_1');
            $table->unsignedInteger('escalation_count')->default(0);
            $table->timestamp('last_escalated_at')->nullable();

            // Spam (Business+)
            $table->boolean('is_spam')->default(false);
            $table->timestamp('marked_spam_at')->nullable();
            $table->foreignId('marked_spam_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('spam_reason')->nullable();

            // Extra
            $table->json('metadata')->nullable();
            $table->json('attachments')->nullable();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('deletion_reason')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['tenant_id', 'status', 'priority']);
            $table->index(['tenant_id', 'client_id', 'status']);
            $table->index(['tenant_id', 'assigned_to', 'status']);
            $table->index(['tenant_id', 'department_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
