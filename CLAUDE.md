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
- `routes/portal.php` — **Dead code.** The old `/portal/{slug}/...` structure is not loaded anywhere; portal routes now live inside `routes/tenant.php` under `/{slug}/`. Do not edit `portal.php` expecting it to take effect. Verify in `bootstrap/app.php` if in doubt.

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

**View-layer guardrails (CI-enforced by `TenantIsolationGuardrailsTest`):** views outside `resources/views/admin/` must not `@extends('layouts.admin')` (give admin screens their own view under `admin/` — never repurpose a tenant view), and any `withoutGlobalScopes()` in a non-admin view must chain `->where('tenant_id', ...)` on the same line. A controller serving both surfaces should pick the view by route, e.g. `view($request->routeIs('admin.*') ? 'admin.sla.index' : 'sla.index', ...)` (see `SlaPolicyController`).

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

Three default roles per tenant: `admin` (16 permissions), `manager` (14), `agent` (5). Seeded via `TenantRoleService::setupDefaultRoles()`.

Controllers enforce permissions via `$this->checkPermission('permission name')` (defined in base `Controller.php`). Owners bypass all permission checks.

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
- `app/Support/` — Framework-agnostic helpers (e.g., `TenantTime`). Not services; no side effects.

### Activity Logging

Models opt into audit logging via the `LogsActivity` trait (`app/Models/Traits/LogsActivity.php`). It auto-captures `created`, `updated`, `deleted`, `restored`, `force_deleted` events and persists field-level diffs to the `activity_logs` table. Models define `$activityLogIgnore` to suppress noisy fields (e.g., Ticket ignores `tracking_token`, `sla_breach_notified_at`). Timestamps, `password`, and `remember_token` are always ignored. Gated by the `audit_logs` plan feature (Business+).

### Single Active Session

`User::purgeOtherSessions()` is called on every successful login (password and Google OAuth) after `session()->regenerate()`. It deletes all rows from the `sessions` table for the user except the current session ID. Latest login wins — previous sessions are immediately invalidated. Depends on `SESSION_DRIVER=database`.

### Scheduled Commands

Defined in `routes/console.php`:
- **`SendSlaBreachWarnings`** — every 15 minutes. Checks tickets with overdue response/resolution times and notifies assigned agents. Only fires if tenant has `email_notifications` feature enabled.
- **`CheckLicenseExpirations`** — daily at 02:00 UTC. Multi-stage notification lifecycle: 4–7 days = approaching, 0–3 days = imminent, then grace period tracking, then final status flip to `EXPIRED`. Uses warning flags on the license to avoid duplicate sends.
- **`EmbedResolvedTickets`** (`ai:embed-tickets {--limit=200}`) — every 15 minutes, `withoutOverlapping()`. Embeds closed tickets without a `solution_embedded_at` so the AI assistant can learn from past resolutions. No-op if OpenAI isn't configured. See **AI Assistant**.

### Ticket Hold Time & SLA

Tickets track hold time via `startHold()`, `endHold()`, and `getTotalHoldTimeMinutes()`. Hold time is excluded from SLA calculations: `getEffectiveResolutionTimeHours()` and `getEffectiveResponseTimeHours()` subtract hold duration. This is critical for accurate SLA compliance reporting. **Resolution-time figures in every report path must use `getEffectiveResolutionTimeHours()`** (not raw `created_at → closed_at`) so hold time is excluded — this includes `ReportService`, `SlaService::getComplianceReport`, `AgentPerformanceService`, `TicketWorkflowService`, and `ServiceReportService`. Response time is reported raw (`created_at → first_response_at`); do NOT subtract hold from it, because `getEffectiveResponseTimeHours()` subtracts *total* hold (including holds after the first response) and would under-report.

**SLA-policy guard (important for tests):** `TicketService::guardSlaPolicy()` throws `InvalidArgumentException` when a ticket priority is set for a tenant that has the `sla_management` feature (Business+) and no matching SLA policy exists (`SlaPolicy::hasPolicyFor(tenantId, clientTier, priority)`). Owners do **not** bypass this guard. Any test that creates/changes ticket priority through `TicketService`/the controller on a Business+ tenant must first seed a policy — a catch-all (`priority` null, `client_tier` null, `is_active` true) matches everything:
```php
SlaPolicy::factory()->create(['tenant_id' => $tenant->id, 'priority' => null, 'client_tier' => null, 'is_active' => true]);
```

SLA policies are managed **per client-tier** (not per-id): routes `sla.index`, `sla.seed-defaults`, `sla.edit-tier`, `sla.update-tier` (`POST /sla/tier/{tier}`), `sla.destroy-tier` (`DELETE /sla/tier/{tier}`), where `{tier}` ∈ `basic|premium|enterprise`. There is no generic `POST /sla` or `PUT /sla/{id}`.

### Queue & Cache

Dev default (`.env.example`): `QUEUE_CONNECTION=database`, `CACHE_STORE=database`, `SESSION_DRIVER=database`. Redis and SQS are opt-in and typically used in production. `composer run dev` starts a queue worker, so queued notifications will be processed locally. If you switch `CACHE_STORE` mid-session, clear the plan-feature cache (`PlanService::clearCache`) since it relies on whatever store is active.

### Testing

- **PHPUnit:** Uses MySQL (not SQLite), `RefreshDatabase` trait, test database `ticketing_test`. The dev box is **dockerized** (containers `ticketing-app`/`ticketing-mysql`/`ticketing-nginx`/`ticketing-redis`; Sail's `laravel.test` service is not used). MySQL is published on host port **3320**; tests run from the host against `127.0.0.1` also work via `phpunit.xml`. Run the suite either way: `php artisan test --compact` on the host, or inside the container with `docker exec ticketing-app php artisan test`.
- **Testing-disk permission gotcha:** if you run tests *inside* the container (as root) and later on the host, branding/logo tests fail with `UnexpectedValueException: FilesystemIterator … Permission denied` because `storage/framework/testing/disks/public/tenant-logos` was created `root:root 0700`. Clear it through the container: `docker exec ticketing-app rm -rf storage/framework/testing/disks/public/tenant-logos`. (Passwordless `sudo` is not available on the host.)
- **Playwright E2E:** Config in `playwright.config.ts`. Tests in `tests/e2e/`. Runs with `headless: false` and `slowMo: 500` for visibility.
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
- *From resolved tickets* — `EmbedResolvedTickets` (`ai:embed-tickets`, scheduled) embeds closed tickets onto `tickets.solution_embedding` / `solution_embedded_at`. `search_resolved_tickets` embeds the agent's query and cosine-matches (top 5).
- *From chat (opt-in)* — `ai_learn_from_chat` setting (default off). Agents click **Save to knowledge** on a reply → `LearnedSnippet` (`learned_snippets` table, `BelongsToTenant`) stores the Q&A + question embedding. Surfaced via `search_resolved_tickets` (the "learned" list, cosine ≥0.3). **Reviewable/removable** at `settings.ai.knowledge` (`settings/ai-knowledge.blade.php`). OpenAI API data is not used to train their models; nothing is learned silently.

**Settings (`ai` group, `AppSettingController::ai/saveAi`, view `settings/ai.blade.php`)** — `ai_enabled` (master), `ai_portal_widget_enabled`, `ai_agent_copilot_enabled`, `ai_learn_from_chat`, `ai_system_prompt` (custom instructions). Admin viewers: conversation history (`settings.ai.conversations`) and learned answers (`settings.ai.knowledge`). All AI routes are `feature:ai_chatbot`-gated; the settings tab is feature-gated across all settings pages.

**Deploy notes:** `composer install` (pdfparser); `npm run build` (charts/markdown JS — ApexCharts is already a dependency); set `OPENAI_API_KEY` + optional `OPENAI_MODEL` (default `gpt-5`)/`OPENAI_REASONING_EFFORT`/`OPENAI_MAX_OUTPUT_TOKENS`/`OPENAI_SEARCH_MODEL`/`OPENAI_EMBED_MODEL` — confirm the chosen model is enabled on the OpenAI account, or `chat()` 404s; `migrate` (adds `chat_conversations`, `chat_messages`, `tickets.solution_embedding`, `learned_snippets`); re-apply `PlanFeature::forPlan('enterprise')` to existing Enterprise plans' `features` so `ai_chatbot` is present; ensure the scheduler runs `ai:embed-tickets`; `config:cache`.

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

**Slug reserved words** — tenant slugs are validated against a blocklist in registration: `admin`, `www`, `mail`, `api`, `portal`, `app`, `support`, `help`, `status`, `login`, `register`, `profile`, `up`, `logout`. Add new reserved route prefixes here if they conflict with the `{slug}` wildcard. **Known gaps:** `connect-jude`, `webhooks`, and `auth` are top-level route prefixes but are NOT yet in the blocklist — a tenant registering one of those slugs would be shadowed by the static route.

### Google Sign-In (Socialite)

Uses `laravel/socialite`. Flow handled by `GoogleSocialiteController`:

1. `/auth/google/redirect` — redirects to Google OAuth (stores `google_intent` in session)
2. `/auth/google/callback` — handles callback:
   - Existing `google_id` match → log in directly
   - Existing email match → link `google_id` to account, log in
   - New user → store `google_profile` in session, redirect to `auth.google.register.form`
3. `/auth/google/register` (GET/POST) — new Google users complete tenant registration (license key, company name, slug). Mirrors `RegisteredUserController` transaction pattern.

Google-registered users have `password = null` and `email_verified_at` pre-set. The `PasswordController` guards against setting a blank password on these accounts. Required `.env` keys: `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI`.

### Controller Concerns

`app/Http/Controllers/Concerns/HasSortableQuery.php` — reusable trait for controllers that support column sorting. Use it instead of duplicating sort logic.

### CI/CD

GitHub Actions (`.github/workflows/`):
- **test.yml** — runs on all pushes and PRs. Spins up MySQL + Redis services, runs `php artisan test --compact` and Pint lint check.
- **deploy.yml** — runs test job first, then on `main` only: builds Docker image → pushes to ECR → runs migrations as an ECS one-off task → updates `ticketing-web` and `ticketing-worker` ECS services → waits for stability. Web and queue worker are separate ECS services.
<!-- Laravel Boost guidelines are auto-injected at runtime by the Laravel Boost MCP server. Do not duplicate them here. -->
