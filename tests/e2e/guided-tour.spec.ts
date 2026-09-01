import { test, expect, Page } from '@playwright/test';

/**
 * The tour is a state machine that spans page loads, so the parts that matter
 * most are invisible to the PHP suite and to the DOM-stub tests: resume after a
 * real navigation, the spotlight landing on the right element, and every exit
 * path leaving nothing behind.
 */

const SLUG = 'reilly-inc-9813';
const BASE = 'http://localhost:8005';

async function login(page: Page) {
    await page.goto(`${BASE}/${SLUG}/tickets`);
    if (page.url().includes('login')) {
        await page.locator('input[name="email"]').fill('test@example.com');
        await page.locator('input[name="password"]').fill('password');
        await page.getByRole('button', { name: 'Log in' }).click();
        await page.waitForURL(/\/(tenant|.*tickets)/, { timeout: 15000 });
        await page.goto(`${BASE}/${SLUG}/tickets`);
    }
    await page.waitForLoadState('networkidle');
}

const launcher = (page: Page) => page.locator('button[aria-haspopup="true"]', { hasText: 'Guided tours' });
const panel = (page: Page) => page.locator('[data-gt="panel"]');
const title = (page: Page) => page.locator('[data-gt="title"]');

async function startTour(page: Page, name: string) {
    await launcher(page).click();
    await page.getByRole('button', { name: new RegExp(name, 'i') }).click();
    await expect(panel(page)).toBeVisible();
}

const progress = (page: Page) =>
    page.evaluate(() => sessionStorage.getItem('guided-tour-progress'));

test.describe('guided tour', () => {
    test('the launcher is reachable and lists only tours the user can take', async ({ page }) => {
        await login(page);

        // Regression: the launcher was originally a floating bubble in the
        // bottom-left, where the sidebar's user panel swallowed every click.
        await expect(launcher(page)).toBeVisible();
        await launcher(page).click();

        await expect(page.getByText('Raise a ticket and route it correctly')).toBeVisible();
        // Honest estimate shown before committing.
        await expect(page.getByText(/3 min · 5 steps/)).toBeVisible();
    });

    test('a tour spans a real navigation and resumes on the next page', async ({ page }) => {
        await login(page);
        await startTour(page, 'Raise a ticket');

        await expect(title(page)).toHaveText('Start here');
        // A step that causes a navigation waits for a real click, not Next.
        await expect(page.locator('[data-gt="next"]')).toBeHidden();
        expect(await progress(page)).toContain('"index":0');

        await page.locator('[data-tour="ticket-create"]').click();
        await page.waitForURL(/tickets\/create/);
        await page.waitForLoadState('networkidle');

        await expect(panel(page)).toBeVisible({ timeout: 10000 });
        await expect(title(page)).toHaveText('The client sets the tier');
        expect(await progress(page)).toContain('"index":1');
    });

    test('the spotlight lands on the step target, not on nothing', async ({ page }) => {
        await login(page);
        await startTour(page, 'Raise a ticket');

        const cutout = await page.locator('[data-gt="cutout"]').boundingBox();
        const target = await page.locator('[data-tour="ticket-create"]').boundingBox();

        expect(cutout!.width).toBeGreaterThan(0);
        // The cut-out sits over the target with a few pixels of padding.
        expect(Math.abs(cutout!.x - target!.x)).toBeLessThan(12);
        expect(Math.abs(cutout!.y - target!.y)).toBeLessThan(12);
    });

    test('the panel stays in the viewport and never covers its own target', async ({ page }) => {
        await page.setViewportSize({ width: 360, height: 720 });
        await login(page);
        await startTour(page, 'Raise a ticket');

        const box = (await panel(page).boundingBox())!;
        expect(box.x).toBeGreaterThanOrEqual(0);
        expect(box.x + box.width).toBeLessThanOrEqual(360);
        expect(box.y).toBeGreaterThanOrEqual(0);

        const target = (await page.locator('[data-tour="ticket-create"]').boundingBox())!;
        const overlaps =
            box.x < target.x + target.width && box.x + box.width > target.x &&
            box.y < target.y + target.height && box.y + box.height > target.y;
        expect(overlaps, 'the panel must not sit on top of the thing it is pointing at').toBe(false);
    });

    test('Escape ends the tour and nothing resumes on the next page', async ({ page }) => {
        await login(page);
        await startTour(page, 'Raise a ticket');

        await page.keyboard.press('Escape');
        await expect(panel(page)).toHaveCount(0);
        expect(await progress(page)).toBeNull();

        // An abandoned tour must not ambush the user somewhere unrelated.
        await page.goto(`${BASE}/${SLUG}/tickets/create`);
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(600);
        await expect(panel(page)).toHaveCount(0);
    });

    test('a tour can be started twice in a row', async ({ page }) => {
        await login(page);

        await startTour(page, 'Raise a ticket');
        await page.keyboard.press('Escape');
        await expect(panel(page)).toHaveCount(0);

        // No first-run flag, nothing to reset.
        await startTour(page, 'Raise a ticket');
        await expect(title(page)).toHaveText('Start here');
    });

    test('a step whose target is missing says so rather than spotlighting nothing', async ({ page }) => {
        await login(page);

        // Remove the anchor the second step points at, as a redesign would.
        await startTour(page, 'Raise a ticket');
        await page.locator('[data-tour="ticket-create"]').click();
        await page.waitForURL(/tickets\/create/);
        await page.waitForLoadState('networkidle');
        await expect(panel(page)).toBeVisible({ timeout: 10000 });

        await page.evaluate(() => document.querySelector('[data-tour="ticket-priority"]')?.remove());
        await page.locator('[data-gt="next"]').click();

        await expect(title(page)).toHaveText(/not on this screen/i, { timeout: 8000 });
        await expect(page.locator('[data-gt="next"]')).toHaveText(/skip/i);
    });
});
