import { test, expect, Page } from '@playwright/test';

const SLUG = 'reilly-inc-9813';
const BASE = 'http://localhost:8005';

async function login(page: Page) {
    await page.goto(`${BASE}/${SLUG}/dashboard`);
    if (page.url().includes('login')) {
        await page.locator('input[name="email"]').fill('test@example.com');
        await page.locator('input[name="password"]').fill('password');
        await page.getByRole('button', { name: 'Log in' }).click();
        await page.waitForURL(new RegExp(`/${SLUG}/`), { timeout: 10000 });
    }
}

// Injects chart messages straight into the widget's Alpine state so we test the
// bar/pie/line rendering without depending on a live OpenAI call.
async function pushChart(page: Page, type: string) {
    const block = '```chart\n' + JSON.stringify({
        type,
        title: type.toUpperCase() + ' demo',
        data: type === 'line'
            ? [{ label: 'Mon', value: 3 }, { label: 'Tue', value: 7 }, { label: 'Wed', value: 5 }, { label: 'Thu', value: 9 }]
            : [{ label: 'Open', value: 4 }, { label: 'Pending', value: 2 }, { label: 'Closed', value: 8 }],
    }) + '\n```';
    await page.evaluate((b) => {
        const Alpine = (window as any).Alpine;
        const el = Array.from(document.querySelectorAll('[x-data]')).find((d) => {
            const data = Alpine.$data(d);
            return data && typeof data.pushAssistant === 'function';
        });
        const data = Alpine.$data(el);
        data.open = true;
        data.pushAssistant(b);
    }, block);
    await page.waitForTimeout(400);
}

test('AI widget renders bar, pie and line charts', async ({ page }) => {
    await login(page);
    await page.waitForTimeout(800); // let Alpine init the widget

    for (const type of ['bar', 'pie', 'line']) {
        await pushChart(page, type);
    }

    // bar => width-styled indigo bars; pie => a conic-gradient disc; line => a polyline
    const bars = await page.locator('div.bg-indigo-500').count();
    const pies = await page.locator('div[style*="conic-gradient"]').count();
    const lines = await page.locator('svg polyline').count();
    console.log('RENDERED', JSON.stringify({ bars, pies, lines }));

    expect(bars).toBeGreaterThan(0);   // bar chart
    expect(pies).toBe(1);              // pie disc
    expect(lines).toBe(1);             // line polyline

    await page.screenshot({ path: 'tests/e2e/screenshots/ai-chart-types.png', fullPage: false });
    console.log('CHARTS_OK');
});
