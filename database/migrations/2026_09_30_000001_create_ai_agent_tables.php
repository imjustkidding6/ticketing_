<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Department -> AI agent -> skill configuration. Purely additive: four new tables,
 * nothing existing is touched and nothing reads them yet. All four are per-tenant
 * (human decision 2026-09-30 Q1), seeded per tenant from one shared PHP catalogue
 * the way DepartmentSeeder::DEFAULTS already is.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // The join key for P5's recommendAgents(): a detected label is resolved to a
            // row by slug within this tenant and nothing else. Unique per tenant, not
            // globally -- two workspaces both have a "mysql" skill and neither owns it.
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('skill_category')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'is_active']);
            $table->index(['tenant_id', 'skill_category']);
        });

        Schema::create('ai_agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            // Model, temperature, max_tokens and enabled_tools stay on ai_profiles, which
            // AiProfileService and the admin console already own (spec SS38: do not
            // duplicate functionality). Nullable because ai_profiles is global platform
            // config a tenant may have no opinion about, and because nothing executes
            // an agent in this feature, so an agent without a profile is still valid.
            $table->foreignId('ai_profile_id')->nullable()->constrained('ai_profiles')->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            // One of AiAgent::ROLE_TYPES. varchar, not a MySQL enum: the tickets.status
            // change of 2026-09-18 established that the PHP constant is the enumeration
            // and adding a value must not need a migration.
            $table->string('role_type');
            // NULL means "use AiAgent::DEFAULT_SYSTEM_PROMPT" (spec SS28). Storing the
            // default text in every row would freeze it at seed time.
            $table->longText('system_prompt')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->integer('priority')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'is_active']);
            $table->index(['tenant_id', 'role_type']);
        });

        Schema::create('ai_agent_skill', function (Blueprint $table) {
            $table->id();
            // tenant_id on the pivot as well as on both parents. Precedent:
            // sla_calendar_hours and ticket_histories both carry one, and
            // client_agent_assignments not carrying one was called a mistake in W1.1.
            // The reason is not the belongsToMany read path (which is already scoped
            // through the parent) -- it is that a pivot gets queried directly for
            // counts, reports and exports, and a direct query on an unscoped table is
            // the isolation bug class this repo keeps finding.
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_agent_id')->constrained()->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained()->cascadeOnDelete();
            $table->string('proficiency')->default('intermediate'); // AiAgentSkill::PROFICIENCIES
            $table->unsignedSmallInteger('weight')->nullable();
            $table->boolean('is_required')->default(false);
            $table->timestamps();

            // Spec SS34: prevent duplicate pivots. (ai_agent_id, skill_id) is enough --
            // the agent is already tenant-owned, so tenant_id would be redundant here.
            $table->unique(['ai_agent_id', 'skill_id']);
            $table->index(['tenant_id', 'skill_id']);
        });

        Schema::create('department_ai_agent', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            // cascadeOnDelete satisfies criterion 16: deleting a department drops its
            // assignments and leaves the agents themselves alone.
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_agent_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('support'); // DepartmentAiAgent::ROLES
            $table->integer('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('auto_invoke')->default(false);
            $table->timestamps();

            $table->unique(['department_id', 'ai_agent_id']);
            // The exact shape P5's getDepartmentAgents()/getAutoInvokeAgents() read.
            $table->index(['department_id', 'is_active', 'priority'], 'dept_ai_agent_selection_idx');
            $table->index(['tenant_id', 'auto_invoke']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('department_ai_agent');
        Schema::dropIfExists('ai_agent_skill');
        Schema::dropIfExists('ai_agents');
        Schema::dropIfExists('skills');
    }
};
