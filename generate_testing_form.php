<?php

require __DIR__.'/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$spreadsheet = new Spreadsheet;
$spreadsheet->getProperties()
    ->setCreator('Ticketing Platform')
    ->setTitle('System Testing Form')
    ->setDescription('Comprehensive QA testing form for the Ticketing Platform');

// Color palette
$colors = [
    'header_bg' => 'FF1E293B', // slate-800
    'header_fg' => 'FFFFFFFF',
    'section_bg' => 'FF4F46E5', // indigo-600
    'section_fg' => 'FFFFFFFF',
    'subsection_bg' => 'FFE0E7FF', // indigo-100
    'subsection_fg' => 'FF312E81', // indigo-900
    'pass_bg' => 'FFDCFCE7', // green-100
    'fail_bg' => 'FFFEE2E2', // red-100
    'skip_bg' => 'FFFEF3C7', // amber-100
    'blocked_bg' => 'FFE5E7EB', // gray-200
    'alt_row' => 'FFF8FAFC', // slate-50
    'white' => 'FFFFFFFF',
    'border' => 'FFD1D5DB', // gray-300
    'light_border' => 'FFE5E7EB', // gray-200
    'starter_bg' => 'FF059669', // emerald-600
    'business_bg' => 'FF2563EB', // blue-600
    'enterprise_bg' => 'FF7C3AED', // violet-600
    'portal_bg' => 'FFEC4899', // pink-500
    'cross_bg' => 'FFEA580C', // orange-600
    'admin_bg' => 'FF1E293B', // slate-800
];

/**
 * Helper: apply header style to a row
 */
function styleHeader(Spreadsheet $spreadsheet, $sheet, int $row, string $bg, string $fg, string $lastCol = 'F'): void
{
    $range = "A{$row}:{$lastCol}{$row}";
    $sheet->getStyle($range)->applyFromArray([
        'font' => ['bold' => true, 'color' => ['argb' => $fg], 'size' => 11],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bg]],
        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
    ]);
}

/**
 * Helper: apply table header style
 */
function styleTableHeader($sheet, int $row, string $lastCol = 'F'): void
{
    global $colors;
    $range = "A{$row}:{$lastCol}{$row}";
    $sheet->getStyle($range)->applyFromArray([
        'font' => ['bold' => true, 'size' => 10, 'color' => ['argb' => $colors['header_fg']]],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $colors['header_bg']]],
        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => $colors['border']]]],
    ]);
}

/**
 * Helper: apply row style
 */
function styleRow($sheet, int $row, bool $alt = false, string $lastCol = 'F'): void
{
    global $colors;
    $range = "A{$row}:{$lastCol}{$row}";
    $bg = $alt ? $colors['alt_row'] : $colors['white'];
    $sheet->getStyle($range)->applyFromArray([
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bg]],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => $colors['light_border']]]],
        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
    ]);
}

/**
 * Helper: add dropdown validation for Status column
 */
function addStatusValidation($sheet, int $row, string $col = 'E'): void
{
    $validation = $sheet->getCell("{$col}{$row}")->getDataValidation();
    $validation->setType(DataValidation::TYPE_LIST)
        ->setErrorStyle(DataValidation::STYLE_STOP)
        ->setAllowBlank(true)
        ->setShowDropDown(true)
        ->setFormula1('"P - Pass,F - Fail,S - Skip,B - Blocked"');
}

// =========================================================
// SHEET 1: Cover / Setup
// =========================================================
$cover = $spreadsheet->getActiveSheet();
$cover->setTitle('Cover & Setup');

// Column widths
$cover->getColumnDimension('A')->setWidth(8);
$cover->getColumnDimension('B')->setWidth(45);
$cover->getColumnDimension('C')->setWidth(15);
$cover->getColumnDimension('D')->setWidth(40);

// Title
$cover->mergeCells('A1:D1');
$cover->setCellValue('A1', 'SYSTEM TESTING FORM — TICKETING PLATFORM');
$cover->getStyle('A1')->applyFromArray([
    'font' => ['bold' => true, 'size' => 18, 'color' => ['argb' => $colors['header_fg']]],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $colors['section_bg']]],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
]);
$cover->getRowDimension(1)->setRowHeight(45);

// Meta fields
$metaFields = [
    ['Tester:', ''],
    ['Date:', ''],
    ['Environment:', ''],
    ['Browser:', ''],
];
$r = 3;
foreach ($metaFields as $field) {
    $cover->setCellValue("A{$r}", $field[0]);
    $cover->getStyle("A{$r}")->getFont()->setBold(true);
    $cover->setCellValue("B{$r}", $field[1]);
    $cover->getStyle("B{$r}")->applyFromArray([
        'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => $colors['border']]]],
    ]);
    $r++;
}

$r++;
$cover->setCellValue("A{$r}", 'Legend:');
$cover->getStyle("A{$r}")->getFont()->setBold(true);
$r++;
$legend = [
    ['P', 'Pass', $colors['pass_bg']],
    ['F', 'Fail', $colors['fail_bg']],
    ['S', 'Skip', $colors['skip_bg']],
    ['B', 'Blocked', $colors['blocked_bg']],
];
foreach ($legend as $item) {
    $cover->setCellValue("A{$r}", $item[0]);
    $cover->setCellValue("B{$r}", $item[1]);
    $cover->getStyle("A{$r}:B{$r}")->applyFromArray([
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $item[2]]],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => $colors['border']]]],
    ]);
    $r++;
}

// Pre-test setup
$r += 2;
$cover->mergeCells("A{$r}:D{$r}");
$cover->setCellValue("A{$r}", 'PRE-TEST SETUP CHECKLIST');
styleHeader($spreadsheet, $cover, $r, $colors['header_bg'], $colors['header_fg'], 'D');
$cover->getRowDimension($r)->setRowHeight(28);
$r++;

$cover->setCellValue("A{$r}", '#');
$cover->setCellValue("B{$r}", 'Setup Step');
$cover->setCellValue("C{$r}", 'Status');
$cover->setCellValue("D{$r}", 'Notes');
styleTableHeader($cover, $r, 'D');
$r++;

$setupSteps = [
    'Database migrated and seeded (php artisan migrate:fresh --seed)',
    '3 Plans exist: Start (5 users, 100 tickets/mo), Business (25 users, 500 tickets/mo), Enterprise (unlimited)',
    'Admin user created (admin@example.com)',
    'Test user created (test@example.com)',
    'Demo Distributor exists with active license',
    'Demo Tenant exists with license activated',
    'Default departments seeded (General, Technical, Sales, Billing)',
    'Roles and permissions seeded',
    'Frontend assets built (npm run build)',
    'Queue worker running (for notifications)',
];

foreach ($setupSteps as $i => $step) {
    $num = '0.'.($i + 1);
    $cover->setCellValue("A{$r}", $num);
    $cover->setCellValue("B{$r}", $step);
    $cover->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    styleRow($cover, $r, $i % 2 === 1, 'D');

    $validation = $cover->getCell("C{$r}")->getDataValidation();
    $validation->setType(DataValidation::TYPE_LIST)->setAllowBlank(true)->setShowDropDown(true)
        ->setFormula1('"Done,Pending,N/A"');

    $r++;
}

// =========================================================
// TEST DATA DEFINITION
// =========================================================

$testSections = [
    // ---- ADMIN PANEL ----
    [
        'sheet' => '1. Admin Panel',
        'color' => $colors['admin_bg'],
        'title' => '1. ADMIN PANEL',
        'subsections' => [
            [
                'name' => '1.1 Admin Authentication',
                'tests' => [
                    ['1.1.1', 'Navigate to /admin/login', 'Admin login form displayed'],
                    ['1.1.2', 'Login with non-admin credentials', 'Access denied (403 or redirect)'],
                    ['1.1.3', 'Login with admin credentials', 'Redirected to admin dashboard'],
                    ['1.1.4', 'Logout from admin panel', 'Session ended, redirected to login'],
                ],
            ],
            [
                'name' => '1.2 Admin Dashboard',
                'tests' => [
                    ['1.2.1', 'View admin dashboard (/admin/)', 'Dashboard loads with all stat cards'],
                    ['1.2.2', 'Verify tenant stats', 'Shows total, active, and suspended tenant counts'],
                    ['1.2.3', 'Verify license stats', 'Shows total, active, and pending license counts'],
                    ['1.2.4', 'Verify distributor and plan counts', 'Correct counts displayed'],
                    ['1.2.5', 'Verify total ticket count', 'Shows tickets across all tenants'],
                    ['1.2.6', 'Verify tickets this month', 'Shows count of tickets created this month'],
                    ['1.2.7', 'Verify expiring licenses list', 'Shows active licenses expiring within 30 days'],
                    ['1.2.8', 'Verify expired licenses list', 'Shows active licenses that have expired'],
                    ['1.2.9', 'Verify plan distribution', 'Shows count of licenses per plan'],
                    ['1.2.10', 'Verify top tenants', 'Shows 5 tenants by user count with ticket counts'],
                    ['1.2.11', 'Verify recent tenants', 'Shows 5 most recently created tenants'],
                ],
            ],
            [
                'name' => '1.3 Distributor Management',
                'tests' => [
                    ['1.3.1', 'Navigate to distributors index', 'List of distributors displayed'],
                    ['1.3.2', 'Create a new distributor', 'Distributor created with auto-generated API key'],
                    ['1.3.3', 'View distributor details', 'Shows name, email, contact, API key, status'],
                    ['1.3.4', 'Edit a distributor', 'Fields updated correctly'],
                    ['1.3.5', 'Delete a distributor', 'Distributor removed from list'],
                ],
            ],
            [
                'name' => '1.4 Plan Management',
                'tests' => [
                    ['1.4.1', 'Navigate to plans index', 'All plans listed (Start, Business, Enterprise)'],
                    ['1.4.2', 'Create a new plan', 'Plan created with name, slug, limits, features'],
                    ['1.4.3', 'Edit an existing plan', 'Features and limits updated correctly'],
                    ['1.4.4', 'Verify Start plan has no features', 'Empty features array confirmed'],
                    ['1.4.5', 'Verify Business plan has 12 features', 'AuditLogs, Billing, Spam, ServiceReports, Attachments, AgentSchedule, SLA, SlaReport, EmailNotif, DetailedReporting, KB, CannedResponses'],
                    ['1.4.6', 'Verify Enterprise plan has 18 features', 'All Business + TicketMerging, Reopening, CustomRoles, DeptMgmt, Escalation, ClientComments'],
                ],
            ],
            [
                'name' => '1.5 License Management',
                'tests' => [
                    ['1.5.1', 'Navigate to licenses index', 'All licenses listed with status badges'],
                    ['1.5.2', 'Create a new license', 'License created with auto key (XXXX-XXXX-XXXX-XXXX-XXXX)'],
                    ['1.5.3', 'Assign license to distributor and plan', 'Foreign keys set correctly'],
                    ['1.5.4', 'View license details', 'Shows key, plan, distributor, status, dates'],
                    ['1.5.5', 'Edit a license (change seats, expiry)', 'Fields updated correctly'],
                    ['1.5.6', 'Revoke an active license', 'Status changes to "revoked"'],
                    ['1.5.7', 'Verify grace period logic', 'License valid during 7-day grace period'],
                ],
            ],
            [
                'name' => '1.6 Tenant Management',
                'tests' => [
                    ['1.6.1', 'Navigate to tenants index', 'All tenants listed with status indicators'],
                    ['1.6.2', 'View tenant details', 'Shows name, slug, users, license info, stats'],
                    ['1.6.3', 'Suspend a tenant', 'Tenant marked suspended, suspended_at set'],
                    ['1.6.4', 'Verify suspended tenant cannot be accessed', 'Users blocked from access'],
                    ['1.6.5', 'Unsuspend a tenant', 'Tenant reactivated, suspended_at cleared'],
                    ['1.6.6', 'Change tenant plan (Start to Business)', 'Plan updated, features become available'],
                    ['1.6.7', 'Impersonate a tenant', 'Admin sees tenant dashboard as that tenant'],
                    ['1.6.8', 'Stop impersonation', 'Admin returned to admin panel'],
                ],
            ],
        ],
    ],

    // ---- STARTER PLAN ----
    [
        'sheet' => '2. Starter Plan',
        'color' => $colors['starter_bg'],
        'title' => '2. STARTER PLAN FEATURES',
        'precondition' => 'Ensure the test tenant is on the Start plan before running these tests.',
        'subsections' => [
            [
                'name' => '2.1 User Registration & Tenant Setup',
                'tests' => [
                    ['2.1.1', 'Navigate to /register', 'Registration form with license key field shown'],
                    ['2.1.2', 'Enter a valid license key', 'License validated, additional fields appear'],
                    ['2.1.3', 'Enter an invalid/used license key', 'Validation error displayed'],
                    ['2.1.4', 'Fill company name, check slug availability', 'Slug generated, availability confirmed via AJAX'],
                    ['2.1.5', 'Enter a duplicate slug', '"Slug already taken" error shown'],
                    ['2.1.6', 'Complete registration', 'User created, tenant created, license activated'],
                    ['2.1.7', 'Verify tenant slug in URL', 'Tenant accessible at /{slug}/dashboard'],
                ],
            ],
            [
                'name' => '2.2 Tenant Selection & Switching',
                'tests' => [
                    ['2.2.1', 'Login as user with one tenant', 'Auto-selected, redirected to dashboard'],
                    ['2.2.2', 'Login as user with multiple tenants', 'Tenant selection page shown'],
                    ['2.2.3', 'Select a tenant', 'Session updated, redirected to tenant dashboard'],
                    ['2.2.4', 'Switch tenant (/tenant/switch)', 'Session updated to new tenant'],
                    ['2.2.5', 'Login as user with no tenants', 'Redirected to /no-tenant page'],
                ],
            ],
            [
                'name' => '2.3 Personal Dashboard',
                'tests' => [
                    ['2.3.1', 'Navigate to /{slug}/dashboard', 'Dashboard loads with all widgets'],
                    ['2.3.2', 'Verify stat cards', 'Open, In Progress, On Hold, Closed, Total, Unassigned, Overdue counts correct'],
                    ['2.3.3', 'Verify priority breakdown', 'Critical, High, Medium, Low counts shown'],
                    ['2.3.4', 'Verify ticket trends chart', '30-day area chart renders with daily counts'],
                    ['2.3.5', 'Verify status distribution chart', 'Donut chart shows ticket status breakdown'],
                    ['2.3.6', 'Verify priority distribution chart', 'Donut chart shows priority breakdown'],
                    ['2.3.7', 'Verify department chart', 'Horizontal bar chart shows top 10 departments'],
                    ['2.3.8', 'Verify calendar widget', 'Month view with deadline dots, clickable dates'],
                    ['2.3.9', 'Verify "My Tickets" section', 'Shows current user\'s 5 open assigned tickets'],
                    ['2.3.10', 'Verify agent workloads (owner/admin only)', 'Shows agent open ticket counts'],
                    ['2.3.11', 'Verify recent tickets table', 'Shows latest 10 tickets'],
                    ['2.3.12', 'Verify 30-second auto-refresh', 'Stats and charts refresh via polling'],
                    ['2.3.13', 'Verify notification bell', 'Shows unread count, dropdown lists recent notifications'],
                ],
            ],
            [
                'name' => '2.4 Ticket Creation & Management',
                'tests' => [
                    ['2.4.1', 'Navigate to create ticket form', 'Form with subject, description, priority, client, dept, category, product, assignee'],
                    ['2.4.2', 'Submit ticket with all required fields', 'Ticket created with auto-generated ticket_number'],
                    ['2.4.3', 'Submit with missing required fields', 'Validation errors for subject, description, priority'],
                    ['2.4.4', 'View ticket list (/{slug}/tickets)', 'All tickets listed with filters'],
                    ['2.4.5', 'Filter tickets by status', 'List shows only matching status'],
                    ['2.4.6', 'Filter tickets by priority', 'List shows only matching priority'],
                    ['2.4.7', 'Filter tickets by department', 'List updates correctly'],
                    ['2.4.8', 'Filter tickets by assigned agent', 'List updates correctly'],
                    ['2.4.9', 'Search tickets by keyword', 'Matching tickets shown'],
                    ['2.4.10', 'View ticket details', 'Full ticket info with client, dept, product, creator, assignee, tasks, history'],
                    ['2.4.11', 'Edit ticket', 'Fields updated, changes saved'],
                    ['2.4.12', 'Delete ticket (soft delete)', 'Soft-deleted with reason and deleted_by recorded'],
                    ['2.4.13', 'Assign ticket to agent', 'Assigned, assignment recorded, notification sent'],
                    ['2.4.14', 'Self-assign ticket', 'Current user becomes assignee'],
                    ['2.4.15', 'Change status to "In Progress"', 'Status updated, history recorded'],
                    ['2.4.16', 'Change status to "On Hold"', 'Status updated, hold_started_at set'],
                    ['2.4.17', 'Change from "On Hold" to "Open"', 'Hold time calculated, added to total_hold_time_minutes'],
                    ['2.4.18', 'Change status to "Closed"', 'Status updated, closed_at set, notification sent'],
                    ['2.4.19', 'Change status to "Cancelled"', 'Status updated, history recorded'],
                    ['2.4.20', 'Verify overdue tickets highlight', 'Tickets past resolution_due_at shown with visual indicator'],
                ],
            ],
            [
                'name' => '2.5 Ticket Task Creation & Management',
                'tests' => [
                    ['2.5.1', 'Add a task to a ticket', 'Task created with auto task_number (T1, T2, ...)'],
                    ['2.5.2', 'Assign task to an agent', 'assigned_to field set'],
                    ['2.5.3', 'Update task description/notes', 'Changes saved'],
                    ['2.5.4', 'Change task status to "In Progress"', 'Status updated'],
                    ['2.5.5', 'Change task status to "Completed"', 'completed_at and completed_by set'],
                    ['2.5.6', 'Change task status to "Cancelled"', 'Status updated'],
                    ['2.5.7', 'Delete a task', 'Task removed'],
                    ['2.5.8', 'Verify task sort order', 'Tasks displayed in correct order'],
                ],
            ],
            [
                'name' => '2.6 Product/Services Management',
                'tests' => [
                    ['2.6.1', 'Navigate to products index', 'All products listed'],
                    ['2.6.2', 'Create a new product', 'Product created with name, description, SKU, price'],
                    ['2.6.3', 'Edit a product', 'Fields updated correctly'],
                    ['2.6.4', 'Delete a product', 'Product removed from list'],
                    ['2.6.5', 'Toggle product active/inactive', 'is_active toggled'],
                    ['2.6.6', 'Assign product to a category', 'category_id set correctly'],
                    ['2.6.7', 'Verify product in ticket creation form', 'Product listed in dropdown'],
                ],
            ],
            [
                'name' => '2.7 Category Management',
                'tests' => [
                    ['2.7.1', 'Navigate to categories index', 'All categories listed'],
                    ['2.7.2', 'Create a new category', 'Category with name, description, department, color, priority_default'],
                    ['2.7.3', 'Assign category to a department', 'department_id set correctly'],
                    ['2.7.4', 'Edit a category', 'Fields updated correctly'],
                    ['2.7.5', 'Delete a category', 'Category removed'],
                    ['2.7.6', 'Toggle category active/inactive', 'is_active toggled'],
                    ['2.7.7', 'Verify category in ticket creation form', 'Category listed, scoped to selected department'],
                ],
            ],
            [
                'name' => '2.8 User Roles (Fixed: Admin, Manager, Agents)',
                'tests' => [
                    ['2.8.1', 'Verify tenant owner has full access', 'Owner can access all features and manage users'],
                    ['2.8.2', 'Verify admin role permissions', 'Admin can manage tickets, clients, settings'],
                    ['2.8.3', 'Verify agent role has limited access', 'Agent can view/manage assigned tickets only'],
                    ['2.8.4', 'Verify workloads visible to owner/admin only', 'Non-owner/admin don\'t see agent workloads'],
                    ['2.8.5', 'Verify role on tenant_user pivot', 'User-tenant relationship has correct role'],
                ],
            ],
            [
                'name' => '2.9 Departments (Fixed / Seeded)',
                'tests' => [
                    ['2.9.1', 'Verify 4 default departments exist', 'General Support, Technical Support, Sales, Billing'],
                    ['2.9.2', 'Verify department codes', 'GEN, TECH, SALES, BILL'],
                    ['2.9.3', 'Verify department colors', '#6366f1, #8b5cf6, #10b981, #f59e0b'],
                    ['2.9.4', 'Verify departments in ticket form', 'All active departments in dropdown'],
                    ['2.9.5', 'Verify dept CRUD blocked on Start plan', 'Feature gate department_management returns 403'],
                ],
            ],
            [
                'name' => '2.10 Basic Reporting & Export',
                'tests' => [
                    ['2.10.1', 'Navigate to reports overview', 'Report page loads with volume and department data'],
                    ['2.10.2', 'Verify default date range (30 days)', 'Data covers last 30 days'],
                    ['2.10.3', 'Change date range using from/to', 'Report data updates to custom range'],
                    ['2.10.4', 'Export volume report as CSV', 'CSV with Total, Open, Closed, by Priority columns'],
                    ['2.10.5', 'Export department report as CSV', 'CSV with Department, Total, Open, Closed columns'],
                    ['2.10.6', 'Verify SLA Compliance report blocked', 'Feature gate sla_report returns 403'],
                    ['2.10.7', 'Verify Agent Performance report blocked', 'Feature gate detailed_reporting returns 403'],
                ],
            ],
            [
                'name' => '2.11 Feature Gating Verification (Start Plan)',
                'tests' => [
                    ['2.11.1', 'Access billing route', '403 — feature billing not available'],
                    ['2.11.2', 'Access attachment download route', '403 — feature attachments not available'],
                    ['2.11.3', 'Access spam management routes', '403 — feature spam_management not available'],
                    ['2.11.4', 'Access SLA management routes', '403 — feature sla_management not available'],
                    ['2.11.5', 'Access agent schedule routes', '403 — feature agent_schedule not available'],
                    ['2.11.6', 'Access service report routes', '403 — feature service_reports not available'],
                    ['2.11.7', 'Access knowledge base routes', '403 — feature knowledge_base not available'],
                    ['2.11.8', 'Access canned responses routes', '403 — feature canned_responses not available'],
                    ['2.11.9', 'Access ticket merging route', '403 — feature ticket_merging not available'],
                    ['2.11.10', 'Access ticket reopen route', '403 — feature ticket_reopening not available'],
                    ['2.11.11', 'Access custom roles routes', '403 — feature custom_roles not available'],
                    ['2.11.12', 'Access department management routes', '403 — feature department_management not available'],
                    ['2.11.13', 'Access escalation route', '403 — feature agent_escalation not available'],
                    ['2.11.14', 'Access ticket comments routes', '403 — feature client_comments not available'],
                    ['2.11.15', 'Verify Business/Enterprise sidebar items hidden', 'Feature-gated nav items not visible'],
                ],
            ],
            [
                'name' => '2.12 Public Ticket Submission & Tracking',
                'tests' => [
                    ['2.12.1', 'Navigate to /{slug}/submit-ticket (no auth)', 'Guest ticket submission form displayed'],
                    ['2.12.2', 'Submit a ticket as guest', 'Ticket created, tracking token returned'],
                    ['2.12.3', 'Navigate to /{slug}/track-ticket', 'Tracking form (ticket number + email)'],
                    ['2.12.4', 'Track ticket by number and email', 'Ticket details shown'],
                    ['2.12.5', 'Track ticket by token', 'Ticket details shown'],
                    ['2.12.6', 'Track with invalid number/email', 'Error message displayed'],
                ],
            ],
            [
                'name' => '2.13 Settings',
                'tests' => [
                    ['2.13.1', 'Navigate to general settings', 'Settings form loads'],
                    ['2.13.2', 'Update company name', 'Saved and reflected in UI'],
                    ['2.13.3', 'Navigate to ticket settings', 'Ticket settings form loads'],
                    ['2.13.4', 'Update ticket settings', 'Changes saved'],
                    ['2.13.5', 'Navigate to branding settings', 'Branding form loads (logo, colors)'],
                    ['2.13.6', 'Update branding', 'Saved, reflected in client portal'],
                    ['2.13.7', 'Verify notification settings blocked', 'Feature gate email_notifications returns 403'],
                ],
            ],
            [
                'name' => '2.14 Notifications (Basic)',
                'tests' => [
                    ['2.14.1', 'Verify bell displays unread count', 'Count matches actual unread notifications'],
                    ['2.14.2', 'Click bell to show recent', 'Dropdown lists recent notifications'],
                    ['2.14.3', 'Mark single notification as read', 'Notification removed from unread'],
                    ['2.14.4', 'Mark all as read', 'Unread count resets to 0'],
                    ['2.14.5', 'Notification on ticket creation', 'Creator gets notification'],
                    ['2.14.6', 'Notification on ticket assignment', 'Assigned agent gets notification'],
                    ['2.14.7', 'Notification on status change', 'Relevant users get notification'],
                ],
            ],
            [
                'name' => '2.15 Client Management',
                'tests' => [
                    ['2.15.1', 'Navigate to clients index', 'All clients listed'],
                    ['2.15.2', 'Create a new client', 'Client with name, email, phone, tier, status'],
                    ['2.15.3', 'Create client with linked user account', 'user_id set, portal access enabled'],
                    ['2.15.4', 'Edit a client', 'Fields updated'],
                    ['2.15.5', 'Delete a client', 'Client removed'],
                    ['2.15.6', 'Set client tier (basic/premium/enterprise)', 'Tier saved correctly'],
                    ['2.15.7', 'Toggle client status (active/inactive)', 'Status toggled'],
                ],
            ],
            [
                'name' => '2.16 Profile Management',
                'tests' => [
                    ['2.16.1', 'View profile page', 'Current user info displayed'],
                    ['2.16.2', 'Update name and email', 'Changes saved'],
                    ['2.16.3', 'Delete account', 'Account removed, session ended'],
                ],
            ],
        ],
    ],

    // ---- BUSINESS PLAN ----
    [
        'sheet' => '3. Business Plan',
        'color' => $colors['business_bg'],
        'title' => '3. BUSINESS PLAN FEATURES',
        'precondition' => 'Change the test tenant to the Business plan. Confirm all Starter tests still pass.',
        'subsections' => [
            [
                'name' => '3.1 Ticket Activity History / Audit Logs',
                'tests' => [
                    ['3.1.1', 'Create a ticket', 'History entry logged: action=created'],
                    ['3.1.2', 'Update ticket fields', 'History entries for each change with field_name, old_value, new_value'],
                    ['3.1.3', 'View ticket activity history', 'Full chronological audit trail visible'],
                    ['3.1.4', 'Verify history captures acting user', 'user_id set on each history entry'],
                    ['3.1.5', 'Verify history metadata', 'metadata JSON populated where applicable'],
                ],
            ],
            [
                'name' => '3.2 Billing',
                'tests' => [
                    ['3.2.1', 'Access billing section on ticket', 'Billing fields visible'],
                    ['3.2.2', 'Mark ticket as billable', 'is_billable set to true'],
                    ['3.2.3', 'Set billable amount', 'billable_amount saved (decimal)'],
                    ['3.2.4', 'Add billing description', 'billable_description saved'],
                    ['3.2.5', 'Mark ticket as billed', 'billed_at timestamp set'],
                    ['3.2.6', 'Remove billable flag', 'is_billable set to false'],
                    ['3.2.7', 'Verify billing blocked on Start plan', 'Returns 403'],
                ],
            ],
            [
                'name' => '3.3 Mark as Spam',
                'tests' => [
                    ['3.3.1', 'Mark a ticket as spam', 'is_spam=true, marked_spam_by set, spam_reason saved'],
                    ['3.3.2', 'Verify spam tickets filterable', 'Spam tickets excluded from default list'],
                    ['3.3.3', 'Unmark a ticket as spam', 'is_spam=false, spam fields cleared'],
                    ['3.3.4', 'Verify spam routes blocked on Start plan', 'Returns 403'],
                ],
            ],
            [
                'name' => '3.4 Auto Generated Service Reports',
                'tests' => [
                    ['3.4.1', 'Navigate to service reports index', 'Report list displayed'],
                    ['3.4.2', 'Generate report from a ticket', 'Report with auto report_number created'],
                    ['3.4.3', 'Verify report contains ticket/client data', 'report_data JSON populated correctly'],
                    ['3.4.4', 'Download a service report', 'File downloads correctly'],
                    ['3.4.5', 'Verify report status lifecycle', 'generated → sent → superseded'],
                    ['3.4.6', 'Verify routes blocked on Start plan', 'Returns 403'],
                ],
            ],
            [
                'name' => '3.5 Attachments',
                'tests' => [
                    ['3.5.1', 'Create ticket with file attachments', 'Files uploaded, attachments JSON saved'],
                    ['3.5.2', 'Verify attachment limit (max 5)', 'Validation error when exceeding 5'],
                    ['3.5.3', 'Verify file size limit (10MB)', 'Validation error for oversized files'],
                    ['3.5.4', 'Verify allowed MIME types', 'Only allowed file types accepted'],
                    ['3.5.5', 'Download an attachment', 'File streams correctly'],
                    ['3.5.6', 'Update ticket with new attachments', 'Attachments updated'],
                    ['3.5.7', 'Verify attachment routes blocked on Start', 'Returns 403'],
                ],
            ],
            [
                'name' => '3.6 Agent Availability Schedule',
                'tests' => [
                    ['3.6.1', 'Navigate to schedules index', 'Agent\'s own schedule displayed'],
                    ['3.6.2', 'Create a schedule entry', 'Entry with day_of_week, start_time, end_time'],
                    ['3.6.3', 'Set availability for each day (0-6)', 'All 7 days configurable'],
                    ['3.6.4', 'Mark a day as unavailable', 'is_available=false for that entry'],
                    ['3.6.5', 'Set effective date and end date', 'Schedule bounded by date range'],
                    ['3.6.6', 'Edit a schedule entry', 'Changes saved'],
                    ['3.6.7', 'Delete a schedule entry', 'Entry removed'],
                    ['3.6.8', 'View team schedule', 'All agents\' schedules visible'],
                    ['3.6.9', 'Verify schedule routes blocked on Start', 'Returns 403'],
                ],
            ],
            [
                'name' => '3.7 SLA Management',
                'tests' => [
                    ['3.7.1', 'Navigate to SLA policies index', 'All policies listed'],
                    ['3.7.2', 'Create an SLA policy', 'Policy with name, client_tier, priority, response/resolution hours'],
                    ['3.7.3', 'Create SLA with tier + priority combo', 'Policy matched correctly'],
                    ['3.7.4', 'Edit an SLA policy', 'Fields updated'],
                    ['3.7.5', 'Delete an SLA policy', 'Policy removed'],
                    ['3.7.6', 'Toggle SLA policy active/inactive', 'is_active toggled'],
                    ['3.7.7', 'Verify SLA auto-assigned on ticket creation', 'sla_policy_id, response_due_at, resolution_due_at set'],
                    ['3.7.8', 'Verify SLA matching logic', 'Correct policy found via findForTicket()'],
                    ['3.7.9', 'Verify SLA routes blocked on Start', 'Returns 403'],
                ],
            ],
            [
                'name' => '3.8 SLA Compliance Report',
                'tests' => [
                    ['3.8.1', 'Navigate to SLA compliance report', 'Report page loads with compliance data'],
                    ['3.8.2', 'Verify compliance metrics', 'Response and resolution compliance percentages'],
                    ['3.8.3', 'Filter by date range', 'Report updates to custom range'],
                    ['3.8.4', 'Verify breach warning command runs', 'sla:send-breach-warnings finds approaching-breach tickets'],
                    ['3.8.5', 'Verify breach notification sent', 'SlaBreachWarningNotification received by assigned agent'],
                    ['3.8.6', 'Verify sla_breach_notified_at updated', 'Timestamp set after notification sent'],
                    ['3.8.7', 'Verify no duplicate breach notifications', 'Already-notified tickets not re-notified'],
                    ['3.8.8', 'Verify SLA report blocked on Start', 'Returns 403'],
                ],
            ],
            [
                'name' => '3.9 Email Notifications',
                'tests' => [
                    ['3.9.1', 'Navigate to notification settings', 'Settings form loads'],
                    ['3.9.2', 'Configure notification preferences', 'Settings saved'],
                    ['3.9.3', 'Create ticket — verify email sent', 'TicketCreatedNotification via email'],
                    ['3.9.4', 'Assign ticket — verify email sent', 'TicketAssignedNotification via email'],
                    ['3.9.5', 'Change status — verify email sent', 'TicketStatusChangedNotification via email'],
                    ['3.9.6', 'SLA breach — verify email sent', 'SlaBreachWarningNotification via email'],
                    ['3.9.7', 'Verify notification settings blocked on Start', 'Returns 403'],
                ],
            ],
            [
                'name' => '3.10 Detailed Reporting & Export',
                'tests' => [
                    ['3.10.1', 'Navigate to agent performance report', 'Report loads with per-agent data'],
                    ['3.10.2', 'Verify agent metrics', 'Total, Open, Closed, Avg Resolution per agent'],
                    ['3.10.3', 'Filter by date range', 'Report updates'],
                    ['3.10.4', 'Export agent performance CSV', 'CSV with Agent, Total, Open, Closed, Avg Resolution'],
                    ['3.10.5', 'Verify agent report blocked on Start', 'Returns 403'],
                ],
            ],
            [
                'name' => '3.11 Knowledge Base',
                'tests' => [
                    ['3.11.1', 'Navigate to KB categories index', 'Categories listed'],
                    ['3.11.2', 'Create a KB category', 'Category with name, auto-slug, icon, sort_order'],
                    ['3.11.3', 'Edit a KB category', 'Fields updated'],
                    ['3.11.4', 'Delete a KB category', 'Category removed'],
                    ['3.11.5', 'Create a KB article', 'Article with title, slug, content, excerpt, category'],
                    ['3.11.6', 'Publish a KB article', 'is_published=true, published_at set'],
                    ['3.11.7', 'Save article as draft', 'is_published=false'],
                    ['3.11.8', 'Edit a KB article', 'Fields updated'],
                    ['3.11.9', 'Delete a KB article', 'Article removed'],
                    ['3.11.10', 'Search KB articles', 'Matching articles returned'],
                    ['3.11.11', 'Verify views_count increments', 'Count increases on each view'],
                    ['3.11.12', 'Verify KB in client portal (public)', 'Published articles visible at portal'],
                    ['3.11.13', 'Verify KB routes blocked on Start', 'Returns 403'],
                ],
            ],
            [
                'name' => '3.12 Canned Responses',
                'tests' => [
                    ['3.12.1', 'Navigate to canned responses index', 'Responses listed'],
                    ['3.12.2', 'Create a canned response', 'Response with name, content, shortcut, category'],
                    ['3.12.3', 'Edit a canned response', 'Fields updated'],
                    ['3.12.4', 'Delete a canned response', 'Response removed'],
                    ['3.12.5', 'List canned responses (API)', '/canned-responses/list returns responses'],
                    ['3.12.6', 'Filter by category', 'Only matching category returned'],
                    ['3.12.7', 'Verify routes blocked on Start', 'Returns 403'],
                ],
            ],
        ],
    ],

    // ---- ENTERPRISE PLAN ----
    [
        'sheet' => '4. Enterprise Plan',
        'color' => $colors['enterprise_bg'],
        'title' => '4. ENTERPRISE PLAN FEATURES',
        'precondition' => 'Change the test tenant to the Enterprise plan. Confirm all Starter and Business tests still pass.',
        'subsections' => [
            [
                'name' => '4.1 Ticket Merging',
                'tests' => [
                    ['4.1.1', 'Create two related tickets', 'Both tickets exist'],
                    ['4.1.2', 'Merge ticket A into ticket B', 'A: is_merged=true, merged_into_ticket_id=B, merged_at set'],
                    ['4.1.3', 'Verify merged ticket is marked', 'Merged indicator visible on ticket A'],
                    ['4.1.4', 'Verify target ticket references', 'Ticket B shows merge history'],
                    ['4.1.5', 'Verify merged ticket not in active list', 'Merged tickets excluded or flagged'],
                    ['4.1.6', 'Verify merge blocked on Business plan', 'Returns 403'],
                ],
            ],
            [
                'name' => '4.2 Ticket Re-Opening',
                'tests' => [
                    ['4.2.1', 'Close a ticket', 'Status = closed, closed_at set'],
                    ['4.2.2', 'Reopen the closed ticket', 'Status → open, closed_at cleared'],
                    ['4.2.3', 'Verify reopened_count incremented', 'Count goes from 0 to 1'],
                    ['4.2.4', 'Reopen same ticket again', 'reopened_count increments to 2'],
                    ['4.2.5', 'Verify reopen blocked on Business plan', 'Returns 403'],
                ],
            ],
            [
                'name' => '4.3 Customized Roles & Permissions',
                'tests' => [
                    ['4.3.1', 'Navigate to roles index', 'Existing roles listed (scoped to tenant)'],
                    ['4.3.2', 'Create a new custom role', 'Role created with name and permissions'],
                    ['4.3.3', 'Assign permissions to role', 'Permissions synced via Spatie'],
                    ['4.3.4', 'Edit a custom role', 'Name and permissions updated'],
                    ['4.3.5', 'Remove permissions from role', 'Permissions removed, access revoked'],
                    ['4.3.6', 'Delete a custom role', 'Role removed'],
                    ['4.3.7', 'Assign custom role to a user', 'User gains role-based permissions'],
                    ['4.3.8', 'Verify role is tenant-scoped', 'Roles isolated per tenant (team_id)'],
                    ['4.3.9', 'Verify roles blocked on Business plan', 'Returns 403'],
                ],
            ],
            [
                'name' => '4.4 Department Management',
                'tests' => [
                    ['4.4.1', 'Navigate to departments index', 'All departments listed'],
                    ['4.4.2', 'Create a new department', 'Dept with name, code, email, color, sort_order'],
                    ['4.4.3', 'Set a department as default', 'is_default=true, only one default'],
                    ['4.4.4', 'Edit a department', 'Fields updated'],
                    ['4.4.5', 'Delete a department', 'Department removed'],
                    ['4.4.6', 'Toggle department active/inactive', 'is_active toggled'],
                    ['4.4.7', 'Verify dept CRUD blocked on Business', 'Returns 403 (department_management)'],
                ],
            ],
            [
                'name' => '4.5 Agent Tiering and Escalation',
                'tests' => [
                    ['4.5.1', 'View ticket at tier_1', 'Ticket shows current_tier = tier_1'],
                    ['4.5.2', 'Escalate ticket tier_1 → tier_2', 'current_tier updated, escalation_count++, last_escalated_at set'],
                    ['4.5.3', 'Escalate ticket tier_2 → tier_3', 'Tier updated again'],
                    ['4.5.4', 'Verify escalation record created', 'Entry with from_tier, to_tier, escalated_by, trigger_type'],
                    ['4.5.5', 'Manual escalation (trigger: manual)', 'trigger_type=manual, reason captured'],
                    ['4.5.6', 'Verify escalation assigns to new user', 'escalated_to_user_id set, ticket reassigned'],
                    ['4.5.7', 'Verify escalation history on ticket', 'All escalations listed chronologically'],
                    ['4.5.8', 'Verify escalation blocked on Business', 'Returns 403'],
                ],
            ],
            [
                'name' => '4.6 Comments & Updates (Client-Agent)',
                'tests' => [
                    ['4.6.1', 'Add a public comment to a ticket', 'type=public, is_public=true'],
                    ['4.6.2', 'Add an internal comment', 'type=internal, is_public=false'],
                    ['4.6.3', 'Verify client reply type', 'type=client_reply when client submits'],
                    ['4.6.4', 'Verify status update type', 'type=status_update on status changes'],
                    ['4.6.5', 'Edit a comment', 'Content updated, edited_at and edited_by set'],
                    ['4.6.6', 'Delete a comment', 'Comment removed'],
                    ['4.6.7', 'Verify internal comments hidden from clients', 'Only is_public=true visible in portal'],
                    ['4.6.8', 'Add comment with attachment', 'attachments JSON saved on comment'],
                    ['4.6.9', 'Verify comment routes blocked on Business', 'Returns 403'],
                ],
            ],
        ],
    ],

    // ---- CLIENT PORTAL ----
    [
        'sheet' => '5. Client Portal',
        'color' => $colors['portal_bg'],
        'title' => '5. CLIENT PORTAL',
        'subsections' => [
            [
                'name' => '5.1 Portal Public Access',
                'tests' => [
                    ['5.1.1', 'Navigate to /portal/{slug}', 'Portal landing page with tenant branding'],
                    ['5.1.2', 'Verify custom branding', 'Portal uses tenant logo, primary_color, accent_color'],
                    ['5.1.3', 'Navigate to portal login', 'Login form displayed'],
                    ['5.1.4', 'Navigate to portal registration', 'Registration form displayed'],
                    ['5.1.5', 'Access KB from portal (Business+)', 'Published articles visible without login'],
                    ['5.1.6', 'Search KB from portal', 'Search results returned'],
                    ['5.1.7', 'View KB category', 'Articles in category listed'],
                    ['5.1.8', 'View KB article', 'Full article content displayed'],
                ],
            ],
            [
                'name' => '5.2 Portal Authentication',
                'tests' => [
                    ['5.2.1', 'Register as a new portal client', 'Client account created, user linked'],
                    ['5.2.2', 'Login to portal', 'Authenticated, redirected to dashboard'],
                    ['5.2.3', 'Login with invalid credentials', 'Error message shown'],
                    ['5.2.4', 'Verify portal auth is separate from main auth', 'Different session/guards'],
                    ['5.2.5', 'Logout from portal', 'Session ended, redirected to portal home'],
                ],
            ],
            [
                'name' => '5.3 Portal Dashboard & Tickets',
                'tests' => [
                    ['5.3.1', 'View portal dashboard', 'Welcome, stats (Open, Closed, Total), recent tickets'],
                    ['5.3.2', 'Create a ticket from portal', 'Ticket created, linked to client'],
                    ['5.3.3', 'View own ticket details', 'Ticket info displayed'],
                    ['5.3.4', 'Verify client cannot see internal comments', 'Only public comments visible'],
                    ['5.3.5', 'Verify client cannot see other tickets', 'Scoped to own tickets only'],
                    ['5.3.6', 'Verify suspended tenant portal blocked', 'Portal returns error'],
                ],
            ],
        ],
    ],

    // ---- CROSS-CUTTING ----
    [
        'sheet' => '6. Cross-Cutting',
        'color' => $colors['cross_bg'],
        'title' => '6. CROSS-CUTTING CONCERNS',
        'subsections' => [
            [
                'name' => '6.1 Multi-Tenancy Isolation',
                'tests' => [
                    ['6.1.1', 'Create data in Tenant A', 'Data exists only in Tenant A'],
                    ['6.1.2', 'Switch to Tenant B', 'Tenant A data not visible'],
                    ['6.1.3', 'Verify URL slug matches session tenant', 'Mismatch handled gracefully'],
                    ['6.1.4', 'Verify tenant_id on all scoped records', 'Foreign key populated'],
                ],
            ],
            [
                'name' => '6.2 Plan Limits',
                'tests' => [
                    ['6.2.1', 'Verify Start plan max 5 users', '6th user blocked'],
                    ['6.2.2', 'Verify Business plan max 25 users', '26th user blocked'],
                    ['6.2.3', 'Verify Enterprise plan unlimited users', 'No user limit'],
                    ['6.2.4', 'Verify Start plan max 100 tickets/month', '101st ticket blocked'],
                    ['6.2.5', 'Verify Business plan max 500 tickets/month', '501st ticket blocked'],
                    ['6.2.6', 'Verify Enterprise unlimited tickets', 'No ticket limit'],
                ],
            ],
            [
                'name' => '6.3 Security & Authorization',
                'tests' => [
                    ['6.3.1', 'Access tenant routes without auth', 'Redirected to login'],
                    ['6.3.2', 'Access admin panel as non-admin', '403 Forbidden'],
                    ['6.3.3', 'Access another tenant URL slug', 'Blocked or redirected'],
                    ['6.3.4', 'CSRF token validation', 'Forms without token rejected (419)'],
                    ['6.3.5', 'Email verification required', 'Unverified users blocked from tenant routes'],
                ],
            ],
            [
                'name' => '6.4 Health & Infrastructure',
                'tests' => [
                    ['6.4.1', 'Hit /health endpoint', 'Returns 200 OK'],
                    ['6.4.2', 'Homepage loads (/)', 'Welcome page with plans displayed'],
                    ['6.4.3', 'Verify queue worker processes jobs', 'Notifications/jobs processed'],
                    ['6.4.4', 'Verify scheduled command runs', 'sla:send-breach-warnings every 15 min'],
                ],
            ],
        ],
    ],
];

// =========================================================
// BUILD SHEETS
// =========================================================

$summaryData = [];
$isFirst = true;

foreach ($testSections as $section) {
    if ($isFirst) {
        $sheet = $spreadsheet->getActiveSheet();
        // We already have the cover sheet, create a new one
        $sheet = $spreadsheet->createSheet();
        $isFirst = false;
    } else {
        $sheet = $spreadsheet->createSheet();
    }

    $sheet->setTitle($section['sheet']);

    // Column widths
    $sheet->getColumnDimension('A')->setWidth(10);
    $sheet->getColumnDimension('B')->setWidth(50);
    $sheet->getColumnDimension('C')->setWidth(55);
    $sheet->getColumnDimension('D')->setWidth(15);
    $sheet->getColumnDimension('E')->setWidth(35);

    // Freeze panes (header rows)
    $sheet->freezePane('A3');

    // Section title
    $row = 1;
    $sheet->mergeCells("A{$row}:E{$row}");
    $sheet->setCellValue("A{$row}", $section['title']);
    $sheet->getStyle("A{$row}:E{$row}")->applyFromArray([
        'font' => ['bold' => true, 'size' => 14, 'color' => ['argb' => $colors['header_fg']]],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $section['color']]],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
    ]);
    $sheet->getRowDimension($row)->setRowHeight(35);
    $row++;

    // Precondition if exists
    if (! empty($section['precondition'])) {
        $sheet->mergeCells("A{$row}:E{$row}");
        $sheet->setCellValue("A{$row}", 'PRE-CONDITION: '.$section['precondition']);
        $sheet->getStyle("A{$row}:E{$row}")->applyFromArray([
            'font' => ['italic' => true, 'size' => 10, 'color' => ['argb' => 'FF92400E']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $colors['skip_bg']]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(25);
        $row++;
    }

    $totalTests = 0;

    foreach ($section['subsections'] as $sub) {
        // Subsection header
        $sheet->mergeCells("A{$row}:E{$row}");
        $sheet->setCellValue("A{$row}", $sub['name']);
        $sheet->getStyle("A{$row}:E{$row}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['argb' => $colors['subsection_fg']]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $colors['subsection_bg']]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(25);
        $row++;

        // Table header
        $sheet->setCellValue("A{$row}", '#');
        $sheet->setCellValue("B{$row}", 'Test Case');
        $sheet->setCellValue("C{$row}", 'Expected Result');
        $sheet->setCellValue("D{$row}", 'Status');
        $sheet->setCellValue("E{$row}", 'Notes');
        styleTableHeader($sheet, $row, 'E');
        $sheet->getRowDimension($row)->setRowHeight(22);
        $row++;

        // Test rows
        foreach ($sub['tests'] as $i => $test) {
            $sheet->setCellValue("A{$row}", $test[0]);
            $sheet->setCellValue("B{$row}", $test[1]);
            $sheet->setCellValue("C{$row}", $test[2]);
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            styleRow($sheet, $row, $i % 2 === 1, 'E');

            // Status dropdown
            addStatusValidation($sheet, $row, 'D');
            $sheet->getStyle("D{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $row++;
            $totalTests++;
        }

        // Spacing between subsections
        $row++;
    }

    $summaryData[] = [
        'name' => $section['title'],
        'count' => $totalTests,
        'color' => $section['color'],
    ];
}

// =========================================================
// SUMMARY SHEET (at end)
// =========================================================
$summary = $spreadsheet->createSheet();
$summary->setTitle('Summary');

$summary->getColumnDimension('A')->setWidth(35);
$summary->getColumnDimension('B')->setWidth(15);
$summary->getColumnDimension('C')->setWidth(12);
$summary->getColumnDimension('D')->setWidth(12);
$summary->getColumnDimension('E')->setWidth(12);
$summary->getColumnDimension('F')->setWidth(12);

$row = 1;
$summary->mergeCells("A{$row}:F{$row}");
$summary->setCellValue("A{$row}", 'TEST EXECUTION SUMMARY');
$summary->getStyle("A{$row}:F{$row}")->applyFromArray([
    'font' => ['bold' => true, 'size' => 16, 'color' => ['argb' => $colors['header_fg']]],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $colors['section_bg']]],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
]);
$summary->getRowDimension($row)->setRowHeight(40);
$row += 2;

// Table header
$summary->setCellValue("A{$row}", 'Section');
$summary->setCellValue("B{$row}", 'Total Tests');
$summary->setCellValue("C{$row}", 'Pass');
$summary->setCellValue("D{$row}", 'Fail');
$summary->setCellValue("E{$row}", 'Skip');
$summary->setCellValue("F{$row}", 'Blocked');
styleTableHeader($summary, $row, 'F');
$row++;

$grandTotal = 0;
foreach ($summaryData as $data) {
    $summary->setCellValue("A{$row}", $data['name']);
    $summary->setCellValue("B{$row}", $data['count']);
    $summary->getStyle("A{$row}")->applyFromArray([
        'font' => ['bold' => true],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $data['color']]],
        'font' => ['color' => ['argb' => $colors['header_fg']], 'bold' => true],
    ]);
    styleRow($summary, $row, false, 'F');
    $summary->getStyle("B{$row}:F{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $grandTotal += $data['count'];
    $row++;
}

// Total row
$summary->setCellValue("A{$row}", 'TOTAL');
$summary->setCellValue("B{$row}", $grandTotal);
$summary->getStyle("A{$row}:F{$row}")->applyFromArray([
    'font' => ['bold' => true, 'size' => 12],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $colors['header_bg']]],
    'font' => ['color' => ['argb' => $colors['header_fg']], 'bold' => true, 'size' => 12],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => $colors['border']]]],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
]);

$row += 2;
$signoffFields = ['Overall Result:', 'Tested By:', 'Sign-off Date:', 'Notes:'];
foreach ($signoffFields as $field) {
    $summary->setCellValue("A{$row}", $field);
    $summary->getStyle("A{$row}")->getFont()->setBold(true);
    $summary->mergeCells("B{$row}:F{$row}");
    $summary->getStyle("B{$row}:F{$row}")->applyFromArray([
        'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => $colors['border']]]],
    ]);
    $row++;
}

// =========================================================
// Set active sheet to cover and save
// =========================================================
$spreadsheet->setActiveSheetIndex(0);

$writer = new Xlsx($spreadsheet);
$outputPath = __DIR__.'/TESTING_FORM.xlsx';
$writer->save($outputPath);

echo "Excel file generated: {$outputPath}\n";
echo "Total test cases: {$grandTotal}\n";
echo 'Sheets created: '.$spreadsheet->getSheetCount()."\n";
