import { chromium } from '@playwright/test';
import path from 'path';
import fs from 'fs';

process.env.LD_LIBRARY_PATH = `/tmp/chromelibs/usr/lib/x86_64-linux-gnu:${process.env.LD_LIBRARY_PATH || ''}`;

const BASE_URL = process.env.APP_URL || 'http://127.0.0.1:8005';
const EXECUTABLE_PATH = '/home/anyang/.cache/puppeteer/chrome/linux-151.0.7922.47/chrome-linux64/chrome';

(async () => {
    console.log('Testing form submit via evaluate...');
    const browser = await chromium.launch({
        executablePath: EXECUTABLE_PATH,
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    const page = await browser.newPage();
    await page.setViewportSize({ width: 1920, height: 1080 });

    await page.goto(`${BASE_URL}/admin/login`, { waitUntil: 'commit' });
    await page.waitForTimeout(500);
    console.log('Opened login page. URL:', page.url());

    await page.fill('#email', 'admin@example.com');
    await page.fill('#password', 'password');
    console.log('Filled email & password.');

    await page.evaluate(() => document.querySelector('form').submit());
    console.log('Submitted form via JS evaluate!');

    await page.waitForTimeout(2000);
    console.log('Current URL after submit:', page.url());

    fs.mkdirSync('screenshots', { recursive: true });
    await page.screenshot({ path: 'screenshots/02_login_success.png' });
    console.log('Saved screenshots/02_login_success.png!');

    await browser.close();
    console.log('SUCCESS!');
    process.exit(0);
})();
