# Development Log — Ticketing SaaS Platform

## Project Overview

A multi-tenant SaaS ticketing system built with Laravel 12, Tailwind CSS v4, and Livewire. The platform supports three subscription tiers (Starter, Business, Enterprise) with feature gating, per-tenant branding, and complete data isolation.

---

## Architecture

- **Framework:** Laravel 12 with Blade templates
- **Frontend:** Tailwind CSS v4, Alpine.js for interactivity
- **Multi-Tenancy:** Custom implementation using `BelongsToTenant` trait with `TenantScope` global scope
- **Feature Gating:** `PlanFeature` enum + `CheckPlanFeature` middleware on routes + `@if` checks in views
- **Roles & Permissions:** Spatie Permission package with team support (`tenant_id` as team foreign key)
- **Testing:** PHPUnit (feature tests) + Playwright (E2E browser tests)
- **Database:** MySQL with per-tenant data isolation via `tenant_id` columns

---

## Subscription Plans & Features

### Starter Plan
Built-in features (no feature gates):
- Admin & Personal Dashboard
- Ticket Creation & Management (CRUD, status, priority, assignment)
- Ticket Task Creation & Checklist with status tracking
- Product/Services Offered Management
- Category Management
- User Roles (Admin, Manager, Agent) — default roles, non-customizable
- Fixed Departments (seeded, no CRUD)
- Basic Reporting
- Client Management
- Dark/Light Theme
- Branding (Logo, Colors per tenant)
- **No public portal** — Starter tenants cannot have self-service ticket submission

### Business Plan
All Starter features plus:
| Feature | Gate Key | Description |
|---------|----------|-------------|
| Ticket Activity History | `audit_logs` | Full audit timeline on tickets + dedicated Activity Logs page |
| Billing | `billing` | Mark tickets as billable, billing report with CSV export |
| Mark as Spam | `spam_management` | Mark/unmark tickets as spam with reason |
| Service Reports | `service_reports` | Auto-generated service reports with PDF download |
| Attachments | `attachments` | File upload/download on tickets |
| Agent Schedule | `agent_schedule` | Agent availability schedule setup & team dashboard |
| SLA Management | `sla_management` | SLA policies by client tier and priority |
| SLA Compliance Report | `sla_report` | SLA compliance analytics |
| Email Notifications | `email_notifications` | Per-tenant SMTP configuration, custom email templates, test email |
| Detailed Reporting | `detailed_reporting` | CSV export on all reports |
| **Public Portal** | _(plan-level gate)_ | Landing page, public ticket submission, ticket tracking |

### Enterprise Plan
All Business features plus:
| Feature | Gate Key | Description |
|---------|----------|-------------|
| Ticket Merging | `ticket_merging` | Merge duplicate tickets into a target ticket |
| Ticket Re-Opening | `ticket_reopening` | Reopen closed tickets with counter |
| Custom Roles & Permissions | `custom_roles` | Create/edit custom roles with granular permissions |
| Department Management | `department_management` | Full CRUD for departments |
| Agent Tiering & Escalation | `agent_escalation` | 3-tier agent system with escalation workflow |
| Comments & Updates | `client_comments` | Agent-client communication with attachments |
| Knowledge Base | `knowledge_base` | KB articles & categories with public portal |
| Canned Responses | `canned_responses` | Pre-written response templates insertable into comments |

---

## Work Completed

### Phase 1: Foundation & Starter Plan (Commits: `839f968` → `16f385a`)
- Initial Laravel 12 project setup with Docker support
- Multi-tenant architecture with `Tenant`, `License`, `Plan` models
- `BelongsToTenant` trait and `TenantScope` for automatic data isolation
- `PlanFeature` enum defining all 18 feature gates across 3 plans
- `PlanSeeder` with Starter (5 users, 100 tickets), Business (25 users, 500 tickets), Enterprise (unlimited)
- Core ticket system: CRUD, status workflow, priority, assignment, task checklist
- Client management with tiers (basic, standard, premium, enterprise)
- Product/Services and Category management
- Department system (fixed/seeded for Starter)
- User management with Admin/Manager/Agent roles
- Dashboard with ticket statistics
- Basic reporting (overview, by department, category, client, agent, product)
- Branding system (per-tenant logo, primary/accent colors, dark mode colors)
- Dark/Light theme toggle

### Phase 2: Business Plan Features (Commits: `2979624` → `658b680`)
- **Email Notifications:** Custom Blade email templates for ticket-created, ticket-assigned, ticket-status-changed (both agent and client variants). `TenantMailService` for per-tenant SMTP configuration. Test email functionality in notification settings.
- **Billing:** Billing report with summary cards, filters, CSV export. Feature-gated billing fields on tickets.
- **SLA Integration:** SLA lookup on ticket create/edit for auto-filling response/resolution times based on client tier + priority.
- **Audit Log Timeline:** Color-coded activity history on ticket show page with icons per action type.
- **Spam Management UI:** Mark/unmark spam buttons in ticket sidebar with reason field.
- **Detailed Reporting Gate:** All CSV export routes and buttons gated behind `detailed_reporting` feature.
- **Service Report PDF:** Enhanced service report template.
- **General Settings:** Extended with company email, phone, address, website fields.

### Phase 3: Enterprise Plan Features
- **Ticket Re-Opening UI:** Amber "Reopen Ticket" button in Status card (visible on closed tickets only). Tracks reopen count.
- **Agent Escalation UI:** Full sidebar card with tier selector (only shows higher tiers), agent reassignment filtered by tier, reason field, and escalation history timeline. Tier enforcement in controller — cannot escalate to same/lower tier, assigned agent must match target tier level. Owner bypasses tier restrictions.
- **Ticket Merging UI:** Sidebar card with target ticket dropdown and confirm button. Shows merge notice with link if ticket was already merged.
- **Client Comments UI:** Full comment list in left column with internal (yellow) / public (green) type badges, edit/delete controls for own comments, add comment form with type selector and file attachments (max 5 files, 10MB each). Canned response insertion dropdown.
- **Comment Attachments:** File upload on both agent comments and client replies. Download route for attachments. Cleanup on comment deletion.
- **Custom Roles & Permissions:** Roles index with "Default" badges for admin/manager/agent. Default roles cannot be deleted. Permission enforcement via `checkPermission()` in base Controller — owners bypass all checks.
- **Department Management:** Full CRUD gated behind Enterprise. Cannot delete default departments or departments with categories.
- **Agent Tiering:** Support tier selector (Tier 1/2/3) in member create/edit, gated behind `agent_escalation` feature. Tier info box explaining tier responsibilities.

### Phase 4: Public Portal
- **Landing Page** (`/{slug}/`): Hero section with tenant branding, "How can we help you today?" heading, two action cards (Submit New Ticket / Track Existing Ticket), contact info from tenant settings. Business+ only — Starter gets 404.
- **Submit Ticket** (`/{slug}/submit-ticket`): Full form matching internal ticket creation — name, email, subject with KB suggestions, cascading department→category selects, products/services multi-select, description, priority, incident date/time. Public API routes for cascading selects.
- **Track Ticket** (`/{slug}/track-ticket`): Search form with ticket number + email. Redirects to full details page on match. Auto-generates tracking token for tickets without one.
- **Track Result** (`/{slug}/track-ticket/{token}`): 2-column layout with ticket details (subject, description, attachments, status explanation), sidebar (status card with human-readable descriptions, ticket info, quick actions). Enterprise tenants see Comments & Updates section with reply form and file attachments.
- **Client Replies** (Enterprise only): Clients can reply to tickets via the tracking page with text + file attachments. Comments show with "You" label for client replies and agent name for agent comments.
- **Portal Route Removal:** Removed `/portal/{tenant}/` routes entirely. All public portal functionality lives under `/{slug}/`.
- **Plan Gating:** Starter tenants get 404 on all public portal pages. Business+ tenants have full access.

### Phase 5: Security & Quality
- **Tenant Scoping Audit:** All `User::query()` calls across controllers and services verified to include `whereHas('tenants', ...)` filtering. Documented in CLAUDE.md as mandatory pattern.
- **Plan Change Cache Fix:** `PlanService::clearCache()` now called when admin changes a tenant's plan, preventing stale feature access.
- **Permission Enforcement:** Added `checkPermission()` to base Controller. Enforced on: TicketController (view, create, edit, assign, delete), ClientController (manage clients), MemberController (manage users), RoleController (manage roles), ReportController (view reports), AppSettingController (manage settings). Owners bypass all permission checks.
- **Activity Logging:** Extensive ticket history logging for: comments (add/edit/delete), tasks (add/update/status change/delete), ticket reopen, ticket delete/restore, plus existing: create, status change, assign, priority change, billing, spam, escalation, merge.
- **Role Seeding Fix:** Fixed `TenantRoleService::setupDefaultRoles()` to use `setTenantContext()` instead of passing team_id directly. Cleaned up orphan roles with null `tenant_id`.
- **RoleController Fix:** Changed `team_id` to `tenant_id` to match Spatie Permission config.
- **Error Pages:** Custom 404 (sad face), 403 (lock), 500 (wrench), 419 (clock), 503 (maintenance with inline CSS).

### Phase 6: Testing
- **PHPUnit Feature Tests (26 new tests):**
  - `TicketReopeningTest` — plan gate, reopen closed ticket, count increment, history entry
  - `AgentEscalationTest` — plan gate, escalate to tier, with/without agent reassignment, tier validation
  - `TicketMergingTest` — plan gate, merge into target, validation
  - `ClientCommentTest` — plan gate, add public/internal comment, update, delete, validation
  - `CustomRoleTest` — plan gate (CRUD tests deferred due to pre-existing schema issue)
  - `DepartmentManagementTest` — plan gate, create, update, delete guards
- **Playwright E2E Tests (16 tests):**
  - Starter tenant 404 enforcement (landing, submit, track)
  - Business portal accessibility (landing, submit, track, form fields)
  - Cross-tenant data isolation (cannot track other tenant's tickets)
  - Invalid tracking token 404
  - Business plan hides comments section
  - Authenticated dashboard tenant context verification
  - Agent tiering hidden on Business plan
  - Portal routes removed (404)

---

## Technical Decisions

| Decision | Rationale |
|----------|-----------|
| `BelongsToTenant` trait for auto-scoping | Prevents accidental cross-tenant data leaks. User model excluded (uses pivot table). |
| Feature gates via middleware + view checks | Double protection: routes return 403, views hide UI elements. |
| Owner bypasses permissions | Owner is the tenant creator — full access without role assignment needed. |
| Tracking tokens for public ticket access | Stateless ticket access without requiring login. Auto-generated on first track. |
| Per-tenant SMTP | Each tenant configures their own email delivery. Falls back to system SMTP. |
| Notifications switched to sync | Removed `ShouldQueue` from notifications for simpler debugging during development. |
| Public portal under `/{slug}/` not `/portal/` | Cleaner URLs, single route prefix for all tenant content. |
| KB portal moved to `/{slug}/kb/` | Avoids route conflict with admin KB routes at `/{slug}/knowledge-base/`. |
| Agent tier enforcement on escalation | Only agents at or above the target tier can be assigned. Owner always eligible. |
| `team_foreign_key` set to `tenant_id` | Spatie Permission teams feature uses `tenant_id` column instead of default `team_id`. |

---

## File Structure (Key Files)

```
app/
  Enums/PlanFeature.php              # All 18 feature gate definitions
  Http/Controllers/
    Controller.php                    # Base with checkPermission() helper
    TicketController.php              # Full ticket CRUD + actions
    TicketCommentController.php       # Comments with attachments
    TicketTaskController.php          # Tasks with activity logging
    EscalationController.php          # Tier-enforced escalation
    ClientPortalController.php        # Public portal (submit, track, reply)
    ActivityLogController.php         # Activity logs page
    RoleController.php                # Custom roles CRUD
    MemberController.php              # User management with tier config
    ReportController.php              # All reports + CSV exports
    AppSettingController.php          # Settings (general, ticket, notifications, branding)
    KbPortalController.php            # Public knowledge base
  Http/Middleware/
    CheckPlanFeature.php              # Feature gate middleware
    EnsureTenantSession.php           # Tenant resolution + permissions
  Models/
    Ticket.php                        # Core ticket with SLA, merge, spam fields
    TicketHistory.php                 # Audit log entries
    TicketComment.php                 # Comments with attachments (JSON)
    Tenant.php                        # Multi-tenant with branding
    Plan.php                          # Subscription plans with features array
  Services/
    PlanService.php                   # Feature access with caching
    TicketService.php                 # Ticket operations + addHistory()
    EscalationService.php             # Tier escalation logic
    TicketMergeService.php            # Merge/unmerge operations
    TenantRoleService.php             # Role/permission setup
    TenantMailService.php             # Per-tenant SMTP configuration
    ReportService.php                 # Report data aggregation
  View/Components/
    ClientPortalLayout.php            # Portal layout with hideNav support

resources/views/
  tickets/show.blade.php              # Full ticket view with all Enterprise features
  tenant/                             # Public portal views
    landing.blade.php                 # Portal landing page
    submit-ticket.blade.php           # Public ticket submission
    track-ticket.blade.php            # Ticket search
    track-result.blade.php            # Ticket details + comments + reply
  activity-logs/index.blade.php       # Activity logs page
  errors/                             # Custom error pages (404, 403, 500, 419, 503)

routes/
  tenant.php                          # All tenant-scoped routes (/{slug}/...)
  web.php                             # Auth, admin, home routes

tests/
  Feature/                            # 26+ PHPUnit feature tests
  e2e/                                # 16 Playwright E2E tests
    tenant-portal.spec.ts
    tenant-isolation.spec.ts

playwright.config.ts                  # Playwright config (headless: false, slowMo: 500)
```

---

## Known Issues / Technical Debt

1. **`created_by` not nullable on tickets table** — Public ticket submissions pass `created_by => null` which will fail on strict databases. Needs a migration to make the column nullable.
2. **Custom Roles CRUD tests deferred** — The RoleController uses `tenant_id` column correctly now, but some CRUD tests were deferred due to Spatie Permission cache behavior in tests.
3. **TenantBranding tests fail** — 3 pre-existing test failures due to filesystem permission on test storage directory.
4. **Notifications are synchronous** — `ShouldQueue` was removed from notifications. Should be re-enabled for production with proper queue worker setup.
5. **No API endpoints** — All functionality is server-rendered Blade. REST API would be needed for mobile apps.
6. **No bulk operations** — No bulk ticket actions (mass assign, mass close, etc.).
