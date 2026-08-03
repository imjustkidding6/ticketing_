const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');

const BASE_URL = process.env.APP_URL || 'http://ticketing-nginx';

(async () => {
    console.log('=== Testing AI Chat Full Workflow: Create, Send Message, Switch Conversations ===');
    const browser = await chromium.launch({
        executablePath: '/usr/bin/chromium',
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    const context = await browser.newContext({ viewport: { width: 1920, height: 1080 } });
    const page = await context.newPage();

    page.on('console', msg => console.log(`[BROWSER CONSOLE ${msg.type()}] ${msg.text()}`));
    page.on('response', res => {
        if (res.url().includes('/admin/ai/chatbot')) {
            console.log(`[XHR RESPONSE ${res.status()}] ${res.url()}`);
        }
    });

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

    console.log('\n3. Step A: Clicking "New Chat" button...');
    await page.click('button:has-text("New Chat")');
    await page.waitForTimeout(1500);

    console.log('Step B: Sending message in newly created chat...');
    await page.fill('textarea[placeholder*="Send a message"]', 'Test Message 1 in New Chat');
    await page.keyboard.press('Enter');
    await page.waitForTimeout(3000); // wait for AI response

    const messagesCount1 = await page.locator('[x-data="aiChatbotApp()"] div[class*="flex-1"] p').count();
    console.log(`Messages visible in Feed after Send: ${messagesCount1}`);
    const firstMsgText = await page.locator('[x-data="aiChatbotApp()"] div[class*="flex-1"] p').first().innerText().catch(() => '');
    console.log(`First message text: "${firstMsgText}"`);

    console.log('\n4. Step C: Clicking "New Chat" button AGAIN to create a 2nd new chat...');
    await page.click('button:has-text("New Chat")');
    await page.waitForTimeout(1500);

    await page.fill('textarea[placeholder*="Send a message"]', 'Test Message 2 in Chat 2');
    await page.keyboard.press('Enter');
    await page.waitForTimeout(3000); // wait for AI response

    const messagesCount2 = await page.locator('[x-data="aiChatbotApp()"] div[class*="flex-1"] p').count();
    console.log(`Messages visible in Feed for Chat 2: ${messagesCount2}`);

    console.log('\n5. Step D: Switching back to Chat 1 (Item 2 in sidebar list)...');
    console.log('Sidebar item 2 text:', await page.locator(convSelector).nth(1).innerText().catch(() => ''));

    await page.locator(convSelector).nth(1).click();
    await page.waitForTimeout(1500);

    console.log('Header Title after clicking Chat 1:', await page.innerText('h2.text-xs.font-bold'));
    const messagesCountAfterSwitch = await page.locator('[x-data="aiChatbotApp()"] div[class*="flex-1"] p').count();
    console.log(`Messages visible in Feed after switching back to Chat 1: ${messagesCountAfterSwitch}`);
    const switchedMsgText = await page.locator('[x-data="aiChatbotApp()"] div[class*="flex-1"] p').first().innerText().catch(() => '');
    console.log(`Switched message text: "${switchedMsgText}"`);

    await browser.close();
})();
