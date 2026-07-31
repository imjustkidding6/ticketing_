import puppeteer from 'puppeteer';
import { chromium } from '@playwright/test';
import path from 'path';
import fs from 'fs';

const BASE_URL = process.env.APP_URL || 'http://127.0.0.1:8005';

const edgePaths = [
    'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
    'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
    'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
    'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe'
];

let executablePath = null;
for (const p of edgePaths) {
    if (fs.existsSync(p)) {
        executablePath = p;
        break;
    }
}

console.log('Using browser path:', executablePath);

(async () => {
    try {
        const puppeteerBrowser = await puppeteer.launch({
            executablePath: executablePath,
            headless: 'new',
            args: ['--no-sandbox', '--window-size=1920,1080', '--force-color-profile=srgb']
        });

        const endpoint = puppeteerBrowser.wsEndpoint();
        console.log('CDP Endpoint:', endpoint);

        // Connect Playwright over CDP to the browser
        const playwrightBrowser = await chromium.connectOverCDP(endpoint);
        console.log('Playwright successfully connected over CDP!');

        const context = playwrightBrowser.contexts()[0];
        const page = await context.newPage();
        await page.setViewportSize({ width: 1920, height: 1080 });

        await page.goto(`${BASE_URL}/admin/login`, { waitUntil: 'networkidle' });
        console.log('Playwright navigated to login page. Title:', await page.title());

        fs.mkdirSync('screenshots', { recursive: true });
        await page.screenshot({ path: 'screenshots/test_playwright_bridge.png' });
        console.log('Saved screenshots/test_playwright_bridge.png!');

        await page.close();
        await playwrightBrowser.close();
        await puppeteerBrowser.close();
        console.log('SUCCESS!');
        process.exit(0);
    } catch (err) {
        console.error('Error:', err);
        process.exit(1);
    }
})();
