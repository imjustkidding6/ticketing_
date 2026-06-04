import { test } from '@playwright/test';
import fs from 'fs';

// Captures the screenshots embedded in the in-app Help & Tutorials pages.
// Output goes to public/images/tutorials/ so the Blade views can reference them
// via asset('images/tutorials/<file>').
//
// Run against a seeded, running app (http://localhost:8008 by default):
//   npx playwright test tests/e2e/screenshots/tutorials.spec.ts

const SLUG = 'reilly-inc-9813';                 // Demo Company (Enterprise plan) in the dev DB
const USER = { email: 'test@example.com', password: 'password' };
const TICKET_ID = 5;
const OUT = 'public/images/tutorials';

// NOTE: the shared playwright.config.ts baseURL (:8008) points at a different app on
// this host; the ticketing app is served on :8005, so override it here.
test.use({ baseURL: 'http://localhost:8005', headless: true, viewport: { width: 1366, height: 900 } });

test('capture tutorial screenshots', async ({ page }) => {
    test.setTimeout(120_000);
    fs.mkdirSync(OUT, { recursive: true });

    // Log in to the tenant workspace.
    await page.goto(`/${SLUG}/dashboard`);
    if (page.url().includes('login')) {
        await page.locator('input[name="email"]').fill(USER.email);
        await page.locator('input[name="password"]').fill(USER.password);
        await page.getByRole('button', { name: /log ?in|sign in/i }).click();
        await page.waitForURL(new RegExp(`/${SLUG}/`), { timeout: 15_000 });
    }

    const shots: Array<[string, string]> = [
        [`/${SLUG}/dashboard`, 'dashboard.png'],
        [`/${SLUG}/departments`, 'departments.png'],
        [`/${SLUG}/categories`, 'categories.png'],
        [`/${SLUG}/products`, 'products.png'],
        [`/${SLUG}/tickets`, 'tickets-list.png'],
        [`/${SLUG}/tickets/create`, 'ticket-create.png'],
        [`/${SLUG}/tickets/${TICKET_ID}`, 'ticket-detail.png'],
        [`/${SLUG}/clients`, 'clients.png'],
        [`/${SLUG}/reports`, 'reports.png'],
        [`/${SLUG}/reports/agents`, 'reports-agents.png'],
        [`/${SLUG}/reports/sla-compliance`, 'sla-compliance.png'],
        [`/${SLUG}/sla`, 'sla-policies.png'],
        [`/${SLUG}/settings/general`, 'settings-general.png'],
        [`/${SLUG}/roles`, 'roles.png'],
        [`/${SLUG}/submit-ticket`, 'portal-submit.png'],
    ];

    for (const [url, file] of shots) {
        try {
            await page.goto(url, { waitUntil: 'networkidle', timeout: 20_000 });
            await page.waitForTimeout(700);
            await page.screenshot({ path: `${OUT}/${file}` });
            console.log('captured', file, '->', page.url());
        } catch (e) {
            console.log('FAILED', file, (e as Error).message);
        }
    }
});
