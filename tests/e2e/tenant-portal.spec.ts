import { test, expect } from '@playwright/test';

const STARTER_SLUG = 'midel';
const BUSINESS_SLUG = 'kentbusiness';

test.describe('Starter Tenant - No Public Portal', () => {
    test('landing page returns 404 for starter plan', async ({ page }) => {
        const response = await page.goto(`/${STARTER_SLUG}/`);
        expect(response?.status()).toBe(404);
    });

    test('submit ticket returns 404 for starter plan', async ({ page }) => {
        const response = await page.goto(`/${STARTER_SLUG}/submit-ticket`);
        expect(response?.status()).toBe(404);
    });

    test('track ticket returns 404 for starter plan', async ({ page }) => {
        const response = await page.goto(`/${STARTER_SLUG}/track-ticket`);
        expect(response?.status()).toBe(404);
    });
});

test.describe('Business Tenant - Public Portal', () => {
    test('landing page shows hero and action cards', async ({ page }) => {
        await page.goto(`/${BUSINESS_SLUG}/`);

        await expect(page.locator('h1')).toContainText('How can we help you today?');
        await expect(page.getByRole('heading', { name: 'Submit New Ticket' })).toBeVisible();
        await expect(page.getByRole('heading', { name: 'Track Existing Ticket' })).toBeVisible();

        await page.screenshot({ path: 'tests/e2e/screenshots/landing-page.png', fullPage: true });
    });

    test('can navigate to submit ticket page', async ({ page }) => {
        await page.goto(`/${BUSINESS_SLUG}/`);

        await page.getByRole('link', { name: 'Submit New Ticket' }).first().click();

        await expect(page).toHaveURL(new RegExp(`/${BUSINESS_SLUG}/submit-ticket`));
        await expect(page.getByRole('heading', { name: 'Submit a Ticket' })).toBeVisible();

        // Form fields
        await expect(page.locator('#name')).toBeVisible();
        await expect(page.locator('#email')).toBeVisible();
        await expect(page.locator('#subject')).toBeVisible();
        await expect(page.locator('#description')).toBeVisible();
        await expect(page.locator('#department_id')).toBeVisible();
        await expect(page.locator('#priority')).toBeVisible();
        await expect(page.locator('#incident_date')).toBeVisible();

        await page.screenshot({ path: 'tests/e2e/screenshots/submit-ticket.png', fullPage: true });
    });

    test('can navigate to track ticket page', async ({ page }) => {
        await page.goto(`/${BUSINESS_SLUG}/`);

        await page.getByRole('link', { name: 'Track Ticket' }).first().click();

        await expect(page).toHaveURL(new RegExp(`/${BUSINESS_SLUG}/track-ticket`));
        await expect(page.getByRole('heading', { name: 'Find Your Ticket', exact: true })).toBeVisible();
        await expect(page.locator('#ticket_number')).toBeVisible();
        await expect(page.locator('#email')).toBeVisible();

        await page.screenshot({ path: 'tests/e2e/screenshots/track-ticket.png', fullPage: true });
    });

    test('submit ticket form validates required fields', async ({ page }) => {
        await page.goto(`/${BUSINESS_SLUG}/submit-ticket`);

        await page.getByRole('button', { name: 'Submit Ticket' }).click();

        await expect(page).toHaveURL(new RegExp(`/${BUSINESS_SLUG}/submit-ticket`));
    });

    test('track ticket shows not found for invalid ticket', async ({ page }) => {
        await page.goto(`/${BUSINESS_SLUG}/track-ticket`);

        await page.locator('#ticket_number').fill('TKT-INVALID-999');
        await page.locator('#email').fill('nobody@test.com');
        await page.getByRole('button', { name: 'Find Ticket' }).click();

        await expect(page.getByText('No ticket found')).toBeVisible();

        await page.screenshot({ path: 'tests/e2e/screenshots/track-not-found.png', fullPage: true });
    });
});


test.describe('Member Management - Agent Tiering', () => {
    test('business plan hides support tier (Enterprise only)', async ({ page }) => {
        await page.goto(`/${BUSINESS_SLUG}/dashboard`);

        if (page.url().includes('login')) {
            await page.locator('input[name="email"]').fill('facula425@gmail.com');
            await page.locator('input[name="password"]').fill('password');
            await page.getByRole('button', { name: 'Log in' }).click();
            await page.waitForURL(new RegExp(`/${BUSINESS_SLUG}/`));
        }

        await page.goto(`/${BUSINESS_SLUG}/members/create`);

        // Select agent role to reveal config
        await page.locator('input[name="role"][value="agent"]').click();

        // Availability should be visible (all plans)
        await expect(page.locator('#is_available')).toBeVisible();

        // Support tier should NOT be visible (Enterprise only)
        await expect(page.locator('#support_tier')).not.toBeVisible();
        await expect(page.getByText('Support Tier Info')).not.toBeVisible();

        await page.screenshot({ path: 'tests/e2e/screenshots/member-create-no-tier.png', fullPage: true });
    });
});

test.describe('Portal Routes Removed', () => {
    test('portal routes return 404', async ({ page }) => {
        const response = await page.goto(`/portal/${BUSINESS_SLUG}/login`);
        expect(response?.status()).toBe(404);
    });
});
