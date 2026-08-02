const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');

const BASE_URL = process.env.APP_URL || 'http://ticketing-nginx';

(async () => {
    console.log('--- Phase 4 & 5: Verifying Fix and Capturing New 27_edit_sla_policy.png ---');
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

    console.log('2. Testing SLA tier edit URLs...');

    // Test starter (alias mapped)
    const starterRes = await page.goto(`${BASE_URL}/admin/sla/tier/starter/edit`, { waitUntil: 'domcontentloaded' });
    console.log(`Starter Tier Edit URL HTTP Status: ${starterRes.status()}`);
    if (starterRes.status() !== 200) {
        throw new Error(`Starter tier edit returned status ${starterRes.status()}`);
    }

    // Test enterprise
    const entRes = await page.goto(`${BASE_URL}/admin/sla/tier/enterprise/edit`, { waitUntil: 'domcontentloaded' });
    console.log(`Enterprise Tier Edit URL HTTP Status: ${entRes.status()}`);
    if (entRes.status() !== 200) {
        throw new Error(`Enterprise tier edit returned status ${entRes.status()}`);
    }

    await page.waitForTimeout(1000);

    // Save image to all required asset locations
    const targets = [
        path.resolve('public/docs/screenshots/27_edit_sla_policy.png'),
        path.resolve('docs/screenshots/27_edit_sla_policy.png'),
        path.resolve('screenshots/27_edit_sla_policy.png')
    ];

    for (const targetPath of targets) {
        const dir = path.dirname(targetPath);
        if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });
        await page.screenshot({ path: targetPath, fullPage: false });
        const size = fs.statSync(targetPath).size;
        console.log(`✓ Overwrote screenshot asset: ${targetPath} (${size} bytes)`);
    }

    await browser.close();
    console.log('✓ Successfully captured fresh, working 27_edit_sla_policy.png screenshot!');
})();
