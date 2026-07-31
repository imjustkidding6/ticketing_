import { chromium } from '@playwright/test';
import path from 'path';
import fs from 'fs';

process.env.LD_LIBRARY_PATH = `/tmp/chromelibs/usr/lib/x86_64-linux-gnu:${process.env.LD_LIBRARY_PATH || ''}`;

const BASE_URL = process.env.APP_URL || 'http://127.0.0.1:8005';
const EXECUTABLE_PATH = '/home/anyang/.cache/puppeteer/chrome/linux-151.0.7922.47/chrome-linux64/chrome';

(async () => {
    console.log('Testing Admin Login and Navigation...');
    const browser = await chromium.launch({
        executablePath: EXECUTABLE_PATH,
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    const context = await browser.newContext({ viewport: { width: 1920, height: 1080 } });
    const page = await context.newPage();

    await page.goto(`${BASE_URL}/admin/login`);
    console.log('At login page:', await page.url());

    await page.fill('#email', 'admin@example.com');
    await page.fill('#password', 'password');
    
    await Promise.all([
        page.waitForURL(`${BASE_URL}/admin**`, { timeout: 10000 }).catch(e => console.log('waitForURL note:', e.message)),
        page.click('button[type="submit"]')
    ]);
    
    await page.waitForTimeout(2000);
    console.log('After login click URL:', await page.url());

    await browser.close();
    process.exit(0);
})();
