# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Build & Development Commands

```bash
# Local development
composer run dev          # Start all dev servers (web, queue, logs, vite)
composer test             # Run PHPUnit tests
npm run dev               # Frontend HMR
npm run build             # Production frontend build
php artisan migrate       # Run migrations
php artisan db:seed       # Run seeders

# Testing
php artisan test --compact                          # Run all tests
php artisan test --compact --filter=TicketController # Run specific test class
php artisan test --compact --filter=test_method_name # Run specific test method
npx playwright test --reporter=list                 # Run E2E browser tests

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

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.3.6
- laravel/framework (LARAVEL) - v12
- laravel/prompts (PROMPTS) - v0
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- phpunit/phpunit (PHPUNIT) - v11
- tailwindcss (TAILWINDCSS) - v4

## Skills Activation

This project has domain-specific skills available. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

- `tailwindcss-development` — Styles applications using Tailwind CSS v4 utilities. Activates when adding styles, restyling components, working with gradients, spacing, layout, flex, grid, responsive design, dark mode, colors, typography, or borders; or when the user mentions CSS, styling, classes, Tailwind, restyle, hero section, cards, buttons, or any visual/UI changes.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan

- Use the `list-artisan-commands` tool when you need to call an Artisan command to double-check the available parameters.

## URLs

- Whenever you share a project URL with the user, you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain/IP, and port.

## Tinker / Debugging

- You should use the `tinker` tool when you need to execute PHP to debug code or query Eloquent models directly.
- Use the `database-query` tool when you only need to read from the database.
- Use the `database-schema` tool to inspect table structure before writing migrations or models.

## Reading Browser Logs With the `browser-logs` Tool

- You can read browser logs, errors, and exceptions using the `browser-logs` tool from Boost.
- Only recent browser logs will be useful - ignore old logs.

## Searching Documentation (Critically Important)

- Boost comes with a powerful `search-docs` tool you should use before trying other approaches when working with Laravel or Laravel ecosystem packages. This tool automatically passes a list of installed packages and their versions to the remote Boost API, so it returns only version-specific documentation for the user's circumstance. You should pass an array of packages to filter on if you know you need docs for particular packages.
- Search the documentation before making code changes to ensure we are taking the correct approach.
- Use multiple, broad, simple, topic-based queries at once. For example: `['rate limiting', 'routing rate limiting', 'routing']`. The most relevant results will be returned first.
- Do not add package names to queries; package information is already shared. For example, use `test resource table`, not `filament 4 test resource table`.

### Available Search Syntax

1. Simple Word Searches with auto-stemming - query=authentication - finds 'authenticate' and 'auth'.
2. Multiple Words (AND Logic) - query=rate limit - finds knowledge containing both "rate" AND "limit".
3. Quoted Phrases (Exact Position) - query="infinite scroll" - words must be adjacent and in that order.
4. Mixed Queries - query=middleware "rate limit" - "middleware" AND exact phrase "rate limit".
5. Multiple Queries - queries=["authentication", "middleware"] - ANY of these terms.

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.

## Constructors

- Use PHP 8 constructor property promotion in `__construct()`.
    - `public function __construct(public GitHub $github) { }`
- Do not allow empty `__construct()` methods with zero parameters unless the constructor is private.

## Type Declarations

- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

```php
protected function isAccessible(User $user, ?string $path = null): bool
{
    ...
}
```

## Enums

- Typically, keys in an Enum should be TitleCase. For example: `FavoritePerson`, `BestLake`, `Monthly`.

## Comments

- Prefer PHPDoc blocks over inline comments. Never use comments within the code itself unless the logic is exceptionally complex.

## PHPDoc Blocks

- Add useful array shape type definitions when appropriate.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `make artisan cmd="make:..."` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using the `list-artisan-commands` tool.
- If you're creating a generic PHP class, use `make artisan cmd="make:class"`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

## Database

- Always use proper Eloquent relationship methods with return type hints. Prefer relationship methods over raw queries or manual joins.
- Use Eloquent models and relationships before suggesting raw database queries.
- Avoid `DB::`; prefer `Model::query()`. Generate code that leverages Laravel's ORM capabilities rather than bypassing them.
- Generate code that prevents N+1 query problems by using eager loading.
- Use Laravel's query builder for very complex database operations.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `list-artisan-commands` to check the available options to `make artisan cmd="make:model"`.

### APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## Controllers & Validation

- Always create Form Request classes for validation rather than inline validation in controllers. Include both validation rules and custom error messages.
- Check sibling Form Requests to see if the application uses array or string based validation rules.

## Authentication & Authorization

- Use Laravel's built-in authentication and authorization features (gates, policies, Sanctum, etc.).

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Queues

- Use queued jobs for time-consuming operations with the `ShouldQueue` interface.

## Configuration

- Use environment variables only in configuration files - never use the `env()` function directly outside of config files. Always use `config('app.name')`, not `env('APP_NAME')`.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `make artisan cmd="make:test --phpunit {name}"` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== laravel/v12 rules ===

# Laravel 12

- CRITICAL: ALWAYS use `search-docs` tool for version-specific Laravel documentation and updated code examples.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

## Laravel 12 Structure

- In Laravel 12, middleware are no longer registered in `app/Http/Kernel.php`.
- Middleware are configured declaratively in `bootstrap/app.php` using `Application::configure()->withMiddleware()`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- The `app\Console\Kernel.php` file no longer exists; use `bootstrap/app.php` or `routes/console.php` for console configuration.
- Console commands in `app/Console/Commands/` are automatically available and do not require manual registration.

## Database

- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 12 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models

- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

=== pint/core rules ===

# Laravel Pint Code Formatter

- You must run `./vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `./vendor/bin/pint --test --format agent`, simply run `./vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `make artisan cmd="make:test --phpunit {name}"` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should cover all happy paths, failure paths, and edge cases.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files; these are core to the application.

## Running Tests

- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `make test`.
- To run all tests in a file: `make artisan cmd="test --compact tests/Feature/ExampleTest.php"`.
- To filter on a particular test name: `make artisan cmd="test --compact --filter=testName"` (recommended after making a change to a related file).

=== tailwindcss/core rules ===

# Tailwind CSS

- Always use existing Tailwind conventions; check project patterns before adding new ones.
- IMPORTANT: Always use `search-docs` tool for version-specific Tailwind CSS documentation and updated code examples. Never rely on training data.
- IMPORTANT: Activate `tailwindcss-development` every time you're working with a Tailwind CSS or styling-related task.

</laravel-boost-guidelines>
