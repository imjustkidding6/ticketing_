import { chromium } from '@playwright/test';
import path from 'path';
import fs from 'fs';

process.env.LD_LIBRARY_PATH = `/tmp/chromelibs/usr/lib/x86_64-linux-gnu:${process.env.LD_LIBRARY_PATH || ''}`;

const BASE_URL = process.env.APP_URL || 'http://127.0.0.1:8005';
const EXECUTABLE_PATH = '/home/anyang/.cache/puppeteer/chrome/linux-151.0.7922.47/chrome-linux64/chrome';

console.log('Testing Playwright launch with extracted libraries...');

(async () => {
    try {
        const browser = await chromium.launch({
            executablePath: EXECUTABLE_PATH,
            headless: true,
            args: ['--no-sandbox', '--disable-setuid-sandbox', '--force-color-profile=srgb']
        });
        const context = await browser.newContext({
            viewport: { width: 1920, height: 1080 }
        });
        const page = await context.newPage();

        await page.goto(`${BASE_URL}/admin/login`, { waitUntil: 'networkidle' });
        console.log('Successfully navigated! Page title:', await page.title());

        fs.mkdirSync('screenshots', { recursive: true });
        await page.screenshot({ path: 'screenshots/test_extracted_libs.png' });
        console.log('Successfully saved test screenshot screenshots/test_extracted_libs.png!');

        await browser.close();
        process.exit(0);
    } catch (err) {
        console.error('Error during Playwright launch:', err);
        process.exit(1);
    }
})();
