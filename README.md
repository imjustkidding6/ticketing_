# CliqueHA TechDesk

A multi-tenant SaaS helpdesk platform built with Laravel 12. Manage support tickets, track SLAs, and deliver customer support across isolated tenant workspaces.

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | Laravel 12, PHP 8.2+ |
| Frontend | Tailwind CSS 3, Alpine.js, Vite 7 |
| Database | MySQL 8 |
| Cache/Queue | Redis (prod), Database (dev) |
| Auth | Laravel Breeze + Spatie Permission v6 |
| PDF | DomPDF |
| Cloud | AWS S3, SES, SQS |
| Testing | PHPUnit 11 |

## Architecture

### Multi-Tenancy

Session-based multi-tenancy with `BelongsToTenant` trait and `TenantScope` global scope. Each tenant gets isolated data, settings, and a public-facing client portal.

- **Tenant resolution**: `EnsureTenantSession` middleware resolves tenant from session
- **Portal resolution**: `{tenant:slug}` route model binding for client portal routes
- **License-based**: Tenants activate via license keys issued by distributors

### Three-Tier Subscription Plans

| Feature | Start | Business | Enterprise |
|---------|:-----:|:--------:|:----------:|
| Max Users | 5 | 25 | Unlimited |
| Max Tickets/Month | 100 | 500 | Unlimited |
| Audit Logs | - | Yes | Yes |
| Ticket Billing | - | Yes | Yes |
| Spam Management | - | Yes | Yes |
| Service Reports (PDF) | - | Yes | Yes |
| File Attachments | - | Yes | Yes |
| Agent Scheduling | - | Yes | Yes |
| SLA Management | - | Yes | Yes |
| SLA Compliance Report | - | Yes | Yes |
| Email Notifications | - | Yes | Yes |
| Detailed Reporting | - | Yes | Yes |
| Ticket Merging | - | - | Yes |
| Ticket Reopening | - | - | Yes |
| Custom Roles & Permissions | - | - | Yes |
| Department Management | - | - | Yes |
| Agent Escalation | - | - | Yes |
| Client-Agent Comments | - | - | Yes |

Features are gated via `CheckPlanFeature` middleware (`feature:feature_name`).

### Authentication Flow

| User Type | Login URL | Redirects To |
|-----------|-----------|-------------|
| Admin (system owner) | `/admin/login` | `/admin` (Admin Console) |
| Tenant user (agent/manager) | `/login` | `/dashboard` (auto-resolves tenant) |
| Client (portal) | `/portal/{slug}/login` | `/portal/{slug}/dashboard` |

Admins who accidentally use `/login` are auto-redirected to `/admin`.

### Client Portal (Public)

Each tenant has a public portal at `/portal/{tenant-slug}/`:

- **Guest ticket submission** — No login required. Name + email + details. Auto-creates Client record.
- **Ticket tracking** — By ticket number + email, or via unique tracking token URL.
- **Authenticated dashboard** — Registered clients can view all their tickets.

## Project Structure

```
app/
├── Enums/              # PlanFeature enum
├── Http/
│   ├── Controllers/
│   │   ├── Admin/      # Admin console controllers
│   │   └── Auth/       # Authentication controllers
│   ├── Middleware/      # Admin, Tenant, Feature, Portal middleware
│   └── Requests/       # Form request validation
├── Models/             # 20 Eloquent models
├── Services/           # Business logic (Ticket, SLA, Report, Escalation, etc.)
└── Traits/             # BelongsToTenant trait

resources/views/
├── admin/              # Admin console views (dashboard, tenants, plans, licenses)
├── auth/               # Login, register, password reset
├── client-portal/      # Public portal (landing, track, dashboard, tickets)
├── layouts/            # App, guest, portal layouts
├── tickets/            # Ticket management views
├── reports/            # Report views
├── settings/           # Tenant settings views
└── ...                 # Departments, categories, clients, products, roles, etc.

routes/
├── web.php             # Main app + admin routes
├── portal.php          # Client portal routes
├── auth.php            # Authentication routes
└── console.php         # Console commands
```

## Setup

### Prerequisites

- PHP 8.2+
- Composer
- Node.js 18+
- MySQL 8

### Installation

```bash
# Clone and install
git clone <repo-url> && cd ticketing
cp .env.example .env

make up
make composer-install
make artisan cmd="key:generate"
make migrate
make seed

# Build frontend assets (host machine)
npm install
npm run build
```

Visit http://localhost:8080

If you change database credentials or see MySQL auth errors, recreate volumes:

```bash
docker compose -p ticketing down -v
make up
```

### Local (No Docker)

```bash
git clone <repo-url> && cd ticketing
composer install
npm install

# Environment
cp .env.example .env
php artisan key:generate

# Database
php artisan migrate
php artisan db:seed

# Build frontend
npm run build

# Start development
composer run dev
```

### Default Credentials (after seeding)

| Scope | Role | Email | Password |
|-------|------|-------|----------|
| System | Admin | admin@example.com | password |
| Demo tenant | Tenant User | test@example.com | password |
| Start plan | Tenant Admin | start-admin@example.com | password |
| Start plan | Agent | start-agent@example.com | password |
| Start plan | Client | start-client@example.com | password |
| Business plan | Tenant Admin | business-admin@example.com | password |
| Business plan | Agent | business-agent@example.com | password |
| Business plan | Client | business-client@example.com | password |
| Enterprise plan | Tenant Admin | enterprise-admin@example.com | password |
| Enterprise plan | Agent | enterprise-agent@example.com | password |
| Enterprise plan | Client | enterprise-client@example.com | password |

### Docker (optional)

```bash
make up          # Start containers
make migrate     # Run migrations
make test        # Run tests
make shell       # Open app shell
```

## Development Commands

```bash
# Development server
composer run dev

# Run tests
php artisan test --compact

# Code formatting
php vendor/bin/pint --dirty

# Single test
php artisan test --compact --filter=testName

# Frontend
npm run dev      # HMR
npm run build    # Production
```

## What Has Been Built

### Core Platform
- Multi-tenant architecture with session-based tenant isolation
- License management system (distributors issue license keys to tenants)
- Three-tier subscription plans with feature gating
- User authentication with tenant context resolution
- Separate admin and tenant login flows

### Ticketing System
- Full ticket lifecycle: create, assign, status changes, close
- Agent self-assignment and manager assignment
- Ticket tasks (sub-tasks within tickets)
- Ticket comments with internal notes and client-visible replies
- Ticket history / audit log
- Ticket merging (Enterprise)
- Ticket reopening (Enterprise)
- Spam management (Business+)
- File attachments with S3 support (Business+)

### SLA & Escalation
- SLA policy management with response/resolution targets (Business+)
- SLA compliance tracking and reporting
- Agent tiering and escalation workflows (Enterprise)

### Reporting
- Ticket volume reports with date range filtering
- Department performance reports
- Agent performance reports (Business+)
- SLA compliance reports (Business+)
- CSV/Excel export for all reports
- Auto-generated PDF service reports (Business+)

### Agent Management
- Agent availability scheduling (Business+)
- Custom roles and permissions via Spatie (Enterprise)
- Department-based team organization

### Client Portal
- Public landing page per tenant with guest ticket submission
- Ticket tracking by number + email or unique token
- Client registration and authenticated dashboard
- Auto-links guest clients to registered accounts

### Admin Console
- Global admin dashboard with tenant/ticket/plan stats
- Tenant management (view, suspend, unsuspend, change plan)
- Tenant usage monitoring (ticket counts, seat usage)
- License management (issue, activate, revoke)
- Distributor management
- Plan management (create, edit plans)
- Tenant impersonation (view tenant as tenant owner)
- Separate admin login at `/admin/login` with dark-themed UI

### SaaS Marketing Page
- Public landing page at `/` with hero, features grid, and dynamic pricing cards
- Plan data pulled from database
- Contact Sales CTA

### Infrastructure
- AWS-ready: S3 storage, SES email, SQS queues
- Docker support (development + production)
- Health check endpoint
- Configurable tenant settings (general, ticket, notifications)

## Planned Next Steps

### Phase 1: Email Notifications
- Ticket creation confirmation emails (to client)
- Ticket assignment notification (to agent)
- Status change notifications
- SLA breach warning emails
- Use Laravel Notifications with queued delivery via SES/SQS

### Phase 2: Knowledge Base
- Tenant-scoped knowledge base articles
- Categories and search
- Public-facing KB on client portal
- Article suggestions during ticket creation

### Phase 3: Canned Responses
- Pre-built response templates per tenant
- Quick insert into ticket replies
- Category-based organization

### Phase 4: Dashboard Widgets & Real-Time
- Live ticket counters with WebSocket/Pusher
- Agent online status
- Real-time ticket updates on client portal
- Dashboard charts (Chart.js or similar)

### Phase 5: Advanced Automation
- Auto-assignment rules (round-robin, load-based, department-based)
- Auto-close stale tickets after configurable period
- Trigger-based actions (e.g., auto-escalate if no response in X hours)
- Macros for bulk ticket operations

### Phase 6: API & Integrations
- RESTful API with Laravel Sanctum (API tokens per tenant)
- Webhook support for ticket events
- Slack/Teams integration for notifications
- Zapier-compatible webhook payloads

### Phase 7: Multi-Language & Localization
- Full i18n support (views already use `__()` helpers)
- Per-tenant locale settings
- Translatable knowledge base articles

### Phase 8: Billing & Payments
- Stripe/Paddle integration for subscription billing
- Usage-based billing (per ticket overage)
- Invoice generation
- Plan upgrade/downgrade self-service

### Phase 9: Advanced Reporting
- Custom report builder
- Scheduled report delivery via email
- Customer satisfaction (CSAT) surveys after ticket close
- First response time and resolution time analytics

### Phase 10: Mobile & PWA
- Progressive Web App support
- Mobile-optimized agent interface
- Push notifications for ticket updates

## License

Proprietary. All rights reserved.
