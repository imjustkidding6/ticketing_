const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');

const BASE_URL = process.env.APP_URL || 'http://ticketing-nginx';

(async () => {
    console.log('=== PHASE 1: Verifying Restored Original AI Assistant UI Layout ===');
    const browser = await chromium.launch({
        executablePath: '/usr/bin/chromium',
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    const context = await browser.newContext({ viewport: { width: 1920, height: 1080 } });
    const page = await context.newPage();

    console.log('1. Logging in as Administrator...');
    await page.goto(`${BASE_URL}/admin/login`, { waitUntil: 'domcontentloaded' });
    await page.fill('input[name="email"]', 'admin@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForTimeout(1500);

    console.log('2. Navigating to Admin -> AI Assistant Chat (/admin/ai/chat-page)...');
    await page.goto(`${BASE_URL}/admin/ai/chat-page`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1500);

    console.log('3. Capturing Restored UI screenshot...');
    await page.screenshot({ path: 'public/docs/screenshots/ai_chat_restored_ui_original.png', fullPage: true });

    // Inspect layout bounding boxes
    const layoutDetails = await page.evaluate(() => {
        const root = document.querySelector('[x-data="aiChatbotApp()"]');
        if (!root) return null;
        const rootRect = root.getBoundingClientRect();
        const sidebar = root.children[0];
        const sidebarRect = sidebar ? sidebar.getBoundingClientRect() : null;
        const mainPanel = root.children[1];
        const mainRect = mainPanel ? mainPanel.getBoundingClientRect() : null;
        return {
            root: { width: rootRect.width, height: rootRect.height },
            sidebar: sidebarRect ? { width: sidebarRect.width, height: sidebarRect.height } : null,
            mainPanel: mainRect ? { width: mainRect.width, height: mainRect.height } : null
        };
    });

    console.log('Restored UI Layout Bounds:', JSON.stringify(layoutDetails, null, 2));

    await browser.close();
})();
