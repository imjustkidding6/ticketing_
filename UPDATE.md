# Phase 1 Update — CliqueHA TechDesk

**Branch:** `02262026_phase1_tenant`
**Date:** March 13, 2026

---

## Summary

This update introduces the full multi-tenant ticketing system with plan-tiered feature separation, role-based access control, user management, and a complete UI overhaul migrating from Tailwind CSS v3 to v4.

---

## What's New

### 1. Multi-Tenant Architecture
- Tenant model with license & plan associations
- Tenant switching for users belonging to multiple organizations
- Tenant-scoped routes under `/{slug}/` prefix
- `EnsureTenantSession` middleware for automatic tenant resolution
- `SetTenantUrlDefaults` middleware for URL generation
- Session-based current tenant tracking via `HasTenants` trait

### 2. Plan-Tiered Feature Separation
Three plan tiers with feature gating via `PlanFeature` enum and `CheckPlanFeature` middleware:

| Feature | Starter | Business | Enterprise |
|---------|:-------:|:--------:|:----------:|
| Tickets, Clients, Categories, Products | ✓ | ✓ | ✓ |
| Reports Overview | ✓ | ✓ | ✓ |
| App Settings & Branding | ✓ | ✓ | ✓ |
| User Management | ✓ | ✓ | ✓ |
| SLA Management | | ✓ | ✓ |
| Agent Schedules | | ✓ | ✓ |
| Knowledge Base | | ✓ | ✓ |
| Canned Responses | | ✓ | ✓ |
| Email Notifications | | ✓ | ✓ |
| Detailed Reporting (Agent Perf, SLA Compliance) | | ✓ | ✓ |
| Service Reports | | ✓ | ✓ |
| Department Management | | | ✓ |
| Custom Roles & Permissions | | | ✓ |
| Ticket Merging & Reopening | | | ✓ |
| Agent Escalation | | | ✓ |
| Client Comments | | | ✓ |

### 3. User Management (All Plans)
- **Card-based user list** with role badges (Admin=red, Manager=blue, Agent=green), department badges, support tier badges, and ticket counts
- **Add users** with role selection (Admin/Manager/Agent) using radio cards with descriptions
- **Support Agent Configuration** — department assignment, support tier (1-3), availability status (shown for Agent/Manager roles)
- **User profile view** — stats (created/assigned/closed tickets), account info, permissions checklist, recent tickets, assigned tickets table
- **Edit users** — update name, email, password, role, support config; remove from organization
- **Activate/Deactivate** users via soft delete toggle
- **Seat management** — respects license seat limits, shows used/total seats

### 4. Role-Based Sidebar Navigation
Sidebar sections with dual gating (tenant role + plan feature):

- **Main** (all users, all plans): Dashboard, Tickets, Clients
- **Management** (owner/admin): User Management, Categories, Products, Departments (Enterprise)
- **Knowledge Base** (Business+, all users): KB Categories, KB Articles
- **SLA** (Business+, owner/admin): SLA Policies
- **Reports** (owner/admin): Overview (all plans), Agent Performance, SLA Compliance, Service Reports (Business+)
- **Schedule** (Business+): My Schedule (all users), Team Schedule (owner/admin)
- **Settings** (owner/admin): App Settings (all plans), Notifications, Canned Responses (Business+), Roles & Permissions (Enterprise)
- **Admin** (global admin only): Admin Panel

### 5. Sidebar UI Overhaul
- Fixed sidebar with logo + app name header
- Tenant name display with color-coded plan badge (amber=Enterprise, blue=Business, gray=Starter)
- Tenant switch link for multi-tenant users
- User menu at bottom with avatar initials, name, email, profile/logout dropdown
- Active state with indigo highlighting
- Mobile responsive with overlay + hamburger toggle
- Notification bell with unread count and dropdown

### 6. Tailwind CSS v3 → v4 Migration
- Replaced PostCSS-based config with `@tailwindcss/vite` plugin
- `@source` directives for content detection
- `@plugin "@tailwindcss/forms"` for form styling
- `@theme` block for custom font configuration
- Removed `tailwind.config.js` and `postcss.config.js`
- Added `[x-cloak]` CSS rule for Alpine.js

### 7. Ticketing System
- Full ticket CRUD with assignment, status changes, task management
- Ticket filtering by status, priority, category, assignment
- Client management with tiers (Basic/Premium/Enterprise)
- Product & category management
- Ticket history tracking
- Client portal with public ticket submission and tracking via token

### 8. Additional Features
- **Admin Panel** — global admin dashboard, tenant management, impersonation
- **SLA Policies** — configurable response/resolution times with breach notifications
- **Agent Schedules** — personal and team schedule management
- **Knowledge Base** — categories and articles with search
- **Canned Responses** — reusable reply templates
- **Service Reports** — generate and download reports per ticket
- **Reports** — overview, agent performance, SLA compliance with export
- **Notification System** — in-app notifications with real-time unread count polling
- **Tenant Branding** — custom logo, primary/accent colors per tenant

---

## Database Changes

### New Tables
- `tenants`, `licenses`, `plans`, `tenant_user` (pivot with role)
- `departments`, `ticket_categories`, `products`, `clients`
- `tickets`, `ticket_assignments`, `ticket_tasks`, `ticket_histories`, `ticket_comments`, `ticket_escalations`
- `sla_policies`, `agent_schedules`, `app_settings`
- `kb_categories`, `kb_articles`, `canned_responses`
- `service_reports`, `notifications`
- Spatie permission tables (`roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`)

### Modified Tables
- `users` — added `is_admin`, `support_tier` (enum: tier_1/2/3), `is_available`, `department_id`, `deleted_at` (soft deletes)
- `plans` — added feature columns
- `tenants` — added branding columns (`logo_path`, `primary_color`, `accent_color`)
- `tickets` — added `tracking_token`, `sla_breach_notified_at`

### Run Migrations
```bash
php artisan migrate
php artisan db:seed
```

---

## Dependencies Added

### PHP (Composer)
- `spatie/laravel-permission` — role & permission management with team/tenant support

### JavaScript (npm)
- `@tailwindcss/vite` — Tailwind CSS v4 Vite plugin
- `@tailwindcss/forms` — form element styling
- `alpinejs` — lightweight JS framework for interactivity

### Removed
- `tailwindcss` (v3 PostCSS plugin — replaced by v4 Vite plugin)
- `postcss`, `autoprefixer` (no longer needed with v4)

---

## Configuration

### Spatie Permissions (`config/permission.php`)
- Teams feature enabled (`'teams' => true`)
- Team foreign key set to `tenant_id` (`'team_foreign_key' => 'tenant_id'`)

### Environment Variables
No new environment variables required. Existing `.env` works as-is.

---

## Testing

All 213 tests passing (422 assertions).

```bash
php artisan test --compact
```

---

## Known Limitations / Future Work
- **User invitation via email** — currently users are created directly with passwords; email invitation flow not yet implemented
- **Escalation Dashboard** — only ticket-level escalation action exists, no standalone dashboard view
- **Additional report types** — Client, Category, Product, Billing, Department reports not yet implemented
- **Real-time notifications** — currently uses polling (30s interval); WebSocket support planned
