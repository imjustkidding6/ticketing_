import { chromium } from '@playwright/test';
import path from 'path';
import fs from 'fs';

process.env.LD_LIBRARY_PATH = `/tmp/chromelibs/usr/lib/x86_64-linux-gnu:${process.env.LD_LIBRARY_PATH || ''}`;

const BASE_URL = process.env.APP_URL || 'http://127.0.0.1:8005';
const EXECUTABLE_PATH = '/home/anyang/.cache/puppeteer/chrome/linux-151.0.7922.47/chrome-linux64/chrome';

function log(msg) {
    process.stdout.write(`${msg}\n`);
}

(async () => {
    log('=== PHASE 1: REPRODUCING AND DIAGNOSING THE 3 BROKEN SCREENS ===');
    fs.mkdirSync('screenshots/debug', { recursive: true });

    const browser = await chromium.launch({
        executablePath: EXECUTABLE_PATH,
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    const context = await browser.newContext({ viewport: { width: 1920, height: 1080 } });
    const page = await context.newPage();

    page.on('console', msg => log(`[BROWSER CONSOLE] ${msg.type()}: ${msg.text()}`));
    page.on('pageerror', err => log(`[PAGE ERROR] ${err.message}`));

    // Log in
    await page.goto(`${BASE_URL}/admin/login`, { waitUntil: 'domcontentloaded' });
    await page.fill('input[name="email"]', 'admin@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForTimeout(2000);
    log(`Logged in as admin@example.com. Current URL: ${page.url()}`);

    // --- FIGURE 23 BEFORE REPRODUCTION ---
    log('\n--- Reproducing Figure 23 (SLA Policy Edit) ---');
    const url23 = `${BASE_URL}/admin/sla/tier/starter/edit`;
    log(`Requesting URL: ${url23}`);
    const resp23 = await page.goto(url23, { waitUntil: 'domcontentloaded' }).catch(e => null);
    log(`HTTP Status: ${resp23 ? resp23.status() : 'N/A'}`);
    log(`Final URL: ${page.url()}`);
    await page.screenshot({ path: 'screenshots/debug/23_before.png' });
    log('Saved debug screenshot: screenshots/debug/23_before.png');

    // --- FIGURE 27 BEFORE REPRODUCTION ---
    log('\n--- Reproducing Figure 27 (AI Assistant Response) ---');
    const url27 = `${BASE_URL}/admin/ai/chat-page`;
    log(`Requesting URL: ${url27}`);
    const resp27 = await page.goto(url27, { waitUntil: 'domcontentloaded' }).catch(e => null);
    log(`HTTP Status: ${resp27 ? resp27.status() : 'N/A'}`);
    log(`Final URL: ${page.url()}`);

    // Try filling prompt and clicking send or submitting
    log('Attempting AI prompt submission...');
    const fillRes = await page.evaluate(() => {
        const textarea = document.querySelector('textarea');
        if (!textarea) return 'No textarea found';
        textarea.value = 'Summarize real-time tenant performance and SLA compliance metrics.';
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
        return 'Filled prompt';
    });
    log(`Fill result: ${fillRes}`);

    await page.evaluate(() => {
        const form = document.querySelector('form');
        if (form) form.submit();
    });
    await page.waitForTimeout(2000);
    log(`URL after submit: ${page.url()}`);
    await page.screenshot({ path: 'screenshots/debug/27_before.png' });
    log('Saved debug screenshot: screenshots/debug/27_before.png');

    // --- FIGURE 30 BEFORE REPRODUCTION ---
    log('\n--- Reproducing Figure 30 (Tutorial Details) ---');
    const url30 = `${BASE_URL}/admin/help/1`;
    log(`Requesting URL: ${url30}`);
    const resp30 = await page.goto(url30, { waitUntil: 'domcontentloaded' }).catch(e => null);
    log(`HTTP Status: ${resp30 ? resp30.status() : 'N/A'}`);
    log(`Final URL: ${page.url()}`);
    await page.screenshot({ path: 'screenshots/debug/30_before.png' });
    log('Saved debug screenshot: screenshots/debug/30_before.png');

    await browser.close();
    process.exit(0);
})();
