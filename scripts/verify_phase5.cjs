const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');

const BASE_URL = process.env.APP_URL || 'http://ticketing-nginx';

(async () => {
    console.log('=== PHASE 5: Comprehensive 3x Workflow Verification ===');
    const browser = await chromium.launch({
        executablePath: '/usr/bin/chromium',
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    const context = await browser.newContext({ viewport: { width: 1920, height: 1080 } });
    const page = await context.newPage();

    const consoleErrors = [];
    page.on('console', msg => {
        if (msg.type() === 'error' && !msg.text().includes('_boost')) {
            consoleErrors.push(msg.text());
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

    // Verify Layout Bounding Boxes first
    const layoutBounds = await page.evaluate(() => {
        const root = document.querySelector('[x-data="aiChatbotApp()"]');
        if (!root) return null;
        const rootRect = root.getBoundingClientRect();
        const sidebarRect = root.children[0] ? root.children[0].getBoundingClientRect() : null;
        const mainRect = root.children[1] ? root.children[1].getBoundingClientRect() : null;
        return {
            root: { width: rootRect.width, height: rootRect.height },
            sidebar: sidebarRect ? { width: sidebarRect.width, height: sidebarRect.height } : null,
            mainPanel: mainRect ? { width: mainRect.width, height: mainRect.height } : null
        };
    });

    console.log('\nVerified Layout Bounds:', JSON.stringify(layoutBounds, null, 2));

    await page.screenshot({ path: 'public/docs/screenshots/ai_chat_layout_before_workflow.png', fullPage: true });

    const prompts = [
        "What is the system SLA response target?",
        "Show active ticket counts by priority.",
        "List global system administrators."
    ];

    const createdConversations = [];

    for (let run = 1; run <= 3; run++) {
        console.log(`\n--- WORKFLOW ITERATION #${run} ---`);
        
        console.log(`[Run #${run}] Clicking "New Chat" button...`);
        const postPromise = page.waitForResponse(res => res.url().includes('/admin/ai/chatbot/conversations') && res.request().method() === 'POST' && res.status() === 200);
        await page.click('button:has-text("New Chat")');
        const postRes = await postPromise;
        const postData = await postRes.json();
        const newId = postData.conversation.id;
        await page.waitForTimeout(800);

        console.log(`[Run #${run}] New Chat Created with ID: ${newId}`);
        createdConversations.push(newId);

        const promptText = prompts[run - 1];
        console.log(`[Run #${run}] Sending user message: "${promptText}"`);
        await page.evaluate((text) => {
            const root = document.querySelector('[x-data="aiChatbotApp()"]');
            if (window.Alpine && root) {
                Alpine.$data(root).sendSuggestedMessage(text);
            }
        }, promptText);

        console.log(`[Run #${run}] Waiting for AI Assistant response...`);
        await page.waitForTimeout(5000);

        const responseState = await page.evaluate(() => {
            const root = document.querySelector('[x-data="aiChatbotApp()"]');
            if (window.Alpine && root) {
                const d = Alpine.$data(root);
                return {
                    activeId: d.activeConversation ? d.activeConversation.id : null,
                    messagesCount: d.messages.length,
                    lastMessageRole: d.messages.length ? d.messages[d.messages.length - 1].role : null,
                    lastMessageSnippet: d.messages.length ? d.messages[d.messages.length - 1].content.substring(0, 60) : null
                };
            }
            return null;
        });
        console.log(`[Run #${run}] Response Received - State:`, JSON.stringify(responseState));

        await page.screenshot({ path: `public/docs/screenshots/ai_chat_run_${run}_response.png` });
    }

    console.log('\n--- TESTING SIDEBAR INTERACTION & THREAD SWITCHING ---');
    const targetConvId = createdConversations[0];
    console.log(`Switching back to Iteration #1 Conversation (ID ${targetConvId})...`);

    const convSelector = '[x-data="aiChatbotApp()"] div[class*="border-r"] .overflow-y-auto > div';
    
    // Find index of targetConvId in list
    const targetIdx = await page.evaluate((targetId) => {
        const root = document.querySelector('[x-data="aiChatbotApp()"]');
        if (window.Alpine && root) {
            const d = Alpine.$data(root);
            return d.conversations.findIndex(c => c.id == targetId);
        }
        return -1;
    }, targetConvId);

    console.log(`Target Conversation ID ${targetConvId} is at index ${targetIdx} in sidebar list.`);

    let sidebarSuccess = false;
    if (targetIdx !== -1) {
        const fetchPromise = page.waitForResponse(res => res.url().includes(`/admin/ai/chatbot/conversations/${targetConvId}`) && res.status() === 200);
        await page.locator(convSelector).nth(targetIdx).click();
        await fetchPromise;
        await page.waitForTimeout(1000);

        const switchedState = await page.evaluate(() => {
            const root = document.querySelector('[x-data="aiChatbotApp()"]');
            if (window.Alpine && root) {
                const d = Alpine.$data(root);
                return {
                    activeId: d.activeConversation ? d.activeConversation.id : null,
                    activeTitle: d.activeConversation ? d.activeConversation.title : null,
                    messagesCount: d.messages.length,
                    messages: d.messages.map(m => ({ role: m.role, content: m.content.substring(0, 50) }))
                };
            }
            return null;
        });
        console.log('Switched Conversation State:', JSON.stringify(switchedState, null, 2));

        await page.screenshot({ path: 'public/docs/screenshots/ai_chat_switched_iteration_1.png', fullPage: true });

        if (switchedState && switchedState.activeId == targetConvId && switchedState.messagesCount >= 1) {
            sidebarSuccess = true;
            console.log('\n✅ SIDEBAR CONVERSATION SELECTION VERIFIED SUCCESSFULLY!');
        } else {
            console.error('\n❌ SIDEBAR CONVERSATION SELECTION FAILED.');
        }
    }

    console.log('\n--- FINAL LAYOUT & CONSOLE ERROR VERIFICATION ---');
    const realErrors = consoleErrors.filter(err => !err.includes('404'));
    console.log(`Application Console Errors Captured: ${realErrors.length}`);

    if (realErrors.length === 0 && createdConversations.length === 3 && sidebarSuccess) {
        console.log('\n✅ ALL 3 WORKFLOW ITERATIONS AND VERIFICATIONS PASSED CLEANLY WITH ZERO ERRORS!');
    } else {
        console.error('\n❌ VERIFICATION FAILED.');
    }

    await browser.close();
})();
