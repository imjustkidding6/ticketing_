const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');

const BASE_URL = process.env.APP_URL || 'http://ticketing-nginx';

(async () => {
    console.log('=== User Scenario Test: New Chat -> Switch Away -> Click New Chat Item ===');
    const browser = await chromium.launch({
        executablePath: '/usr/bin/chromium',
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    const context = await browser.newContext({ viewport: { width: 1920, height: 1080 } });
    const page = await context.newPage();

    page.on('console', msg => console.log(`[BROWSER CONSOLE ${msg.type()}] ${msg.text()}`));

    console.log('1. Logging in as Administrator...');
    await page.goto(`${BASE_URL}/admin/login`, { waitUntil: 'domcontentloaded' });
    await page.fill('input[name="email"]', 'admin@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForTimeout(1500);

    console.log('2. Navigating to AI Chat page...');
    await page.goto(`${BASE_URL}/admin/ai/chat-page`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1000);

    const convSelector = '[x-data="aiChatbotApp()"] div[class*="border-r"] .overflow-y-auto > div';

    console.log('3. Clicking "New Chat" button...');
    await page.click('button:has-text("New Chat")');
    await page.waitForTimeout(1500);

    let activeTitle = await page.innerText('h2.text-xs.font-bold');
    console.log(`Active Conversation Title after New Chat: "${activeTitle}"`);

    console.log('4. Clicking an older conversation item (Item 3 in sidebar)...');
    await page.locator(convSelector).nth(2).click();
    await page.waitForTimeout(1000);

    activeTitle = await page.innerText('h2.text-xs.font-bold');
    console.log(`Active Conversation Title after clicking Item 3: "${activeTitle}"`);

    console.log('5. Clicking the newly created conversation item (Item 1 in sidebar)...');
    const item1Text = await page.locator(convSelector).nth(0).innerText();
    console.log(`Text of Item 1: "${item1Text.replace(/\n+/g, ' ')}"`);

    await page.locator(convSelector).nth(0).click();
    await page.waitForTimeout(1000);

    activeTitle = await page.innerText('h2.text-xs.font-bold');
    console.log(`Active Conversation Title after clicking Item 1: "${activeTitle}"`);

    // Also test clicking the inner text span or icon of Item 1
    console.log('\nTesting clicking inner span of Item 1 directly...');
    await page.locator(convSelector).nth(0).locator('span').first().click();
    await page.waitForTimeout(1000);
    console.log(`Active Conversation Title after clicking inner span: "${await page.innerText('h2.text-xs.font-bold')}"`);

    await browser.close();
})();
