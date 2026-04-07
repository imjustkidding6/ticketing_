import { test, expect } from '@playwright/test';

const STARTER_SLUG = 'midel';
const BUSINESS_SLUG = 'kentbusiness';

test.describe('Tenant Isolation - Plan Gating', () => {
    test('starter tenant gets 404 on all public portal pages', async ({ page }) => {
        const landing = await page.goto(`/${STARTER_SLUG}/`);
        expect(landing?.status()).toBe(404);

        const submit = await page.goto(`/${STARTER_SLUG}/submit-ticket`);
        expect(submit?.status()).toBe(404);

        const track = await page.goto(`/${STARTER_SLUG}/track-ticket`);
        expect(track?.status()).toBe(404);

        const portal = await page.goto(`/portal/${STARTER_SLUG}/`);
        expect(portal?.status()).toBe(404);

        const portalLogin = await page.goto(`/portal/${STARTER_SLUG}/login`);
        expect(portalLogin?.status()).toBe(404);
    });

    test('business tenant portal pages are accessible', async ({ page }) => {
        await page.goto(`/${BUSINESS_SLUG}/`);
        await expect(page.getByRole('heading', { name: 'Submit New Ticket' })).toBeVisible();

        await page.goto(`/${BUSINESS_SLUG}/submit-ticket`);
        await expect(page.getByRole('heading', { name: 'Submit a Ticket' })).toBeVisible();

        await page.goto(`/${BUSINESS_SLUG}/track-ticket`);
        await expect(page.getByRole('heading', { name: 'Find Your Ticket', exact: true })).toBeVisible();

        await page.goto(`/portal/${BUSINESS_SLUG}/`);
        expect(page.url()).toContain(BUSINESS_SLUG);
    });
});

test.describe('Tenant Isolation - Cross-Tenant Data', () => {
    test('cannot track tickets from another tenant', async ({ page }) => {
        await page.goto(`/${BUSINESS_SLUG}/track-ticket`);

        await page.locator('#ticket_number').fill('TKT-FAKE-CROSS-TENANT');
        await page.locator('#email').fill('nobody@other-tenant.com');
        await page.getByRole('button', { name: 'Find Ticket' }).click();

        await expect(page.getByText('No ticket found')).toBeVisible();
    });

    test('invalid tracking token returns 404', async ({ page }) => {
        const response = await page.goto(`/${BUSINESS_SLUG}/track-ticket/totally-invalid-token-xyz`);
        expect(response?.status()).toBe(404);
    });
});

test.describe('Tenant Isolation - Business Plan Features', () => {
    test('track result page has no comments section for business plan', async ({ page }) => {
        await page.goto(`/${BUSINESS_SLUG}/track-ticket`);

        await page.locator('#ticket_number').fill('TKT-INVALID');
        await page.locator('#email').fill('test@test.com');
        await page.getByRole('button', { name: 'Find Ticket' }).click();

        // No comments section visible (not found page)
        await expect(page.getByText('Comments & Updates')).not.toBeVisible();
    });
});

test.describe('Tenant Isolation - Authenticated Dashboard', () => {
    test('business tenant dashboard shows correct tenant context', async ({ page }) => {
        await page.goto(`/${BUSINESS_SLUG}/dashboard`);

        if (page.url().includes('login')) {
            await page.locator('input[name="email"]').fill('facula425@gmail.com');
            await page.locator('input[name="password"]').fill('password');
            await page.getByRole('button', { name: 'Log in' }).click();
            await page.waitForURL(new RegExp(`/${BUSINESS_SLUG}/`));
        }

        await expect(page).toHaveURL(new RegExp(`/${BUSINESS_SLUG}/dashboard`));

        // Business plan should NOT show enterprise Roles & Permissions in sidebar
        await expect(page.getByRole('link', { name: 'Roles & Permissions' })).not.toBeVisible();

        await page.screenshot({ path: 'tests/e2e/screenshots/business-dashboard.png', fullPage: true });
    });
});
