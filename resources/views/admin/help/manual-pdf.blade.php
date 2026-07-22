<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CliqueHA SaaS Ticketing System - Administrator User Manual</title>
    <style>
        @page {
            margin: 25mm 20mm 25mm 20mm;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 11pt;
            line-height: 1.6;
            color: #1e293b;
            background-color: #ffffff;
        }
        
        /* Cover Page Styling */
        .cover-page {
            page-break-after: always;
            text-align: center;
            padding-top: 80px;
        }
        .cover-logo {
            font-size: 32pt;
            font-weight: 900;
            color: #4f46e5;
            letter-spacing: 2px;
            margin-bottom: 10px;
        }
        .cover-subtitle {
            font-size: 14pt;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 3px;
            margin-bottom: 60px;
        }
        .cover-title {
            font-size: 26pt;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 20px;
            line-height: 1.3;
        }
        .cover-meta {
            margin-top: 120px;
            font-size: 11pt;
            color: #475569;
            border-top: 2px solid #e2e8f0;
            padding-top: 25px;
            display: inline-block;
            width: 80%;
        }
        .cover-meta table {
            width: 100%;
            margin: 0 auto;
        }
        .cover-meta td {
            padding: 4px 10px;
            text-align: left;
        }

        /* Page Headers and Break rules */
        .page-break {
            page-break-before: always;
        }
        
        h1 {
            font-size: 20pt;
            color: #0f172a;
            border-bottom: 2px solid #4f46e5;
            padding-bottom: 8px;
            margin-top: 30px;
            margin-bottom: 15px;
        }
        h2 {
            font-size: 15pt;
            color: #1e293b;
            margin-top: 25px;
            margin-bottom: 12px;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 4px;
        }
        h3 {
            font-size: 12pt;
            color: #334155;
            margin-top: 18px;
            margin-bottom: 8px;
        }
        p {
            margin-bottom: 12px;
            text-align: justify;
        }

        /* Callout Boxes */
        .callout {
            padding: 12px 16px;
            border-radius: 6px;
            margin: 15px 0;
            font-size: 10.5pt;
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
        .callout-best-practice {
            background-color: #faf5ff;
            border-left: 4px solid #9333ea;
            color: #7e22ce;
        }

        /* Table Styling */
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            font-size: 10pt;
        }
        table.data-table th, table.data-table td {
            border: 1px solid #cbd5e1;
            padding: 8px 12px;
            text-align: left;
        }
        table.data-table th {
            background-color: #f1f5f9;
            color: #0f172a;
            font-weight: bold;
        }
        table.data-table tr:nth-child(even) {
            background-color: #f8fafc;
        }

        /* TOC Styling */
        .toc-list {
            list-style: none;
            padding-left: 0;
        }
        .toc-list li {
            padding: 6px 0;
            border-bottom: 1px dashed #e2e8f0;
            font-size: 11pt;
        }
        .toc-number {
            font-weight: bold;
            color: #4f46e5;
            display: inline-block;
            width: 30px;
        }

        .faq-item {
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid #f1f5f9;
        }
        .faq-question {
            font-weight: bold;
            color: #0f172a;
            margin-bottom: 4px;
        }
        .faq-answer {
            color: #334155;
        }
    </style>
</head>
<body>

    <!-- COVER PAGE -->
    <div class="cover-page">
        <div class="cover-logo">CliqueHA</div>
        <div class="cover-subtitle">Enterprise Multi-Tenant SaaS Platform</div>
        
        <div class="cover-title">
            System Administrator<br>User Manual
        </div>
        
        <p style="text-align: center; color: #64748b; font-size: 12pt; margin-top: 20px;">
            Comprehensive Operational & Governance Guide for Platform Administrators
        </p>

        <div class="cover-meta">
            <table>
                <tr>
                    <td><strong>System Version:</strong> v2.4.0 (Enterprise)</td>
                    <td><strong>Document Status:</strong> Official Master Guide</td>
                </tr>
                <tr>
                    <td><strong>Generated Date:</strong> {{ date('F j, Y') }}</td>
                    <td><strong>Author:</strong> Operations & Security Team</td>
                </tr>
                <tr>
                    <td><strong>Target Portal:</strong> Global Admin Panel (/admin)</td>
                    <td><strong>Classification:</strong> Administrator Restricted</td>
                </tr>
            </table>
        </div>
    </div>

    <!-- TABLE OF CONTENTS -->
    <div class="page-break"></div>
    <h1>Table of Contents</h1>
    <ul class="toc-list">
        <li><span class="toc-number">1.</span> <strong>Introduction</strong> — Purpose of Admin Panel, System Overview, Responsibilities</li>
        <li><span class="toc-number">2.</span> <strong>Logging In & Security</strong> — Sign-In, Credentials, Password Reset, Logout</li>
        <li><span class="toc-number">3.</span> <strong>Dashboard Overview</strong> — Widgets, Telemetry, License & AI Health Telemetry</li>
        <li><span class="toc-number">4.</span> <strong>Licensing Module</strong> — Viewing, Key Generation, Renewals, Expiration</li>
        <li><span class="toc-number">5.</span> <strong>Distributor Management</strong> — Adding Resellers, Inventories, Assignments</li>
        <li><span class="toc-number">6.</span> <strong>Subscription Plans</strong> — Tiers, Seat Allocation, Ticket Caps, Features</li>
        <li><span class="toc-number">7.</span> <strong>Tenant Management</strong> — Provisioning, Updates, Activation, Suspension</li>
        <li><span class="toc-number">8.</span> <strong>Global User Management</strong> — Accounts, Roles, Passwords, Status Control</li>
        <li><span class="toc-number">9.</span> <strong>SLA Policies</strong> — First Response/Resolution Targets, Priority Escalations</li>
        <li><span class="toc-number">10.</span> <strong>Reports & Analytics</strong> — Business Telemetry, Export Formats, Data Filters</li>
        <li><span class="toc-number">11.</span> <strong>Audit Logs</strong> — Compliance Monitoring, Security Records, History</li>
        <li><span class="toc-number">12.</span> <strong>System Settings</strong> — Global Branding, SMTP Email, Maintenance Mode</li>
        <li><span class="toc-number">13.</span> <strong>Admin Users</strong> — Super-Admin Accounts, Role Privileges, Password Resets</li>
        <li><span class="toc-number">14.</span> <strong>System Announcements</strong> — Global Broadcasts, Scheduling, Severity Levels</li>
        <li><span class="toc-number">15.</span> <strong>User Feedback</strong> — Customer Ratings, Reviews, Satisfaction Telemetry</li>
        <li><span class="toc-number">16.</span> <strong>AI Bugs & Diagnostics</strong> — Automated Diagnostics, Trace Tracking, Fixes</li>
        <li><span class="toc-number">17.</span> <strong>Help & Built-in Tutorials</strong> — Interactive Guides, Knowledge Base</li>
        <li><span class="toc-number">18.</span> <strong>Notification Center</strong> — Platform Alerts, SLA Breach Warnings</li>
        <li><span class="toc-number">19.</span> <strong>AI Assistant & Copilot</strong> — Chatbot Navigation, Voice Input, Exports</li>
        <li><span class="toc-number">20.</span> <strong>Security Best Practices</strong> — Account Safeguards, Audit Practices</li>
        <li><span class="toc-number">21.</span> <strong>Troubleshooting Guide</strong> — Common Symptoms, Diagnostics, Resolution</li>
        <li><span class="toc-number">22.</span> <strong>Frequently Asked Questions</strong> — 20+ Detailed Administrator FAQs</li>
        <li><span class="toc-number">23.</span> <strong>Contact & Support</strong> — Support Channels, Maintenance Protocols</li>
    </ul>

    <!-- 1. INTRODUCTION -->
    <div class="page-break"></div>
    <h1>1. Introduction</h1>
    <h2>1.1 Purpose of the Admin Panel</h2>
    <p>
        The CliqueHA Admin Panel serves as the centralized management center for the multi-tenant SaaS ticketing system. While tenant portals and agent workspaces handle day-to-day ticket responses within isolated corporate boundaries, the Admin Panel grants global oversight and administrative control across all registered tenants, licenses, subscription plans, system SLAs, and AI copilot services.
    </p>

    <h2>1.2 System Overview</h2>
    <p>
        The architecture isolates tenant data at both logical and relational levels. Features include multi-distributor license key generation, custom SLA escalation policies, real-time telemetry analytics, automated PDF report rendering, and an integrated OpenAI-powered AI Copilot Assistant capable of voice transcript processing and instant system navigation.
    </p>

    <h2>1.3 Administrator Responsibilities</h2>
    <ul>
        <li><strong>Tenant Provisioning & Management:</strong> Overseeing tenant creation, plan assignments, seat allocation, and lifecycle states (Active, Suspended).</li>
        <li><strong>License & Distributor Governance:</strong> Issuing 25-character license keys to distributors and monitoring expiration windows.</li>
        <li><strong>Platform Security & Auditing:</strong> Monitoring global audit logs, administering admin credentials, and maintaining strict security standards.</li>
        <li><strong>System Health Maintenance:</strong> Managing global announcements, SMTP email dispatch gateways, AI engine status, and scheduled maintenance modes.</li>
    </ul>

    <!-- 2. LOGGING IN -->
    <h1>2. Logging In & Security</h1>
    <h2>2.1 Accessing the Sign-In Screen</h2>
    <p>
        To access the global administration portal, open a secure browser session and navigate to <code>/admin</code> (e.g. <code>https://your-domain.com/admin</code>).
    </p>

    <div class="callout callout-note">
        <strong>Default Administrator Credentials:</strong><br>
        • <strong>Email:</strong> <code>admin@example.com</code><br>
        • <strong>Password:</strong> <code>password</code>
    </div>

    <h2>2.2 Forgot Password & Recovery</h2>
    <p>
        If an administrator forgets their password:
        1. Click <strong>Forgot Password?</strong> on the login screen.
        2. Enter the administrator's registered email address.
        3. Follow the secure token link dispatched to your inbox to reset your credentials. Alternatively, run <code>php artisan ai:restore-admin</code> via CLI.
    </p>

    <h2>2.3 Secure Logout</h2>
    <p>
        Always terminate active sessions when leaving work stations unattended by clicking your admin profile avatar at the bottom-left sidebar or top navigation header and selecting <strong>Logout</strong>.
    </p>

    <!-- 3. DASHBOARD -->
    <div class="page-break"></div>
    <h1>3. Dashboard Overview & Telemetry</h1>
    <p>
        The Admin Dashboard provides real-time telemetry metrics updated automatically across the SaaS environment.
    </p>

    <table class="data-table">
        <thead>
            <tr>
                <th>Dashboard Widget</th>
                <th>Metric Telemetry</th>
                <th>Administrative Relevance</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>Total Licenses</strong></td>
                <td>Count of all issued keys</td>
                <td>Monitors key generation volume & reseller distribution.</td>
            </tr>
            <tr>
                <td><strong>Active Tenants</strong></td>
                <td>Un-suspended active accounts</td>
                <td>Indicates current customer organization volume.</td>
            </tr>
            <tr>
                <td><strong>Distributors</strong></td>
                <td>Registered partner resellers</td>
                <td>Tracks channel partner network growth.</td>
            </tr>
            <tr>
                <td><strong>Plans Overview</strong></td>
                <td>Starter, Business, Enterprise</td>
                <td>Monitors active subscription tiers.</td>
            </tr>
            <tr>
                <td><strong>Tickets Today</strong></td>
                <td>24-hour volume count</td>
                <td>Measures real-time support load across all tenants.</td>
            </tr>
            <tr>
                <td><strong>AI Health Status</strong></td>
                <td>OpenAI API & Circuit Breaker</td>
                <td>Verifies operational status of AI assistance modules.</td>
            </tr>
        </tbody>
    </table>

    <div class="callout callout-best-practice">
        <strong>Best Practice:</strong> Check the <em>License Expiration Alerts</em> card on the dashboard daily to identify tenants reaching key expiration within 30 days.
    </div>

    <!-- 4. LICENSING -->
    <h1>4. Licensing Module</h1>
    <h2>4.1 License Lifecycle & Keys</h2>
    <p>
        Licenses govern tenant seat allocations, plan tiers, and validity windows. License keys follow the 25-character formatted standard: <code>CLIQ-XXXX-XXXX-XXXX-XXXX</code>.
    </p>

    <h2>4.2 Issuing a License Key</h2>
    <ol>
        <li>Navigate to <strong>Business ➔ Licenses</strong>.</li>
        <li>Click <strong>Create New License</strong>.</li>
        <li>Select the target <strong>Distributor</strong> and <strong>Subscription Plan</strong>.</li>
        <li>Set the <strong>Max Seats</strong> (maximum user accounts allowed for the tenant).</li>
        <li>Specify duration in days (e.g. <code>365</code> for 1 year).</li>
        <li>Click <strong>Generate License</strong>.</li>
    </ol>

    <h2>4.3 Expiration & Revocation</h2>
    <p>
        When a license expires, the tenant automatically shifts into read-only access mode until renewed. Clicking <strong>Revoke</strong> immediately disables the key and suspends tenant portal access.
    </p>

    <!-- 5. DISTRIBUTOR MANAGEMENT -->
    <h1>5. Distributor Management</h1>
    <h2>5.1 Adding & Editing Distributors</h2>
    <p>
        Distributors act as partner entities managing license key allocations. To add a distributor:
        1. Navigate to <strong>Business ➔ Distributors</strong>.
        2. Click <strong>Add Distributor</strong>.
        3. Enter Organization Name, Contact Email, Phone Number, and Status.
        4. Click <strong>Save Distributor</strong>.
    </p>

    <!-- 6. PLANS -->
    <div class="page-break"></div>
    <h1>6. Subscription Plans</h1>
    <table class="data-table">
        <thead>
            <tr>
                <th>Plan Name</th>
                <th>Seat Capacity</th>
                <th>Monthly Ticket Cap</th>
                <th>Included Features</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>Starter</strong></td>
                <td>5 Users</td>
                <td>100 Tickets/Mo</td>
                <td>Core Ticketing, Knowledge Base, Email Alerts</td>
            </tr>
            <tr>
                <td><strong>Business</strong></td>
                <td>15 Users</td>
                <td>500 Tickets/Mo</td>
                <td>SLA Rules, AI Copilot, Custom Departments</td>
            </tr>
            <tr>
                <td><strong>Enterprise</strong></td>
                <td>Unlimited</td>
                <td>Unlimited</td>
                <td>Custom Branding, Dedicated AI Models, Priority SLA</td>
            </tr>
        </tbody>
    </table>

    <!-- 7. TENANT MANAGEMENT -->
    <h1>7. Tenant Management</h1>
    <h2>7.1 Provisioning Tenants</h2>
    <p>
        To provision a new corporate client:
        1. Navigate to <strong>Workspace ➔ Tenants</strong>.
        2. Click <strong>Add Tenant</strong>.
        3. Enter Organization Name, Subdomain/Slug, and initial Tenant Owner email address.
        4. Select Subscription Plan and click <strong>Create Tenant</strong>.
    </p>

    <h2>7.2 Account Suspension & Impersonation</h2>
    <ul>
        <li><strong>Suspension:</strong> Click <strong>Suspend Tenant</strong> to block tenant access due to non-payment or policy breach.</li>
        <li><strong>Impersonation:</strong> Click <strong>Impersonate Tenant</strong> to log into the tenant dashboard as an admin for technical assistance. Click <em>Stop Impersonation</em> to return.</li>
    </ul>

    <!-- 8. USER MANAGEMENT -->
    <h1>8. Global User Management</h1>
    <p>
        The User Management screen (<strong>Workspace ➔ Users</strong>) lists every user account registered across all corporate tenants. Administrators can reset passwords, reassign roles (`Admin`, `Agent`, `Client`), or deactivate accounts.
    </p>

    <!-- 9. SLA POLICIES -->
    <h1>9. SLA Policies & Escalations</h1>
    <p>
        Service Level Agreements enforce target response and resolution deadlines based on ticket priority levels.
    </p>

    <table class="data-table">
        <thead>
            <tr>
                <th>Priority</th>
                <th>First Response Target</th>
                <th>Resolution Target</th>
                <th>Escalation Rule</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>Low</strong></td>
                <td>24 Hours</td>
                <td>72 Hours</td>
                <td>Agent Reminder Notification</td>
            </tr>
            <tr>
                <td><strong>Medium</strong></td>
                <td>8 Hours</td>
                <td>24 Hours</td>
                <td>Notify Team Supervisor</td>
            </tr>
            <tr>
                <td><strong>High</strong></td>
                <td>2 Hours</td>
                <td>8 Hours</td>
                <td>Escalate to Department Manager</td>
            </tr>
            <tr>
                <td><strong>Critical</strong></td>
                <td>30 Minutes</td>
                <td>2 Hours</td>
                <td>Immediate SMS & Global Admin Alert</td>
            </tr>
        </tbody>
    </table>

    <!-- 10. REPORTS -->
    <div class="page-break"></div>
    <h1>10. Reports & Analytics</h1>
    <p>
        Access aggregated performance metrics under <strong>Analytics ➔ Reports</strong>. Filter by date ranges or tenant categories, then export data in **CSV** or printable **PDF** formats.
    </p>

    <!-- 11. AUDIT LOGS -->
    <h1>11. Audit Logs & Compliance</h1>
    <p>
        Every critical administrative action, login event, and permission modification is immutably logged in <strong>Analytics ➔ Audit Logs</strong> with exact timestamps, user IDs, and IP addresses.
    </p>

    <!-- 12. SYSTEM SETTINGS -->
    <h1>12. System Settings & Branding</h1>
    <ul>
        <li><strong>General Branding:</strong> Upload global logos and customize platform header titles.</li>
        <li><strong>SMTP Configuration:</strong> Configure outbound mail servers (Host, Port, SSL/TLS, Encryption keys).</li>
        <li><strong>Maintenance Mode:</strong> Enable to display a maintenance banner and temporarily restrict public access.</li>
    </ul>

    <!-- 13. ADMIN USERS -->
    <h1>13. Admin Users</h1>
    <p>
        Manage global administrative accounts under <strong>Administration ➔ Administrators</strong>. Assign super-admin permissions, reset admin passwords, and enforce security policies.
    </p>

    <!-- 14. ANNOUNCEMENTS -->
    <h1>14. System Announcements</h1>
    <p>
        Create system-wide notices under <strong>Operations ➔ Announcements</strong>. Set announcement severity to <code>Info</code>, <code>Warning</code>, or <code>Danger</code> to broadcast banners across all tenant dashboards.
    </p>

    <!-- 15. FEEDBACK -->
    <h1>15. User Feedback</h1>
    <p>
        Review customer satisfaction scores and feedback submissions from tenant administrators under <strong>Help ➔ User Feedback</strong>.
    </p>

    <!-- 16. AI BUGS -->
    <h1>16. AI Bugs & Diagnostics</h1>
    <p>
        Inspect automated error trace reports and diagnostic summaries generated by the AI diagnostic pipeline under <strong>AI ➔ AI Diagnostics</strong>.
    </p>

    <!-- 17. HELP & TUTORIALS -->
    <h1>17. Help & Tutorials</h1>
    <p>
        Access embedded interactive guides, feature walkthroughs, and knowledge base documentation under <strong>Help ➔ Help Center</strong>.
    </p>

    <!-- 18. NOTIFICATIONS -->
    <h1>18. Notification Center</h1>
    <p>
        Click the top header bell icon to view unread SLA breach alerts, license expiration warnings, and critical system events.
    </p>

    <!-- 19. AI ASSISTANT -->
    <div class="page-break"></div>
    <h1>19. AI Assistant & Copilot</h1>
    <p>
        Launch the AI Assistant from the bottom-right floating widget or via <strong>AI ➔ Chat</strong>.
    </p>
    <ul>
        <li><strong>Voice Input (Web Speech API):</strong> Click the microphone icon to speak your administrative query.</li>
        <li><strong>Export Conversation:</strong> Export chat transcripts as **JSON**, **CSV**, or **HTML**.</li>
    </ul>

    <!-- 20. SECURITY BEST PRACTICES -->
    <h1>20. Security Best Practices</h1>
    <div class="callout callout-warning">
        <strong>Security Mandate:</strong>
        1. Always change default administrator passwords immediately upon deployment.<br>
        2. Require all administrators to use passwords exceeding 12 characters with mixed symbols.<br>
        3. Review active super-admins under <em>Administration ➔ Administrators</em> monthly.
    </div>

    <!-- 21. TROUBLESHOOTING -->
    <h1>21. Troubleshooting Guide</h1>
    <table class="data-table">
        <thead>
            <tr>
                <th>Symptom</th>
                <th>Probable Root Cause</th>
                <th>Recommended Action</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>Login Failure</strong></td>
                <td>Incorrect password or missing user.</td>
                <td>Run <code>php artisan ai:restore-admin</code> via CLI to restore defaults.</td>
            </tr>
            <tr>
                <td><strong>Tenant User Exceeded</strong></td>
                <td>Max seats quota reached.</td>
                <td>Edit license seat allocation in <strong>Business ➔ Licenses</strong>.</td>
            </tr>
            <tr>
                <td><strong>Emails Not Sending</strong></td>
                <td>SMTP settings misconfigured.</td>
                <td>Verify mail server host/port in <strong>Administration ➔ Settings</strong>.</td>
            </tr>
            <tr>
                <td><strong>AI Copilot Offline</strong></td>
                <td>API key unconfigured.</td>
                <td>Set key in <strong>AI ➔ AI Settings</strong>.</td>
            </tr>
        </tbody>
    </table>

    <!-- 22. FAQS -->
    <div class="page-break"></div>
    <h1>22. Frequently Asked Questions (20+ Administrator FAQs)</h1>

    <div class="faq-item">
        <div class="faq-question">1. How do I log into the Admin Panel for the first time?</div>
        <div class="faq-answer">Navigate to <code>/admin</code> and enter <code>admin@example.com</code> with password <code>password</code>.</div>
    </div>

    <div class="faq-item">
        <div class="faq-question">2. How do I add a new corporate tenant?</div>
        <div class="faq-answer">Go to <strong>Workspace ➔ Tenants</strong> and click <strong>Add Tenant</strong>.</div>
    </div>

    <div class="faq-item">
        <div class="faq-question">3. What happens when a license expires?</div>
        <div class="faq-answer">The tenant shifts into read-only mode until a renewed key is applied.</div>
    </div>

    <div class="faq-item">
        <div class="faq-question">4. Can I log into a tenant workspace for troubleshooting?</div>
        <div class="faq-answer">Yes, click <strong>Impersonate Tenant</strong> on the tenant detail page.</div>
    </div>

    <div class="faq-item">
        <div class="faq-question">5. How do I export audit logs for compliance?</div>
        <div class="faq-answer">Go to <strong>Analytics ➔ Audit Logs</strong> and click <strong>Export CSV</strong>.</div>
    </div>

    <div class="faq-item">
        <div class="faq-question">6. How do I update platform branding and logos?</div>
        <div class="faq-answer">Navigate to <strong>Administration ➔ Settings</strong> and upload new branding assets.</div>
    </div>

    <div class="faq-item">
        <div class="faq-question">7. How do I issue a license key valid for 1 year?</div>
        <div class="faq-answer">In <strong>Business ➔ Licenses</strong>, set Duration to <code>365</code> days.</div>
    </div>

    <div class="faq-item">
        <div class="faq-question">8. Where are SLA response targets configured?</div>
        <div class="faq-answer">Go to <strong>Operations ➔ SLA Policies</strong>.</div>
    </div>

    <div class="faq-item">
        <div class="faq-question">9. How do I send a maintenance broadcast to all users?</div>
        <div class="faq-answer">Create a new announcement under <strong>Operations ➔ Announcements</strong>.</div>
    </div>

    <div class="faq-item">
        <div class="faq-question">10. Can I cap tenant monthly ticket creation?</div>
        <div class="faq-answer">Yes, configure ticket quotas under <strong>Business ➔ Plans</strong>.</div>
    </div>

    <div class="faq-item">
        <div class="faq-question">11. How do I create an additional Administrator?</div>
        <div class="faq-answer">Go to <strong>Administration ➔ Administrators</strong> and click <strong>Add Administrator</strong>.</div>
    </div>

    <div class="faq-item">
        <div class="faq-question">12. What is a Distributor account?</div>
        <div class="faq-answer">A channel partner authorized to allocate and manage license keys.</div>
    </div>

    <div class="faq-item">
        <div class="faq-question">13. How does the AI Copilot help administrators?</div>
        <div class="faq-answer">It provides system navigation, telemetry summaries, and automated resolution tips.</div>
    </div>

    <div class="faq-item">
        <div class="faq-question">14. How do I export AI conversation history?</div>
        <div class="faq-answer">Click <strong>Export</strong> in the AI chat workspace to download JSON, CSV, or HTML format.</div>
    </div>

    <div class="faq-item">
        <div class="faq-question">15. Does voice input require external software?</div>
        <div class="faq-answer">No, it uses your browser's native Web Speech API.</div>
    </div>

    <div class="faq-item">
        <div class="faq-question">16. How do I enable global Maintenance Mode?</div>
        <div class="faq-answer">Toggle Maintenance Mode under <strong>Administration ➔ Settings</strong>.</div>
    </div>

    <div class="faq-item">
        <div class="faq-question">17. Where do I check AI engine health?</div>
        <div class="faq-answer">Navigate to <strong>AI ➔ AI Usage</strong> or <strong>AI Diagnostics</strong>.</div>
    </div>

    <div class="faq-item">
        <div class="faq-question">18. Can I suspend a non-paying tenant?</div>
        <div class="faq-answer">Yes, click <strong>Suspend Tenant</strong> under <strong>Workspace ➔ Tenants</strong>.</div>
    </div>

    <div class="faq-item">
        <div class="faq-question">19. How do I open global search?</div>
        <div class="faq-answer">Press <code>Ctrl + K</code> (or <code>Cmd + K</code> on Mac).</div>
    </div>

    <div class="faq-item">
        <div class="faq-question">20. What command restores admin access if locked out?</div>
        <div class="faq-answer">Execute <code>php artisan ai:restore-admin</code> in terminal.</div>
    </div>

    <div class="faq-item">
        <div class="faq-question">21. Is tenant data isolated across subscriptions?</div>
        <div class="faq-answer">Yes, logical and database queries are strictly scoped per tenant ID.</div>
    </div>

    <!-- 23. CONTACT & SUPPORT -->
    <h1>23. Contact & Support</h1>
    <p>
        For enterprise escalation, system maintenance support, or licensing assistance:
    </p>
    <ul>
        <li><strong>Support Portal:</strong> <code>https://cliqueha.com/support</code></li>
        <li><strong>Email Escalations:</strong> <code>support@cliqueha.com</code></li>
        <li><strong>Documentation Updates:</strong> Updated quarterly with every major release.</li>
    </ul>

</body>
</html>
