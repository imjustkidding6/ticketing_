# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Build & Development Commands

```bash
# Start/stop Docker environment
vendor/bin/sail up -d     # Start containers
vendor/bin/sail stop      # Stop containers

# Local development
vendor/bin/sail composer run dev   # Start all dev servers (web, queue, logs, vite)
vendor/bin/sail npm run dev        # Frontend HMR
vendor/bin/sail npm run build      # Production frontend build
vendor/bin/sail artisan migrate    # Run migrations
vendor/bin/sail artisan db:seed    # Run seeders

# Testing
vendor/bin/sail artisan test --compact                          # Run all tests
vendor/bin/sail artisan test --compact --filter=TicketController # Run specific test class
vendor/bin/sail artisan test --compact --filter=test_method_name # Run specific test method
npx playwright test --reporter=list                              # Run E2E browser tests

# Code formatting
vendor/bin/sail bin pint --dirty --format agent  # Format changed files

# Docker (alternative)
make up / make down / make test / make migrate / make fresh
```

## Architecture

Multi-tenant SaaS ticketing system. Laravel 12, Tailwind CSS v4, Alpine.js, Blade templates.

### Routing Structure

Routes are registered in `bootstrap/app.php`:
- `routes/web.php` — Auth, admin panel, home, profile, tenant switching
- `routes/tenant.php` — All tenant-scoped routes under `/{slug}/` prefix with regex `[a-z0-9][a-z0-9\-]*[a-z0-9]`

Tenant routes resolve the tenant from the URL slug via `EnsureTenantSession` middleware, which sets `session('current_tenant_id')` and syncs the Spatie Permission team context.

### Multi-Tenancy (CRITICAL)

Data isolation between tenants is a security requirement.

**Auto-scoped models** use the `BelongsToTenant` trait (`app/Models/Traits/BelongsToTenant.php`) which applies `TenantScope` globally. This covers: Ticket, Client, Department, Product, TicketCategory, SlaPolicy, etc. These are safe by default.

**The User model is NOT auto-scoped.** It uses a many-to-many `tenant_user` pivot. Every User query in tenant context MUST include:

```php
// CORRECT
User::query()
    ->whereHas('tenants', fn ($q) => $q->where('tenant_id', session('current_tenant_id')))
    ->get();

// WRONG — leaks users across tenants
User::all();
User::find($id);
```

**Public portal controllers** (ClientPortalController, KbPortalController) operate outside the tenant session. They must use `withoutGlobalScopes()` and manually filter by `tenant_id`:
```php
Ticket::withoutGlobalScopes()->where('tenant_id', $tenant->id)->get();
```

**Admin controllers** (`app/Http/Controllers/Admin/`) intentionally operate across tenants.

### Feature Gating (3-Tier Plans)

Features are gated via `PlanFeature` enum (`app/Enums/PlanFeature.php`):
- **Starter** — No gated features (core functionality only, no public portal)
- **Business** — 10 features: `audit_logs`, `billing`, `spam_management`, `service_reports`, `attachments`, `agent_schedule`, `sla_management`, `sla_report`, `email_notifications`, `detailed_reporting`
- **Enterprise** — All Business + 8: `ticket_merging`, `ticket_reopening`, `custom_roles`, `department_management`, `agent_escalation`, `client_comments`, `knowledge_base`, `canned_responses`

Enforcement points:
- **Routes:** `->middleware('feature:feature_name')` — returns 403 for missing features
- **Views:** `@if(app(PlanService::class)->currentTenantHasFeature(PlanFeature::FeatureName))`
- **Plan-level gates:** Public portal pages (`/{slug}/`, submit-ticket, track-ticket) are Business+ only. Starter tenants get 404.
- **Cache:** `PlanService` caches features for 300s. Call `PlanService::clearCache($tenant)` after plan changes.

### Permissions

Uses Spatie Permission with `tenant_id` as `team_foreign_key` (configured in `config/permission.php`).

Three default roles per tenant: `admin` (16 permissions), `manager` (14), `agent` (5). Seeded via `TenantRoleService::setupDefaultRoles()`.

Controllers enforce permissions via `$this->checkPermission('permission name')` (defined in base `Controller.php`). Owners bypass all permission checks.

### Middleware Stack

| Alias | Class | Purpose |
|-------|-------|---------|
| `tenant` | `EnsureTenantSession` | Resolves tenant from URL slug, sets session, syncs Spatie team |
| `feature` | `CheckPlanFeature` | Validates tenant plan has required feature(s) |
| `admin` | `AdminMiddleware` | Requires `is_admin` flag on user |
| `portal` | `EnsureClientPortalAccess` | Validates authenticated client belongs to tenant |

### Key Services

| Service | Purpose |
|---------|---------|
| `PlanService` | Feature access checks with caching |
| `TicketService` | Ticket CRUD, notifications, `addHistory()` for audit logging |
| `EscalationService` | Tier-based ticket escalation with validation |
| `TicketMergeService` | Merge/unmerge ticket operations |
| `TenantRoleService` | Default role/permission setup, role sync |
| `TenantMailService` | Per-tenant SMTP configuration |
| `ReportService` | Report data aggregation and CSV exports |

### Testing

- **PHPUnit:** Uses MySQL (not SQLite). Test database: `ticketing_test` on `127.0.0.1:3306`. Tests use `RefreshDatabase` trait.
- **Playwright E2E:** Config in `playwright.config.ts`. Tests in `tests/e2e/`. Runs with `headless: false` and `slowMo: 500` for visibility.
- **Test helpers** in `tests/TestCase.php`: `withTenant($tenant)` sets test context, `tenantUrl($path)` generates tenant-prefixed URLs.
- **Common test pattern** (used across all feature tests):
```php
private function createBusinessTenant(): Tenant {
    $plan = Plan::factory()->create(['slug' => 'business', 'features' => PlanFeature::forPlan('business')]);
    $license = License::factory()->active()->forPlan($plan)->create();
    return Tenant::factory()->create(['license_id' => $license->id]);
}

private function setupTenantContext(Tenant $tenant): User {
    $user = User::factory()->create();
    $tenant->addUser($user, 'member');
    $this->actingAs($user)->withTenant($tenant)->withSession(['current_tenant_id' => $tenant->id]);
    return $user;
}
```
- **17 factories** available in `database/factories/` for all major models.

### Public Portal

Public-facing pages live under `/{slug}/` (not `/portal/`). The `/portal/` route prefix was removed.

| Route | Controller Method | Plan |
|-------|-------------------|------|
| `/{slug}/` | `publicLanding` | Business+ |
| `/{slug}/submit-ticket` | `publicSubmitForm` / `publicSubmitStore` | Business+ |
| `/{slug}/track-ticket` | `publicTrackForm` | Business+ |
| `/{slug}/track-ticket/{token}` | `publicTrackByToken` | Business+ |
| `/{slug}/track-ticket/{token}/reply` | `publicReply` | Enterprise (client_comments) |
| `/{slug}/kb/*` | `KbPortalController` | Enterprise (knowledge_base) |

Starter tenants return 404 for all public portal URLs (enforced via `abortIfStarter()` in `ClientPortalController`).

### Escalation System

Agent tiering (Enterprise only): 3 tiers (tier_1, tier_2, tier_3). Escalation enforced:
- Can only escalate **up** (not same or lower tier)
- Assigned agent must have `support_tier` >= target tier
- Owner bypasses tier restrictions
- Agent dropdown in UI filters by tier using JS
<!-- Laravel Boost guidelines are auto-injected at runtime by the Laravel Boost MCP server. Do not duplicate them here. -->
