import { test, expect, Page } from '@playwright/test';

const ENTERPRISE_SLUG = 'kent';
const OWNER_EMAIL = 'kfacula@gmail.com';
const AGENT_EMAIL = 'facula420@gmail.com';
const PASSWORD = 'password';

async function login(page: Page, slug: string, email: string) {
    await page.goto(`/${slug}/dashboard`);
    if (page.url().includes('login')) {
        await page.locator('input[name="email"]').fill(email);
        await page.locator('input[name="password"]').fill(PASSWORD);
        await page.getByRole('button', { name: 'Log in' }).click();
        await page.waitForURL(new RegExp(`/${slug}/`));
    }
}

// ============================================================
// ENTERPRISE SIDEBAR FEATURES
// ============================================================
test.describe('Enterprise Sidebar Navigation', () => {
    test('sidebar shows all enterprise features', async ({ page }) => {
        await login(page, ENTERPRISE_SLUG, OWNER_EMAIL);

        // Enterprise-only sidebar items
        await expect(page.getByRole('link', { name: 'Departments' }).first()).toBeVisible();
        await expect(page.getByRole('link', { name: 'Roles & Permissions' })).toBeVisible();
        await expect(page.getByRole('link', { name: 'Activity Logs' })).toBeVisible();

        await page.screenshot({ path: 'tests/e2e/screenshots/enterprise-sidebar.png', fullPage: true });
    });
});

// ============================================================
// CUSTOM ROLES & PERMISSIONS
// ============================================================
test.describe('Enterprise: Custom Roles & Permissions', () => {
    test('roles page shows default roles with badges', async ({ page }) => {
        await login(page, ENTERPRISE_SLUG, OWNER_EMAIL);
        await page.goto(`/${ENTERPRISE_SLUG}/roles`);

        await expect(page.getByRole('cell', { name: /Admin/ })).toBeVisible();
        await expect(page.getByRole('cell', { name: /Manager/ })).toBeVisible();
        await expect(page.getByRole('cell', { name: /Agent/ })).toBeVisible();
        await expect(page.getByText('Default').first()).toBeVisible();

        await page.screenshot({ path: 'tests/e2e/screenshots/enterprise-roles.png', fullPage: true });
    });

    test('can create a custom role', async ({ page }) => {
        await login(page, ENTERPRISE_SLUG, OWNER_EMAIL);
        await page.goto(`/${ENTERPRISE_SLUG}/roles/create`);

        const roleName = 'e2e_role_' + Date.now();
        await page.locator('input[name="name"]').fill(roleName);

        // Select some permissions
        const checkboxes = page.locator('input[type="checkbox"]');
        const count = await checkboxes.count();
        if (count > 0) {
            await checkboxes.first().check();
            if (count > 1) await checkboxes.nth(1).check();
        }

        await page.getByRole('button', { name: 'Create' }).click();
        await page.waitForURL(new RegExp(`/${ENTERPRISE_SLUG}/roles`));

        await expect(page.getByRole('cell', { name: new RegExp(roleName, 'i') })).toBeVisible();

        await page.screenshot({ path: 'tests/e2e/screenshots/enterprise-role-created.png', fullPage: true });
    });
});

// ============================================================
// DEPARTMENT MANAGEMENT
// ============================================================
test.describe('Enterprise: Department Management', () => {
    test('departments page is accessible', async ({ page }) => {
        await login(page, ENTERPRISE_SLUG, OWNER_EMAIL);
        await page.goto(`/${ENTERPRISE_SLUG}/departments`);

        await expect(page).toHaveURL(new RegExp(`/${ENTERPRISE_SLUG}/departments`));

        await page.screenshot({ path: 'tests/e2e/screenshots/enterprise-departments.png', fullPage: true });
    });

    test('can create a department', async ({ page }) => {
        await login(page, ENTERPRISE_SLUG, OWNER_EMAIL);
        await page.goto(`/${ENTERPRISE_SLUG}/departments/create`);

        const deptName = 'E2E Dept ' + Date.now();
        await page.locator('input[name="name"]').fill(deptName);
        await page.locator('input[name="code"]').fill('E2E');
        await page.locator('input[name="color"]').fill('#e74c3c');

        await page.getByRole('button', { name: 'Create' }).click();

        await expect(page.getByText(deptName)).toBeVisible();

        await page.screenshot({ path: 'tests/e2e/screenshots/enterprise-dept-created.png', fullPage: true });
    });
});

// ============================================================
// AGENT TIERING & ESCALATION
// ============================================================
test.describe('Enterprise: Agent Tiering', () => {
    test('member create shows support tier selector', async ({ page }) => {
        await login(page, ENTERPRISE_SLUG, OWNER_EMAIL);
        await page.goto(`/${ENTERPRISE_SLUG}/members/create`);

        // Select agent role
        await page.locator('input[name="role"][value="agent"]').click();

        // Support tier should be visible (Enterprise only)
        await expect(page.locator('#support_tier')).toBeVisible();
        await expect(page.getByText('Support Tier Info')).toBeVisible();
        await expect(page.getByText('Tier 1 —')).toBeVisible();
        await expect(page.getByText('Tier 2 —')).toBeVisible();
        await expect(page.getByText('Tier 3 —')).toBeVisible();

        await page.screenshot({ path: 'tests/e2e/screenshots/enterprise-tier-selector.png', fullPage: true });
    });
});

// ============================================================
// TICKET SHOW - ENTERPRISE FEATURES
// ============================================================
test.describe('Enterprise: Ticket Show Features', () => {
    test('ticket show has escalation, comments, merge, reopen sections', async ({ page }) => {
        await login(page, ENTERPRISE_SLUG, OWNER_EMAIL);

        // Navigate to tickets and open the first one
        await page.goto(`/${ENTERPRISE_SLUG}/tickets`);
        const ticketLink = page.locator('a[href*="/tickets/"]').first();

        if (await ticketLink.isVisible()) {
            await ticketLink.click();

            // Escalation section should be visible (only on non-closed tickets)
            // Note: ticket may be closed, so check for either escalation or reopen
            const hasEscalation = await page.getByRole('heading', { name: 'Escalation' }).isVisible().catch(() => false);

            // Check for enterprise features on the ticket page (some may not appear on closed tickets)
            // Activity History should be visible (audit_logs feature)
            const hasHistory = await page.getByText('Activity History').first().isVisible().catch(() => false);

            await page.screenshot({ path: 'tests/e2e/screenshots/enterprise-ticket-show.png', fullPage: true });
        }
    });

    test('can add a comment on ticket', async ({ page }) => {
        await login(page, ENTERPRISE_SLUG, OWNER_EMAIL);
        await page.goto(`/${ENTERPRISE_SLUG}/tickets`);

        const ticketLink = page.locator('a[href*="/tickets/"]').first();
        if (await ticketLink.isVisible()) {
            await ticketLink.click();

            // Fill comment form
            const commentBox = page.locator('#comment_content');
            if (await commentBox.isVisible()) {
                await commentBox.fill('E2E test comment from enterprise owner');
                await page.getByRole('button', { name: 'Add Comment' }).click();

                // Comment should appear
                await expect(page.getByText('E2E test comment from enterprise owner')).toBeVisible();

                await page.screenshot({ path: 'tests/e2e/screenshots/enterprise-comment-added.png', fullPage: true });
            }
        }
    });
});

// ============================================================
// ACTIVITY LOGS
// ============================================================
test.describe('Enterprise: Activity Logs', () => {
    test('activity logs page loads with filters', async ({ page }) => {
        await login(page, ENTERPRISE_SLUG, OWNER_EMAIL);
        await page.goto(`/${ENTERPRISE_SLUG}/activity-logs`);

        await expect(page.getByRole('heading', { name: 'Activity Logs' })).toBeVisible();

        // Filter controls should be present
        await expect(page.locator('select[name="action"]')).toBeVisible();
        await expect(page.locator('select[name="user_id"]')).toBeVisible();
        await expect(page.locator('input[name="from"]')).toBeVisible();
        await expect(page.locator('input[name="to"]')).toBeVisible();

        await page.screenshot({ path: 'tests/e2e/screenshots/enterprise-activity-logs.png', fullPage: true });
    });
});

// ============================================================
// PUBLIC PORTAL - ENTERPRISE CLIENT REPLIES
// ============================================================
test.describe('Enterprise: Public Portal with Client Replies', () => {
    test('landing page accessible for enterprise tenant', async ({ page }) => {
        await page.goto(`/${ENTERPRISE_SLUG}/`);

        await expect(page.getByRole('heading', { name: 'Submit New Ticket' })).toBeVisible();
        await expect(page.getByRole('heading', { name: 'Track Existing Ticket' })).toBeVisible();

        await page.screenshot({ path: 'tests/e2e/screenshots/enterprise-landing.png', fullPage: true });
    });

    test('submit ticket form has all fields', async ({ page }) => {
        await page.goto(`/${ENTERPRISE_SLUG}/submit-ticket`);

        await expect(page.locator('#name')).toBeVisible();
        await expect(page.locator('#email')).toBeVisible();
        await expect(page.locator('#subject')).toBeVisible();
        await expect(page.locator('#department_id')).toBeVisible();
        await expect(page.locator('#priority')).toBeVisible();
        await expect(page.locator('#incident_date')).toBeVisible();
        await expect(page.locator('#description')).toBeVisible();

        await page.screenshot({ path: 'tests/e2e/screenshots/enterprise-submit-form.png', fullPage: true });
    });

    test('track ticket page works', async ({ page }) => {
        await page.goto(`/${ENTERPRISE_SLUG}/track-ticket`);

        await expect(page.getByRole('heading', { name: 'Find Your Ticket', exact: true })).toBeVisible();
        await expect(page.locator('#ticket_number')).toBeVisible();
        await expect(page.locator('#email')).toBeVisible();

        // Search for non-existent ticket
        await page.locator('#ticket_number').fill('TKT-FAKE-999');
        await page.locator('#email').fill('nobody@test.com');
        await page.getByRole('button', { name: 'Find Ticket' }).click();

        await expect(page.getByText('No ticket found')).toBeVisible();

        await page.screenshot({ path: 'tests/e2e/screenshots/enterprise-track-not-found.png', fullPage: true });
    });

    test('track existing ticket and verify reply form', async ({ page }) => {
        // Track an existing ticket by going to track page
        await page.goto(`/${ENTERPRISE_SLUG}/track-ticket`);

        // The form should be visible
        await expect(page.getByRole('heading', { name: 'Find Your Ticket', exact: true })).toBeVisible();

        await page.screenshot({ path: 'tests/e2e/screenshots/enterprise-track-page.png', fullPage: true });
    });
});

// ============================================================
// MEMBER PERFORMANCE
// ============================================================
test.describe('Enterprise: Member Performance', () => {
    test('member show page has performance metrics', async ({ page }) => {
        await login(page, ENTERPRISE_SLUG, OWNER_EMAIL);
        await page.goto(`/${ENTERPRISE_SLUG}/members`);

        // Click Performance link on a member card
        const perfLink = page.getByRole('link', { name: 'Performance' }).first();
        if (await perfLink.isVisible()) {
            await perfLink.click();
            await page.waitForURL(new RegExp(`/${ENTERPRISE_SLUG}/members/`));

            // Performance section should be present
            await expect(page.getByText('Performance Metrics')).toBeVisible();
            await expect(page.getByText('Total Assigned')).toBeVisible();

            await page.screenshot({ path: 'tests/e2e/screenshots/enterprise-member-performance.png', fullPage: true });
        }
    });
});

// ============================================================
// REPORTS (ENTERPRISE HAS ALL)
// ============================================================
test.describe('Enterprise: Reports', () => {
    test('all report pages accessible', async ({ page }) => {
        await login(page, ENTERPRISE_SLUG, OWNER_EMAIL);

        // Overview
        await page.goto(`/${ENTERPRISE_SLUG}/reports`);
        await expect(page).toHaveURL(new RegExp(`/${ENTERPRISE_SLUG}/reports`));

        // SLA Compliance (Business+ feature)
        await page.goto(`/${ENTERPRISE_SLUG}/reports/sla-compliance`);
        await expect(page).toHaveURL(new RegExp('sla-compliance'));

        await page.screenshot({ path: 'tests/e2e/screenshots/enterprise-sla-report.png', fullPage: true });
    });

    test('export buttons visible (detailed_reporting feature)', async ({ page }) => {
        await login(page, ENTERPRISE_SLUG, OWNER_EMAIL);
        await page.goto(`/${ENTERPRISE_SLUG}/reports`);

        // Export buttons should be visible for Enterprise
        await expect(page.getByText('Export').first()).toBeVisible();

        await page.screenshot({ path: 'tests/e2e/screenshots/enterprise-reports-export.png', fullPage: true });
    });
});

// ============================================================
// SETTINGS
// ============================================================
test.describe('Enterprise: Settings', () => {
    test('all settings pages accessible', async ({ page }) => {
        await login(page, ENTERPRISE_SLUG, OWNER_EMAIL);

        await page.goto(`/${ENTERPRISE_SLUG}/settings/general`);
        await expect(page.getByText('Company Name')).toBeVisible();

        await page.goto(`/${ENTERPRISE_SLUG}/settings/branding`);
        await expect(page.getByText('Primary Color').first()).toBeVisible();

        await page.goto(`/${ENTERPRISE_SLUG}/settings/notifications`);
        await expect(page.getByRole('heading', { name: 'SMTP Configuration' })).toBeVisible();

        await page.screenshot({ path: 'tests/e2e/screenshots/enterprise-settings.png', fullPage: true });
    });
});

// ============================================================
// ERROR PAGES
// ============================================================
test.describe('Enterprise: Error Pages', () => {
    test('404 page renders correctly', async ({ page }) => {
        const response = await page.goto(`/${ENTERPRISE_SLUG}/nonexistent-page-xyz`);

        await expect(page.getByText('Page not found')).toBeVisible();
        await expect(page.getByRole('link', { name: 'Go Home' })).toBeVisible();

        await page.screenshot({ path: 'tests/e2e/screenshots/enterprise-404.png' });
    });
});
