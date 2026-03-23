# Starter Features Refinement — CliqueHA TechDesk

**Branch:** `02262026_phase1_tenant`
**Date:** March 16, 2026
**Reference:** `starter/basic_plan` branch from `cliqueha_ticketing` project

---

## Summary

This update ports and adapts all starter-tier features from the reference project (`starter/basic_plan` branch of `cliqueha_ticketing`) into the current multi-tenant SaaS ticketing system. Features have been adapted from the React + Inertia.js architecture to Blade + Alpine.js.

---

## What Was Added

### 1. Ticket Workflow System

**New files:**
- `app/Services/TicketWorkflowService.php` — Manages ticket status transitions with validation
- `app/Http/Controllers/WorkflowController.php` — Workflow dashboard, analytics, status transitions
- `resources/views/workflow/dashboard.blade.php` — Workflow metrics + recent tickets
- `resources/views/workflow/analytics.blade.php` — Date-range analytics with KPI cards

**Features:**
- Valid status transition map: `open → assigned → in_progress → on_hold → closed/cancelled`
- Duplicate status change prevention (5-second cache lock)
- Task completion validation before closing (all tasks must be completed/cancelled)
- Auto-advance to next logical status
- First response time tracking on `in_progress`
- Workflow metrics: open, assigned, in_progress, on_hold, closed today/week, overdue, unassigned
- Workflow analytics: resolution/response time averages, priority/status breakdowns, false alarm count

**Routes:**
| Method | URI | Name |
|--------|-----|------|
| GET | `/{slug}/workflow` | `workflow.dashboard` |
| GET | `/{slug}/workflow/analytics` | `workflow.analytics` |
| POST | `/{slug}/workflow/tickets/{ticket}/status` | `workflow.update-status` |
| POST | `/{slug}/workflow/tickets/{ticket}/next-status` | `workflow.next-status` |

---

### 2. Enhanced Ticket Management

**Updated files:**
- `app/Http/Controllers/TicketController.php` — Added 6 new methods
- `app/Models/Ticket.php` — Added fields, methods, scopes, relationships

**New ticket features:**

| Feature | Description |
|---------|-------------|
| **Search** | Dedicated search page — matches ticket_number, subject, description, client name |
| **Trashed tickets** | View soft-deleted tickets with restore and permanent delete |
| **Restore** | Restore soft-deleted tickets |
| **Force delete** | Permanently delete with attachment cleanup |
| **False alarm** | Mark ticket as false alarm and auto-close |
| **Child tickets** | Create linked child tickets inheriting parent's client/department/category |

**New routes:**
| Method | URI | Name |
|--------|-----|------|
| GET | `/{slug}/tickets-search` | `tickets.search` |
| GET | `/{slug}/tickets-trashed` | `tickets.trashed` |
| POST | `/{slug}/tickets/{ticket}/restore` | `tickets.restore` |
| DELETE | `/{slug}/tickets/{ticket}/force-delete` | `tickets.force-delete` |
| POST | `/{slug}/tickets/{ticket}/false-alarm` | `tickets.false-alarm` |
| POST | `/{slug}/tickets/{ticket}/child` | `tickets.create-child` |

**New views:**
- `resources/views/tickets/search.blade.php`
- `resources/views/tickets/trashed.blade.php`

**New Ticket model methods:**
- `startHold()` / `endHold()` / `getTotalHoldTimeMinutes()` — Hold time management
- `getEffectiveResolutionTimeHours()` / `getEffectiveResponseTimeHours()` — Excluding hold time
- `mergeInto(Ticket, User)` — Merge ticket into target
- `reopen(User)` — Reopen closed/cancelled ticket
- `addToHistory(...)` — Direct history entry from model

**New scopes:** `billable()`, `billed()`, `unbilled()`

**New relationships:** `mergedTickets()`, `deletedByUser()`

---

### 3. Incident & Service Date Tracking

**Migration:** `2026_03_16_000001_add_starter_fields_to_tickets_table.php`

New columns on `tickets` table:
- `incident_date` — When the incident occurred (timestamp, nullable)
- `preferred_service_date` — Client's preferred service date (timestamp, nullable)
- `is_false_alarm` — Whether the ticket was a false alarm (boolean, default false)

---

### 4. Task Status History

**Migration:** `2026_03_16_000002_create_task_status_histories_table.php`

**New model:** `app/Models/TaskStatusHistory.php`
- Tracks every task status change with old/new status, user, notes, metadata
- `getStatusChangeDescription()` — Human-readable change description

**Updated model:** `app/Models/TicketTask.php`
- `statusHistory()` relationship
- `updateStatus(string, User, ?string)` — Updates status and records history
- `markAsCompleted()`, `markAsInProgress()`, `markAsPending()`, `markAsCancelled()` — Convenience methods
- `canChangeStatus()` — Returns false if cancelled
- `getStatusProgressPercentage()` — Returns 0/50/100 based on status
- `getLatestStatusUpdate()` — Most recent history entry

**New task routes:**
| Method | URI | Name |
|--------|-----|------|
| POST | `/{slug}/tickets/{ticket}/tasks/bulk-update` | `tickets.tasks.bulk-update` |
| POST | `/{slug}/tickets/{ticket}/tasks/bulk-status-update` | `tickets.tasks.bulk-status-update` |
| GET | `/{slug}/tickets/{ticket}/tasks/{task}/history` | `tickets.tasks.history` |

---

### 5. Client Agent Assignment

**Migration:** `2026_03_16_000003_create_client_agent_assignments_table.php`

**New model:** `app/Models/ClientAgentAssignment.php`
- Monthly agent-to-client assignment tracking
- Scopes: `currentMonth()`, `active()`
- Unique constraint: one agent per client per month

**Updated:** `app/Http/Controllers/ClientController.php` — `assignAgent()` method
**Updated:** `app/Models/Client.php` — `agentAssignments()` relationship, `currentAgent()` method

**New route:**
| Method | URI | Name |
|--------|-----|------|
| POST | `/{slug}/clients/{client}/assign-agent` | `clients.assign-agent` |

---

### 6. SMS Message Infrastructure

**Migration:** `2026_03_16_000004_create_sms_messages_table.php`

**New model:** `app/Models/SmsMessage.php`
- Stores SMS messages with sender, content, recv_time
- Links to tickets via `ticket_id`
- Ready for gateway integration (Tg200 or other)

---

### 7. Agent Performance Service

**New file:** `app/Services/AgentPerformanceService.php`

Methods:
- `getAgentPerformanceReport(User, from, to)` — Per-agent metrics (assigned, open, closed, avg resolution, avg response, overdue, by priority/status)
- `getAllAgentsPerformance(from, to)` — All agents in current tenant
- `getTeamPerformanceMetrics(from, to)` — Team-wide closure rate, avg resolution, overdue, unassigned

---

### 8. Admin User Management

**New controller:** `app/Http/Controllers/Admin/UserController.php`

| Method | URI | Name |
|--------|-----|------|
| GET | `/admin/users` | `admin.users.index` |
| GET | `/admin/users/create` | `admin.users.create` |
| POST | `/admin/users` | `admin.users.store` |
| GET | `/admin/users/{user}/edit` | `admin.users.edit` |
| PUT | `/admin/users/{user}` | `admin.users.update` |
| POST | `/admin/users/{user}/toggle-status` | `admin.users.toggle-status` |

**New views:**
- `resources/views/admin/users/index.blade.php` — Search, filter by department/status, toggle active
- `resources/views/admin/users/create.blade.php` — Create form with department, admin flag, support tier
- `resources/views/admin/users/edit.blade.php` — Edit form

---

### 9. Admin Performance Dashboard

**New controller:** `app/Http/Controllers/Admin/PerformanceController.php`

| Method | URI | Name |
|--------|-----|------|
| GET | `/admin/performance` | `admin.performance.index` |
| GET | `/admin/performance/agent/{agent}` | `admin.performance.show` |
| POST | `/admin/performance/export` | `admin.performance.export` |

**New views:**
- `resources/views/admin/performance/index.blade.php` — Team metrics + agent table + CSV export
- `resources/views/admin/performance/show.blade.php` — Individual agent with priority/status breakdowns

---

### 10. Admin Multi-Report System

**New controller:** `app/Http/Controllers/Admin/ReportController.php`

| Report | View Route | Export Route |
|--------|-----------|--------------|
| Client | `admin.reports.clients` | `admin.reports.clients.export` |
| Agent | `admin.reports.agents` | `admin.reports.agents.export` |
| Category | `admin.reports.categories` | `admin.reports.categories.export` |
| Product | `admin.reports.products` | `admin.reports.products.export` |

All reports support:
- Date range filtering (from/to)
- CSV export
- Ticket counts (total, open, closed)

**New views:**
- `resources/views/admin/reports/clients.blade.php`
- `resources/views/admin/reports/agents.blade.php`
- `resources/views/admin/reports/categories.blade.php`
- `resources/views/admin/reports/products.blade.php`

---

### 11. Navigation Updates

**Sidebar (`layouts/app.blade.php`):**
- Added **Workflow** link in the Main section (visible to all users)

**Admin nav (`layouts/admin.blade.php`):**
- Added **Users** link
- Added **Performance** link
- Added **Reports** link

---

## Database Changes

### New Tables

| Table | Purpose |
|-------|---------|
| `task_status_histories` | Audit trail for task status changes |
| `client_agent_assignments` | Monthly agent-to-client assignment tracking |
| `sms_messages` | SMS message storage for gateway integration |

### Modified Tables

| Table | Changes |
|-------|---------|
| `tickets` | Added `incident_date`, `preferred_service_date`, `is_false_alarm` |

### Run Migrations

```bash
php artisan migrate
```

---

## New Model Relationships

| Model | New Relationship | Type |
|-------|-----------------|------|
| `Ticket` | `mergedTickets()` | HasMany |
| `Ticket` | `deletedByUser()` | BelongsTo |
| `TicketTask` | `statusHistory()` | HasMany |
| `Client` | `agentAssignments()` | HasMany |
| `Product` | `tickets()` | HasMany |
| `TicketCategory` | `tickets()` | HasMany |

---

## Files Created

### Migrations (4)
- `database/migrations/2026_03_16_000001_add_starter_fields_to_tickets_table.php`
- `database/migrations/2026_03_16_000002_create_task_status_histories_table.php`
- `database/migrations/2026_03_16_000003_create_client_agent_assignments_table.php`
- `database/migrations/2026_03_16_000004_create_sms_messages_table.php`

### Models (3)
- `app/Models/TaskStatusHistory.php`
- `app/Models/ClientAgentAssignment.php`
- `app/Models/SmsMessage.php`

### Services (2)
- `app/Services/TicketWorkflowService.php`
- `app/Services/AgentPerformanceService.php`

### Controllers (4)
- `app/Http/Controllers/WorkflowController.php`
- `app/Http/Controllers/Admin/UserController.php`
- `app/Http/Controllers/Admin/PerformanceController.php`
- `app/Http/Controllers/Admin/ReportController.php`

### Views (15)
- `resources/views/workflow/dashboard.blade.php`
- `resources/views/workflow/analytics.blade.php`
- `resources/views/tickets/search.blade.php`
- `resources/views/tickets/trashed.blade.php`
- `resources/views/admin/users/index.blade.php`
- `resources/views/admin/users/create.blade.php`
- `resources/views/admin/users/edit.blade.php`
- `resources/views/admin/performance/index.blade.php`
- `resources/views/admin/performance/show.blade.php`
- `resources/views/admin/reports/clients.blade.php`
- `resources/views/admin/reports/agents.blade.php`
- `resources/views/admin/reports/categories.blade.php`
- `resources/views/admin/reports/products.blade.php`

### Files Modified (11)
- `app/Models/Ticket.php` — Added fields, methods, scopes, relationships
- `app/Models/TicketTask.php` — Added status history, convenience methods
- `app/Models/Client.php` — Added agent assignment relationship
- `app/Models/Product.php` — Added tickets relationship
- `app/Models/TicketCategory.php` — Added tickets relationship
- `app/Http/Controllers/TicketController.php` — Added search, restore, force-delete, false alarm, child tickets
- `app/Http/Controllers/ClientController.php` — Added agent assignment
- `app/Http/Controllers/TicketTaskController.php` — Added bulk operations, status history
- `routes/tenant.php` — Added all new tenant routes
- `routes/web.php` — Added admin user/performance/report routes
- `resources/views/layouts/app.blade.php` — Added Workflow sidebar link
- `resources/views/layouts/admin.blade.php` — Added Users, Performance, Reports nav links

---

## Feature Mapping (Reference → Current)

| Reference Feature | Status | Notes |
|---|---|---|
| Workflow dashboard | Ported | Adapted for Blade views |
| Workflow analytics | Ported | Date-range KPI dashboard |
| Ticket search | Ported | Dedicated search page |
| Soft delete restore | Ported | Trashed view with restore/force-delete |
| False alarm marking | Ported | Mark and auto-close |
| Incident/service dates | Ported | New DB columns + model fields |
| Parent/child tickets | Ported | Create child from parent |
| Task status history | Ported | Full audit trail with history model |
| Task bulk operations | Ported | Bulk update and bulk status update |
| Agent performance | Ported | Service + admin dashboard |
| Admin user management | Ported | CRUD with toggle activation |
| Admin reports (4 types) | Ported | Client, agent, category, product with CSV export |
| Client agent assignment | Ported | Monthly assignment tracking |
| SMS infrastructure | Ported | Model + migration ready for gateway |
