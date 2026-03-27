# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Build & Development Commands

### Local Development (without Docker)

```bash
# One-time setup (install deps, generate key, migrate, build frontend)
composer run setup

# Start all development servers concurrently (web, queue, logs, vite)
composer run dev

# Run tests
composer test

# Frontend only
npm run dev      # Development with HMR
npm run build    # Production build

# Database
php artisan migrate
php artisan db:seed
```

### Docker Development

```bash
# Start all containers (app, nginx, mysql, redis)
make up
# or: docker compose up -d

# Stop all containers
make down
# or: docker compose down

# View logs
make logs

# Open shell in app container
make shell

# Run migrations
make migrate

# Run tests
make test

# Fresh migrate with seeders
make fresh

# Run any artisan command
make artisan cmd="make:model Post"

# Build production image
docker build -t ticketing:prod --target=production .

# Test production locally
docker compose -f docker-compose.prod.yml up --build
```

### First Time Docker Setup

1. Copy environment file:
   ```bash
   cp .env.example .env
   ```

2. Start containers:
   ```bash
   make up
   ```

3. Install dependencies:
   ```bash
   make composer-install
   ```

4. Generate app key:
   ```bash
   make artisan cmd="key:generate"
   ```

5. Run migrations:
   ```bash
   make migrate
   ```

6. Build frontend (run on host, or uncomment node service in docker-compose.yml):
   ```bash
   npm install && npm run build
   ```

7. Visit http://localhost:8080

## Architecture

This is a Laravel 12 application with Vite frontend bundler, Tailwind CSS v4, and Livewire.

**Backend (PHP/Laravel):**
- MVC pattern: Models in `app/Models/`, Controllers in `app/Http/Controllers/`
- Livewire components in `app/Livewire/`
- Routes defined in `routes/web.php`
- Queue jobs use database driver
- Sessions and cache use database driver (or Redis in Docker)

**Frontend:**
- Entry points: `resources/js/app.js` and `resources/css/app.css`
- Blade templates in `resources/views/`
- Livewire component views in `resources/views/livewire/`
- Layout components in `resources/views/layouts/`
- Axios for HTTP requests (configured in `resources/js/bootstrap.js`)

**Livewire:**
- Components are in `app/Livewire/`
- Views are in `resources/views/livewire/`
- Use `<livewire:component-name />` or `@livewire('component-name')` in Blade
- Example component: Counter (`/demo` route)

**Docker:**
- Multi-stage Dockerfile (development + production)
- Development: PHP-FPM + Nginx + MySQL + Redis
- Production: Alpine-based with Nginx bundled
- Config files in `docker/nginx/` and `docker/php/`

**Testing:**
- PHPUnit with in-memory SQLite
- Test suites in `tests/Unit/` and `tests/Feature/`
- Run with `make test` (Docker) or `composer test` (local)

## Docker Services

| Service | Port | Description |
|---------|------|-------------|
| nginx   | 8080 | Web server |
| mysql   | 3306 | Database |
| redis   | 6379 | Cache/Sessions |

## Multi-Tenancy Rules (CRITICAL)

This is a multi-tenant SaaS application. Data isolation between tenants is a security requirement. **Every query must be tenant-scoped.**

### How Tenant Scoping Works

- **Models with `BelongsToTenant` trait** (Ticket, Client, Department, Product, etc.) are **automatically scoped** via `TenantScope` global scope — they filter by `tenant_id` column. These are safe by default.
- **The `User` model does NOT use `BelongsToTenant`** — it uses a many-to-many pivot table (`tenant_user`). **User queries are NEVER automatically scoped** and must ALWAYS be manually filtered.

### Mandatory Pattern for User Queries

Every time you query the `User` model in tenant-scoped context (controllers, services, views), you **MUST** filter by tenant:

```php
// CORRECT — always use this pattern
User::query()
    ->whereHas('tenants', fn ($q) => $q->where('tenant_id', session('current_tenant_id')))
    ->orderBy('name')
    ->get();

// CORRECT — for find/findOrFail, chain after tenant filter
User::query()
    ->whereHas('tenants', fn ($q) => $q->where('tenant_id', session('current_tenant_id')))
    ->findOrFail($userId);

// WRONG — leaks users from other tenants
User::query()->orderBy('name')->get();
User::findOrFail($userId);
User::find($userId);
User::all();
```

### Checklist Before Completing Any Feature

1. **Search for unscoped User queries**: Grep for `User::query()`, `User::find`, `User::all`, `User::where` in any file you touched — verify each has tenant filtering.
2. **Blade dropdowns**: Any `<select>` that lists agents/users must be populated from a tenant-scoped query.
3. **Validation rules**: `Rule::exists('users', 'id')` alone is NOT sufficient — the controller must also verify the user belongs to the current tenant before using the ID.
4. **Reports & exports**: Any report that aggregates by user/agent must filter users by tenant.
5. **Admin controllers** (`app/Http/Controllers/Admin/`) are exempt — they operate across tenants intentionally.

### Feature Gating

- Features are gated via `PlanFeature` enum in `app/Enums/PlanFeature.php`
- Route middleware: `->middleware('feature:feature_name')`
- View checks: `@if(app(\App\Services\PlanService::class)->currentTenantHasFeature(\App\Enums\PlanFeature::FeatureName))`
- Plans: Starter (no gated features), Business (10 features), Enterprise (all 18 features)
- See `PlanFeature::businessFeatures()` and `PlanFeature::enterpriseFeatures()` for the full list

## AWS Deployment

The production Docker image is ready for deployment to:
- Amazon ECS (Elastic Container Service)
- AWS Elastic Beanstalk (Docker platform)
- Amazon EKS (Kubernetes)

Build and push to ECR:
```bash
docker build -t ticketing:prod --target=production .
docker tag ticketing:prod <aws-account-id>.dkr.ecr.<region>.amazonaws.com/ticketing:latest
docker push <aws-account-id>.dkr.ecr.<region>.amazonaws.com/ticketing:latest
```

===

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
- laravel/sail (SAIL) - v1
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

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `vendor/bin/sail npm run build`, `vendor/bin/sail npm run dev`, or `vendor/bin/sail composer run dev`. Ask them.

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

<!-- Explicit Return Types and Method Params -->
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

=== sail rules ===

# Laravel Sail

- This project runs inside Laravel Sail's Docker containers. You MUST execute all commands through Sail.
- Start services using `vendor/bin/sail up -d` and stop them with `vendor/bin/sail stop`.
- Open the application in the browser by running `vendor/bin/sail open`.
- Always prefix PHP, Artisan, Composer, and Node commands with `vendor/bin/sail`. Examples:
    - Run Artisan Commands: `vendor/bin/sail artisan migrate`
    - Install Composer packages: `vendor/bin/sail composer install`
    - Execute Node commands: `vendor/bin/sail npm run dev`
    - Execute PHP scripts: `vendor/bin/sail php [script]`
- View all available Sail commands by running `vendor/bin/sail` without arguments.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `vendor/bin/sail artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using the `list-artisan-commands` tool.
- If you're creating a generic PHP class, use `vendor/bin/sail artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

## Database

- Always use proper Eloquent relationship methods with return type hints. Prefer relationship methods over raw queries or manual joins.
- Use Eloquent models and relationships before suggesting raw database queries.
- Avoid `DB::`; prefer `Model::query()`. Generate code that leverages Laravel's ORM capabilities rather than bypassing them.
- Generate code that prevents N+1 query problems by using eager loading.
- Use Laravel's query builder for very complex database operations.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `list-artisan-commands` to check the available options to `vendor/bin/sail artisan make:model`.

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
- When creating tests, make use of `vendor/bin/sail artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `vendor/bin/sail npm run build` or ask the user to run `vendor/bin/sail npm run dev` or `vendor/bin/sail composer run dev`.

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

- You must run `vendor/bin/sail bin pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/sail bin pint --test --format agent`, simply run `vendor/bin/sail bin pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `vendor/bin/sail artisan make:test --phpunit {name}` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should cover all happy paths, failure paths, and edge cases.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files; these are core to the application.

## Running Tests

- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `vendor/bin/sail artisan test --compact`.
- To run all tests in a file: `vendor/bin/sail artisan test --compact tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `vendor/bin/sail artisan test --compact --filter=testName` (recommended after making a change to a related file).

=== tailwindcss/core rules ===

# Tailwind CSS

- Always use existing Tailwind conventions; check project patterns before adding new ones.
- IMPORTANT: Always use `search-docs` tool for version-specific Tailwind CSS documentation and updated code examples. Never rely on training data.
- IMPORTANT: Activate `tailwindcss-development` every time you're working with a Tailwind CSS or styling-related task.

</laravel-boost-guidelines>
