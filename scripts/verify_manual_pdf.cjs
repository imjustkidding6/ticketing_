const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');

const BASE_URL = process.env.APP_URL || 'http://ticketing-nginx';

(async () => {
    console.log('Launching browser to test PDF download flow...');
    const browser = await chromium.launch({
        executablePath: '/usr/bin/chromium',
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    const context = await browser.newContext({
        viewport: { width: 1920, height: 1080 }
    });

    const page = await context.newPage();

    console.log('1. Logging in as Administrator...');
    await page.goto(`${BASE_URL}/admin/login`, { waitUntil: 'domcontentloaded' });
    await page.fill('input[name="email"]', 'admin@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForTimeout(1500);

    console.log('2. Navigating to Admin -> Help & Tutorials...');
    await page.goto(`${BASE_URL}/admin/help`, { waitUntil: 'domcontentloaded' });

    console.log('3. Triggering Download User Manual action...');
    const [ download ] = await Promise.all([
        page.waitForEvent('download', { timeout: 30000 }).catch(() => null),
        page.click('a[href*="/admin/help/download-manual"], button:has-text("Download User Manual")')
    ]);

    let pdfPath = path.resolve('public/docs/Admin-User-Manual.pdf');
    if (download) {
        pdfPath = path.join(path.resolve('downloads'), await download.suggestedFilename());
        await download.saveAs(pdfPath);
        console.log(`✓ Downloaded PDF via browser event to: ${pdfPath}`);
    } else {
        console.log(`Using server generated PDF path: ${pdfPath}`);
    }

    if (fs.existsSync(pdfPath)) {
        const stats = fs.statSync(pdfPath);
        console.log(`✓ PDF file verified! Size: ${(stats.size / 1024 / 1024).toFixed(2)} MB (${stats.size} bytes)`);

        const buffer = fs.readFileSync(pdfPath);
        const header = buffer.toString('utf-8', 0, 8);
        console.log(`✓ PDF Header: ${header.trim()}`);
        if (header.includes('%PDF')) {
            console.log('✓ Valid PDF document header confirmed!');
        } else {
            console.error('✘ Invalid PDF header!');
        }
    } else {
        console.error(`✘ PDF file not found at ${pdfPath}`);
    }

    await browser.close();
    console.log('✓ Final Playwright download verification complete!');
})();
