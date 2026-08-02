const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');

const BASE_URL = process.env.APP_URL || 'http://ticketing-nginx';

(async () => {
    console.log('=== PHASE 1: Precise Reproduction of AI Chat "New Chat" Click Bug ===');
    const browser = await chromium.launch({
        executablePath: '/usr/bin/chromium',
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    const context = await browser.newContext({
        viewport: { width: 1920, height: 1080 }
    });

    const page = await context.newPage();

    const consoleLogs = [];
    const networkResponses = [];

    page.on('console', msg => {
        consoleLogs.push(`[CONSOLE ${msg.type()}] ${msg.text()}`);
    });

    page.on('response', response => {
        networkResponses.push(`[RESPONSE ${response.status()}] ${response.url()}`);
    });

    console.log('1. Logging in as Administrator...');
    await page.goto(`${BASE_URL}/admin/login`, { waitUntil: 'domcontentloaded' });
    await page.fill('input[name="email"]', 'admin@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForTimeout(1500);

    console.log('2. Navigating to Admin -> AI Assistant Chat (/admin/ai/chat-page)...');
    await page.goto(`${BASE_URL}/admin/ai/chat-page`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1500);

    const sidebarSelector = '[x-data="aiChatbotApp()"] > div:first-child';
    const convItemsSelector = `${sidebarSelector} .overflow-y-auto > div`;

    console.log('\n3. Inspecting initial sidebar conversation items...');
    const initialSidebarItems = await page.$$eval(convItemsSelector, els => els.map(e => ({
        text: e.innerText.trim().replace(/\n+/g, ' | '),
        classes: e.className
    })));
    console.log(`Initial Sidebar Conversations Count: ${initialSidebarItems.length}`);
    console.log('Initial items:', initialSidebarItems);

    // CONTROL TEST: Click an existing older conversation item in sidebar
    if (initialSidebarItems.length > 1) {
        console.log('\n--- CONTROL TEST: Clicking an existing older conversation in sidebar ---');
        console.log(`Clicking second existing item: "${initialSidebarItems[1].text}"...`);
        const preCount = networkResponses.length;
        await page.click(`${convItemsSelector}:nth-child(2)`);
        await page.waitForTimeout(1000);
        console.log(`Network responses triggered by clicking existing item: ${networkResponses.length - preCount}`);
        console.log('Recent responses:', networkResponses.slice(preCount));

        const activeTitle = await page.innerText('h2.text-xs.font-bold');
        console.log(`Header Title after clicking existing item: "${activeTitle}"`);
    }

    console.log('\n--- TEST FLOW: Clicking "New Chat" button ---');
    networkResponses.length = 0; // reset
    await page.click('button:has-text("New Chat")');
    await page.waitForTimeout(1500);

    console.log('Network responses after clicking "New Chat":');
    console.log(networkResponses);

    console.log('\nInspecting sidebar conversation list immediately after "New Chat"...');
    const postNewChatSidebarItems = await page.$$eval(convItemsSelector, els => els.map(e => ({
        text: e.innerText.trim().replace(/\n+/g, ' | '),
        classes: e.className
    })));
    console.log(`Updated Sidebar Count: ${postNewChatSidebarItems.length}`);
    console.log('Sidebar items after New Chat:', postNewChatSidebarItems);

    // Inspect Alpine JS internal state
    const alpineState = await page.evaluate(() => {
        const root = document.querySelector('[x-data]');
        if (window.Alpine && root) {
            const data = Alpine.$data(root);
            return {
                activeConversation: data.activeConversation,
                conversationsCount: data.conversations ? data.conversations.length : 0,
                conversations: data.conversations
            };
        }
        return null;
    });
    console.log('\nAlpine JS Internal State after New Chat:');
    console.log(JSON.stringify(alpineState, null, 2));

    console.log('\n--- CLICK TEST: Attempting to click the newly created "New AI Conversation" item ---');
    networkResponses.length = 0;

    console.log('Clicking the newly created item (first item in sidebar)...');
    await page.click(`${convItemsSelector}:first-child`);
    await page.waitForTimeout(1000);

    console.log(`Network requests triggered by clicking new chat item: ${networkResponses.length}`);
    console.log('Network responses:', networkResponses);

    const postClickActiveTitle = await page.innerText('h2.text-xs.font-bold');
    console.log(`Header Title after clicking new item: "${postClickActiveTitle}"`);

    console.log('\n--- Console Logs Captured ---');
    console.log(consoleLogs.join('\n'));

    await browser.close();
})();
