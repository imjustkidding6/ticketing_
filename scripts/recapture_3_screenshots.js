import { chromium } from '@playwright/test';
import path from 'path';
import fs from 'fs';

process.env.LD_LIBRARY_PATH = `/tmp/chromelibs/usr/lib/x86_64-linux-gnu:${process.env.LD_LIBRARY_PATH || ''}`;

const BASE_URL = process.env.APP_URL || 'http://127.0.0.1:8005';
const EXECUTABLE_PATH = '/home/anyang/.cache/puppeteer/chrome/linux-151.0.7922.47/chrome-linux64/chrome';

const DIRS = [
    path.resolve('screenshots'),
    path.resolve('docs/screenshots'),
    path.resolve('public/docs/screenshots')
];

DIRS.forEach(d => fs.mkdirSync(d, { recursive: true }));

function log(msg) {
    process.stdout.write(`${msg}\n`);
}

async function saveOverwriting(page, filename) {
    await page.waitForTimeout(500);
    for (const dir of DIRS) {
        const filePath = path.join(dir, filename);
        await page.screenshot({ path: filePath, fullPage: false });
        log(`[RE-CAPTURED & OVERWRITTEN] ${filePath} (${fs.statSync(filePath).size} bytes)`);
    }
}

(async () => {
    log('=== PHASE 5: RE-CAPTURING THE 3 CORRECTED SCREENSHOTS ===');

    const browser = await chromium.launch({
        executablePath: EXECUTABLE_PATH,
        headless: true,
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--force-color-profile=srgb',
            '--window-size=1920,1080'
        ]
    });

    const context = await browser.newContext({
        viewport: { width: 1920, height: 1080 },
        deviceScaleFactor: 1
    });

    const page = await context.newPage();

    // Force Light Mode
    await page.addInitScript(() => {
        localStorage.setItem('theme', 'light');
        document.documentElement.classList.remove('dark');
    });

    // 1. Login
    await page.goto(`${BASE_URL}/admin/login`, { waitUntil: 'domcontentloaded' });
    await page.fill('input[name="email"]', 'admin@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForTimeout(2000);
    log(`Logged in as admin@example.com`);

    // 2. Re-capture Figure 23: 23_edit_sla_policy.png
    log('\n--- Re-capturing Figure 23: 23_edit_sla_policy.png ---');
    await page.goto(`${BASE_URL}/admin/sla/tier/basic/edit`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(800);
    await saveOverwriting(page, '23_edit_sla_policy.png');

    // 3. Re-capture Figure 27: 27_ai_response.png
    log('\n--- Re-capturing Figure 27: 27_ai_response.png ---');
    await page.goto(`${BASE_URL}/admin/ai/chat-page`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(1000);
    await page.evaluate(() => {
        const btn = Array.from(document.querySelectorAll('button')).find(b => b.textContent.includes('Tenant Stats') || b.textContent.includes('How many active tenants'));
        if (btn) btn.click();
    });
    await page.waitForTimeout(3000);
    await saveOverwriting(page, '27_ai_response.png');

    // 4. Re-capture Figure 30: 30_tutorial_details.png
    log('\n--- Re-capturing Figure 30: 30_tutorial_details.png ---');
    await page.goto(`${BASE_URL}/admin/help/getting-started`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(800);
    await saveOverwriting(page, '30_tutorial_details.png');

    log('\n✓ RE-CAPTURE OF ALL 3 SCREENSHOTS COMPLETED SUCCESSFULLY!');
    await browser.close();
    process.exit(0);
})();
