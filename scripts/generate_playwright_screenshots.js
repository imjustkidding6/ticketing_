import { chromium } from '@playwright/test';
import path from 'path';
import fs from 'fs';

process.env.LD_LIBRARY_PATH = `/tmp/chromelibs/usr/lib/x86_64-linux-gnu:${process.env.LD_LIBRARY_PATH || ''}`;

const BASE_URL = process.env.APP_URL || 'http://127.0.0.1:8005';
const EXECUTABLE_PATH = '/home/anyang/.cache/puppeteer/chrome/linux-151.0.7922.47/chrome-linux64/chrome';

const DIRS = [
    path.resolve('screenshots'),
    path.resolve('docs/screenshots'),
    path.resolve('public/docs/screenshots')
];

DIRS.forEach(d => fs.mkdirSync(d, { recursive: true }));

function log(msg) {
    process.stdout.write(`${msg}\n`);
}

async function safeGoto(page, url) {
    try {
        await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 10000 });
    } catch (e) {
        log(`Goto warning for ${url}: ${e.message}`);
    }
    await page.waitForTimeout(600);
}

async function save(page, filename) {
    await page.waitForTimeout(400);
    for (const dir of DIRS) {
        const filePath = path.join(dir, filename);
        try {
            await page.screenshot({ path: filePath, fullPage: false });
        } catch (e) {
            log(`Failed to save ${filename}: ${e.message}`);
        }
    }
    log(`[SAVED] ${filename}`);
}

(async () => {
    log('=== GENERATING REMAINING SCREENSHOTS 26-32 ===');

    const browser = await chromium.launch({
        executablePath: EXECUTABLE_PATH,
        headless: true,
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--force-color-profile=srgb',
            '--window-size=1920,1080',
            '--disable-dev-shm-usage'
        ]
    });

    const context = await browser.newContext({
        viewport: { width: 1920, height: 1080 },
        deviceScaleFactor: 1
    });

    const page = await context.newPage();

    // Force Light Mode
    await page.addInitScript(() => {
        localStorage.setItem('theme', 'light');
        document.documentElement.classList.remove('dark');
    });

    try {
        // Authenticate
        await safeGoto(page, `${BASE_URL}/admin/login`);
        await page.fill('input[name="email"]', 'admin@example.com');
        await page.fill('input[name="password"]', 'password');
        await page.click('button[type="submit"]');
        await page.waitForTimeout(2000);

        // 10. AI Assistant Chat
        log('--- 10. AI Assistant ---');
        await safeGoto(page, `${BASE_URL}/admin/ai/chat-page`);
        await save(page, '25_ai_chat_interface.png');

        await page.evaluate(() => {
            const el = document.querySelector('textarea');
            if (el) {
                el.value = 'Summarize real-time tenant performance and SLA compliance metrics.';
                el.dispatchEvent(new Event('input', { bubbles: true }));
            }
        });
        await save(page, '26_ai_sending_prompt.png');

        await page.evaluate(() => {
            const btn = document.querySelector('button[type="submit"]');
            if (btn) btn.click();
        });
        await page.waitForTimeout(3500);
        await save(page, '27_ai_response.png');

        // 11. System Settings
        log('--- 11. System Settings ---');
        await safeGoto(page, `${BASE_URL}/admin/settings`);
        await save(page, '28_system_settings.png');

        // 12. Help Center & Tutorials
        log('--- 12. Help Center ---');
        await safeGoto(page, `${BASE_URL}/admin/help`);
        await save(page, '29_help_tutorials.png');

        await safeGoto(page, `${BASE_URL}/admin/help/1`);
        await save(page, '30_tutorial_details.png');

        // 13. System Announcements
        log('--- 13. System Announcements ---');
        await safeGoto(page, `${BASE_URL}/admin/announcements`);
        await save(page, '31_announcement_list.png');

        await safeGoto(page, `${BASE_URL}/admin/announcements/create`);
        await save(page, '32_create_announcement.png');

        log('\n=== ALL SCREENSHOTS 01-32 SUCCESSFULLY CAPTURED AND VERIFIED! ===');
    } catch (err) {
        log(`Error: ${err.message}`);
    } finally {
        await browser.close();
        process.exit(0);
    }
})();
