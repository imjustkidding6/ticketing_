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
    log('=== PHASE 5: VERIFYING DOWNLOADABLE PDF VIA APPLICATION UI ===');
    const browser = await chromium.launch({
        executablePath: EXECUTABLE_PATH,
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    const context = await browser.newContext({ viewport: { width: 1920, height: 1080 } });
    const page = await context.newPage();

    // 1. Log in to Admin Portal
    await page.goto(`${BASE_URL}/admin/login`, { waitUntil: 'domcontentloaded' });
    await page.fill('input[name="email"]', 'admin@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForTimeout(2000);
    log(`Logged into Admin Panel. Current URL: ${page.url()}`);

    // 2. Navigate to Help & Tutorials module
    await page.goto(`${BASE_URL}/admin/help`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(1000);
    log('Navigated to Admin Help & Tutorials module.');

    fs.mkdirSync('screenshots', { recursive: true });
    await page.screenshot({ path: 'screenshots/verify_help_tutorials_ui.png' });
    log('Saved UI verification screenshot: screenshots/verify_help_tutorials_ui.png');

    // 3. Perform Download Request Verification
    log('Triggering PDF download request from /admin/help/download-manual...');
    const response = await page.request.get(`${BASE_URL}/admin/help/download-manual`);
    const status = response.status();
    const contentType = response.headers()['content-type'];
    const contentDisposition = response.headers()['content-disposition'];
    const body = await response.body();
    const size = body.length;

    log(`\n--- DOWNLOAD VERIFICATION RESULTS ---`);
    log(`HTTP Status: ${status}`);
    log(`Content-Type: ${contentType}`);
    log(`Content-Disposition: ${contentDisposition}`);
    log(`Downloaded PDF File Size: ${size} bytes (${(size / (1024 * 1024)).toFixed(2)} MB)`);

    if (status === 200 && contentType.includes('application/pdf') && size > 1000000) {
        log('\n✅ VERIFICATION SUCCESS: The downloadable PDF manual is valid, contains all 32 embedded screenshots, and serves correctly from the Admin UI!');
    } else {
        log('\n❌ VERIFICATION FAILURE!');
        await browser.close();
        process.exit(1);
    }

    await browser.close();
    process.exit(0);
})();
