const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');

const BASE_URL = process.env.APP_URL || 'http://ticketing-nginx';

(async () => {
    console.log('=== PHASE 2: Deep Diagnostics of AI Chat Alpine State & Click Binding ===');
    const browser = await chromium.launch({
        executablePath: '/usr/bin/chromium',
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    const context = await browser.newContext({ viewport: { width: 1920, height: 1080 } });
    const page = await context.newPage();

    const consoleLogs = [];
    const networkResponses = [];

    page.on('console', msg => consoleLogs.push(`[CONSOLE ${msg.type()}] ${msg.text()}`));
    page.on('response', res => networkResponses.push(`[RESPONSE ${res.status()}] ${res.url()}`));

    console.log('1. Logging in as Administrator...');
    await page.goto(`${BASE_URL}/admin/login`, { waitUntil: 'domcontentloaded' });
    await page.fill('input[name="email"]', 'admin@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForTimeout(1500);

    console.log('2. Navigating to Admin -> AI Assistant Chat (/admin/ai/chat-page)...');
    await page.goto(`${BASE_URL}/admin/ai/chat-page`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1000);

    console.log('\n3. Inspecting Alpine aiChatbotApp() Initial State...');
    const initialState = await page.evaluate(() => {
        const root = document.querySelector('[x-data="aiChatbotApp()"]');
        if (window.Alpine && root) {
            const d = Alpine.$data(root);
            return {
                conversations: d.conversations,
                activeConversation: d.activeConversation,
                messagesCount: d.messages.length
            };
        }
        return null;
    });
    console.log('Initial Alpine State:', JSON.stringify(initialState, null, 2));

    console.log('\n4. Triggering createNewChat() via button click...');
    networkResponses.length = 0;
    await page.click('button:has-text("New Chat")');
    await page.waitForTimeout(1500);

    console.log('Network responses after createNewChat():', networkResponses);

    const postCreateState = await page.evaluate(() => {
        const root = document.querySelector('[x-data="aiChatbotApp()"]');
        if (window.Alpine && root) {
            const d = Alpine.$data(root);
            return {
                conversations: d.conversations,
                activeConversation: d.activeConversation,
                messagesCount: d.messages.length
            };
        }
        return null;
    });
    console.log('\nAlpine State after createNewChat():', JSON.stringify(postCreateState, null, 2));

    console.log('\n5. Inspecting DOM elements in sidebar list...');
    const sidebarDomItems = await page.$$eval('[x-data="aiChatbotApp()"] .w-80 .overflow-y-auto > div', els => els.map((e, idx) => ({
        index: idx,
        text: e.innerText.trim().replace(/\n+/g, ' | '),
        classes: e.className,
        hasClick: !!e.getAttribute('@click') || !!e.getAttribute('x-on:click')
    })));
    console.log('Sidebar DOM Items:', sidebarDomItems);

    console.log('\n6. Testing clicking each conversation item in sidebar directly via Alpine selectConversation()...');
    const selectResult = await page.evaluate(async () => {
        const root = document.querySelector('[x-data="aiChatbotApp()"]');
        if (window.Alpine && root) {
            const d = Alpine.$data(root);
            if (d.conversations.length > 0) {
                const firstConv = d.conversations[0];
                await d.selectConversation(firstConv);
                return {
                    selectedId: firstConv.id,
                    activeConvId: d.activeConversation ? d.activeConversation.id : null,
                    messagesCount: d.messages.length
                };
            }
        }
        return null;
    });
    console.log('Direct selectConversation(firstConv) result:', selectResult);

    console.log('\n7. Clicking the top conversation DOM element in sidebar...');
    networkResponses.length = 0;
    await page.click('[x-data="aiChatbotApp()"] .w-80 .overflow-y-auto > div:first-child');
    await page.waitForTimeout(1000);
    console.log('Network responses after clicking DOM element:', networkResponses);

    const postDomClickState = await page.evaluate(() => {
        const root = document.querySelector('[x-data="aiChatbotApp()"]');
        if (window.Alpine && root) {
            const d = Alpine.$data(root);
            return {
                activeConversation: d.activeConversation,
                messagesCount: d.messages.length
            };
        }
        return null;
    });
    console.log('Alpine State after clicking DOM element:', JSON.stringify(postDomClickState, null, 2));

    console.log('\n--- Console Logs ---');
    console.log(consoleLogs.join('\n'));

    await browser.close();
})();
