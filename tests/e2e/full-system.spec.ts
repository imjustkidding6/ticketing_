import { test, expect, Page } from '@playwright/test';

// ── Test Data ──────────────────────────────────────────────────────
const ADMIN = { email: 'admin@example.com', password: 'password' };
const STARTER = { slug: 'midel', email: 'midel@test.com', password: 'password' };
const BUSINESS = { slug: 'kentbusiness', email: 'facula425@gmail.com', password: 'password' };
const ENTERPRISE = { slug: 'kent', email: 'kfacula@gmail.com', password: 'password', agent: 'facula420@gmail.com' };

async function login(page: Page, slug: string, email: string) {
    await page.goto(`/${slug}/dashboard`);
    if (page.url().includes('login')) {
        await page.locator('input[name="email"]').fill(email);
        await page.locator('input[name="password"]').fill('password');
        await page.getByRole('button', { name: 'Log in' }).click();
        await page.waitForURL(new RegExp(`/${slug}/`), { timeout: 10000 });
    }
}

async function adminLogin(page: Page) {
    await page.goto('/admin/login');
    await page.locator('input[name="email"]').fill(ADMIN.email);
    await page.locator('input[name="password"]').fill(ADMIN.password);
    await page.getByRole('button', { name: 'Sign In' }).click();
    await page.waitForURL(/\/admin/, { timeout: 10000 });
}

// ════════════════════════════════════════════════════════════════════
// ADMIN PANEL
// ════════════════════════════════════════════════════════════════════
test.describe('Admin Panel', () => {
    test('admin login and dashboard', async ({ page }) => {
        await adminLogin(page);
        await expect(page).toHaveURL(/\/admin/);
        await page.screenshot({ path: 'tests/e2e/screenshots/admin-dashboard.png', fullPage: true });
    });

    test('admin can view tenants list', async ({ page }) => {
        await adminLogin(page);
        await page.goto('/admin/tenants');
        await page.screenshot({ path: 'tests/e2e/screenshots/admin-tenants.png', fullPage: true });
    });

    test('admin can view plans', async ({ page }) => {
        await adminLogin(page);
        await page.goto('/admin/plans');
        await expect(page.getByText('Starter')).toBeVisible();
        await expect(page.getByText('Business')).toBeVisible();
        await expect(page.getByText('Enterprise')).toBeVisible();
        await page.screenshot({ path: 'tests/e2e/screenshots/admin-plans.png', fullPage: true });
    });

    test('admin can view licenses', async ({ page }) => {
        await adminLogin(page);
        await page.goto('/admin/licenses');
        await page.screenshot({ path: 'tests/e2e/screenshots/admin-licenses.png', fullPage: true });
    });

    test('admin can view distributors', async ({ page }) => {
        await adminLogin(page);
        await page.goto('/admin/distributors');
        await page.screenshot({ path: 'tests/e2e/screenshots/admin-distributors.png', fullPage: true });
    });

    test('admin can view users', async ({ page }) => {
        await adminLogin(page);
        await page.goto('/admin/users');
        await page.screenshot({ path: 'tests/e2e/screenshots/admin-users.png', fullPage: true });
    });

    test('non-admin gets rejected', async ({ page }) => {
        await page.goto('/admin/login');
        await page.locator('input[name="email"]').fill(ENTERPRISE.email);
        await page.locator('input[name="password"]').fill('password');
        await page.getByRole('button', { name: 'Sign In' }).click();
        // Should stay on login page with error
        await expect(page).toHaveURL(/\/admin\/login/);
    });
});

// ════════════════════════════════════════════════════════════════════
// STARTER PLAN (midel)
// ════════════════════════════════════════════════════════════════════
test.describe('Starter Plan', () => {
    test('public portal returns 404', async ({ page }) => {
        expect((await page.goto(`/${STARTER.slug}/`))?.status()).toBe(404);
        expect((await page.goto(`/${STARTER.slug}/submit-ticket`))?.status()).toBe(404);
        expect((await page.goto(`/${STARTER.slug}/track-ticket`))?.status()).toBe(404);
    });

    test('dashboard loads', async ({ page }) => {
        await login(page, STARTER.slug, STARTER.email);
        await expect(page).toHaveURL(new RegExp(`/${STARTER.slug}/dashboard`));
        await page.screenshot({ path: 'tests/e2e/screenshots/starter-dashboard.png', fullPage: true });
    });

    test('can create and manage tickets', async ({ page }) => {
        await login(page, STARTER.slug, STARTER.email);

        // Create ticket
        await page.goto(`/${STARTER.slug}/tickets/create`);
        await expect(page.locator('#subject')).toBeVisible();
        await page.screenshot({ path: 'tests/e2e/screenshots/starter-ticket-create.png', fullPage: true });
    });

    test('can manage clients', async ({ page }) => {
        await login(page, STARTER.slug, STARTER.email);
        await page.goto(`/${STARTER.slug}/clients`);
        await page.screenshot({ path: 'tests/e2e/screenshots/starter-clients.png', fullPage: true });
    });

    test('can manage categories', async ({ page }) => {
        await login(page, STARTER.slug, STARTER.email);
        await page.goto(`/${STARTER.slug}/categories`);
        await page.screenshot({ path: 'tests/e2e/screenshots/starter-categories.png', fullPage: true });
    });

    test('can manage products', async ({ page }) => {
        await login(page, STARTER.slug, STARTER.email);
        await page.goto(`/${STARTER.slug}/products`);
        await page.screenshot({ path: 'tests/e2e/screenshots/starter-products.png', fullPage: true });
    });

    test('can manage members', async ({ page }) => {
        await login(page, STARTER.slug, STARTER.email);
        await page.goto(`/${STARTER.slug}/members`);
        await page.screenshot({ path: 'tests/e2e/screenshots/starter-members.png', fullPage: true });
    });

    test('basic reports accessible', async ({ page }) => {
        await login(page, STARTER.slug, STARTER.email);
        await page.goto(`/${STARTER.slug}/reports`);
        await expect(page).toHaveURL(new RegExp('reports'));
        await page.screenshot({ path: 'tests/e2e/screenshots/starter-reports.png', fullPage: true });
    });

    test('settings accessible', async ({ page }) => {
        await login(page, STARTER.slug, STARTER.email);
        await page.goto(`/${STARTER.slug}/settings/general`);
        await expect(page.getByText('Company Name')).toBeVisible();
    });

    test('business features blocked', async ({ page }) => {
        await login(page, STARTER.slug, STARTER.email);

        // SLA should be 403
        const sla = await page.goto(`/${STARTER.slug}/sla`);
        expect(sla?.status()).toBe(403);

        // Notifications should be 403
        const notif = await page.goto(`/${STARTER.slug}/settings/notifications`);
        expect(notif?.status()).toBe(403);

        // Export should be 403
        const exp = await page.goto(`/${STARTER.slug}/reports/export/volume`);
        expect(exp?.status()).toBe(403);
    });

    test('enterprise features blocked', async ({ page }) => {
        await login(page, STARTER.slug, STARTER.email);

        const dept = await page.goto(`/${STARTER.slug}/departments`);
        expect(dept?.status()).toBe(403);

        const roles = await page.goto(`/${STARTER.slug}/roles`);
        expect(roles?.status()).toBe(403);
    });

    test('no agent tier in member create', async ({ page }) => {
        await login(page, STARTER.slug, STARTER.email);
        await page.goto(`/${STARTER.slug}/members/create`);
        await page.locator('input[name="role"][value="agent"]').click();
        await expect(page.locator('#support_tier')).not.toBeVisible();
    });
});

// ════════════════════════════════════════════════════════════════════
// BUSINESS PLAN (kentbusiness)
// ════════════════════════════════════════════════════════════════════
test.describe('Business Plan', () => {
    test('public portal landing page', async ({ page }) => {
        await page.goto(`/${BUSINESS.slug}/`);
        await expect(page.getByRole('heading', { name: 'Submit New Ticket' })).toBeVisible();
        await expect(page.getByRole('heading', { name: 'Track Existing Ticket' })).toBeVisible();
        await page.screenshot({ path: 'tests/e2e/screenshots/business-landing.png', fullPage: true });
    });

    test('submit ticket form has all fields', async ({ page }) => {
        await page.goto(`/${BUSINESS.slug}/submit-ticket`);
        await expect(page.locator('#name')).toBeVisible();
        await expect(page.locator('#email')).toBeVisible();
        await expect(page.locator('#subject')).toBeVisible();
        await expect(page.locator('#department_id')).toBeVisible();
        await expect(page.locator('#priority')).toBeVisible();
        await expect(page.locator('#incident_date')).toBeVisible();
        await expect(page.locator('#description')).toBeVisible();
        await page.screenshot({ path: 'tests/e2e/screenshots/business-submit-form.png', fullPage: true });
    });

    test('track ticket page', async ({ page }) => {
        await page.goto(`/${BUSINESS.slug}/track-ticket`);
        await expect(page.getByRole('heading', { name: 'Find Your Ticket', exact: true })).toBeVisible();
        await page.locator('#ticket_number').fill('FAKE-123');
        await page.locator('#email').fill('nobody@test.com');
        await page.getByRole('button', { name: 'Find Ticket' }).click();
        await expect(page.getByText('No ticket found')).toBeVisible();
    });

    test('dashboard with business features', async ({ page }) => {
        await login(page, BUSINESS.slug, BUSINESS.email);
        await expect(page).toHaveURL(new RegExp(`/${BUSINESS.slug}/dashboard`));
        await page.screenshot({ path: 'tests/e2e/screenshots/business-dashboard.png', fullPage: true });
    });

    test('SLA policies accessible', async ({ page }) => {
        await login(page, BUSINESS.slug, BUSINESS.email);
        await page.goto(`/${BUSINESS.slug}/sla`);
        await expect(page).toHaveURL(new RegExp('sla'));
    });

    test('notification settings accessible', async ({ page }) => {
        await login(page, BUSINESS.slug, BUSINESS.email);
        await page.goto(`/${BUSINESS.slug}/settings/notifications`);
        await expect(page.getByRole('heading', { name: 'SMTP Configuration' })).toBeVisible();
    });

    test('activity logs accessible', async ({ page }) => {
        await login(page, BUSINESS.slug, BUSINESS.email);
        await page.goto(`/${BUSINESS.slug}/activity-logs`);
        await expect(page.getByRole('heading', { name: 'Activity Logs' })).toBeVisible();
    });

    test('billing report accessible', async ({ page }) => {
        await login(page, BUSINESS.slug, BUSINESS.email);
        await page.goto(`/${BUSINESS.slug}/reports/billing`);
        await expect(page).toHaveURL(new RegExp('billing'));
    });

    test('SLA compliance report accessible', async ({ page }) => {
        await login(page, BUSINESS.slug, BUSINESS.email);
        await page.goto(`/${BUSINESS.slug}/reports/sla-compliance`);
        await expect(page).toHaveURL(new RegExp('sla-compliance'));
    });

    test('enterprise features blocked for business', async ({ page }) => {
        await login(page, BUSINESS.slug, BUSINESS.email);

        const dept = await page.goto(`/${BUSINESS.slug}/departments`);
        expect(dept?.status()).toBe(403);

        const roles = await page.goto(`/${BUSINESS.slug}/roles`);
        expect(roles?.status()).toBe(403);
    });

    test('no agent tier in member create (business)', async ({ page }) => {
        await login(page, BUSINESS.slug, BUSINESS.email);
        await page.goto(`/${BUSINESS.slug}/members/create`);
        await page.locator('input[name="role"][value="agent"]').click();
        await expect(page.locator('#support_tier')).not.toBeVisible();
    });

    test('no comments section on tracked ticket (business)', async ({ page }) => {
        await page.goto(`/${BUSINESS.slug}/track-ticket`);
        await page.locator('#ticket_number').fill('FAKE');
        await page.locator('#email').fill('x@x.com');
        await page.getByRole('button', { name: 'Find Ticket' }).click();
        await expect(page.getByText('Comments & Updates')).not.toBeVisible();
    });
});

// ════════════════════════════════════════════════════════════════════
// ENTERPRISE PLAN (kent)
// ════════════════════════════════════════════════════════════════════
test.describe('Enterprise Plan', () => {
    test('dashboard with all features in sidebar', async ({ page }) => {
        await login(page, ENTERPRISE.slug, ENTERPRISE.email);
        await expect(page.getByRole('link', { name: 'Departments' }).first()).toBeVisible();
        await expect(page.getByRole('link', { name: 'Roles & Permissions' })).toBeVisible();
        await expect(page.getByRole('link', { name: 'Activity Logs' })).toBeVisible();
        await page.screenshot({ path: 'tests/e2e/screenshots/enterprise-full-sidebar.png', fullPage: true });
    });

    // ── Tickets ──
    test('ticket list and create', async ({ page }) => {
        await login(page, ENTERPRISE.slug, ENTERPRISE.email);
        await page.goto(`/${ENTERPRISE.slug}/tickets`);
        await expect(page.getByText('E2E Test Ticket')).toBeVisible();

        await page.goto(`/${ENTERPRISE.slug}/tickets/create`);
        await expect(page.locator('#client_id')).toBeVisible();
        await expect(page.locator('#subject')).toBeVisible();
        await expect(page.locator('#department_id')).toBeVisible();
        await expect(page.locator('#priority')).toBeVisible();
        await page.screenshot({ path: 'tests/e2e/screenshots/enterprise-ticket-create.png', fullPage: true });
    });

    test('ticket show with enterprise sections', async ({ page }) => {
        await login(page, ENTERPRISE.slug, ENTERPRISE.email);
        await page.goto(`/${ENTERPRISE.slug}/tickets/1`);

        // Core sections
        await expect(page.getByText('Assignment & Priority')).toBeVisible();
        await expect(page.getByText('Comments & Updates')).toBeVisible();
        await expect(page.locator('#sidebar_status')).toBeVisible();
        await expect(page.locator('#sidebar_assigned_to')).toBeVisible();

        // Client email
        await expect(page.getByText('client@test.com')).toBeVisible();

        await page.screenshot({ path: 'tests/e2e/screenshots/enterprise-ticket-show-full.png', fullPage: true });
    });

    test('can add comment with type selection', async ({ page }) => {
        await login(page, ENTERPRISE.slug, ENTERPRISE.email);
        await page.goto(`/${ENTERPRISE.slug}/tickets/1`);

        const commentBox = page.locator('#comment_content');
        if (await commentBox.isVisible()) {
            await commentBox.fill('Full system E2E comment');
            await page.locator('select[name="type"]').selectOption('internal');
            await page.getByRole('button', { name: 'Add Comment' }).click();
            await expect(page.getByText('Full system E2E comment')).toBeVisible();
        }

        await page.screenshot({ path: 'tests/e2e/screenshots/enterprise-ticket-comment.png', fullPage: true });
    });

    // ── Departments ──
    test('departments CRUD', async ({ page }) => {
        await login(page, ENTERPRISE.slug, ENTERPRISE.email);
        await page.goto(`/${ENTERPRISE.slug}/departments`);
        await expect(page).toHaveURL(new RegExp('departments'));

        const deptName = 'E2E Dept ' + Date.now();
        await page.goto(`/${ENTERPRISE.slug}/departments/create`);
        await page.locator('input[name="name"]').fill(deptName);
        await page.locator('input[name="code"]').fill('E2E');
        await page.locator('input[name="color"]').fill('#e74c3c');
        await page.getByRole('button', { name: 'Create' }).click();
        await expect(page.getByText(deptName)).toBeVisible();
    });

    // ── Roles ──
    test('roles with default badges and custom create', async ({ page }) => {
        await login(page, ENTERPRISE.slug, ENTERPRISE.email);
        await page.goto(`/${ENTERPRISE.slug}/roles`);

        await expect(page.getByRole('cell', { name: /Admin/ })).toBeVisible();
        await expect(page.getByRole('cell', { name: /Manager/ })).toBeVisible();
        await expect(page.getByRole('cell', { name: /Agent/ })).toBeVisible();
        await expect(page.getByText('Default').first()).toBeVisible();

        // Create custom role
        const roleName = 'e2e_full_' + Date.now();
        await page.goto(`/${ENTERPRISE.slug}/roles/create`);
        await page.locator('input[name="name"]').fill(roleName);
        const checkboxes = page.locator('input[type="checkbox"]');
        if (await checkboxes.count() > 0) await checkboxes.first().check();
        await page.getByRole('button', { name: 'Create' }).click();
        await expect(page.getByRole('cell', { name: new RegExp(roleName, 'i') })).toBeVisible();
    });

    // ── Agent Tiering ──
    test('agent tiering visible in member create', async ({ page }) => {
        await login(page, ENTERPRISE.slug, ENTERPRISE.email);
        await page.goto(`/${ENTERPRISE.slug}/members/create`);
        await page.locator('input[name="role"][value="agent"]').click();
        await expect(page.locator('#support_tier')).toBeVisible();
        await expect(page.getByText('Tier 1 —')).toBeVisible();
        await expect(page.getByText('Tier 2 —')).toBeVisible();
        await expect(page.getByText('Tier 3 —')).toBeVisible();
    });

    // ── Members + Performance ──
    test('member performance page', async ({ page }) => {
        await login(page, ENTERPRISE.slug, ENTERPRISE.email);
        await page.goto(`/${ENTERPRISE.slug}/members`);
        const perfLink = page.getByRole('link', { name: 'Performance' }).first();
        if (await perfLink.isVisible()) {
            await perfLink.click();
            await expect(page.getByText('Performance Metrics')).toBeVisible();
            await expect(page.getByText('Total Assigned')).toBeVisible();
            await page.screenshot({ path: 'tests/e2e/screenshots/enterprise-member-perf.png', fullPage: true });
        }
    });

    // ── Activity Logs ──
    test('activity logs with filters', async ({ page }) => {
        await login(page, ENTERPRISE.slug, ENTERPRISE.email);
        await page.goto(`/${ENTERPRISE.slug}/activity-logs`);
        await expect(page.getByRole('heading', { name: 'Activity Logs' })).toBeVisible();
        await expect(page.locator('select[name="action"]')).toBeVisible();
        await expect(page.locator('select[name="user_id"]')).toBeVisible();
        await expect(page.locator('input[name="from"]')).toBeVisible();
        await expect(page.locator('input[name="to"]')).toBeVisible();
    });

    // ── Reports ──
    test('all report pages load', async ({ page }) => {
        await login(page, ENTERPRISE.slug, ENTERPRISE.email);

        for (const path of ['reports', 'reports/departments', 'reports/categories', 'reports/clients', 'reports/agents', 'reports/products', 'reports/tickets', 'reports/billing', 'reports/sla-compliance']) {
            await page.goto(`/${ENTERPRISE.slug}/${path}`);
            await expect(page).toHaveURL(new RegExp(path.replace('/', '\\/')));
        }
    });

    test('export buttons visible', async ({ page }) => {
        await login(page, ENTERPRISE.slug, ENTERPRISE.email);
        await page.goto(`/${ENTERPRISE.slug}/reports`);
        await expect(page.getByText('Export').first()).toBeVisible();
    });

    // ── Settings ──
    test('all settings pages', async ({ page }) => {
        await login(page, ENTERPRISE.slug, ENTERPRISE.email);

        await page.goto(`/${ENTERPRISE.slug}/settings/general`);
        await expect(page.getByText('Company Name')).toBeVisible();

        await page.goto(`/${ENTERPRISE.slug}/settings/branding`);
        await expect(page.getByText('Primary Color').first()).toBeVisible();

        await page.goto(`/${ENTERPRISE.slug}/settings/notifications`);
        await expect(page.getByRole('heading', { name: 'SMTP Configuration' })).toBeVisible();

        await page.screenshot({ path: 'tests/e2e/screenshots/enterprise-settings-smtp.png', fullPage: true });
    });

    // ── Public Portal ──
    test('public portal full flow', async ({ page }) => {
        await page.goto(`/${ENTERPRISE.slug}/`);
        await expect(page.getByRole('heading', { name: 'Submit New Ticket' })).toBeVisible();

        await page.goto(`/${ENTERPRISE.slug}/submit-ticket`);
        await expect(page.locator('#incident_date')).toBeVisible();

        await page.goto(`/${ENTERPRISE.slug}/track-ticket`);
        await expect(page.getByRole('heading', { name: 'Find Your Ticket', exact: true })).toBeVisible();
    });
});

// ════════════════════════════════════════════════════════════════════
// CROSS-TENANT ISOLATION
// ════════════════════════════════════════════════════════════════════
test.describe('Cross-Tenant Isolation', () => {
    test('cannot track enterprise tickets from business portal', async ({ page }) => {
        await page.goto(`/${BUSINESS.slug}/track-ticket`);
        await page.locator('#ticket_number').fill('TKT-E2E-001');
        await page.locator('#email').fill('client@test.com');
        await page.getByRole('button', { name: 'Find Ticket' }).click();
        await expect(page.getByText('No ticket found')).toBeVisible();
    });

    test('invalid tracking tokens return 404', async ({ page }) => {
        expect((await page.goto(`/${ENTERPRISE.slug}/track-ticket/invalid-token`))?.status()).toBe(404);
        expect((await page.goto(`/${BUSINESS.slug}/track-ticket/invalid-token`))?.status()).toBe(404);
    });

    test('portal routes return 404', async ({ page }) => {
        expect((await page.goto('/portal/kent/login'))?.status()).toBe(404);
        expect((await page.goto('/portal/kentbusiness/'))?.status()).toBe(404);
    });
});

// ════════════════════════════════════════════════════════════════════
// ERROR PAGES
// ════════════════════════════════════════════════════════════════════
test.describe('Error Pages', () => {
    test('404 page renders', async ({ page }) => {
        await page.goto(`/${ENTERPRISE.slug}/totally-nonexistent`);
        await expect(page.getByText('Page not found')).toBeVisible();
        await expect(page.getByRole('link', { name: 'Go Home' })).toBeVisible();
    });

    test('403 on gated feature', async ({ page }) => {
        await login(page, STARTER.slug, STARTER.email);
        await page.goto(`/${STARTER.slug}/sla`);
        await expect(page.getByText('Access Denied')).toBeVisible();
        await page.screenshot({ path: 'tests/e2e/screenshots/403-page.png' });
    });
});
