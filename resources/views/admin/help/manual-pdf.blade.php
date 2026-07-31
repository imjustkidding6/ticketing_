<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CliqueHA SaaS Ticketing System - Administrator User Manual</title>
    <style>
        @page {
            margin: 18mm 14mm 18mm 14mm;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 10pt;
            line-height: 1.5;
            color: #1e293b;
            background-color: #ffffff;
        }

        /* Cover Page Styling */
        .cover-page {
            page-break-after: always;
            text-align: center;
            padding-top: 50px;
        }
        .cover-logo {
            font-size: 38pt;
            font-weight: 900;
            color: #4f46e5;
            letter-spacing: 2px;
            margin-bottom: 8px;
        }
        .cover-subtitle {
            font-size: 12pt;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 3px;
            margin-bottom: 40px;
        }
        .cover-title {
            font-size: 24pt;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 18px;
            line-height: 1.3;
        }
        .cover-desc {
            font-size: 10.5pt;
            color: #475569;
            margin-top: 15px;
            margin-bottom: 40px;
            line-height: 1.6;
            max-width: 80%;
            margin-left: auto;
            margin-right: auto;
        }
        .cover-meta {
            margin-top: 50px;
            font-size: 9.5pt;
            color: #475569;
            border-top: 2px solid #e2e8f0;
            padding-top: 20px;
            display: inline-block;
            width: 90%;
        }
        .cover-meta table {
            width: 100%;
            border-collapse: collapse;
        }
        .cover-meta td {
            padding: 5px 12px;
            text-align: left;
        }

        /* Page Headers & Formatting */
        .page-break {
            page-break-before: always;
        }
        
        h1 {
            font-size: 17pt;
            color: #0f172a;
            border-bottom: 2px solid #4f46e5;
            padding-bottom: 5px;
            margin-top: 22px;
            margin-bottom: 12px;
        }
        h2 {
            font-size: 13pt;
            color: #1e293b;
            margin-top: 18px;
            margin-bottom: 8px;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 3px;
        }
        h3 {
            font-size: 11pt;
            color: #334155;
            margin-top: 14px;
            margin-bottom: 6px;
        }
        p {
            margin-bottom: 10px;
            text-align: justify;
        }

        /* Screenshot Styling */
        .screenshot-box {
            margin: 14px 0;
            text-align: center;
            page-break-inside: avoid;
        }
        .screenshot-img {
            max-width: 95%;
            height: auto;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
        }
        .screenshot-caption {
            font-size: 9pt;
            color: #475569;
            font-weight: bold;
            margin-top: 6px;
        }

        /* Callout Boxes */
        .callout {
            padding: 9px 12px;
            border-radius: 5px;
            margin: 10px 0;
            font-size: 9.5pt;
            page-break-inside: avoid;
        }
        .callout-note {
            background-color: #f0f9ff;
            border-left: 4px solid #0284c7;
            color: #0369a1;
        }
        .callout-tip {
            background-color: #f0fdf4;
            border-left: 4px solid #16a34a;
            color: #15803d;
        }
        .callout-warning {
            background-color: #fffbebfb;
            border-left: 4px solid #d97706;
            color: #b45309;
        }
        .callout-important {
            background-color: #fef2f2;
            border-left: 4px solid #dc2626;
            color: #b91c1c;
        }

        /* Table Styling */
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
            font-size: 9pt;
            page-break-inside: avoid;
        }
        table.data-table th, table.data-table td {
            border: 1px solid #cbd5e1;
            padding: 5px 8px;
            text-align: left;
        }
        table.data-table th {
            background-color: #f1f5f9;
            color: #0f172a;
            font-weight: bold;
        }

        /* Step Styling */
        .step-header {
            font-weight: bold;
            color: #4f46e5;
            font-size: 10.5pt;
            margin-top: 12px;
            margin-bottom: 4px;
        }

        /* TOC Styling */
        .toc-list {
            list-style: none;
            padding-left: 0;
        }
        .toc-list li {
            padding: 4px 0;
            border-bottom: 1px dashed #e2e8f0;
            font-size: 10pt;
        }
        .toc-number {
            font-weight: bold;
            color: #4f46e5;
            display: inline-block;
            width: 28px;
        }
    </style>
</head>
<body>

    <!-- COVER PAGE -->
    <div class="cover-page">
        <div class="cover-logo">CliqueHA</div>
        <div class="cover-subtitle">Enterprise Multi-Tenant SaaS Platform</div>
        
        <div class="cover-title">
            System Administrator<br>Operations & Governance User Manual
        </div>
        
        <div class="cover-desc">
            Official Master Step-by-Step Operations & Governance Guide for Global Administration, Multi-Tenant Provisioning, License Minting, SLA Compliance Control, and AI Assistant Management.
        </div>

        <div class="cover-meta">
            <table>
                <tr>
                    <td><strong>Platform Release:</strong> v2.4.0 (Enterprise Suite)</td>
                    <td><strong>Document Control:</strong> Official Master Guide</td>
                </tr>
                <tr>
                    <td><strong>Generated Date:</strong> {{ date('F j, Y') }}</td>
                    <td><strong>Authoring Team:</strong> Platform Operations & Engineering</td>
                </tr>
                <tr>
                    <td><strong>Target Interface:</strong> Global Admin Panel (/admin)</td>
                    <td><strong>Security Level:</strong> Restricted — System Administrator Only</td>
                </tr>
            </table>
        </div>
    </div>

    <!-- TABLE OF CONTENTS -->
    <div class="page-break"></div>
    <h1>Table of Contents</h1>
    <ul class="toc-list">
        <li><span class="toc-number">1.</span> <strong>Introduction & System Architecture</strong> — Purpose, Scope, Tenant Isolation & Governance</li>
        <li><span class="toc-number">2.</span> <strong>Authentication & Security Operations</strong> — Administrator Sign-In, Session Security & Logout</li>
        <li><span class="toc-number">3.</span> <strong>Executive Dashboard Telemetry</strong> — Real-Time Telemetry Cards & Global Navigation Menu</li>
        <li><span class="toc-number">4.</span> <strong>Tenant Management & Provisioning</strong> — Tenant Directory, Creation Form, Editing, Suspension & Deletion Safeguards</li>
        <li><span class="toc-number">5.</span> <strong>Global User Management & Roles</strong> — Account Directory, User Provisioning, Credential Edits & Status Toggles</li>
        <li><span class="toc-number">6.</span> <strong>Subscription License Key Minting</strong> — Key Minting Form, Expiration, Distributor Binding, Revocation & Reactivation</li>
        <li><span class="toc-number">7.</span> <strong>Subscription Plan Governance</strong> — Plan Tiers Directory, Create/Edit Forms, Seat Limits & Feature Flags</li>
        <li><span class="toc-number">8.</span> <strong>Distributor & Reseller Partner Network</strong> — Reseller Directory, Partner Registration, Quotas & Channel Metrics</li>
        <li><span class="toc-number">9.</span> <strong>SLA Policies & Command Center</strong> — Health Dashboard, Policy Registry, Response Target Config & Audit Exports</li>
        <li><span class="toc-number">10.</span> <strong>System Notification Center</strong> — Security Alerts, SLA Breach Warnings & Notification Filtering</li>
        <li><span class="toc-number">11.</span> <strong>AI Assistant & Copilot Intelligence</strong> — Interactive Chatbot, Telemetry Prompts, Formatted Code Blocks & Diagnostics</li>
        <li><span class="toc-number">12.</span> <strong>System Settings & Global Parameters</strong> — Application Configuration, Branding, SMTP Parameters & Maintenance Mode</li>
        <li><span class="toc-number">13.</span> <strong>Help Center & Interactive Tutorials</strong> — Tutorial Directory, Detailed Walkthroughs & Manual PDF Download</li>
        <li><span class="toc-number">14.</span> <strong>System Announcements & Broadcasts</strong> — Global Banners, Severity Tiers & Audience Scheduling</li>
        <li><span class="toc-number">15.</span> <strong>Bug Reports & Platform Feedback Audit</strong> — Issue Triage, Developer Escalations & CSAT Rating Audits</li>
    </ul>

    <!-- 1. INTRODUCTION -->
    <div class="page-break"></div>
    <h1>1. Introduction & System Architecture</h1>
    <h2>1.1 Purpose & Enterprise Multi-Tenant Scope</h2>
    <p>
        The CliqueHA Admin Panel serves as the central control plane for managing the multi-tenant SaaS ticketing infrastructure. While individual corporate tenants operate within isolated tenant portals for daily customer support, the Global Administration Portal (<code>/admin</code>) grants complete administrative authority across all registered corporate tenants, users, license keys, SLA policies, reseller partnerships, and AI copilot services.
    </p>

    <h2>1.2 System Architecture & Tenant Isolation</h2>
    <p>
        The platform enforces strict logical data isolation across corporate tenants. Each tenant workspace is bound to a unique domain/slug, dedicated database scoping rules, and subscription entitlements. System Administrators oversee global infrastructure health, issue cryptographically signed license keys, configure SLA response thresholds, manage global announcements, and perform cross-tenant analytics.
    </p>

    <div class="callout callout-important">
        <strong>IMPORTANT SECURITY NOTICE:</strong> Actions performed within the Global Admin Panel affect all tenant organizations platform-wide. Administrators must adhere to least-privilege operational guidelines and verify all destructive actions before execution.
    </div>

    <!-- 2. AUTHENTICATION -->
    <div class="page-break"></div>
    <h1>2. Authentication & Security Operations</h1>
    <h2>2.1 Administrator Sign-In Operations</h2>
    <p>
        Access to the global administration workspace is restricted to authorized administrator accounts. Follow the step-by-step procedure below to authenticate into the system.
    </p>

    <div class="step-header">Step 1: Navigate to the Authentication Gateway</div>
    <p>Open your browser and navigate to <code>http://your-domain.com/admin/login</code> or <code>/login</code>.</p>
    <div class="screenshot-box">
        <img src="{{ public_path('docs/screenshots/01_login_page.png') }}" class="screenshot-img" alt="Login Screen">
        <div class="screenshot-caption">Figure 1: Platform Login Screen</div>
    </div>
    <p>
        <strong>Interface Description:</strong> The sign-in portal features input fields for your Administrator Email and Password, a "Remember Me" session persistence toggle, and brand validation branding.
    </p>

    <div class="step-header">Step 2: Enter Credentials & Submit</div>
    <table class="data-table">
        <tr>
            <th>Field Name</th>
            <th>Type / Requirement</th>
            <th>Description & Validation Rules</th>
        </tr>
        <tr>
            <td><strong>Email Address</strong></td>
            <td>Required / Valid Email</td>
            <td>Your registered administrator email address (e.g., <code>admin@example.com</code>).</td>
        </tr>
        <tr>
            <td><strong>Password</strong></td>
            <td>Required / Minimum 8 chars</td>
            <td>Account authentication password. Must meet platform complexity requirements.</td>
        </tr>
        <tr>
            <td><strong>Remember Me</strong></td>
            <td>Optional Checkbox</td>
            <td>Extends session cookie duration for up to 30 days on trusted administrative devices.</td>
        </tr>
    </table>

    <div class="step-header">Step 3: Verification & Dashboard Redirection</div>
    <p>Click <strong>Sign In</strong>. Upon credential validation, the system verifies your administrator role flags and redirects you directly to the Executive Dashboard.</p>
    <div class="screenshot-box">
        <img src="{{ public_path('docs/screenshots/02_login_success.png') }}" class="screenshot-img" alt="Login Success">
        <div class="screenshot-caption">Figure 2: Successful Administrator Login & Workspace Redirection</div>
    </div>
    <p>
        <strong>Expected Result:</strong> A success notification appears, and the platform loads the main executive dashboard telemetry view.
    </p>

    <div class="callout callout-warning">
        <strong>SECURITY NOTE:</strong> Five consecutive failed sign-in attempts will temporarily lock out the account IP address for 15 minutes to prevent brute-force attacks.
    </div>

    <!-- 3. DASHBOARD OVERVIEW -->
    <div class="page-break"></div>
    <h1>3. Executive Dashboard Telemetry</h1>
    <h2>3.1 Executive Dashboard Telemetry Overview</h2>
    <p>
        The Admin Dashboard provides real-time telemetry across all platform operations, tenant activity, support ticket volumes, and SLA performance.
    </p>

    <div class="screenshot-box">
        <img src="{{ public_path('docs/screenshots/03_dashboard_overview.png') }}" class="screenshot-img" alt="Dashboard Overview">
        <div class="screenshot-caption">Figure 3: Dashboard Overview & Real-Time Telemetry</div>
    </div>

    <div class="screenshot-box">
        <img src="{{ public_path('docs/screenshots/04_sidebar_navigation.png') }}" class="screenshot-img" alt="Navigation Sidebar">
        <div class="screenshot-caption">Figure 4: Global Sidebar Navigation Bar</div>
    </div>

    <h2>3.2 Key Telemetry Metrics Breakdown</h2>
    <table class="data-table">
        <tr>
            <th>Telemetry Widget</th>
            <th>Description & Purpose</th>
            <th>Operational Target</th>
        </tr>
        <tr>
            <td><strong>Active Tenants</strong></td>
            <td>Total count of active registered corporate tenant organizations currently provisioned.</td>
            <td>Monitor multi-tenant platform expansion.</td>
        </tr>
        <tr>
            <td><strong>Active Users</strong></td>
            <td>Total active global user accounts across all tenant organizations and admin roles.</td>
            <td>Track platform concurrency & seat utilization.</td>
        </tr>
        <tr>
            <td><strong>Active Licenses</strong></td>
            <td>Currently minted and active enterprise subscription license keys in circulation.</td>
            <td>Verify license compliance & expiration health.</td>
        </tr>
        <tr>
            <td><strong>Total Tickets</strong></td>
            <td>Aggregated support ticket volume logged across all tenant workspaces.</td>
            <td>Assess global support workload demands.</td>
        </tr>
        <tr>
            <td><strong>SLA Compliance</strong></td>
            <td>Percentage of tickets resolved within mandated SLA response & resolution deadlines.</td>
            <td>Target: Maintain &ge; 98.5% compliance.</td>
        </tr>
    </table>

    <!-- 4. TENANT MANAGEMENT -->
    <div class="page-break"></div>
    <h1>4. Tenant Management & Lifecycle Governance</h1>
    <h2>4.1 Tenant Directory Overview</h2>
    <p>
        The Tenant Management workspace enables administrators to provision, configure, monitor, suspend, or decommission tenant organizations across their complete lifecycle.
    </p>

    <div class="step-header">Step 1: Access Tenant Directory</div>
    <p>Navigate to <strong>Workspace &rarr; Tenants</strong> using the left navigation sidebar.</p>
    <div class="screenshot-box">
        <img src="{{ public_path('docs/screenshots/05_tenant_list.png') }}" class="screenshot-img" alt="Tenant List">
        <div class="screenshot-caption">Figure 5: Tenant Directory Management Page</div>
    </div>
    <p>
        <strong>Key Interface Elements:</strong> Search Bar, Plan Filter Dropdown, Status Filter (Active / Suspended), Tenant Table (ID, Name, Domain, Plan, Users, Status, Actions), and "Create Tenant" Action Button.
    </p>

    <div class="step-header">Step 2: Provision a New Workspace Tenant</div>
    <p>Click the <strong>Create Tenant</strong> button in the top right header to open the provisioning form.</p>
    <div class="screenshot-box">
        <img src="{{ public_path('docs/screenshots/06_create_tenant.png') }}" class="screenshot-img" alt="Create Tenant Form">
        <div class="screenshot-caption">Figure 6: Provision New Tenant Workspace Form</div>
    </div>
    <table class="data-table">
        <tr>
            <th>Form Input Field</th>
            <th>Required / Type</th>
            <th>Description & Validation Rules</th>
        </tr>
        <tr>
            <td><strong>Company / Tenant Name</strong></td>
            <td>Required / Text</td>
            <td>Official corporate entity name (e.g., <code>Acme Enterprises</code>). Must be unique.</td>
        </tr>
        <tr>
            <td><strong>Domain Slug</strong></td>
            <td>Required / Alphanumeric</td>
            <td>Subdomain identifier (e.g., <code>acme</code> for <code>acme.domain.com</code>). Lowercase only.</td>
        </tr>
        <tr>
            <td><strong>Tenant Owner Email</strong></td>
            <td>Required / Email</td>
            <td>Primary contact email for the initial Tenant Owner account. Receives welcome credentials.</td>
        </tr>
        <tr>
            <td><strong>Subscription Plan</strong></td>
            <td>Required / Select</td>
            <td>Select plan tier (Starter, Business, Enterprise). Dictates seat capacity & feature flags.</td>
        </tr>
        <tr>
            <td><strong>Max Seat Capacity</strong></td>
            <td>Required / Integer</td>
            <td>Maximum agent seats allocated to this tenant (e.g., <code>50</code>). Must be &ge; 1.</td>
        </tr>
    </table>

    <div class="step-header">Step 3: Edit Tenant Information & License Plan</div>
    <p>To update an existing tenant's parameters, click the <strong>Edit</strong> icon on the tenant table row.</p>
    <div class="screenshot-box">
        <img src="{{ public_path('docs/screenshots/07_tenant_edit.png') }}" class="screenshot-img" alt="Edit Tenant">
        <div class="screenshot-caption">Figure 7: Edit Tenant Information & License Plan Configuration</div>
    </div>

    <div class="step-header">Step 4: Execute Administrative Control Actions</div>
    <p>From the tenant action menu, administrators can perform instant administrative operations:</p>
    <div class="screenshot-box">
        <img src="{{ public_path('docs/screenshots/08_tenant_actions.png') }}" class="screenshot-img" alt="Tenant Control Actions">
        <div class="screenshot-caption">Figure 8: Tenant Administrative Action Controls</div>
    </div>
    <ul>
        <li><strong>Log in as Tenant (Impersonation):</strong> Temporarily switch identity into the tenant workspace to troubleshoot tenant issues directly without requesting user passwords.</li>
        <li><strong>Suspend / Unsuspend:</strong> Immediately freeze or restore tenant portal access for billing or compliance reasons.</li>
        <li><strong>Update Seat Allocation:</strong> Increase or decrease allocated user seat capacity dynamically.</li>
    </ul>

    <div class="step-header">Step 5: Deleting a Tenant Workspace</div>
    <p>To delete an inactive or terminated tenant, click <strong>Delete</strong>. The system opens a security confirmation modal.</p>
    <div class="screenshot-box">
        <img src="{{ public_path('docs/screenshots/09_tenant_delete_modal.png') }}" class="screenshot-img" alt="Tenant Delete Modal">
        <div class="screenshot-caption">Figure 9: Tenant Deletion Confirmation Modal with Related Record Safeguards</div>
    </div>
    <div class="callout callout-important">
        <strong>DELETION SAFEGUARD:</strong> Deleting a tenant permanently removes all associated support tickets, departments, categories, and client accounts. This action is irreversible.
    </div>

    <!-- 5. USER MANAGEMENT -->
    <div class="page-break"></div>
    <h1>5. Global User Management & Roles</h1>
    <h2>5.1 Account Directory & Role Matrix</h2>
    <p>
        Manage all system user accounts across administrative and tenant scopes. The system enforces role-based access control across four principal tiers: Super Admin, Tenant Owner, Support Agent, and Client User.
    </p>

    <div class="step-header">Step 1: Access Global User Directory</div>
    <p>Navigate to <strong>Workspace &rarr; Users</strong>.</p>
    <div class="screenshot-box">
        <img src="{{ public_path('docs/screenshots/10_user_list.png') }}" class="screenshot-img" alt="User Directory">
        <div class="screenshot-caption">Figure 10: Global User Directory</div>
    </div>

    <div class="step-header">Step 2: Provision a New Global User Account</div>
    <p>Click <strong>Create User</strong> to launch the user provisioning form.</p>
    <div class="screenshot-box">
        <img src="{{ public_path('docs/screenshots/11_create_user.png') }}" class="screenshot-img" alt="Create User Form">
        <div class="screenshot-caption">Figure 11: Create New Global User Form</div>
    </div>
    <table class="data-table">
        <tr>
            <th>Field Name</th>
            <th>Type / Requirement</th>
            <th>Description & Purpose</th>
        </tr>
        <tr>
            <td><strong>Full Name</strong></td>
            <td>Required / Text</td>
            <td>User's first and last name (e.g., <code>Sarah Jenkins</code>).</td>
        </tr>
        <tr>
            <td><strong>Email Address</strong></td>
            <td>Required / Unique Email</td>
            <td>System sign-in email address and notification destination.</td>
        </tr>
        <tr>
            <td><strong>Password</strong></td>
            <td>Required / Secret</td>
            <td>Initial account password. System prompts user to reset upon first login.</td>
        </tr>
        <tr>
            <td><strong>Role Assignment</strong></td>
            <td>Required / Dropdown</td>
            <td>Assign role (Super Admin, Tenant Admin, Agent, Client).</td>
        </tr>
        <tr>
            <td><strong>Tenant Binding</strong></td>
            <td>Conditional / Select</td>
            <td>Select assigned corporate tenant workspace (N/A for Super Admins).</td>
        </tr>
    </table>

    <div class="step-header">Step 3: Edit User Credentials & Role Allocations</div>
    <p>Click <strong>Edit</strong> on any user row to adjust account details or reset credentials.</p>
    <div class="screenshot-box">
        <img src="{{ public_path('docs/screenshots/12_edit_user.png') }}" class="screenshot-img" alt="Edit User">
        <div class="screenshot-caption">Figure 12: Edit User Credentials & Role Assignment</div>
    </div>

    <div class="step-header">Step 4: Account Status Toggles & Security Actions</div>
    <p>Toggle account status between Active and Suspended instantly via the user directory toggle switch.</p>
    <div class="screenshot-box">
        <img src="{{ public_path('docs/screenshots/13_user_actions.png') }}" class="screenshot-img" alt="User Actions">
        <div class="screenshot-caption">Figure 13: Toggle User Status & Security Actions</div>
    </div>

    <!-- 6. LICENSES -->
    <div class="page-break"></div>
    <h1>6. Subscription License Keys & Minting</h1>
    <h2>6.1 License Directory & Minting Operations</h2>
    <p>
        Issue, track, and bind enterprise subscription license keys (<code>LIC-XXXX-XXXX</code>) to corporate tenants and reseller distributors.
    </p>

    <div class="step-header">Step 1: Access License Key Directory</div>
    <p>Navigate to <strong>Billing &rarr; Licenses</strong>.</p>
    <div class="screenshot-box">
        <img src="{{ public_path('docs/screenshots/14_license_list.png') }}" class="screenshot-img" alt="License Directory">
        <div class="screenshot-caption">Figure 14: Enterprise License Key Directory</div>
    </div>

    <div class="step-header">Step 2: Generate & Issue New License Key</div>
    <p>Click <strong>Generate License Key</strong> to open the key minting form.</p>
    <div class="screenshot-box">
        <img src="{{ public_path('docs/screenshots/15_generate_license.png') }}" class="screenshot-img" alt="Generate License Form">
        <div class="screenshot-caption">Figure 15: Issue & Mint New License Key Form</div>
    </div>
    <table class="data-table">
        <tr>
            <th>License Input Field</th>
            <th>Type / Requirement</th>
            <th>Description & Rules</th>
        </tr>
        <tr>
            <td><strong>Plan Tier</strong></td>
            <td>Required / Dropdown</td>
            <td>Select plan tier entitlement (Starter, Business, Enterprise).</td>
        </tr>
        <tr>
            <td><strong>Max Seat Count</strong></td>
            <td>Required / Integer</td>
            <td>Total user capacity granted by key (e.g., <code>100 seats</code>).</td>
        </tr>
        <tr>
            <td><strong>Expiration Date</strong></td>
            <td>Required / Date Picker</td>
            <td>Date when license key expires and requires renewal.</td>
        </tr>
        <tr>
            <td><strong>Distributor Partner</strong></td>
            <td>Optional / Select</td>
            <td>Bind license key to a registered reseller partner distributor.</td>
        </tr>
    </table>

    <div class="step-header">Step 3: Edit License Configuration & Distributor Binding</div>
    <div class="screenshot-box">
        <img src="{{ public_path('docs/screenshots/16_edit_license.png') }}" class="screenshot-img" alt="Edit License">
        <div class="screenshot-caption">Figure 16: License Configuration & Distributor Binding</div>
    </div>

    <div class="step-header">Step 4: Revoke & Reactivate License Keys</div>
    <div class="screenshot-box">
        <img src="{{ public_path('docs/screenshots/17_license_actions.png') }}" class="screenshot-img" alt="License Controls">
        <div class="screenshot-caption">Figure 17: Revoke & Reactivate License Controls</div>
    </div>
    <div class="callout callout-warning">
        <strong>REVOCATION NOTICE:</strong> Revoking a license key immediately degrades the bound tenant workspace to Read-Only mode until a valid renewal key is registered.
    </div>

    <!-- 7. SUBSCRIPTION PLANS -->
    <div class="page-break"></div>
    <h1>7. Subscription Plan Governance</h1>
    <h2>7.1 Plan Tiers & Feature Flag Management</h2>
    <p>
        Define product packaging tiers, pricing models, seat quotas, and feature entitlements.
    </p>

    <div class="step-header">Step 1: Access Subscription Plans Directory</div>
    <p>Navigate to <strong>Billing &rarr; Plans</strong>.</p>
    <div class="screenshot-box">
        <img src="{{ public_path('docs/screenshots/18_plan_list.png') }}" class="screenshot-img" alt="Plan List">
        <div class="screenshot-caption">Figure 18: Subscription Plans Directory</div>
    </div>

    <div class="step-header">Step 2: Create a New Subscription Plan Tier</div>
    <p>Click <strong>Create Plan Tier</strong>.</p>
    <div class="screenshot-box">
        <img src="{{ public_path('docs/screenshots/19_create_plan.png') }}" class="screenshot-img" alt="Create Plan">
        <div class="screenshot-caption">Figure 19: Create New Subscription Plan Tier Form</div>
    </div>
    <table class="data-table">
        <tr>
            <th>Plan Attribute</th>
            <th>Type</th>
            <th>Description & Entitlement Impact</th>
        </tr>
        <tr>
            <td><strong>Plan Name & Code</strong></td>
            <td>Required / Text</td>
            <td>Display name and unique system code slug (e.g., <code>enterprise-plus</code>).</td>
        </tr>
        <tr>
            <td><strong>Monthly / Annual Price</strong></td>
            <td>Required / Currency</td>
            <td>Subscription pricing rates billed to tenant organizations.</td>
        </tr>
        <tr>
            <td><strong>Seat Limit</strong></td>
            <td>Required / Integer</td>
            <td>Default maximum user seat allocation included in plan.</td>
        </tr>
        <tr>
            <td><strong>Feature Entitlements</strong></td>
            <td>Checkboxes</td>
            <td>Toggle access to AI Copilot, Custom SLAs, API Access, SSO Integration.</td>
        </tr>
    </table>

    <div class="step-header">Step 3: Edit Plan Limits & Feature Entitlements</div>
    <div class="screenshot-box">
        <img src="{{ public_path('docs/screenshots/20_edit_plan.png') }}" class="screenshot-img" alt="Edit Plan">
        <div class="screenshot-caption">Figure 20: Edit Subscription Plan Entitlements & Pricing Configuration</div>
    </div>

    <!-- 8. DISTRIBUTORS -->
    <div class="page-break"></div>
    <h1>8. Distributor & Reseller Partner Network</h1>
    <h2>8.1 Channel Partner Directory & License Quotas</h2>
    <p>
        Track reseller partners, channel sales allocations, distributor inventories, and commission metrics.
    </p>

    <div class="step-header">Step 1: Access Distributor Directory</div>
    <p>Navigate to <strong>Partners &rarr; Distributors</strong>.</p>
    <div class="screenshot-box">
        <img src="{{ public_path('docs/screenshots/21_distributor_list.png') }}" class="screenshot-img" alt="Distributor List">
        <div class="screenshot-caption">Figure 21: Reseller Partner Distributor Directory</div>
    </div>

    <div class="step-header">Step 2: Register New Reseller Partner</div>
    <p>Click <strong>Register Partner</strong> to complete partner onboarding.</p>
    <div class="screenshot-box">
        <img src="{{ public_path('docs/screenshots/22_create_distributor.png') }}" class="screenshot-img" alt="Register Partner">
        <div class="screenshot-caption">Figure 22: Register New Reseller Partner Form</div>
    </div>

    <div class="step-header">Step 3: Edit Reseller Partner Configuration</div>
    <div class="screenshot-box">
        <img src="{{ public_path('docs/screenshots/23_edit_distributor.png') }}" class="screenshot-img" alt="Edit Distributor">
        <div class="screenshot-caption">Figure 23: Edit Reseller Distributor Configuration Form</div>
    </div>

    <!-- 9. SLA POLICIES -->
    <div class="page-break"></div>
    <h1>9. SLA Policies & Command Center</h1>
    <h2>9.1 SLA Health Command Center Dashboard</h2>
    <p>
        Monitor real-time SLA compliance across all support tiers, track warning thresholds, and prevent SLA breaches.
    </p>

    <div class="screenshot-box">
        <img src="{{ public_path('docs/screenshots/24_sla_health_command_center.png') }}" class="screenshot-img" alt="SLA Command Center">
        <div class="screenshot-caption">Figure 24: SLA Health Command Center Dashboard</div>
    </div>

    <h2>9.2 SLA Policy Registry & Response Target Configuration</h2>
    <div class="screenshot-box">
        <img src="{{ public_path('docs/screenshots/25_sla_registry.png') }}" class="screenshot-img" alt="SLA Registry">
        <div class="screenshot-caption">Figure 25: SLA Policy Registry Overview</div>
    </div>

    <div class="step-header">Step 1: Configure Response & Resolution Targets</div>
    <p>Click <strong>Create Policy</strong> to establish response time targets per priority.</p>
    <div class="screenshot-box">
        <img src="{{ public_path('docs/screenshots/26_create_sla_policy.png') }}" class="screenshot-img" alt="Create Policy Modal">
        <div class="screenshot-caption">Figure 26: Configure SLA Policy Targets Modal</div>
    </div>
    <table class="data-table">
        <tr>
            <th>Priority Tier</th>
            <th>First Response Target</th>
            <th>Resolution Target</th>
            <th>Escalation Trigger</th>
        </tr>
        <tr>
            <td><strong>Urgent</strong></td>
            <td>15 Minutes</td>
            <td>2 Hours</td>
            <td>Escalate to Tier 3 Lead at 50% time elapsed</td>
        </tr>
        <tr>
            <td><strong>High</strong></td>
            <td>1 Hour</td>
            <td>8 Hours</td>
            <td>Escalate to Senior Agent at 75% time elapsed</td>
        </tr>
        <tr>
            <td><strong>Normal</strong></td>
            <td>4 Hours</td>
            <td>24 Hours</td>
            <td>Warning alert to assigned team at 80% time</td>
        </tr>
        <tr>
            <td><strong>Low</strong></td>
            <td>12 Hours</td>
            <td>72 Hours</td>
            <td>Standard queue monitoring</td>
        </tr>
    </table>

    <div class="step-header">Step 2: Edit Tier Response Targets & Escalation Rules</div>
    <div class="screenshot-box">
        <img src="{{ public_path('docs/screenshots/27_edit_sla_policy.png') }}" class="screenshot-img" alt="Edit SLA Targets">
        <div class="screenshot-caption">Figure 27: Edit SLA Target Response Times & Tier Escalation Rules</div>
    </div>

    <!-- 10. NOTIFICATIONS -->
    <div class="page-break"></div>
    <h1>10. System Notification Center</h1>
    <h2>10.1 Centralized System Notification Management</h2>
    <p>
        Monitor security alerts, SLA breach warnings, license expiration notices, and system health status.
    </p>

    <div class="screenshot-box">
        <img src="{{ public_path('docs/screenshots/28_notification_center.png') }}" class="screenshot-img" alt="Notification Center">
        <div class="screenshot-caption">Figure 28: System Notification Center Workspace</div>
    </div>

    <div class="step-header">Step 1: Filtering & Marking Notifications Read</div>
    <div class="screenshot-box">
        <img src="{{ public_path('docs/screenshots/29_notification_actions.png') }}" class="screenshot-img" alt="Notification Actions">
        <div class="screenshot-caption">Figure 29: Filter Alerts & Mark Notifications Read</div>
    </div>

    <!-- 11. AI ASSISTANT -->
    <div class="page-break"></div>
    <h1>11. AI Assistant & Copilot Intelligence</h1>
    <h2>11.1 AI Assistant Chat Workspace & Diagnostics</h2>
    <p>
        Engage with the embedded AI Copilot assistant to run platform diagnostics, query real-time telemetry metrics, format code solutions, and export chat transcripts.
    </p>

    <div class="screenshot-box">
        <img src="{{ public_path('docs/screenshots/30_ai_chat_interface.png') }}" class="screenshot-img" alt="AI Chat Workspace">
        <div class="screenshot-caption">Figure 30: AI Assistant Workspace & Chat Interface</div>
    </div>

    <div class="step-header">Step 1: Submitting Operational Prompts</div>
    <p>Type natural language queries in the prompt input field and click <strong>Send</strong>.</p>
    <div class="screenshot-box">
        <img src="{{ public_path('docs/screenshots/31_ai_sending_prompt.png') }}" class="screenshot-img" alt="Sending Prompt">
        <div class="screenshot-caption">Figure 31: Submitting Telemetry Prompt to AI Copilot</div>
    </div>

    <div class="step-header">Step 2: Analyzing AI Responses & Code Blocks</div>
    <div class="screenshot-box">
        <img src="{{ public_path('docs/screenshots/32_ai_response.png') }}" class="screenshot-img" alt="AI Formatted Response">
        <div class="screenshot-caption">Figure 32: AI Assistant Formatted Response with Code Blocks</div>
    </div>

    <div class="step-header">Step 3: AI Analytics, System Health Diagnostics & Playground</div>
    <div class="screenshot-box">
        <img src="{{ public_path('docs/screenshots/33_ai_analytics_playground.png') }}" class="screenshot-img" alt="AI Analytics">
        <div class="screenshot-caption">Figure 33: AI Analytics & Prompt Playground Workspace</div>
    </div>

    <div class="callout callout-note">
        <strong>SAFETY SAFEGUARD:</strong> Potentially destructive AI commands (e.g., tenant deletion or bulk status modifications) require manual administrator confirmation before execution.
    </div>

    <!-- 12. SETTINGS -->
    <div class="page-break"></div>
    <h1>12. System Settings & Global Parameters</h1>
    <h2>12.1 Application Configuration & Maintenance Mode</h2>
    <p>
        Manage global application branding, SMTP mail gateway credentials, default localization parameters, and maintenance mode toggles.
    </p>

    <div class="screenshot-box">
        <img src="{{ public_path('docs/screenshots/34_system_settings.png') }}" class="screenshot-img" alt="System Settings Overview">
        <div class="screenshot-caption">Figure 34: System Settings Overview Page</div>
    </div>

    <div class="step-header">Step 1: Update Application Parameters & Save</div>
    <div class="screenshot-box">
        <img src="{{ public_path('docs/screenshots/35_system_settings_save.png') }}" class="screenshot-img" alt="Save Settings Confirmation">
        <div class="screenshot-caption">Figure 35: System Settings Update Confirmation & Notification</div>
    </div>

    <!-- 13. HELP & TUTORIALS -->
    <div class="page-break"></div>
    <h1>13. Help Center & Interactive Tutorials</h1>
    <h2>13.1 Tutorial Library & Offline User Manual Downloads</h2>
    <p>
        Access interactive operational guides, module walkthroughs, and download updated PDF User Manual releases.
    </p>

    <div class="screenshot-box">
        <img src="{{ public_path('docs/screenshots/36_help_tutorials.png') }}" class="screenshot-img" alt="Help Center">
        <div class="screenshot-caption">Figure 36: Help Center & Interactive Tutorials Directory</div>
    </div>

    <div class="step-header">Step 1: Navigating Interactive Guides</div>
    <div class="screenshot-box">
        <img src="{{ public_path('docs/screenshots/37_tutorial_details.png') }}" class="screenshot-img" alt="Tutorial Walkthrough">
        <div class="screenshot-caption">Figure 37: Interactive Tutorial Guide & Detailed Walkthrough</div>
    </div>

    <div class="step-header">Step 2: Download Downloadable User Manual PDF</div>
    <p>Click <strong>Download User Manual</strong> under Admin &rarr; Help & Tutorials to generate and download the master PDF manual.</p>
    <div class="screenshot-box">
        <img src="{{ public_path('docs/screenshots/38_download_manual_flow.png') }}" class="screenshot-img" alt="Download Manual Flow">
        <div class="screenshot-caption">Figure 38: Download Administrator User Manual PDF Action</div>
    </div>

    <!-- 14. ANNOUNCEMENTS -->
    <div class="page-break"></div>
    <h1>14. System Announcements & Broadcasts</h1>
    <h2>14.1 Platform Announcement Banners</h2>
    <p>
        Broadcast system maintenance notifications, platform release notes, and urgency banners to all tenant administrators and support agents.
    </p>

    <div class="screenshot-box">
        <img src="{{ public_path('docs/screenshots/39_announcement_list.png') }}" class="screenshot-img" alt="Announcements Directory">
        <div class="screenshot-caption">Figure 39: System Announcements Directory</div>
    </div>

    <div class="step-header">Step 1: Create & Schedule System Announcement</div>
    <p>Click <strong>Create Announcement</strong> to set up a global banner message.</p>
    <div class="screenshot-box">
        <img src="{{ public_path('docs/screenshots/40_create_announcement.png') }}" class="screenshot-img" alt="Create Announcement Form">
        <div class="screenshot-caption">Figure 40: Create System Announcement Broadcast Form</div>
    </div>

    <!-- 15. BUG REPORTS & FEEDBACK -->
    <div class="page-break"></div>
    <h1>15. Bug Reports & Platform Feedback Audit</h1>
    <h2>15.1 Bug Report Triage & Escalation Workflow</h2>
    <p>
        Audit escalations from tenant feedback, track system error tracebacks, and manage fix resolution states.
    </p>

    <div class="screenshot-box">
        <img src="{{ public_path('docs/screenshots/41_bug_report_list.png') }}" class="screenshot-img" alt="Bug Reports Directory">
        <div class="screenshot-caption">Figure 41: Bug Reports & System Diagnostics Directory</div>
    </div>

    <h2>15.2 Tenant CSAT Feedback & Rating Audits</h2>
    <div class="screenshot-box">
        <img src="{{ public_path('docs/screenshots/42_feedback_management.png') }}" class="screenshot-img" alt="Tenant Feedback">
        <div class="screenshot-caption">Figure 42: Tenant CSAT Feedback & Rating Audit Directory</div>
    </div>

</body>
</html>
