const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');

const BASE_URL = process.env.APP_URL || 'http://ticketing-nginx';

(async () => {
    console.log('=== PHASE 2: Reproducing the Original Bug on Restored UI Layout ===');
    const browser = await chromium.launch({
        executablePath: '/usr/bin/chromium',
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    const context = await browser.newContext({ viewport: { width: 1920, height: 1080 } });
    const page = await context.newPage();

    const consoleLogs = [];
    const networkRequests = [];

    page.on('console', msg => consoleLogs.push(`[CONSOLE ${msg.type()}] ${msg.text()}`));
    page.on('response', res => networkRequests.push(`[XHR ${res.status()}] ${res.url()}`));

    console.log('1. Logging in as Administrator...');
    await page.goto(`${BASE_URL}/admin/login`, { waitUntil: 'domcontentloaded' });
    await page.fill('input[name="email"]', 'admin@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForTimeout(1500);

    console.log('2. Navigating to Admin -> AI Assistant Chat (/admin/ai/chat-page)...');
    await page.goto(`${BASE_URL}/admin/ai/chat-page`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1000);

    const convSelector = '[x-data="aiChatbotApp()"] div[class*="border-r"] .overflow-y-auto > div';

    console.log('\n3. Inspecting Initial Alpine State...');
    const initialState = await page.evaluate(() => {
        const root = document.querySelector('[x-data="aiChatbotApp()"]');
        if (window.Alpine && root) {
            const d = Alpine.$data(root);
            return {
                conversationsCount: d.conversations.length,
                conversations: d.conversations.slice(0, 3),
                activeConversation: d.activeConversation
            };
        }
        return null;
    });
    console.log('Initial Alpine State:', JSON.stringify(initialState, null, 2));

    console.log('\n4. Clicking "New Chat" button...');
    networkRequests.length = 0;
    await page.click('button:has-text("New Chat")');
    await page.waitForTimeout(1500);

    console.log('Network Requests triggered by "New Chat":', networkRequests);

    const stateAfterNewChat = await page.evaluate(() => {
        const root = document.querySelector('[x-data="aiChatbotApp()"]');
        if (window.Alpine && root) {
            const d = Alpine.$data(root);
            return {
                conversationsCount: d.conversations.length,
                newlyCreatedConv: d.conversations[0],
                activeConversation: d.activeConversation
            };
        }
        return null;
    });
    console.log('\nAlpine State After New Chat:', JSON.stringify(stateAfterNewChat, null, 2));

    console.log('\n5. Clicking an existing older conversation (Item 3 in sidebar) first to make it active...');
    await page.locator(convSelector).nth(2).click();
    await page.waitForTimeout(1000);

    const activeHeaderAfterSwitch = await page.innerText('h2.text-xs.font-bold');
    console.log(`Active Header Title after selecting older conversation: "${activeHeaderAfterSwitch}"`);

    console.log('\n6. Now attempting to click the newly created "New AI Conversation" item (Item 1 in sidebar list)...');
    networkRequests.length = 0;
    await page.locator(convSelector).nth(0).click();
    await page.waitForTimeout(1000);

    console.log('Network Requests triggered by clicking newly created conversation item:', networkRequests);

    const activeHeaderAfterClickingNewItem = await page.innerText('h2.text-xs.font-bold');
    console.log(`Active Header Title after clicking newly created conversation item: "${activeHeaderAfterClickingNewItem}"`);

    const stateAfterClickingNewItem = await page.evaluate(() => {
        const root = document.querySelector('[x-data="aiChatbotApp()"]');
        if (window.Alpine && root) {
            const d = Alpine.$data(root);
            return {
                activeConversationId: d.activeConversation ? d.activeConversation.id : null,
                activeConversationTitle: d.activeConversation ? d.activeConversation.title : null
            };
        }
        return null;
    });
    console.log('Alpine Active State after clicking newly created conversation item:', JSON.stringify(stateAfterClickingNewItem, null, 2));

    console.log('\n--- Console Logs Captured ---');
    console.log(consoleLogs.join('\n'));

    await browser.close();
})();
