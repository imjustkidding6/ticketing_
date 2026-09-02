import { test, expect, Page } from '@playwright/test';

/**
 * Walks every tour the workspace offers, all the way to the end.
 *
 * The per-tour tests cover the mechanism; this covers the content. A tour whose
 * anchor sits on a hidden input, on an element that only renders in one state,
 * or on a page that 404s for this workspace fails silently in every other
 * suite — the endpoint still serves it and the anchor test still passes,
 * because the anchor exists in the template.
 */

const BASE = 'http://localhost:8005';
const SLUG = process.env.TOUR_SLUG || 'reilly-inc-9813';

async function login(page: Page) {
    await page.goto(`${BASE}/${SLUG}/tutorials`);
    if (page.url().includes('login')) {
        await page.locator('input[name="email"]').fill('test@example.com');
        await page.locator('input[name="password"]').fill('password');
        await page.getByRole('button', { name: 'Log in' }).click();
        await page.waitForTimeout(2500);
        await page.goto(`${BASE}/${SLUG}/tutorials`);
    }
    await page.waitForLoadState('networkidle');
}

test('every offered tour runs to completion', async ({ page }) => {
    test.setTimeout(600000);

    const jsErrors: string[] = [];
    page.on('pageerror', (e) => jsErrors.push(e.message));

    await login(page);

    const tours = await page.evaluate(async (slug) => {
        const r = await fetch(`/${slug}/tours`, { headers: { Accept: 'application/json' } });
        return r.ok ? (await r.json()).tours : [];
    }, SLUG);

    expect(tours.length, 'the workspace offers no tours at all').toBeGreaterThan(0);

    const broken: string[] = [];

    for (const t of tours) {
        const def = await page.evaluate(async ({ k, slug }) => {
            const r = await fetch(`/${slug}/tours/${k}`, { headers: { Accept: 'application/json' } });
            return r.ok ? (await r.json()).tour : null;
        }, { k: t.key, slug: SLUG });

        if (!def) { broken.push(`${t.key}: listed by the launcher but the endpoint refused it`); continue; }

        // Start it exactly as the help page does.
        await page.evaluate((k) => sessionStorage.setItem('guided-tour-progress', JSON.stringify({ key: k, index: 0 })), t.key);
        await page.goto(`${BASE}${def.steps[0].url}`);
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(900);

        let failed: string | null = null;

        for (let i = 0; i < def.steps.length; i++) {
            if (!(await page.locator('[data-gt="panel"]').count())) {
                failed = `no panel at step ${i + 1} — the tour did not resume on ${def.steps[i].page}`;
                break;
            }

            const title = (await page.locator('[data-gt="title"]').textContent())?.trim() ?? '';
            if (/not on this screen|another page/i.test(title)) {
                failed = `stuck at step ${i + 1} (${def.steps[i].selector}): "${title}"`;
                break;
            }

            if (await page.locator('[data-gt="next"]').isHidden()) {
                const target = page.locator(def.steps[i].selector).first();
                if (!(await target.count())) { failed = `step ${i + 1}: target ${def.steps[i].selector} not on the page`; break; }
                await target.click();
                await page.waitForLoadState('networkidle');
                await page.waitForTimeout(1100);
            } else {
                await page.locator('[data-gt="next"]').click();
                await page.waitForTimeout(500);
            }
        }

        const leftover = await page.evaluate(() => sessionStorage.getItem('guided-tour-progress'));

        if (!failed && (await page.locator('[data-gt="panel"]').count())) {
            failed = 'the panel was still open after the last step';
        }
        if (!failed && leftover) {
            failed = `finished but left progress behind: ${leftover}`;
        }

        if (failed) broken.push(`${t.key} (${def.steps.length} steps): ${failed}`);

        await page.evaluate(() => sessionStorage.removeItem('guided-tour-progress'));
    }

    expect(broken, `tours that do not run to the end:\n  ${broken.join('\n  ')}`).toEqual([]);
    expect(jsErrors, `JS errors while running the tours:\n  ${jsErrors.join('\n  ')}`).toEqual([]);
});
