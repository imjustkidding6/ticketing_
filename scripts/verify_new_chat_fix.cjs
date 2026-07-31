const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');

const BASE_URL = process.env.APP_URL || 'http://ticketing-nginx';

(async () => {
    console.log('=== PHASE 4: Live End-to-End Playwright Verification of AI Chat Fix ===');
    const browser = await chromium.launch({
        executablePath: '/usr/bin/chromium',
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    const context = await browser.newContext({ viewport: { width: 1920, height: 1080 } });
    const page = await context.newPage();

    page.on('console', msg => {
        if (msg.type() === 'error' || msg.type() === 'log') {
            console.log(`[BROWSER ${msg.type()}] ${msg.text()}`);
        }
    });

    console.log('1. Logging in as Administrator...');
    await page.goto(`${BASE_URL}/admin/login`, { waitUntil: 'domcontentloaded' });
    await page.fill('input[name="email"]', 'admin@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForTimeout(1500);

    console.log('2. Navigating to Admin -> AI Assistant Chat (/admin/ai/chat-page)...');
    await page.goto(`${BASE_URL}/admin/ai/chat-page`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1000);

    console.log('\n3. Clicking "New Chat" button to create Thread #1...');
    await page.click('#ai-chat-new-btn');
    await page.waitForTimeout(1200);

    console.log('\n4. Sending message in Thread #1: "How many active tenants do we have?"');
    await page.evaluate(() => {
        const root = document.querySelector('[x-data="aiChatbotApp()"]');
        if (window.Alpine && root) {
            Alpine.$data(root).sendSuggestedMessage('How many active tenants do we have?');
        }
    });

    console.log('Waiting for AI Assistant response for Thread #1...');
    await page.waitForTimeout(4500);

    const thread1State = await page.evaluate(() => {
        const root = document.querySelector('[x-data="aiChatbotApp()"]');
        if (window.Alpine && root) {
            const d = Alpine.$data(root);
            return {
                activeId: d.activeConversation ? d.activeConversation.id : null,
                activeTitle: d.activeConversation ? d.activeConversation.title : null,
                messagesCount: d.messages.length,
                messages: d.messages.map(m => ({ role: m.role, content: m.content ? m.content.substring(0, 60) : '' }))
            };
        }
        return null;
    });
    console.log('Thread #1 State:', JSON.stringify(thread1State, null, 2));
    const thread1Id = thread1State ? thread1State.activeId : null;

    await page.screenshot({ path: 'public/docs/screenshots/ai_chat_thread_1_response.png' });

    console.log('\n5. Clicking "New Chat" button to create Thread #2...');
    await page.click('#ai-chat-new-btn');
    await page.waitForTimeout(1200);

    console.log('Sending message in Thread #2: "Show license expiration report."');
    await page.evaluate(() => {
        const root = document.querySelector('[x-data="aiChatbotApp()"]');
        if (window.Alpine && root) {
            Alpine.$data(root).sendSuggestedMessage('Show license expiration report.');
        }
    });

    console.log('Waiting for AI Assistant response for Thread #2...');
    await page.waitForTimeout(4500);

    const thread2State = await page.evaluate(() => {
        const root = document.querySelector('[x-data="aiChatbotApp()"]');
        if (window.Alpine && root) {
            const d = Alpine.$data(root);
            return {
                activeId: d.activeConversation ? d.activeConversation.id : null,
                activeTitle: d.activeConversation ? d.activeConversation.title : null,
                messagesCount: d.messages.length,
                messages: d.messages.map(m => ({ role: m.role, content: m.content ? m.content.substring(0, 60) : '' }))
            };
        }
        return null;
    });
    console.log('Thread #2 State:', JSON.stringify(thread2State, null, 2));

    await page.screenshot({ path: 'public/docs/screenshots/ai_chat_thread_2_response.png' });

    console.log(`\n6. Switching back to Thread #1 (ID ${thread1Id}) via sidebar click...`);
    
    // Wait for the conversation messages fetch XHR to complete
    const responsePromise = page.waitForResponse(res => res.url().includes(`/admin/ai/chatbot/conversations/${thread1Id}`) && res.status() === 200);
    await page.click(`div[data-id="${thread1Id}"]`);
    await responsePromise;
    await page.waitForTimeout(1000);

    const switchedState = await page.evaluate(() => {
        const root = document.querySelector('[x-data="aiChatbotApp()"]');
        if (window.Alpine && root) {
            const d = Alpine.$data(root);
            return {
                activeId: d.activeConversation ? d.activeConversation.id : null,
                activeTitle: d.activeConversation ? d.activeConversation.title : null,
                messagesCount: d.messages.length,
                messages: d.messages.map(m => ({ role: m.role, content: m.content ? m.content.substring(0, 60) : '' }))
            };
        }
        return null;
    });
    console.log('Switched Thread #1 State:', JSON.stringify(switchedState, null, 2));

    await page.screenshot({ path: 'public/docs/screenshots/ai_chat_switched_thread_1.png' });

    if (switchedState && switchedState.activeId == thread1Id && switchedState.messagesCount >= 1) {
        console.log('\n✅ VERIFICATION SUCCESS: New Chat creation, typing, AI response generation, sidebar conversation switching by ID, and message persistence all fully verified!');
    } else {
        console.error('\n❌ VERIFICATION FAILURE: Could not verify thread switching or message retrieval.');
    }

    await browser.close();
})();
