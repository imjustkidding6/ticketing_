import { chromium } from '@playwright/test';
import { exec } from 'child_process';
import path from 'path';
import fs from 'fs';

const BASE_URL = process.env.APP_URL || 'http://127.0.0.1:8005';

console.log('Testing Windows Edge launch via CDP...');

// Launch Edge with remote debugging port on Windows
const cmd = `cmd.exe /c "start /B "" "C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe" --remote-debugging-port=9222 --headless=new --user-data-dir="C:\\tmp\\edge_dev_profile" --window-size=1920,1080 --no-first-run --no-default-browser-check"`;

exec(cmd, async (err) => {
    if (err) console.error('Exec error:', err);
});

(async () => {
    // Wait for browser CDP server to start
    let connected = false;
    let browser;
    for (let i = 0; i < 10; i++) {
        try {
            await new Promise(r => setTimeout(r, 1000));
            browser = await chromium.connectOverCDP('http://127.0.0.1:9222');
            connected = true;
            console.log('Successfully connected to Edge via CDP!');
            break;
        } catch (e) {
            console.log(`Waiting for CDP port... attempt ${i + 1}`);
        }
    }

    if (!connected) {
        console.error('Failed to connect to CDP port 9222');
        process.exit(1);
    }

    const context = browser.contexts()[0];
    const page = await context.newPage();
    await page.setViewportSize({ width: 1920, height: 1080 });

    await page.goto(`${BASE_URL}/admin/login`, { waitUntil: 'networkidle' });
    console.log('Page title:', await page.title());

    fs.mkdirSync('screenshots', { recursive: true });
    await page.screenshot({ path: 'screenshots/test_login.png' });
    console.log('Successfully saved test screenshot!');

    await page.close();
    await browser.close();
    process.exit(0);
})();
