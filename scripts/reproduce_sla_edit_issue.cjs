const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');

const BASE_URL = process.env.APP_URL || 'http://ticketing-nginx';

(async () => {
    console.log('--- PHASE 1: Reproducing SLA Tier Edit Issue ---');
    const browser = await chromium.launch({
        executablePath: '/usr/bin/chromium',
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    const context = await browser.newContext({
        viewport: { width: 1920, height: 1080 }
    });

    const page = await context.newPage();

    const consoleLogs = [];
    const networkLogs = [];

    page.on('console', msg => {
        consoleLogs.push(`[CONSOLE ${msg.type()}] ${msg.text()}`);
    });

    page.on('response', response => {
        networkLogs.push(`[RESPONSE] ${response.status()} ${response.url()}`);
    });

    console.log('1. Logging in as Administrator...');
    await page.goto(`${BASE_URL}/admin/login`, { waitUntil: 'domcontentloaded' });
    await page.fill('input[name="email"]', 'admin@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForTimeout(1500);

    console.log('2. Navigating to SLA Registry page (/admin/sla)...');
    const indexResponse = await page.goto(`${BASE_URL}/admin/sla`, { waitUntil: 'domcontentloaded' });
    console.log(`SLA Index Page Status: ${indexResponse.status()}`);

    // Inspect edit links on the page
    const editLinks = await page.$$eval('a[href*="edit"]', els => els.map(e => ({ href: e.href, text: e.innerText.trim() })));
    console.log('Found SLA edit links on SLA index page:', JSON.stringify(editLinks, null, 2));

    console.log('\n3. Testing navigation to /admin/sla/tier/starter/edit (used in screenshot script)...');
    const starterResponse = await page.goto(`${BASE_URL}/admin/sla/tier/starter/edit`, { waitUntil: 'domcontentloaded' }).catch(e => null);
    console.log(`URL: ${page.url()}`);
    console.log(`HTTP Status Code for starter: ${starterResponse ? starterResponse.status() : 'Error'}`);
    const starterTitle = await page.title();
    console.log(`Page Title: ${starterTitle}`);

    const errorScreenshotPath = path.resolve('public/docs/screenshots/27_edit_sla_policy_repro.png');
    await page.screenshot({ path: errorScreenshotPath, fullPage: false });
    console.log(`Saved error screenshot to: ${errorScreenshotPath}`);

    console.log('\n4. Testing navigation to /admin/sla/tier/basic/edit...');
    const basicResponse = await page.goto(`${BASE_URL}/admin/sla/tier/basic/edit`, { waitUntil: 'domcontentloaded' }).catch(e => null);
    console.log(`URL: ${page.url()}`);
    console.log(`HTTP Status Code for basic: ${basicResponse ? basicResponse.status() : 'Error'}`);
    console.log(`Page Title: ${await page.title()}`);

    console.log('\n5. Testing navigation to /admin/sla/tier/enterprise/edit...');
    const entResponse = await page.goto(`${BASE_URL}/admin/sla/tier/enterprise/edit`, { waitUntil: 'domcontentloaded' }).catch(e => null);
    console.log(`URL: ${page.url()}`);
    console.log(`HTTP Status Code for enterprise: ${entResponse ? entResponse.status() : 'Error'}`);
    console.log(`Page Title: ${await page.title()}`);

    console.log('\n--- Console Logs ---');
    console.log(consoleLogs.join('\n'));

    console.log('\n--- Network Logs (Last 10) ---');
    console.log(networkLogs.slice(-10).join('\n'));

    await browser.close();
})();
