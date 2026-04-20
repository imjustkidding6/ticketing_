# System Testing Form — Ticketing Platform

**Tester:** ____________________
**Date:** ____________________
**Environment:** ____________________
**Browser:** ____________________

**Legend:** Mark each test — **P** (Pass), **F** (Fail), **S** (Skip), **B** (Blocked)

---

## Pre-Test Setup Checklist

| # | Setup Step | Status | Notes |
|---|-----------|--------|-------|
| 0.1 | Database migrated and seeded (`php artisan migrate:fresh --seed`) | | |
| 0.2 | 3 Plans exist: Start (5 users, 100 tickets/mo), Business (25 users, 500 tickets/mo), Enterprise (unlimited) | | |
| 0.3 | Admin user created (`admin@example.com`) | | |
| 0.4 | Test user created (`test@example.com`) | | |
| 0.5 | Demo Distributor exists with active license | | |
| 0.6 | Demo Tenant exists with license activated | | |
| 0.7 | Default departments seeded (General, Technical, Sales, Billing) | | |
| 0.8 | Roles and permissions seeded | | |
| 0.9 | Frontend assets built (`npm run build`) | | |
| 0.10 | Queue worker running (for notifications) | | |

---

## 1. ADMIN PANEL

### 1.1 Admin Authentication

| # | Test Case | Expected Result | Status | Notes |
|---|-----------|----------------|--------|-------|
| 1.1.1 | Navigate to `/admin/login` | Admin login form displayed | | |
| 1.1.2 | Login with non-admin credentials | Access denied (403 or redirect) | | |
| 1.1.3 | Login with admin credentials | Redirected to admin dashboard | | |
| 1.1.4 | Logout from admin panel | Session ended, redirected to login | | |

### 1.2 Admin Dashboard

| # | Test Case | Expected Result | Status | Notes |
|---|-----------|----------------|--------|-------|
| 1.2.1 | View admin dashboard (`/admin/`) | Dashboard loads with all stat cards | | |
| 1.2.2 | Verify tenant stats | Shows total, active, and suspended tenant counts | | |
| 1.2.3 | Verify license stats | Shows total, active, and pending license counts | | |
| 1.2.4 | Verify distributor and plan counts | Correct counts displayed | | |
| 1.2.5 | Verify total ticket count | Shows tickets across all tenants | | |
| 1.2.6 | Verify tickets this month | Shows count of tickets created this month | | |
| 1.2.7 | Verify expiring licenses list | Shows active licenses expiring within 30 days | | |
| 1.2.8 | Verify expired licenses list | Shows active licenses that have expired | | |
| 1.2.9 | Verify plan distribution | Shows count of licenses per plan | | |
| 1.2.10 | Verify top tenants | Shows 5 tenants by user count with ticket counts | | |
| 1.2.11 | Verify recent tenants | Shows 5 most recently created tenants | | |

### 1.3 Distributor Management

| # | Test Case | Expected Result | Status | Notes |
|---|-----------|----------------|--------|-------|
| 1.3.1 | Navigate to distributors index | List of distributors displayed | | |
| 1.3.2 | Create a new distributor | Distributor created with auto-generated API key | | |
| 1.3.3 | View distributor details | Shows name, email, contact, API key, status | | |
| 1.3.4 | Edit a distributor | Fields updated correctly | | |
| 1.3.5 | Delete a distributor | Distributor removed from list | | |

### 1.4 Plan Management

| # | Test Case | Expected Result | Status | Notes |
|---|-----------|----------------|--------|-------|
| 1.4.1 | Navigate to plans index | All plans listed (Start, Business, Enterprise) | | |
| 1.4.2 | Create a new plan | Plan created with name, slug, limits, features | | |
| 1.4.3 | Edit an existing plan | Features and limits updated correctly | | |
| 1.4.4 | Verify Start plan has no features | Empty features array confirmed | | |
| 1.4.5 | Verify Business plan has 12 features | AuditLogs, Billing, SpamManagement, ServiceReports, Attachments, AgentSchedule, SlaManagement, SlaReport, EmailNotifications, DetailedReporting, KnowledgeBase, CannedResponses | | |
| 1.4.6 | Verify Enterprise plan has 18 features | All Business features + TicketMerging, TicketReopening, CustomRoles, DepartmentManagement, AgentEscalation, ClientComments | | |

### 1.5 License Management

| # | Test Case | Expected Result | Status | Notes |
|---|-----------|----------------|--------|-------|
| 1.5.1 | Navigate to licenses index | All licenses listed with status badges | | |
| 1.5.2 | Create a new license | License created with auto-generated key (XXXX-XXXX-XXXX-XXXX-XXXX format) | | |
| 1.5.3 | Assign license to a distributor and plan | Foreign keys set correctly | | |
| 1.5.4 | View license details | Shows key, plan, distributor, status, dates | | |
| 1.5.5 | Edit a license (change seats, expiry) | Fields updated correctly | | |
| 1.5.6 | Revoke an active license | Status changes to "revoked" | | |
| 1.5.7 | Verify grace period logic | License remains valid during grace period (default 7 days) | | |

### 1.6 Tenant Management

| # | Test Case | Expected Result | Status | Notes |
|---|-----------|----------------|--------|-------|
| 1.6.1 | Navigate to tenants index | All tenants listed with status indicators | | |
| 1.6.2 | View tenant details | Shows name, slug, users, license info, stats | | |
| 1.6.3 | Suspend a tenant | Tenant marked as suspended, `suspended_at` set | | |
| 1.6.4 | Verify suspended tenant cannot be accessed | Users of suspended tenant blocked from access | | |
| 1.6.5 | Unsuspend a tenant | Tenant reactivated, `suspended_at` cleared | | |
| 1.6.6 | Change tenant plan (e.g., Start to Business) | Plan updated, features become available | | |
| 1.6.7 | Impersonate a tenant | Admin sees tenant dashboard as that tenant | | |
| 1.6.8 | Stop impersonation | Admin returned to admin panel | | |

---

## 2. STARTER PLAN FEATURES

> **Pre-condition:** Ensure the test tenant is on the **Start** plan before running these tests.

### 2.1 User Registration & Tenant Setup

| # | Test Case | Expected Result | Status | Notes |
|---|-----------|----------------|--------|-------|
| 2.1.1 | Navigate to `/register` | Registration form with license key field shown | | |
| 2.1.2 | Enter a valid license key | License validated, additional fields appear | | |
| 2.1.3 | Enter an invalid/used license key | Validation error displayed | | |
| 2.1.4 | Fill company name and check slug availability (`/register/check-slug`) | Slug generated, availability confirmed via AJAX | | |
| 2.1.5 | Enter a duplicate slug | "Slug already taken" error shown | | |
| 2.1.6 | Complete registration | User created, tenant created, license activated, redirected | | |
| 2.1.7 | Verify tenant slug in URL | Tenant accessible at `/{slug}/dashboard` | | |

### 2.2 Tenant Selection & Switching

| # | Test Case | Expected Result | Status | Notes |
|---|-----------|----------------|--------|-------|
| 2.2.1 | Login as user with one tenant | Auto-selected, redirected to dashboard | | |
| 2.2.2 | Login as user with multiple tenants | Tenant selection page shown | | |
| 2.2.3 | Select a tenant | Session updated, redirected to tenant dashboard | | |
| 2.2.4 | Switch tenant (`/tenant/switch`) | Session updated to new tenant | | |
| 2.2.5 | Login as user with no tenants | Redirected to `/no-tenant` page | | |

### 2.3 Personal Dashboard (Tenant)

| # | Test Case | Expected Result | Status | Notes |
|---|-----------|----------------|--------|-------|
| 2.3.1 | Navigate to `/{slug}/dashboard` | Dashboard loads with all widgets | | |
| 2.3.2 | Verify stat cards | Open, In Progress, On Hold, Closed This Month, Total, Unassigned, Overdue counts correct | | |
| 2.3.3 | Verify priority breakdown | Critical, High, Medium, Low counts shown | | |
| 2.3.4 | Verify ticket trends chart | 30-day area chart renders with daily counts | | |
| 2.3.5 | Verify status distribution chart | Donut chart shows ticket status breakdown | | |
| 2.3.6 | Verify priority distribution chart | Donut chart shows priority breakdown | | |
| 2.3.7 | Verify department chart | Horizontal bar chart shows top 10 departments | | |
| 2.3.8 | Verify calendar widget | Month view with deadline dots, clickable dates show tickets | | |
| 2.3.9 | Verify "My Tickets" section | Shows current user's 5 open assigned tickets | | |
| 2.3.10 | Verify agent workloads | Visible for owner/admin roles only, shows agent open ticket counts | | |
| 2.3.11 | Verify recent tickets table | Shows latest 10 tickets | | |
| 2.3.12 | Verify 30-second auto-refresh | Dashboard stats and charts refresh via `/dashboard/stats` polling | | |
| 2.3.13 | Verify notification bell | Shows unread count, dropdown lists recent notifications | | |

### 2.4 Ticket Creation & Management

| # | Test Case | Expected Result | Status | Notes |
|---|-----------|----------------|--------|-------|
| 2.4.1 | Navigate to create ticket form | Form displays with all fields (subject, description, priority, client, dept, category, product, assignee) | | |
| 2.4.2 | Submit ticket with all required fields | Ticket created with auto-generated ticket_number | | |
| 2.4.3 | Submit ticket with missing required fields | Validation errors shown for subject, description, priority | | |
| 2.4.4 | View ticket list (`/{slug}/tickets`) | All tickets listed with filters | | |
| 2.4.5 | Filter tickets by status | List updates to show only matching status | | |
| 2.4.6 | Filter tickets by priority | List updates to show only matching priority | | |
| 2.4.7 | Filter tickets by department | List updates correctly | | |
| 2.4.8 | Filter tickets by assigned agent | List updates correctly | | |
| 2.4.9 | Search tickets by keyword | Matching tickets shown | | |
| 2.4.10 | View ticket details (`/{slug}/tickets/{id}`) | Full ticket info, client, department, product, creator, assignee, tasks, history shown | | |
| 2.4.11 | Edit ticket | Fields updated, changes saved | | |
| 2.4.12 | Delete ticket (soft delete) | Ticket soft-deleted with reason and deleted_by recorded | | |
| 2.4.13 | Assign ticket to agent | Ticket assigned, assignment recorded, notification sent | | |
| 2.4.14 | Self-assign ticket | Current user becomes assignee | | |
| 2.4.15 | Change ticket status to "In Progress" | Status updated, history recorded | | |
| 2.4.16 | Change ticket status to "On Hold" | Status updated, `hold_started_at` set | | |
| 2.4.17 | Change ticket status from "On Hold" to "Open" | Hold time calculated and added to `total_hold_time_minutes` | | |
| 2.4.18 | Change ticket status to "Closed" | Status updated, `closed_at` set, notification sent | | |
| 2.4.19 | Change ticket status to "Cancelled" | Status updated, history recorded | | |
| 2.4.20 | Verify overdue tickets highlight | Tickets past `resolution_due_at` shown with visual indicator | | |

### 2.5 Ticket Task Creation & Management

| # | Test Case | Expected Result | Status | Notes |
|---|-----------|----------------|--------|-------|
| 2.5.1 | Add a task to a ticket | Task created with auto-generated task_number (T1, T2, ...) | | |
| 2.5.2 | Assign task to an agent | `assigned_to` field set | | |
| 2.5.3 | Update task description/notes | Changes saved | | |
| 2.5.4 | Change task status to "In Progress" | Status updated | | |
| 2.5.5 | Change task status to "Completed" | Status updated, `completed_at` and `completed_by` set | | |
| 2.5.6 | Change task status to "Cancelled" | Status updated | | |
| 2.5.7 | Delete a task | Task removed | | |
| 2.5.8 | Verify task sort order | Tasks displayed in correct order | | |

### 2.6 Product/Services Management

| # | Test Case | Expected Result | Status | Notes |
|---|-----------|----------------|--------|-------|
| 2.6.1 | Navigate to products index | All products listed | | |
| 2.6.2 | Create a new product | Product created with name, description, SKU, price | | |
| 2.6.3 | Edit a product | Fields updated correctly | | |
| 2.6.4 | Delete a product | Product removed from list | | |
| 2.6.5 | Toggle product active/inactive | `is_active` toggled, inactive products filterable | | |
| 2.6.6 | Assign product to a category | `category_id` set correctly | | |
| 2.6.7 | Verify product appears in ticket creation form | Product listed in dropdown | | |

### 2.7 Category Management

| # | Test Case | Expected Result | Status | Notes |
|---|-----------|----------------|--------|-------|
| 2.7.1 | Navigate to categories index | All categories listed | | |
| 2.7.2 | Create a new category | Category created with name, description, department, color, priority_default | | |
| 2.7.3 | Assign category to a department | `department_id` set correctly | | |
| 2.7.4 | Edit a category | Fields updated correctly | | |
| 2.7.5 | Delete a category | Category removed | | |
| 2.7.6 | Toggle category active/inactive | `is_active` toggled | | |
| 2.7.7 | Verify category appears in ticket creation form | Category listed in dropdown, scoped to selected department | | |

### 2.8 User Roles (Fixed Roles: Admin, Manager, Agents)

| # | Test Case | Expected Result | Status | Notes |
|---|-----------|----------------|--------|-------|
| 2.8.1 | Verify tenant owner has full access | Owner can access all features and manage users | | |
| 2.8.2 | Verify admin role permissions | Admin can manage tickets, clients, settings | | |
| 2.8.3 | Verify agent role has limited access | Agent can view/manage assigned tickets | | |
| 2.8.4 | Verify agent workloads only visible to owner/admin | Non-owner/admin users don't see agent workloads section | | |
| 2.8.5 | Verify role is set on `tenant_user` pivot | User-tenant relationship has correct role value | | |

### 2.9 Departments (Fixed — Seeded)

| # | Test Case | Expected Result | Status | Notes |
|---|-----------|----------------|--------|-------|
| 2.9.1 | Verify 4 default departments exist | General Support, Technical Support, Sales, Billing | | |
| 2.9.2 | Verify departments have correct codes | GEN, TECH, SALES, BILL | | |
| 2.9.3 | Verify departments have colors | #6366f1, #8b5cf6, #10b981, #f59e0b | | |
| 2.9.4 | Verify departments display on ticket creation form | All active departments in dropdown | | |
| 2.9.5 | Verify department CRUD is **blocked** on Start plan | Feature gate `department_management` returns 403 | | |

### 2.10 Basic Reporting & Export

| # | Test Case | Expected Result | Status | Notes |
|---|-----------|----------------|--------|-------|
| 2.10.1 | Navigate to reports overview (`/{slug}/reports`) | Report page loads with volume and department data | | |
| 2.10.2 | Verify default date range (30 days) | Data covers last 30 days | | |
| 2.10.3 | Change date range using from/to params | Report data updates to custom range | | |
| 2.10.4 | Export volume report as CSV | CSV downloads with Total, Open, Closed, by Priority columns | | |
| 2.10.5 | Export department report as CSV | CSV downloads with Department, Total, Open, Closed columns | | |
| 2.10.6 | Verify SLA Compliance report is **blocked** on Start plan | Feature gate `sla_report` returns 403 | | |
| 2.10.7 | Verify Agent Performance report is **blocked** on Start plan | Feature gate `detailed_reporting` returns 403 | | |

### 2.11 Feature Gating Verification (Start Plan)

> Confirm that **all** Business/Enterprise features are blocked.

| # | Test Case | Expected Result | Status | Notes |
|---|-----------|----------------|--------|-------|
| 2.11.1 | Access billing route | 403 — feature `billing` not available | | |
| 2.11.2 | Access attachment download route | 403 — feature `attachments` not available | | |
| 2.11.3 | Access spam management routes | 403 — feature `spam_management` not available | | |
| 2.11.4 | Access SLA management routes | 403 — feature `sla_management` not available | | |
| 2.11.5 | Access agent schedule routes | 403 — feature `agent_schedule` not available | | |
| 2.11.6 | Access service report routes | 403 — feature `service_reports` not available | | |
| 2.11.7 | Access knowledge base routes | 403 — feature `knowledge_base` not available | | |
| 2.11.8 | Access canned responses routes | 403 — feature `canned_responses` not available | | |
| 2.11.9 | Access ticket merging route | 403 — feature `ticket_merging` not available | | |
| 2.11.10 | Access ticket reopen route | 403 — feature `ticket_reopening` not available | | |
| 2.11.11 | Access custom roles routes | 403 — feature `custom_roles` not available | | |
| 2.11.12 | Access department management routes | 403 — feature `department_management` not available | | |
| 2.11.13 | Access escalation route | 403 — feature `agent_escalation` not available | | |
| 2.11.14 | Access ticket comments routes | 403 — feature `client_comments` not available | | |
| 2.11.15 | Verify Business/Enterprise sidebar items are hidden | Feature-gated nav items not visible in sidebar | | |

### 2.12 Public Ticket Submission & Tracking

| # | Test Case | Expected Result | Status | Notes |
|---|-----------|----------------|--------|-------|
| 2.12.1 | Navigate to `/{slug}/submit-ticket` (no auth) | Guest ticket submission form displayed | | |
| 2.12.2 | Submit a ticket as guest | Ticket created, tracking token returned | | |
| 2.12.3 | Navigate to `/{slug}/track-ticket` | Tracking form displayed (ticket number + email) | | |
| 2.12.4 | Track ticket by number and email | Ticket details shown | | |
| 2.12.5 | Track ticket by token (`/{slug}/track-ticket/{token}`) | Ticket details shown | | |
| 2.12.6 | Track with invalid number/email | Error message displayed | | |

### 2.13 Settings (General)

| # | Test Case | Expected Result | Status | Notes |
|---|-----------|----------------|--------|-------|
| 2.13.1 | Navigate to general settings | Settings form loads | | |
| 2.13.2 | Update company name | Saved and reflected in UI | | |
| 2.13.3 | Navigate to ticket settings | Ticket-specific settings form loads | | |
| 2.13.4 | Update ticket settings | Changes saved | | |
| 2.13.5 | Navigate to branding settings | Branding form loads (logo, colors) | | |
| 2.13.6 | Update branding (logo, primary color, accent color) | Saved, reflected in client portal | | |
| 2.13.7 | Verify notification settings **blocked** on Start plan | Feature gate `email_notifications` returns 403 | | |

### 2.14 Notifications (Basic)

| # | Test Case | Expected Result | Status | Notes |
|---|-----------|----------------|--------|-------|
| 2.14.1 | Verify notification bell displays unread count | Count matches actual unread notifications | | |
| 2.14.2 | Click bell to show recent notifications | Dropdown lists recent notifications | | |
| 2.14.3 | Mark single notification as read | Notification removed from unread | | |
| 2.14.4 | Mark all notifications as read | Unread count resets to 0 | | |
| 2.14.5 | Verify notification on ticket creation | Creator gets notification | | |
| 2.14.6 | Verify notification on ticket assignment | Assigned agent gets notification | | |
| 2.14.7 | Verify notification on status change | Relevant users get notification | | |

### 2.15 Client Management

| # | Test Case | Expected Result | Status | Notes |
|---|-----------|----------------|--------|-------|
| 2.15.1 | Navigate to clients index | All clients listed | | |
| 2.15.2 | Create a new client | Client created with name, email, phone, tier, status | | |
| 2.15.3 | Create client with linked user account | `user_id` set, portal access enabled | | |
| 2.15.4 | Edit a client | Fields updated | | |
| 2.15.5 | Delete a client | Client removed | | |
| 2.15.6 | Set client tier (basic/premium/enterprise) | Tier saved correctly | | |
| 2.15.7 | Toggle client status (active/inactive) | Status toggled | | |

### 2.16 Profile Management

| # | Test Case | Expected Result | Status | Notes |
|---|-----------|----------------|--------|-------|
| 2.16.1 | View profile page | Current user info displayed | | |
| 2.16.2 | Update name and email | Changes saved | | |
| 2.16.3 | Delete account | Account removed, session ended | | |

---

## 3. BUSINESS PLAN FEATURES

> **Pre-condition:** Change the test tenant to the **Business** plan before running these tests. Confirm all Starter tests still pass.

### 3.1 Ticket Activity History / Audit Logs (`audit_logs`)

| # | Test Case | Expected Result | Status | Notes |
|---|-----------|----------------|--------|-------|
| 3.1.1 | Create a ticket | History entry logged: action=created | | |
| 3.1.2 | Update ticket fields (priority, status, assignment) | History entries for each change with field_name, old_value, new_value | | |
| 3.1.3 | View ticket activity history on ticket detail page | Full chronological audit trail visible | | |
| 3.1.4 | Verify history captures the acting user | `user_id` set on each history entry | | |
| 3.1.5 | Verify history metadata for complex changes | `metadata` JSON populated where applicable | | |

### 3.2 Billing (`billing`)

| # | Test Case | Expected Result | Status | Notes |
|---|-----------|----------------|--------|-------|
| 3.2.1 | Navigate to ticket detail and access billing section | Billing fields visible | | |
| 3.2.2 | Mark ticket as billable | `is_billable` set to true | | |
| 3.2.3 | Set billable amount | `billable_amount` saved (decimal) | | |
| 3.2.4 | Add billing description | `billable_description` saved | | |
| 3.2.5 | Mark ticket as billed | `billed_at` timestamp set | | |
| 3.2.6 | Remove billable flag | `is_billable` set to false | | |
| 3.2.7 | Verify billing route blocked on Start plan | Returns 403 | | |

### 3.3 Mark as Spam (`spam_management`)

| # | Test Case | Expected Result | Status | Notes |
|---|-----------|----------------|--------|-------|
| 3.3.1 | Mark a ticket as spam | `is_spam=true`, `marked_spam_by` set, `spam_reason` saved | | |
| 3.3.2 | Verify spam tickets filterable | Spam tickets excluded from default list (notSpam scope) | | |
| 3.3.3 | Unmark a ticket as spam | `is_spam=false`, spam fields cleared | | |
| 3.3.4 | Verify spam routes blocked on Start plan | Returns 403 | | |

### 3.4 Auto Generated Service Reports (`service_reports`)

| # | Test Case | Expected Result | Status | Notes |
|---|-----------|----------------|--------|-------|
| 3.4.1 | Navigate to service reports index | Report list displayed | | |
| 3.4.2 | Generate a service report from a ticket | Report created with auto-generated `report_number` | | |
| 3.4.3 | Verify report contains ticket and client data | `report_data` JSON populated correctly | | |
| 3.4.4 | Download a service report | File downloads (PDF or generated format) | | |
| 3.4.5 | Verify report status lifecycle | generated → sent → superseded | | |
| 3.4.6 | Verify service report routes blocked on Start plan | Returns 403 | | |

### 3.5 Attachments (`attachments`)

| # | Test Case | Expected Result | Status | Notes |
|---|-----------|----------------|--------|-------|
| 3.5.1 | Create ticket with file attachments | Files uploaded and stored, `attachments` JSON saved | | |
| 3.5.2 | Verify attachment limit (max 5 files) | Validation error when exceeding 5 | | |
| 3.5.3 | Verify file size limit (10MB per file) | Validation error for oversized files | | |
| 3.5.4 | Verify allowed MIME types | Only allowed file types accepted | | |
| 3.5.5 | Download an attachment | File streams correctly from storage | | |
| 3.5.6 | Update ticket with new attachments | Attachments updated | | |
| 3.5.7 | Verify attachment routes blocked on Start plan | Returns 403 | | |

### 3.6 Agent Availability Schedule (`agent_schedule`)

| # | Test Case | Expected Result | Status | Notes |
|---|-----------|----------------|--------|-------|
| 3.6.1 | Navigate to schedules index | Agent's own schedule displayed | | |
| 3.6.2 | Create a schedule entry | Entry created with day_of_week, start_time, end_time | | |
| 3.6.3 | Set availability for each day (0=Sun through 6=Sat) | All 7 days configurable | | |
| 3.6.4 | Mark a day as unavailable | `is_available=false` for that entry | | |
| 3.6.5 | Set effective date and end date | Schedule bounded by date range | | |
| 3.6.6 | Edit a schedule entry | Changes saved | | |
| 3.6.7 | Delete a schedule entry | Entry removed | | |
| 3.6.8 | View team schedule | All agents' schedules visible | | |
| 3.6.9 | Verify schedule routes blocked on Start plan | Returns 403 | | |

### 3.7 SLA Management (`sla_management`)

| # | Test Case | Expected Result | Status | Notes |
|---|-----------|----------------|--------|-------|
| 3.7.1 | Navigate to SLA policies index | All policies listed | | |
| 3.7.2 | Create an SLA policy | Policy created with name, client_tier, priority, response_time_hours, resolution_time_hours | | |
| 3.7.3 | Create SLA with specific client tier + priority combo | Policy matched correctly | | |
| 3.7.4 | Edit an SLA policy | Fields updated | | |
| 3.7.5 | Delete an SLA policy | Policy removed | | |
| 3.7.6 | Toggle SLA policy active/inactive | `is_active` toggled | | |
| 3.7.7 | Verify SLA auto-assigned on ticket creation | `sla_policy_id`, `response_due_at`, `resolution_due_at` set | | |
| 3.7.8 | Verify SLA matching logic (client tier + priority) | Correct policy found via `findForTicket()` | | |
| 3.7.9 | Verify SLA routes blocked on Start plan | Returns 403 | | |

### 3.8 SLA Compliance Report (`sla_report`)

| # | Test Case | Expected Result | Status | Notes |
|---|-----------|----------------|--------|-------|
| 3.8.1 | Navigate to SLA compliance report | Report page loads with compliance data | | |
| 3.8.2 | Verify compliance metrics displayed | Response and resolution compliance percentages | | |
| 3.8.3 | Filter by date range | Report updates to custom range | | |
| 3.8.4 | Verify SLA breach warning command runs | `sla:send-breach-warnings` finds approaching-breach tickets | | |
| 3.8.5 | Verify breach notification sent to assigned agent | SlaBreachWarningNotification received | | |
| 3.8.6 | Verify `sla_breach_notified_at` updated | Timestamp set after notification sent | | |
| 3.8.7 | Verify no duplicate breach notifications | Already-notified tickets not re-notified | | |
| 3.8.8 | Verify SLA report blocked on Start plan | Returns 403 | | |

### 3.9 Email Notifications (`email_notifications`)

| # | Test Case | Expected Result | Status | Notes |
|---|-----------|----------------|--------|-------|
| 3.9.1 | Navigate to notification settings | Settings form loads | | |
| 3.9.2 | Configure notification preferences | Settings saved | | |
| 3.9.3 | Create a ticket — verify email sent | TicketCreatedNotification sent via email channel | | |
| 3.9.4 | Assign a ticket — verify email sent | TicketAssignedNotification sent via email | | |
| 3.9.5 | Change ticket status — verify email sent | TicketStatusChangedNotification sent via email | | |
| 3.9.6 | SLA breach warning — verify email sent | SlaBreachWarningNotification sent via email | | |
| 3.9.7 | Verify notification settings blocked on Start plan | Returns 403 | | |

### 3.10 Detailed Reporting & Export (`detailed_reporting`)

| # | Test Case | Expected Result | Status | Notes |
|---|-----------|----------------|--------|-------|
| 3.10.1 | Navigate to agent performance report | Report loads with per-agent data | | |
| 3.10.2 | Verify agent metrics | Total, Open, Closed, Avg Resolution Time shown per agent | | |
| 3.10.3 | Filter by date range | Report updates | | |
| 3.10.4 | Export agent performance CSV | CSV downloads with Agent, Total, Open, Closed, Avg Resolution (hrs) | | |
| 3.10.5 | Verify agent report blocked on Start plan | Returns 403 | | |

### 3.11 Knowledge Base (`knowledge_base`)

| # | Test Case | Expected Result | Status | Notes |
|---|-----------|----------------|--------|-------|
| 3.11.1 | Navigate to KB categories index | Categories listed | | |
| 3.11.2 | Create a KB category | Category created with name, auto-slug, icon, sort_order | | |
| 3.11.3 | Edit a KB category | Fields updated | | |
| 3.11.4 | Delete a KB category | Category removed | | |
| 3.11.5 | Create a KB article | Article created with title, auto-slug, content, excerpt, category | | |
| 3.11.6 | Publish a KB article | `is_published=true`, `published_at` set | | |
| 3.11.7 | Save article as draft | `is_published=false` | | |
| 3.11.8 | Edit a KB article | Fields updated | | |
| 3.11.9 | Delete a KB article | Article removed | | |
| 3.11.10 | Search KB articles | Matching articles returned | | |
| 3.11.11 | Verify article `views_count` increments | Count goes up on each view | | |
| 3.11.12 | Verify KB accessible in client portal (public) | Published articles visible at `/portal/{slug}/knowledge-base` | | |
| 3.11.13 | Verify KB routes blocked on Start plan | Returns 403 | | |

### 3.12 Canned Responses (`canned_responses`)

| # | Test Case | Expected Result | Status | Notes |
|---|-----------|----------------|--------|-------|
| 3.12.1 | Navigate to canned responses index | Responses listed | | |
| 3.12.2 | Create a canned response | Response created with name, content, shortcut, category | | |
| 3.12.3 | Edit a canned response | Fields updated | | |
| 3.12.4 | Delete a canned response | Response removed | | |
| 3.12.5 | List canned responses (API/dropdown) | `/canned-responses/list` returns responses | | |
| 3.12.6 | Filter by category | Only matching category returned | | |
| 3.12.7 | Verify canned response routes blocked on Start plan | Returns 403 | | |

---

## 4. ENTERPRISE PLAN FEATURES

> **Pre-condition:** Change the test tenant to the **Enterprise** plan before running these tests. Confirm all Starter and Business tests still pass.

### 4.1 Ticket Merging (`ticket_merging`)

| # | Test Case | Expected Result | Status | Notes |
|---|-----------|----------------|--------|-------|
| 4.1.1 | Create two related tickets | Both tickets exist | | |
| 4.1.2 | Merge ticket A into ticket B | Ticket A: `is_merged=true`, `merged_into_ticket_id=B`, `merged_at` set | | |
| 4.1.3 | Verify merged ticket is marked | Merged indicator visible on ticket A | | |
| 4.1.4 | Verify target ticket references | Ticket B shows merge history | | |
| 4.1.5 | Verify merged ticket not in active list | Merged tickets excluded or flagged | | |
| 4.1.6 | Verify merge route blocked on Business plan | Returns 403 | | |

### 4.2 Ticket Re-Opening (`ticket_reopening`)

| # | Test Case | Expected Result | Status | Notes |
|---|-----------|----------------|--------|-------|
| 4.2.1 | Close a ticket | Ticket status = closed, `closed_at` set | | |
| 4.2.2 | Reopen the closed ticket | Status changes (closed → open), `closed_at` cleared | | |
| 4.2.3 | Verify `reopened_count` incremented | Count goes from 0 to 1 | | |
| 4.2.4 | Reopen same ticket again | `reopened_count` increments to 2 | | |
| 4.2.5 | Verify reopen route blocked on Business plan | Returns 403 | | |

### 4.3 Customized Roles & Permissions (`custom_roles`)

| # | Test Case | Expected Result | Status | Notes |
|---|-----------|----------------|--------|-------|
| 4.3.1 | Navigate to roles index | Existing roles listed (scoped to tenant via team_id) | | |
| 4.3.2 | Create a new custom role | Role created with name and selected permissions | | |
| 4.3.3 | Assign permissions to role | Permissions synced via Spatie | | |
| 4.3.4 | Edit a custom role | Name and permissions updated | | |
| 4.3.5 | Remove permissions from role | Permissions removed, access revoked | | |
| 4.3.6 | Delete a custom role | Role removed | | |
| 4.3.7 | Assign custom role to a user | User gains role-based permissions | | |
| 4.3.8 | Verify role is tenant-scoped | Roles isolated per tenant (team_id) | | |
| 4.3.9 | Verify custom roles routes blocked on Business plan | Returns 403 | | |

### 4.4 Department Management (`department_management`)

| # | Test Case | Expected Result | Status | Notes |
|---|-----------|----------------|--------|-------|
| 4.4.1 | Navigate to departments index | All departments listed | | |
| 4.4.2 | Create a new department | Department created with name, code, email, color, sort_order | | |
| 4.4.3 | Set a department as default | `is_default=true`, only one default allowed | | |
| 4.4.4 | Edit a department | Fields updated | | |
| 4.4.5 | Delete a department | Department removed | | |
| 4.4.6 | Toggle department active/inactive | `is_active` toggled | | |
| 4.4.7 | Verify department CRUD blocked on Business plan | Returns 403 (feature `department_management`) | | |

### 4.5 Agent Tiering and Escalation (`agent_escalation`)

| # | Test Case | Expected Result | Status | Notes |
|---|-----------|----------------|--------|-------|
| 4.5.1 | View ticket at tier_1 | Ticket shows `current_tier = tier_1` | | |
| 4.5.2 | Escalate ticket from tier_1 to tier_2 | `current_tier` updated, `escalation_count` incremented, `last_escalated_at` set | | |
| 4.5.3 | Escalate ticket from tier_2 to tier_3 | Tier updated again | | |
| 4.5.4 | Verify escalation record created | TicketEscalation entry with from_tier, to_tier, escalated_by, trigger_type | | |
| 4.5.5 | Manual escalation (trigger: manual) | `trigger_type = manual`, reason captured | | |
| 4.5.6 | Verify escalation assigns to new user | `escalated_to_user_id` set, ticket reassigned | | |
| 4.5.7 | Verify escalation history on ticket detail | All escalations listed chronologically | | |
| 4.5.8 | Verify escalation route blocked on Business plan | Returns 403 | | |

### 4.6 Comments & Updates Section — Client-Agent (`client_comments`)

| # | Test Case | Expected Result | Status | Notes |
|---|-----------|----------------|--------|-------|
| 4.6.1 | Add a public comment to a ticket | Comment created, `type=public`, `is_public=true` | | |
| 4.6.2 | Add an internal comment | Comment created, `type=internal`, `is_public=false` | | |
| 4.6.3 | Verify client reply type | `type=client_reply` when client submits | | |
| 4.6.4 | Verify status update type | `type=status_update` on status changes | | |
| 4.6.5 | Edit a comment | Content updated, `edited_at` and `edited_by` set | | |
| 4.6.6 | Delete a comment | Comment removed | | |
| 4.6.7 | Verify internal comments hidden from clients | Only `is_public=true` comments visible in portal | | |
| 4.6.8 | Add comment with attachment | `attachments` JSON saved on comment | | |
| 4.6.9 | Verify comment routes blocked on Business plan | Returns 403 | | |

---

## 5. CLIENT PORTAL

> Test the client-facing portal at `/portal/{tenant:slug}`

### 5.1 Portal Public Access

| # | Test Case | Expected Result | Status | Notes |
|---|-----------|----------------|--------|-------|
| 5.1.1 | Navigate to `/portal/{slug}` | Portal landing page with tenant branding | | |
| 5.1.2 | Verify custom branding (logo, colors) | Portal uses tenant's `logo_path`, `primary_color`, `accent_color` | | |
| 5.1.3 | Navigate to portal login | Login form displayed | | |
| 5.1.4 | Navigate to portal registration | Registration form displayed | | |
| 5.1.5 | Access KB from portal (Business+ plan) | Published articles visible without login | | |
| 5.1.6 | Search KB from portal | Search results returned | | |
| 5.1.7 | View KB category | Articles in category listed | | |
| 5.1.8 | View KB article | Full article content displayed | | |

### 5.2 Portal Authentication

| # | Test Case | Expected Result | Status | Notes |
|---|-----------|----------------|--------|-------|
| 5.2.1 | Register as a new portal client | Client account created, user linked | | |
| 5.2.2 | Login to portal | Authenticated, redirected to portal dashboard | | |
| 5.2.3 | Login with invalid credentials | Error message shown | | |
| 5.2.4 | Verify portal auth is separate from main auth | Different session/guards | | |
| 5.2.5 | Logout from portal | Session ended, redirected to portal home | | |

### 5.3 Portal Dashboard & Tickets

| # | Test Case | Expected Result | Status | Notes |
|---|-----------|----------------|--------|-------|
| 5.3.1 | View portal dashboard | Welcome message, stat cards (Open, Closed, Total), recent tickets | | |
| 5.3.2 | Create a ticket from portal | Ticket created, linked to client | | |
| 5.3.3 | View own ticket details | Ticket info displayed | | |
| 5.3.4 | Verify client cannot see internal comments | Only public comments visible | | |
| 5.3.5 | Verify client cannot see other clients' tickets | Scoped to own tickets only | | |
| 5.3.6 | Verify suspended tenant portal is blocked | Portal returns error for suspended tenants | | |

---

## 6. CROSS-CUTTING CONCERNS

### 6.1 Multi-Tenancy Isolation

| # | Test Case | Expected Result | Status | Notes |
|---|-----------|----------------|--------|-------|
| 6.1.1 | Create data in Tenant A | Data exists only in Tenant A | | |
| 6.1.2 | Switch to Tenant B | Tenant A's data not visible | | |
| 6.1.3 | Verify URL slug matches session tenant | Mismatch handled gracefully | | |
| 6.1.4 | Verify `tenant_id` set on all tenant-scoped records | Foreign key populated | | |

### 6.2 Plan Limits

| # | Test Case | Expected Result | Status | Notes |
|---|-----------|----------------|--------|-------|
| 6.2.1 | Verify Start plan max 5 users | 6th user blocked | | |
| 6.2.2 | Verify Business plan max 25 users | 26th user blocked | | |
| 6.2.3 | Verify Enterprise plan unlimited users | No user limit | | |
| 6.2.4 | Verify Start plan max 100 tickets/month | 101st ticket blocked | | |
| 6.2.5 | Verify Business plan max 500 tickets/month | 501st ticket blocked | | |
| 6.2.6 | Verify Enterprise plan unlimited tickets | No ticket limit | | |

### 6.3 Security & Authorization

| # | Test Case | Expected Result | Status | Notes |
|---|-----------|----------------|--------|-------|
| 6.3.1 | Access tenant routes without auth | Redirected to login | | |
| 6.3.2 | Access admin panel as non-admin | 403 Forbidden | | |
| 6.3.3 | Access another tenant's URL slug directly | Blocked or redirected | | |
| 6.3.4 | CSRF token validation | Forms without token rejected (419) | | |
| 6.3.5 | Email verification required | Unverified users blocked from tenant routes | | |

### 6.4 Health & Infrastructure

| # | Test Case | Expected Result | Status | Notes |
|---|-----------|----------------|--------|-------|
| 6.4.1 | Hit `/health` endpoint | Returns 200 OK | | |
| 6.4.2 | Homepage loads (`/`) | Welcome page with plans displayed | | |
| 6.4.3 | Verify queue worker processes jobs | Notifications/jobs processed | | |
| 6.4.4 | Verify scheduled command runs | `sla:send-breach-warnings` runs every 15 minutes | | |

---

## Test Summary

| Section | Total Tests | Pass | Fail | Skip | Blocked |
|---------|------------|------|------|------|---------|
| 1. Admin Panel | 30 | | | | |
| 2. Starter Plan | 80 | | | | |
| 3. Business Plan | 62 | | | | |
| 4. Enterprise Plan | 40 | | | | |
| 5. Client Portal | 19 | | | | |
| 6. Cross-Cutting | 15 | | | | |
| **TOTAL** | **246** | | | | |

**Overall Result:** ______ (Pass / Fail)
**Tested By:** ______________________
**Sign-off Date:** ______________________
**Notes:** ____________________________________________________________
