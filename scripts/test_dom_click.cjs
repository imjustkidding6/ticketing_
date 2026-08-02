const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');

const BASE_URL = process.env.APP_URL || 'http://ticketing-nginx';

(async () => {
    console.log('=== Testing DOM Click on Conversations Sidebar ===');
    const browser = await chromium.launch({
        executablePath: '/usr/bin/chromium',
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    const context = await browser.newContext({ viewport: { width: 1920, height: 1080 } });
    const page = await context.newPage();

    page.on('console', msg => console.log(`[BROWSER CONSOLE] ${msg.type()}: ${msg.text()}`));

    console.log('1. Logging in as Administrator...');
    await page.goto(`${BASE_URL}/admin/login`, { waitUntil: 'domcontentloaded' });
    await page.fill('input[name="email"]', 'admin@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForTimeout(1500);

    console.log('2. Navigating to AI Chat page...');
    await page.goto(`${BASE_URL}/admin/ai/chat-page`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1000);

    // Correct selector for conversation items in sidebar
    const convSelector = '[x-data="aiChatbotApp()"] div[class*="border-r"] .overflow-y-auto > div';

    console.log('3. Inspecting sidebar DOM items count...');
    const count = await page.locator(convSelector).count();
    console.log(`Found ${count} conversation rows in sidebar.`);

    for (let i = 0; i < Math.min(count, 5); i++) {
        const text = await page.locator(convSelector).nth(i).innerText();
        const classes = await page.locator(convSelector).nth(i).getAttribute('class');
        console.log(`Item ${i}: "${text.replace(/\n+/g, ' ')}" | Classes: ${classes}`);
    }

    console.log('\n4. Clicking "New Chat" button...');
    await page.click('button:has-text("New Chat")');
    await page.waitForTimeout(1500);

    const postNewCount = await page.locator(convSelector).count();
    console.log(`Found ${postNewCount} conversation rows after "New Chat".`);

    for (let i = 0; i < Math.min(postNewCount, 5); i++) {
        const text = await page.locator(convSelector).nth(i).innerText();
        const classes = await page.locator(convSelector).nth(i).getAttribute('class');
        console.log(`Post-New Item ${i}: "${text.replace(/\n+/g, ' ')}" | Classes: ${classes}`);
    }

    console.log('\n5. Clicking on Item 1 (New AI Conversation)...');
    // First click Item 2 to make Item 1 unselected
    if (postNewCount > 1) {
        console.log('Clicking Item 2...');
        await page.locator(convSelector).nth(1).click();
        await page.waitForTimeout(800);
        console.log('Header Title after clicking Item 2:', await page.innerText('h2.text-xs.font-bold'));
    }

    console.log('Now clicking Item 0 (Newly created chat)...');
    await page.locator(convSelector).nth(0).click();
    await page.waitForTimeout(800);

    console.log('Header Title after clicking Item 0:', await page.innerText('h2.text-xs.font-bold'));

    await browser.close();
})();
