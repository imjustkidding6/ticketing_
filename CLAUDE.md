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

# Docker (alternative, bypasses Sail and calls docker-compose directly)
make up / make down / make test / make migrate / make fresh
```

Prefer Sail commands. The `Makefile` targets point at the same containers but call `docker-compose` directly and do not go through Sail's entrypoint.

### Laravel Boost (MCP)

The Laravel Boost MCP server is configured for this project. When you need to introspect the running app, prefer its tools over shelling out: `database-schema` / `database-query` for DB inspection, `tinker` for evaluating PHP in the app context, `list-routes` / `list-artisan-commands` for route/command discovery, `read-log-entries` for recent logs, and `search-docs` for version-matched Laravel/Spatie/Pint docs.

## Architecture

Multi-tenant SaaS ticketing system. Laravel 12, Tailwind CSS v4, Alpine.js, Blade templates.

### Routing Structure

Routes are registered in `bootstrap/app.php`. The `then:` closure in `withRouting()` mounts `routes/tenant.php` under a dynamic `{slug}` prefix with regex `[a-z0-9][a-z0-9\-]*[a-z0-9]`:
- `routes/web.php` — Auth, admin panel, home, profile, tenant switching
- `routes/tenant.php` — All tenant-scoped routes (dashboard, tickets, clients, settings, reports, public portal)
- `routes/api.php` — Token-authenticated REST API under `/api/v1/` (see **REST API (v1)**). Loaded via the `api:` key in `withRouting()`; the tenant is resolved from the bearer token, not the URL slug.

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

**Validation rules are not tenant-scoped by default.** `exists:clients,id` queries the raw table — validation runs before any model, so the global scope never sees the value and an id from another workspace is accepted. Use the `ScopesRulesToTenant` trait (`app/Http/Requests/Concerns/`) for every foreign key accepted from a request:

```php
'client_id'   => ['required', $this->tenantExists('clients')],
'assigned_to' => array_merge(['nullable'], $this->tenantMemberRules()),  // users + tenant_user pivot
```

**Tenant mail config is global runtime state.** `TenantMailService::configure()` mutates `config('mail')`, so every path that sets it must unset it — otherwise a worker handling several tenants sends one tenant's mail through another's SMTP server and From address. Prefer `TenantMailService::usingTenant($tenantId, fn () => ...)`, which restores defaults in a `finally`. `configure()` with no tenant or no configured SMTP resets rather than leaving the previous tenant in place.

**Admin controllers** (`app/Http/Controllers/Admin/`) intentionally operate across tenants.

**Isolation guardrails (CI-enforced by `TenantIsolationGuardrailsTest`):** it inspected Blade only for a long time, which is why an unscoped admin query in PHP went unnoticed. It now also scans `app/` (statement-level, joined on `;`, with a justified `ALLOWED_BYPASSES` list) and enforces a second-order rule that a static check *can* express: **a file outside `App\Http\Controllers\Admin\` that branches on `routeIs('admin.*')` has self-declared as dual-surface and must be registered in `DUAL_SURFACE` with behaviour tests covering both halves** (`DualSurfaceScopeTest`). A static check cannot decide whether a given `withoutGlobalScopes()` is legitimate — surface is not a property of a file. Views outside `resources/views/admin/` must not `@extends('layouts.admin')` (give admin screens their own view under `admin/` — never repurpose a tenant view), and any `withoutGlobalScopes()` in a non-admin view must chain `->where('tenant_id', ...)` on the same line. A controller serving both surfaces should pick the view by route, e.g. `view($request->routeIs('admin.*') ? 'admin.sla.index' : 'sla.index', ...)` (see `SlaPolicyController`).

### Feature Gating (3-Tier Plans)

Features are gated via `PlanFeature` enum (`app/Enums/PlanFeature.php`):
- **Starter** — No gated features (core functionality only, no public portal)
- **Business** — 10 features: `audit_logs`, `billing`, `spam_management`, `service_reports`, `attachments`, `agent_schedule`, `sla_management`, `sla_report`, `email_notifications`, `detailed_reporting`
- **Enterprise** — All Business + 9: `ticket_merging`, `ticket_reopening`, `custom_roles`, `department_management`, `agent_escalation`, `client_comments`, `knowledge_base`, `canned_responses`, `ai_chatbot`

Enforcement points:
- **Routes:** `->middleware('feature:feature_name')` — returns 403 for missing features
- **Views:** `@if(app(PlanService::class)->currentTenantHasFeature(PlanFeature::FeatureName))`
- **Plan-level gates:** Public portal pages (`/{slug}/`, submit-ticket, track-ticket) are Business+ only. Starter tenants get 404.
- **Cache:** `PlanService` caches features for 300s. Call `PlanService::clearCache($tenant)` after plan changes.

**Gotcha:** `PlanFeature::forPlan()` matches the plan slug `'start'`, not `'starter'` — `forPlan('starter')` hits the `default` arm and silently returns `[]`. Seeders/tests must pass `'start'` (or `'business'`/`'enterprise'`).

### Permissions

Uses Spatie Permission with `tenant_id` as `team_foreign_key` (configured in `config/permission.php`).

`TenantRoleService` is the single source of truth for both the permission catalogue and the role matrix:

- **`PERMISSION_GROUPS`** — every permission, grouped by area (Tickets, Ticket collaboration, Clients, Catalog, Departments, SLA, Knowledge base, Canned responses, Reports, Operations, People, Configuration, AI). 76 permissions, named `<action> <resource>` (`view clients`, `create clients`, …). Use `TenantRoleService::allPermissions()` for the flat list; the group keys drive the headings in the custom-role editor. **Every permission must be enforced somewhere** — `RolePermissionMatrixTest::test_no_permission_is_defined_without_being_enforced` greps `app/`, `resources/views/` and `routes/` and fails on any that isn't. Don't add a permission you aren't going to check.
- **`BASE_ROLE_PERMISSIONS`** — seven seeded roles, in decreasing access: `admin`, `manager`, `supervisor`, `senior agent`, `agent`, `viewer`, `billing`. Seeded per tenant via `setupDefaultRoles()`. `ROLE_DESCRIPTIONS` supplies the blurbs shown in the member/role pickers.
- **`FEATURE_PERMISSIONS`** — extra grants layered on per plan feature (`knowledge_base`, `canned_responses`, `ai_chatbot`, `agent_escalation`, `department_management`, …), so a Starter tenant's admin never receives Enterprise-only permissions.
- **`LEGACY_PERMISSION_MAP`** — maps each pre-expansion name (`manage clients`, `manage settings`, …) onto the granular ones that replaced it. `php artisan roles:migrate-permissions [--dry-run] [--tenant=ID]` re-seeds default roles and expands tenant **custom** roles through this map. Run it after deploying; without it, roles still holding old names lose access.

Notable deliberate grants: `agent` has **no** `view clients` (agents work tickets; they don't browse the client directory), and `viewer` holds view-only permissions exclusively — `RolePermissionMatrixTest` asserts both.

Controllers enforce permissions via `$this->checkPermission('permission name')` (aborts 403) or `$this->userCan('…')` (returns bool — use it to shape a response rather than deny it, e.g. hiding a section or widening a query). Both live in base `Controller.php` and share two bypasses: workspace **owners** bypass every check, and **platform admins** (`is_admin`) bypass on `admin.*` routes — they hold no tenant role, and controllers such as `SlaPolicyController` serve both the tenant and admin surfaces.

`view all tickets` is the one permission that decides **scope, not access**: the `ScopesTicketsToDepartments` trait (`app/Http/Controllers/Concerns/`) uses it to lift the department restriction, so a role without it sees a narrower list rather than a 403.

**Department scoping fails closed.** A user with no departments gets only the tickets they are assigned to or created — not everything. Any controller action resolving a `{ticket}` route parameter must call `authorizeTicketAccess($ticket)` from that trait; applying the check to only some actions leaves the others open to a same-tenant IDOR (a restricted agent refused the show page but able to open `/edit`).

When adding a permission: add it to a group in `PERMISSION_GROUPS`, grant it in the role matrix (or `FEATURE_PERMISSIONS` if plan-gated), enforce it in the controller, and gate the sidebar entry with `$sidebarCan('…')` in `layouts/app.blade.php`.

### Middleware Stack

| Alias | Class | Purpose |
|-------|-------|---------|
| `tenant` | `EnsureTenantSession` | Resolves tenant from URL slug, sets session, syncs Spatie team |
| `feature` | `CheckPlanFeature` | Validates tenant plan has required feature(s) |
| `admin` | `AdminMiddleware` | Requires `is_admin` flag on user |
| `portal` | `EnsureClientPortalAccess` | Validates authenticated client belongs to tenant |
| `api-token` | `AuthenticateApiToken` | Resolves tenant from bearer token, sets session + Spatie team (no URL slug) |

`SetTenantUrlDefaults` is appended to the `web` group (not aliased). It injects the current tenant slug as a default URL parameter so `route(...)` calls inside tenant pages don't need `slug` passed explicitly.

### Key Services (`app/Services/`)

| Service | Purpose |
|---------|---------|
| `PlanService` | Feature access checks with 300s cache |
| `TicketService` | Ticket CRUD, notifications, `addHistory()` for audit logging |
| `TicketWorkflowService` | State transitions and workflow rules for tickets |
| `TicketMergeService` | Merge/unmerge ticket operations |
| `EscalationService` | Tier-based ticket escalation with validation |
| `SlaService` | SLA policy evaluation and breach tracking |
| `AgentPerformanceService` | Agent metrics / performance aggregation |
| `ReportService` | Report data aggregation and CSV exports |
| `ServiceReportService` | Service-report generation (distinct from ReportService) |
| `TenantRoleService` | Default role/permission setup, role sync |
| `OnboardingService` | Drives the post-registration guided checklist (customize workspace, review departments, create categories, invite member, first ticket, explore reports) |
| `ActivityLogger` | Static `log()` helper for writing manual `ActivityLog` entries (complements the automatic `LogsActivity` trait); resolves tenant from the subject or current `TenantScope` |
| `TenantMailService` | Per-tenant SMTP configuration |
| `TenantUrlHelper` | Helper for building tenant-prefixed URLs outside request context |
| `OpenAiService` | Thin HTTP client for OpenAI (`chat()`, `webSearch()`, `embed()`); no tenant logic |
| `AiAssistantService` | Tenant-aware AI orchestrator: tool-calling loop, copilot one-shots, ticket-draft polish, embeddings learning (see **AI Assistant**) |
| `PageContextResolver` | Turns the URL path the agent is viewing into a description (+ ticket/client details) for the assistant |
| `GitHubService` | Thin HTTP client for the GitHub REST API (`createIssue()`, `getPull()`) used by the AI Programmer loop (see **AI Bug Fixing**) |

Ancillary namespaces:
- `app/Notifications/` — Per-event notifications (`TicketCreated`, `TicketAssigned`, `TicketStatusChanged`, `SlaBreachWarning`, client variants, `SystemAnnouncement`). Dispatched by services rather than controllers; respect the `email_notifications` feature where relevant.
- `app/Support/` — Framework-agnostic helpers (`ZonedTime` + `TenantTime`/`AdminTime`, `SystemGuide`, `FileParser`). Not services; no side effects.

### Activity Logging

Models opt into audit logging via the `LogsActivity` trait (`app/Models/Traits/LogsActivity.php`). It auto-captures `created`, `updated`, `deleted`, `restored`, `force_deleted` events and persists field-level diffs to the `activity_logs` table. Models define `$activityLogIgnore` to suppress noisy fields (e.g., Ticket ignores `tracking_token`, `sla_breach_notified_at`). Timestamps, `password`, and `remember_token` are always ignored. Gated by the `audit_logs` plan feature (Business+).

### Single Active Session

`User::purgeOtherSessions()` is called on every successful login (password and Google OAuth) after `session()->regenerate()`. It deletes all rows from the `sessions` table for the user except the current session ID. Latest login wins — previous sessions are immediately invalidated. Depends on `SESSION_DRIVER=database`.

### Scheduled Commands

Defined in `routes/console.php`:
- **`SendSlaBreachWarnings`** — every 15 minutes. Checks tickets with overdue response/resolution times and notifies assigned agents. Only fires if tenant has `email_notifications` feature enabled.
- **`CheckLicenseExpirations`** — daily at 02:00 UTC. Multi-stage notification lifecycle: 4–7 days = approaching, 0–3 days = imminent, then grace period tracking, then final status flip to `EXPIRED`. Uses warning flags on the license to avoid duplicate sends.
- **`ai:embed:tickets {--tenant=} {--force} {--limit=}`** — every 15 minutes, `withoutOverlapping()`. Embeds closed tickets without a `solution_embedded_at` so the AI assistant can learn from past resolutions. No-op if OpenAI isn't configured. Siblings `ai:embed:knowledge` (daily 03:00), `ai:embed:snippets` (hourly) and `ai:embed:all`; `ai:maintenance --mode=hourly|daily|weekly` prunes usage logs and resets metric counters. See **AI Assistant**.

### Ticket Status Lifecycle (CRITICAL)

Statuses are `open`, `assigned`, `in_progress`, `on_hold`, `pending`, `closed`, `cancelled`, enumerated on the model as **`Ticket::STATUSES`** (plus `OPEN_STATUSES`, `PAUSED_STATUSES`, `CLOSED_STATUSES`). Use those constants — the literal array used to be copied into a dozen files, and a status added without updating all of them rolls out silently half-applied.

There is **no `resolved` status** — resolution and closure are the same event. This diverges from Zendesk's solved/closed split deliberately: their closed tickets are locked, while ours can be reopened (`closed → open`), and `reopened_count` / `first_closed_at` give a reopen-rate metric the split has to reconstruct. `pending` means *waiting on the customer* and is what the solved/closed split is usually reached for.

`tickets.status` is a **varchar, not a MySQL ENUM** (changed 2026-09-18). The database will therefore store any string, so `Ticket::STATUSES` is the only enumeration and every entry point validates against it — `TicketService::changeStatus()`, `UpdateTicketRequest`, the status endpoint, the API filter and the Jude connector's `UpdateStatus` tool. Adding a status is now a code change with no migration; removing one still needs a data plan.

The allowed moves live in **`Ticket::TRANSITIONS`**, with `allowedTransitions()` and `canTransitionTo()` on the model. The map is enforced inside **`TicketService::changeStatus()`**, which throws `InvalidStatusTransitionException` (an `InvalidArgumentException`, so existing catch blocks handle it). It is enforced there — rather than in a controller — so that every entry point obeys it: the web controller, the edit form, the REST API and the assistant tools. **Never bypass `changeStatus()` to write `status` directly.**

`TicketWorkflowService` is the higher-level wrapper: `updateStatus()` adds a 5-second double-submit collapse (returns `false`, distinct from the exception raised for an illegal move) and `reopenTicket()` owns the reopen rules.

**Reopening** must go through `TicketWorkflowService::reopenTicket()`, which refuses anything not `closed`/`cancelled`, increments `reopened_count`, stamps `last_reopened_at`/`last_reopen_reason` and writes the history entry, all in a transaction. `TicketController::changeStatus()` routes a `closed → open` move there too, so both the Reopen button and the status dropdown behave identically. Leaving `closed` by any route clears `closed_at` — an open ticket carrying a closure timestamp is counted as resolved by every `closed_at`-keyed report.

**Merging** rules live in `TicketMergeService::guardMerge()`: no self-merge, no merging a ticket that is already merged, no merging *into* an already-merged ticket (which would build a chain the mother can't see). Merge moves tasks and comments to the target and records what it moved — plus the pre-merge status — in `tickets.merge_metadata`, so `unmerge()` can move exactly those rows back and restore the original status rather than force-opening the ticket.

### Ticket Hold Time & SLA

Hold time is banked **inline in `TicketService::changeStatus()`**, which closes the running segment whenever the status moves off a `Ticket::PAUSED_STATUSES` value, adds the elapsed minutes to `total_hold_time_minutes`, clears `hold_started_at` and calls `SlaService::extendForHold()` to move the deadline. That is the only pause mechanism — the old `Ticket::startHold()` / `endHold()` pair was **removed**, because nothing called it and it banked minutes *without* moving the deadline, so it would have disagreed with the breach scheduler. Do not add a second one.

`getTotalHoldTimeMinutes()` adds the *currently running* segment (`hold_started_at → now()`) to the banked total, but ignores it on a `closed`/`cancelled` ticket. Without that guard a ticket reaching a terminal state with `hold_started_at` still set has a resolution time that shrinks every day — a historical figure that moves. `changeStatus()` never leaves the field set; any path writing `status` directly does (the Jude connector's `UpdateStatus` tool is one, see the connector caveat below), which is one more reason nothing may bypass the service.

Hold time is excluded from SLA calculations: `getEffectiveResolutionTimeHours()` and `getEffectiveResponseTimeHours()` subtract hold duration. This is critical for accurate SLA compliance reporting. **Resolution-time figures in every report path must use `getEffectiveResolutionTimeHours()`** (not raw `created_at → closed_at`) so hold time is excluded — this includes `ReportService`, `SlaService::getComplianceReport`, `AgentPerformanceService`, `TicketWorkflowService`, and `ServiceReportService`. Response time is reported raw (`created_at → first_response_at`) and `SlaService::getComplianceReport()` judges the met/missed verdict on that **same raw figure against the policy target** — not against the hold-extended `response_due_at`, which the breach scheduler still uses. Judging the verdict one way and printing the hours the other produced rows reading "10h / 4h target" beside a green MET badge. Do NOT subtract hold from response time: `getEffectiveResponseTimeHours()` subtracts *total* hold, including holds after the first response, and would under-report.

**SLA due dates are recomputed, not set once.** `SlaService::assignSla()` runs on creation *and* after any priority change (`changePriority()` and the priority path of `updateTicket()`), because targets differ per priority — a ticket triaged up to critical must not keep its low-priority deadline.

**Hold time moves the deadline, it doesn't just get subtracted later.** When a hold ends, `changeStatus()` calls `SlaService::extendForHold()` to push `response_due_at`/`resolution_due_at` out by the hold duration. This keeps the two SLA consumers in agreement: the breach scheduler's wall-clock check (`resolution_due_at < now()`) and the compliance report's effective-hours check become the same rule. If you change one, change the other. Tickets currently `on_hold` are excluded from the breach sweep entirely, since their clock is paused and the extension isn't applied until the hold ends.

**SLA-policy guard (important for tests):** `TicketService::guardSlaPolicy()` throws `InvalidArgumentException` when a ticket priority is set for a tenant that has the `sla_management` feature (Business+) and no matching SLA policy exists (`SlaPolicy::hasPolicyFor(tenantId, clientTier, priority)`). Owners do **not** bypass this guard. Any test that creates/changes ticket priority through `TicketService`/the controller on a Business+ tenant must first seed a policy — a catch-all (`priority` null, `client_tier` null, `is_active` true) matches everything:
```php
SlaPolicy::factory()->create(['tenant_id' => $tenant->id, 'priority' => null, 'client_tier' => null, 'is_active' => true]);
```

SLA policies are managed **per client-tier** (not per-id): routes `sla.index`, `sla.seed-defaults`, `sla.edit-tier`, `sla.update-tier` (`POST /sla/tier/{tier}`), `sla.destroy-tier` (`DELETE /sla/tier/{tier}`), where `{tier}` ∈ `basic|premium|enterprise`. There is no generic `POST /sla` or `PUT /sla/{id}`.

### Timezones & Reporting (CRITICAL)

Storage and `config('app.timezone')` are **UTC**; display goes through `TenantTime::format()` in the tenant's timezone.

**Two zones, each named explicitly.** `App\Support\ZonedTime` holds the date maths; `TenantTime` supplies the workspace timezone and `AdminTime` supplies `admin_timezone`. `TenantTime::timezone()` used to branch on whether `session('current_tenant_id')` happened to be set, so an admin who had impersonated a workspace saw different day boundaries from one who had not — same page, different answers from browsing history. Callers state which zone they want; nothing infers it. In Blade: **`@localdt` for tenant screens, `@admindt` for admin screens** (`TenantIsolationGuardrailsTest` fails an admin view using `@localdt`). A grep for `TenantTime` cannot see through the directive — that is how 11 call sites were missed once already. The trap is filtering: `whereDate(..., today())`, `now()->startOfMonth()` and `Carbon::parse($date)->startOfDay()` all evaluate on the *server's* calendar, so a report could exclude a row it then printed as being inside the range.

Use `App\Support\TenantTime` for every date boundary that reaches a query:

| Helper | Returns |
|--------|---------|
| `TenantTime::now()` | now, in the tenant's timezone |
| `TenantTime::startOfDay($d)` / `endOfDay($d)` | the UTC instant of a tenant-local day boundary (null = today) |
| `TenantTime::dayRange($d)` | `[from, to]` UTC pair — the replacement for `whereDate()` |
| `TenantTime::startOfWeek()` / `startOfMonth()` / `startOfDaysAgo(n)` | tenant-local boundaries as UTC |
| `TenantTime::sqlLocal('created_at')` | SQL expression shifting a UTC column into tenant-local time, for `GROUP BY DATE(...)` |

A bare date string is parsed **as** a tenant-local date; a real instant is converted, preserving the moment.

**When iterating buckets to build labels, walk tenant-local time** (`$from->copy()->setTimezone(TenantTime::timezone())`), not the UTC instants — the SQL groups on local dates and UTC-derived keys will silently never match, producing an all-zero chart.

`sqlLocal()` deliberately avoids `CONVERT_TZ`, which returns NULL when MySQL's timezone tables aren't loaded. The trade-off is that it applies the tenant's *current* UTC offset, so a range spanning a DST change buckets those rows by today's offset. Zones without DST are unaffected.

### Queue & Cache

Dev default (`.env.example`): `QUEUE_CONNECTION=database`, `CACHE_STORE=database`, `SESSION_DRIVER=database`. Redis and SQS are opt-in and typically used in production. `composer run dev` starts a queue worker, so queued notifications will be processed locally. If you switch `CACHE_STORE` mid-session, clear the plan-feature cache (`PlanService::clearCache`) since it relies on whatever store is active.

### Testing

- **PHPUnit:** Uses MySQL (not SQLite), `RefreshDatabase` trait, test database `ticketing_test`. The dev box is **dockerized** (containers `ticketing-app`/`ticketing-mysql`/`ticketing-nginx`/`ticketing-redis`; Sail's `laravel.test` service is not used). The MySQL container is often renamed with a hash prefix, so resolve it by image rather than by name: `docker ps --filter ancestor=mysql:8.0`.
- **Use the bundled runner, not the raw command.** `phpunit.xml` pins `DB_USERNAME`/`DB_PASSWORD` to `root`/`root`, and PHPUnit sets those *before* Laravel loads `.env` — Dotenv is immutable, so `.env` cannot override them. When the container's root password differs, **every test fails on authentication before executing**, which looks exactly like a branch having broken everything and has cost real time more than once.
```bash
.claude/skills/delivery-team/scripts/run_tests.sh                     # full suite (~12 min)
.claude/skills/delivery-team/scripts/run_tests.sh --filter=TicketTest # one class
TEST_DB_NAME=ticketing_mine .claude/skills/delivery-team/scripts/run_tests.sh   # isolated DB
```
  `TEST_DB_NAME` matters whenever two runs could overlap: sharing one database tears down the other's schema mid-run and surfaces as `Table 'migrations' already exists` — a collision that reads as a broken branch. The script creates the database and refuses to run from a linked git worktree, since the app container mounts only the main checkout and would silently test the wrong branch.
- **Testing-disk permission gotcha:** if you run tests *inside* the container (as root) and later on the host, branding/logo tests fail with `UnexpectedValueException: FilesystemIterator … Permission denied` because `storage/framework/testing/disks/public/tenant-logos` was created `root:root 0700`. Clear it through the container: `docker exec ticketing-app rm -rf storage/framework/testing/disks/public/tenant-logos`. (Passwordless `sudo` is not available on the host.)
- **Playwright E2E:** Config in `playwright.config.ts`. Tests in `tests/e2e/`. Runs **headless**, chromium only, 1920x1080, `baseURL` `http://localhost:8005`, `screenshot: 'only-on-failure'`, `video: 'retain-on-failure'`, 180s timeout.
- **Test helpers** in `tests/TestCase.php`: `withTenant($tenant)` sets test context, `tenantUrl($path)` generates tenant-prefixed URLs.
- **Common test pattern** (used across all feature tests):
```php
private function createBusinessTenant(): Tenant {
    $plan = Plan::factory()->create(['slug' => 'business', 'features' => PlanFeature::forPlan('business')]);
    $license = License::factory()->active()->forPlan($plan)->create();
    $tenant = Tenant::factory()->create(['license_id' => $license->id]);
    // Business+ tenants enforce the SLA-policy guard on ticket priority; seed a catch-all
    // policy so TicketService ops don't throw (see "Ticket Hold Time & SLA").
    SlaPolicy::factory()->create(['tenant_id' => $tenant->id, 'priority' => null, 'client_tier' => null, 'is_active' => true]);
    return $tenant;
}

private function setupTenantContext(Tenant $tenant): User {
    $user = User::factory()->create();
    $tenant->addUser($user, 'member');
    $this->actingAs($user)->withTenant($tenant)->withSession(['current_tenant_id' => $tenant->id]);
    return $user;
}
```
- Factories in `database/factories/` cover all major models (Tenant, User, Plan, License, Ticket, Client, Department, Product, etc.). `Tenant::factory()->withLicense()` attaches an active license in one call — needed because dashboard access redirects to `license.expired` without a valid license.
- **Default seeded credentials:** `admin@example.com` / `password` (admin), `test@example.com` / `password` (tenant user).

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

**Submit-form prefill/lock** — `/{slug}/submit-ticket` accepts plain query-string params (`ClientPortalController::resolveSubmitPrefill()`): text fields are prefilled **and locked** (read-only in the view); `department`/`department_id`, `category`/`category_id`, and `product_ids`/`products`/`product` are resolved **by id or name** (category matching scoped to the resolved department). The URLs are **not signed** — any visitor can craft one, so treat prefill values as untrusted input.

**Per-client autofill link + QR** — the client detail page (`ClientController@show`) builds a prefilled submit-ticket link for that client; `GET clients/{client}/autofill-qr` (`ClientController@autofillQr`, permission `manage clients`) streams it as a PNG QR code (`?download=1` for attachment; optional department/category/products baked in). Uses the `endroid/qr-code` package.

### REST API (v1)

Token-authenticated REST API under `/api/v1/`, defined in `routes/api.php` (mounted via the `api:` key in `withRouting()`). Controllers live in `app/Http/Controllers/Api/V1/`.

**Authentication** — the `api-token` middleware (`AuthenticateApiToken`) reads the `Authorization: Bearer <token>` header. Tokens are `tk_`-prefixed random strings; only their SHA-256 hash is stored in `api_tokens.token`. The middleware looks up the token by hash (`ApiToken::findByPlainToken`, which uses `withoutGlobalScopes()` since there's no session yet), rejects missing/invalid/expired tokens (401) and inactive tenants (403), then **establishes tenant context the same way URL-slug routes do**: sets `session('current_tenant_id')` and the Spatie `PermissionRegistrar` team id. After that, `BelongsToTenant` global scopes apply automatically — so controller queries like `Ticket::where(...)` are already tenant-scoped without manual `tenant_id` filtering. The resolved tenant/token are also stashed on `$request->attributes` (`api_tenant`, `api_token`).

**Endpoints:** `GET/POST /tickets`, `GET /tickets/{ticketNumber}`, `POST /clients`, `GET /departments`, `GET /categories`. List/show responses go through `TicketController::presentTicket()` (a hand-rolled array shape, not an API Resource). Ticket creation reuses `TicketService::createTicket()` and `firstOrCreate`s the client by email.

**Token management UI** — `AppSettingController::apiTokens/generateApiToken/revokeApiToken` (routes `settings.api-tokens*`, view `settings/api-tokens.blade.php`), gated by the `manage settings` permission. The plain token is shown **once** via a flashed `plain_token` after generation; it is never recoverable afterward. `ApiToken` is itself a `BelongsToTenant` model, so the management screens are tenant-scoped normally.

### AI Assistant (OpenAI)

OpenAI-powered assistant gated by the **`ai_chatbot`** feature (**Enterprise only**; `PlanFeature::AiChatbot`, `minimumPlan()='enterprise'`). Everything is also opt-in per tenant via `ai` settings (default off), so it ships dark.

**Two low-level + orchestrator services:**
- `OpenAiService` — `chat($messages, $tools, $opts)` (model `OPENAI_MODEL`, default `gpt-5`), `webSearch($query)` (model `OPENAI_SEARCH_MODEL` = `gpt-4o-mini-search-preview`; no temperature/tools), `embed($text)` (model `OPENAI_EMBED_MODEL` = `text-embedding-3-small`). Config in `config/services.php` under `openai`. `isConfigured()` gates everything. **Model-aware params:** for reasoning models (gpt-5 family, o-series — but NOT `gpt-5-chat-latest`) `chat()` auto-translates the request: `max_tokens` → `max_completion_tokens` (floored at `OPENAI_MAX_OUTPUT_TOKENS`, default 6000, since hidden reasoning tokens count against it), drops `temperature`, and adds `reasoning_effort` (`OPENAI_REASONING_EFFORT`, default `low`). Callers keep passing `max_tokens`/`temperature` unchanged.
- `AiAssistantService` — tenant-aware orchestrator. `converse()` runs a persisted **tool-calling loop** (max 8 iterations). Tools (function-calling): `search_knowledge_base`, `search_system_guide` (`app/Support/SystemGuide.php`), `search_web`, `create_ticket`, `lookup_ticket_status`, `query_tickets`, `query_clients`, `ticket_stats`, `search_resolved_tickets` (embeddings retrieval — also returns confirmed-helpful "learned" snippets), `report_bug`. The AI composes filters; code enforces tenant scope + read-only + row caps. Charts: the model emits a ```chart fenced JSON block — single-series `{type, title, data:[{label,value}]}` or multi-series `{type, title, labels:[…], series:[{name, data:[…]}]}`; `type` ∈ `bar|column|line|area|pie|donut` (+ optional `stacked`). Rendered client-side with **ApexCharts** via `window.NexusChat.renderApexChart` (`resources/js/assistant-ui.js`).

**Two channels** (`ChatConversation.channel`, `ChatMessage`):
- **In-app agent assistant** — `partials/app-ai-assistant.blade.php`, floating widget included in `layouts/app.blade.php` (gated by feature + `ai_enabled`). Per-user conversation history, **Markdown-rendered replies** (`window.NexusChat.renderMarkdown`, escape-first/XSS-safe), file upload (`app/Support/FileParser.php` → images/vision, PDF via `smalot/pdfparser`, text), ApexCharts charts (bar/column/line/area/pie/donut, multi-series), **page-context awareness** (sends `window.location.pathname`; `PageContextResolver` describes the page incl. ticket/client details), **Save to knowledge** button (opt-in learning), resizable **width + height** (drag handles, persisted in localStorage). Endpoints: `assistant.message`, `assistant.learn`, `assistant.conversations`, `assistant.conversation`.
- **Public portal bot** — `client-portal/partials/ai-chat-widget.blade.php`, included from the portal layout (gated by feature + `ai_enabled` + `ai_portal_widget_enabled`). Per-browser-session token, throttled (`throttle:ai-chat`) + per-tenant daily cap. Controller `Portal\AiChatController` (`tenant.ai-chat`, `tenant.ai-chat.history`). Public gating is done **in the controller** (no session for `feature:` middleware) — mirrors `KbPortalController::resolveTenant`.

**Agent copilot (one-shot, stateless)** — `tickets.ai.draft-reply`, `tickets.ai.summarize` (gated by feature; UI shown when `ai_agent_copilot_enabled`).

**Ticket-draft AI clean-up** — `AiAssistantService::structureTicketDraft()` rewrites a rough subject/description into a clean subject + structured description + suggested resolution tasks (strict JSON; never invents facts). Keeps the data feeding self-learning tidy. Surfaces:
- Create form (`tickets.ai.structure`) — subject + description + tasks; shared partial `tickets/partials/_ai-assist.blade.php`.
- Edit form — same partial with `$withTasks=false`.
- Public submit form (`tenant.ai-polish`, `Portal\AiChatController@polish`) — subject + description only (no internal tasks); throttled + daily cap.
- Ticket-task polish (ticket detail page, `tickets/show.blade.php`) — `AiAssistantService::polishTask()` rewrites a single rough task (`tickets.ai.polish-task`), `polishTaskList()` restructures the whole checklist (`tickets.ai.polish-tasks`) into a proposed list that is applied via a separate POST `tickets.tasks.polish-apply` (`TicketTaskController@applyPolishedTasks`). All `feature:ai_chatbot`-gated.

**Self-learning (genuine ML, not base-model training):**
- *From resolved tickets* — `ai:embed:tickets` (scheduled) embeds closed tickets onto `tickets.solution_embedding` / `solution_embedded_at`, from the subject, description, closing remarks and every **public** agent reply (internal notes are deliberately excluded from retrieval). `search_resolved_tickets` embeds the agent's query and cosine-matches (top 5).
- *From chat (opt-in)* — `ai_learn_from_chat` setting (default off). Agents click **Save to knowledge** on a reply → `LearnedSnippet` (`learned_snippets` table, `BelongsToTenant`) stores the Q&A + question embedding. Surfaced via `search_resolved_tickets` (the "learned" list, cosine ≥0.3). **Reviewable/removable** at `settings.ai.knowledge` (`settings/ai-knowledge.blade.php`). OpenAI API data is not used to train their models; nothing is learned silently.

**Settings (`ai` group, `AppSettingController::ai/saveAi`, view `settings/ai.blade.php`)** — `ai_enabled` (master), `ai_portal_widget_enabled`, `ai_agent_copilot_enabled`, `ai_learn_from_chat`, `ai_system_prompt` (custom instructions). Admin viewers: conversation history (`settings.ai.conversations`) and learned answers (`settings.ai.knowledge`). All AI routes are `feature:ai_chatbot`-gated; the settings tab is feature-gated across all settings pages.

**Deploy notes:** `composer install` (pdfparser); `npm run build` (charts/markdown JS — ApexCharts is already a dependency); set `OPENAI_API_KEY` + optional `OPENAI_MODEL` (default `gpt-5`)/`OPENAI_REASONING_EFFORT`/`OPENAI_MAX_OUTPUT_TOKENS`/`OPENAI_SEARCH_MODEL`/`OPENAI_EMBED_MODEL` — confirm the chosen model is enabled on the OpenAI account, or `chat()` 404s; `migrate` (adds `chat_conversations`, `chat_messages`, `tickets.solution_embedding`, `learned_snippets`); re-apply `PlanFeature::forPlan('enterprise')` to existing Enterprise plans' `features` so `ai_chatbot` is present; ensure the scheduler runs `ai:embed:tickets`; `config:cache`.

### AI Platform Infrastructure (guardrails, cost, admin console)

The services around `OpenAiService`/`AiAssistantService` are the operational layer: guardrails, cost accounting and the cross-tenant admin console. Any new AI call path must go through them, or it is unmoderated and uncosted.

**Request path for a tenant chat turn** (`AiAssistantService::converse()`):
1. `PromptInjectionService::isInjection()` — refuses the turn and writes an `AiModerationLog` (`type=prompt_injection`).
2. `ModerationService::isFlagged($text, 'balanced', …)` — same log table, `type=moderation`.
3. `OpenAiService::post()` wraps *every* endpoint in `CircuitBreakerService::ensureAvailable()` / `recordFailure()` / `recordSuccess()` (cache-backed). A tripped breaker throws `OpenAiException` before any HTTP call, so an OpenAI outage fails fast instead of timing out per request.
4. `AiUsageTrackerService::log()` writes an `ai_usage_logs` row per call — model, prompt/completion tokens, `estimated_cost`, `latency_ms`, `response_status` (`success|failed|rate_limited|moderated`) and `feature` (`chat|copilot|embed|web_search`). This table is the only cost record; a path that bypasses it is invisible to the analytics screens.

**`SemanticCacheService` is misnamed on purpose** — keys are `md5()` of the exact query string, so it is a *lexical* cache. Two paraphrases of the same question are separate entries. Its own docblock says so; don't "fix" callers on the assumption that it dedupes by meaning.

**`EmbeddingService`** owns all vector work (`embedKbArticle()`, ticket/snippet embedding, cosine search) and is what the `ai:embed:*` commands drive. `AiProfileService` supplies the named parameter presets in `ai_profiles` (Fast / Balanced / Cheap / High Accuracy / Vision / Reasoning — model, temperature, max_tokens, top_p, penalties, `enabled_tools`, one `is_default`).

**Rate limiters** are declared in `AppServiceProvider` (not in route files): `ai-chat` (public portal bot), `portal-ai`, `agent-ai`, `admin-ai`. Every AI route should carry one.

**Three assistant surfaces, three controllers** — `Portal\PortalAiAssistantController` (`portal.ai.*`), `App\AppAiAssistantController` (`app.ai.*`), and the admin ones below. They are separate from the `assistant.*` widget endpoints.

**Admin AI console (`/admin/ai`, platform staff, cross-tenant)** — `AiDashboardController` (dashboard), `AiAdminManagerController` (settings, prompt templates in `ai_prompt_templates`, conversation browser + export, feedback in `chat_message_feedbacks`, playground, analytics via `AiMetricsService`, health/circuit-breaker state), `AdminAiCopilotController` + `AdminAiCopilotService` (a copilot that answers over Tenant/License/Plan/Distributor/Ticket/User — cross-tenant by design), `AiChatbotController` + `AiChatbotService` (admin chat with conversation CRUD) and `AiConversationExportService` (`exportJson()` / `exportCsv()`).

**`ai:maintenance --mode=hourly|daily|weekly`** prunes `ai_usage_logs`, warms caches and recalculates the metric counters `AiMetricsService` reads.

### AI Bug Fixing ("AI Programmer" = Claude Code)

A closed loop where a user reports a product bug to the AI Assistant and an autonomous coding agent (Claude Code, via the GitHub Action) opens a fix PR.

**Flow:** user reports a bug in chat → `report_bug` tool files a `BugReport` (`bug_reports` table, `BelongsToTenant`; reporter + the conversation it came from) → internal staff (`is_admin`) are notified (`BugReportFiled`) and review it at **`/admin/bugs`** (`Admin\BugReportController`, cross-tenant via `withoutGlobalScopes()`) → staff click **"Fix with AI Programmer"** → `GitHubService::createIssue()` files an issue labelled **`ai-fix`** → `.github/workflows/claude-fix.yml` (Anthropic's `anthropics/claude-code-action`) implements a fix on a branch, runs the suite, and opens a **PR** → `POST /webhooks/github` (`GitHubWebhookController`, HMAC-`X-Hub-Signature-256` validated, CSRF-exempt via `bootstrap/app.php`) advances `BugReport.status` (`escalated`→`pr_opened`→`merged`, matched to the bug by the issue number referenced in the PR) → the reporter sees the update **inside the assistant** (`assistant.bug-updates` + `ack`; an amber launcher dot and an indigo status message).

**Status lifecycle** (`BugReport` consts): `new`→`triaged`→`escalated`→`pr_opened`→`merged`/`closed`/`rejected`. `USER_FACING_STATUSES` (pr_opened/merged/rejected) are surfaced to the reporter once (`user_notified_status` guards repeats).

**Guardrails:** only `is_admin` can escalate (intake is Enterprise via `ai_chatbot`, but the Fix queue is internal/cross-tenant). Claude Code runs only in GitHub's sandboxed CI (no prod/tenant data); it opens a **PR only** — a human merges and the unchanged `deploy.yml` ships from `main`. No auto-deploy. The issue body is PII-free (the admin reviews before escalating).

**Config / secrets (set later):** `migrate` adds the `bug_reports` table. `config/services.php` `github` block — `GITHUB_TOKEN` (fine-grained PAT, issues:write on the repo), `GITHUB_REPO`, `GITHUB_WEBHOOK_SECRET`. The Action needs repo secret **`ANTHROPIC_API_KEY`** (not in the app). Until configured, `GitHubService::isConfigured()` is false and **Fix gracefully degrades** to a local escalation (no issue filed), so the queue still works. Point a GitHub repo webhook (PR events) at `/webhooks/github` with the same secret.

### Jude Assistant Hub Connector (external voice control)

Separate from the in-app **AI Assistant** (OpenAI) above. This exposes the helpdesk as a set of tools that an **external** "Jude Hub" (a voice/desktop assistant) calls over HTTP — the app is the tool *provider*, not the LLM host.

Provided by the internal Composer package **`cliqueha/assistant-connector`** (VCS repo in `composer.json`, `repositories` block → `github.com/imjustkidding6/assistant-connector.git`; the package README documents a `path`-repo install for local dev). Its `AssistantConnectorServiceProvider` auto-registers everything: the `assistant.token` middleware alias (`AuthenticateDesktopToken`), the routes `GET|POST /api/assistant/manifest|execute`, the `desktop_tokens` migration, and the `assistant:issue-token` console command.

- **App-side config:** `config/assistant-connector.php` — declares the exposed tools, the `assistant.token` middleware, `user_model`, `context_resolver`, and the route `prefix` (`api/assistant`). The Hub reads `GET /api/assistant/manifest` (tool schemas) and calls `POST /api/assistant/execute`. Bearer auth uses **`DesktopToken`** (package model, `desktop_tokens` table) — NOT the `api_tokens` used by the REST API (v1); the two token systems are unrelated.
- **Tools** live in `app/Assistant/` (`FindTickets`, `GetTicket`, `TicketSummary`, `ListClients`, `CreateTicket`, `UpdateStatus`, `AddComment`). Each extends `Cliqueha\AssistantConnector\AssistantTool` and defines `name()`, `description()`, `inputSchema()`, `handle($input, $user)`, and `writes()` (true → the Hub asks the user to confirm before running).
- **Tenant context:** `app/Assistant/SetTenantContext.php` (the `context_resolver`) runs after token auth. The token API is stateless, so it sets the active tenant from the token's stamped `tenant_id` (via `$user->setCurrentTenant()`) — but only if `$user->belongsToTenant($tenant)`; otherwise it silently falls back to the user's current/first workspace (`ensureCurrentTenant()`). After that, `BelongsToTenant` global scopes apply — so the tool `handle()` methods query `Ticket`/`Client` unscoped-by-hand and still stay tenant-isolated.
- **Self-serve token page:** `/connect-jude` (`ConnectJudeController`, routes `connect-jude`, `.generate`, `.revoke`; sidebar link in `layouts/app.blade.php`). A signed-in user generates a token stamped with `session('current_tenant_id')` so the assistant acts in that workspace. Plain token is shown once.
- **CAVEAT — bypasses `TicketService`:** the `CreateTicket`/`UpdateStatus`/`AddComment` tools write via `Ticket::create()` / `->update()` directly, so they skip `TicketService` notifications, `addHistory()` audit logging, the SLA-policy guard, and workflow rules. If these tools need parity with the UI/API, route them through the services instead.

### Escalation System

Agent tiering (Enterprise only): 3 tiers (tier_1, tier_2, tier_3). Escalation enforced:
- Can only escalate **up** (not same or lower tier)
- Assigned agent must have `support_tier` >= target tier
- Owner bypasses tier restrictions
- Agent dropdown in UI filters by tier using JS

### Tenant Registration Flow

`RegisteredUserController::store()` runs everything inside a single DB transaction: creates the tenant (with a user-chosen slug), activates the license, creates the owner user, calls `DepartmentSeeder::seedForTenant($tenant)` and `TenantRoleService::setupDefaultRoles($tenant)`, then redirects directly to the new tenant's dashboard. When modifying onboarding, keep all of this inside the transaction.

**Slug reserved words** — tenant slugs are validated in registration against `Tenant::RESERVED_SLUGS` (`app/Models/Tenant.php:24`), enforced via `Rule::notIn()` in both `RegisteredUserController` and `GoogleSocialiteController`. Add new top-level route prefixes there when they would collide with the `{slug}` wildcard — `health` matters in particular now that the ALB health check points at `/health`.

### Google Sign-In (Socialite)

Uses `laravel/socialite`. Flow handled by `GoogleSocialiteController`:

1. `/auth/google/redirect` — redirects to Google OAuth (stores `google_intent` in session)
2. `/auth/google/callback` — handles callback:
   - Existing `google_id` match → log in directly
   - Existing email match → link `google_id` to account, log in
   - New user → store `google_profile` in session, redirect to `auth.google.register.form`
3. `/auth/google/register` (GET/POST) — new Google users complete tenant registration (license key, company name, slug). Mirrors `RegisteredUserController` transaction pattern.

Google-registered users have `password = null` and `email_verified_at` pre-set. The `PasswordController` guards against setting a blank password on these accounts. Required `.env` keys: `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI`.

### Help Centre & Guided Tours

A permission-scoped help centre (`/{slug}/tutorials`) and the guided tours that run on the real screens. They are one feature: neither may ever mention something the reader cannot do.

**Content is data, never markup.** Everything lives in `config/help.php`; `HelpService` reads and filters it. The page, the contents list, the search index, the PDF and the tour endpoint all call the same service methods — the moment one of them re-checks a permission itself they drift apart. Views render what the service returns and check nothing.

**Filtering is recursive.** A `permission` may sit on a topic, on one block inside a topic, on a single FAQ entry, or on a tour. Gating whole topics is the obvious half and the useless half: an agent who can update tickets but not merge them reads the status section and never sees "Merging duplicates". A topic whose blocks all filtered away is dropped, and so is a sub-heading left standing with nothing under it (`dropOrphanHeadings()`). Topics may also carry a `feature` — permission and plan are different questions.

**Tours span pages.** Each step declares a `route`; progress lives in `sessionStorage` per tab; the runner resumes on the next page load. `TourRunner.run()` **saves progress as its first act, before the early return** for a page with no steps — tours start from the help centre where no step lives, so a save placed after that branch never runs and the tour dies silently at the first navigation. `HelpService` resolves each step's route to a URL so the runner can offer "take me there".

**A tour is only proven by running it.** `tests/e2e/guided-tour-walk.spec.ts` walks every offered tour to its last step. The anchor test only proves the attribute exists *in a template* — it cannot see that the element is a hidden input, that it renders only in one state, or that its page 404s for this workspace. All three shipped and all three were invisible to every other suite.

**A topic may also carry a `gate`** — the name of a `Tenant` method (a string, so config stays cacheable). API access is switched on per workspace and its page 404s otherwise, which is neither a permission nor a plan feature.

**Anchors are `data-tour="…"` attributes only** — never a class or a script's id. `HelpCentreTest` scans `resources/views/` and fails the build if a step targets an anchor no template contains, or names an unregistered route.

**Empty states must explain themselves.** A legitimate search returning nothing reads as a broken search box. `hiddenTopicsFor()` exposes the titles and required permissions of filtered-out topics, so a dead-end query names the module and the permission to ask for. Title matches rank above keyword matches.

**Search filters every region** — topic bodies, tutorial cards, contents entries and their group labels — against `searchTextFor()`, or the page contradicts itself.

**The PDF comes from the same filtered content.** A static manual covering everything undoes the scoping the moment someone downloads it; a test asserts two roles get different files.

Current scope: **24 topics, 22 tours, 62 steps, 40 `data-tour` anchors** across 31 templates.

**A tour step must target something every visit renders.** Anchors on a hidden input, on a block inside an `@if` that is usually false, or on a control that only appears for some records will pass the anchor test and still dead-end a reader — the attribute is in the template, just not on the page. Prefer a container that always renders; where the subject genuinely is conditional (the escalate box only exists below tier 3), write the topic instead of a tour.

`config/features.php` switches a module off product-wide: routes stay live, but it is dropped from the help centre. The knowledge base is off there — it has views and routes but no navigation entry, so documenting it sent people somewhere they could not otherwise reach.

Adding a topic or tour: write it in `config/help.php`, add any `data-tour` anchors to the templates, then run `php artisan test --filter=HelpCentreTest` and `npm run test:tour`. Keep tours to 2–7 steps and teach the consequence, not the button.

**One content store, two surfaces.** `/{slug}/tutorials` renders what the reader's role permits; `/admin/help` renders the same `config/help.php` unfiltered, because platform staff hold no tenant role and a permission filter would hand them an empty guide. The admin page labels each topic with the permission and plan feature it requires, which is what a customer will be told to ask for. There is no second copy of the content and no per-topic template — old links redirect to the topic anchor on whichever page applies.

**The scoping is swept, not spot-checked.** `HelpPermissionSweepTest` walks every seeded role against every topic and every tour and asserts the page, the tour endpoint, the launcher listing and the PDF all agree with `HelpService`. It also proves a hidden topic's body never reaches the HTML, and that guessing a tour key is not a way round the launcher — the endpoint 403s and carries no payload.

`hiddenTopicsFor()` deliberately exposes a hidden topic's title, search words and required permission names so the empty state can explain itself; a test pins that boundary so nothing else joins them.

Reference implementation and the fuller rationale: `../projects/alstylehris/docs/scoped-help-and-tours.md`.

### Controller Concerns

`app/Http/Controllers/Concerns/HasSortableQuery.php` — reusable trait for controllers that support column sorting. Use it instead of duplicating sort logic.

### CI/CD

GitHub Actions (`.github/workflows/`):
- **test.yml** — pushes and PRs, plus `workflow_call:` so `deploy.yml` can reuse it (it could not before, so every deploy failed at its first job). MySQL + Redis services, `php artisan test --compact`, then `vendor/bin/pint --test`. **Pint is a hard gate**: it inspects the whole repo, and `pint --dirty` locally only checks changed files, so the suite can be green while CI is red on files nobody touched.
- **deploy.yml** — test job first, then on `main` only: build → ECR → migrate → update services → wait. The migration runs as a one-off ECS task that is **waited on and exit-code checked**; a failed migration aborts before any service is updated. Both `ticketing-web` and `ticketing-worker` are waited for stability. A `rollback` job captures both task-definition revisions *before* the deploy and restores them on failure — it deliberately does not revert the schema, because rolling code back under a migrated database is how an incident becomes data loss.
- Migrations run **only** there. `docker/entrypoint.sh` is gated on `RUN_MIGRATIONS_ON_BOOT` (false in both ECS task definitions); it used to migrate on every container start, so every web task, every worker task and the deploy task raced each other.
- The ECS health check points at **`/health`** (`HealthCheckController`, checks DB + cache), not `/up`. `/up` returns 200 while the database is down, so `wait services-stable` reported success on deploys that had taken the site offline.
<!-- Laravel Boost guidelines are auto-injected at runtime by the Laravel Boost MCP server. Do not duplicate them here. -->
