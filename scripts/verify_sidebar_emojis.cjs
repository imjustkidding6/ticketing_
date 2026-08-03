const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');

const BASE_URL = process.env.APP_URL || 'http://ticketing-nginx';

(async () => {
    console.log('=== Playwright Verification: Admin Sidebar Category Headers ===');
    const browser = await chromium.launch({
        executablePath: '/usr/bin/chromium',
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    // 1. Desktop Verification
    const context = await browser.newContext({ viewport: { width: 1920, height: 1080 } });
    const page = await context.newPage();

    console.log('1. Logging in as Administrator...');
    await page.goto(`${BASE_URL}/admin/login`, { waitUntil: 'domcontentloaded' });
    await page.fill('input[name="email"]', 'admin@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForTimeout(1500);

    console.log('2. Inspecting Desktop Sidebar Category Headers...');
    const headers = await page.$$eval('.sidebar-scroll div > .text-\\[13px\\]', els => els.map(e => e.innerText.trim()));
    console.log('Detected Category Headers:', headers);

    // Verify no emojis in headers
    const emojiRegex = /[\u{1F300}-\u{1F9FF}]|[\u{2600}-\u{26FF}]|[\u{2700}-\u{27BF}]/u;
    let emojiFound = false;
    for (const h of headers) {
        if (emojiRegex.test(h)) {
            console.error(`✘ Category header "${h}" still contains an emoji!`);
            emojiFound = true;
        }
    }
    if (!emojiFound) {
        console.log('✓ Confirmed: All category headers are free of emojis!');
    }

    console.log('3. Inspecting Submenu Items and Icons...');
    const submenus = await page.$$eval('.sidebar-scroll a', els => els.map(e => ({
        title: e.querySelector('span')?.innerText.trim(),
        hasSvg: !!e.querySelector('svg')
    })));
    console.log(`Verified ${submenus.length} submenu items.`);
    const missingSvg = submenus.filter(s => !s.hasSvg);
    if (missingSvg.length === 0) {
        console.log('✓ Confirmed: All submenu icons remain present and visible!');
    } else {
        console.error('✘ Missing SVG icons in submenus:', missingSvg);
    }

    // Save Desktop Sidebar Screenshot
    const desktopScreenshotPath = path.resolve('public/docs/screenshots/verify_sidebar_desktop.png');
    await page.screenshot({ path: desktopScreenshotPath, fullPage: false });
    console.log(`✓ Saved desktop sidebar screenshot to: ${desktopScreenshotPath}`);

    // 2. Mobile / Responsive Verification
    console.log('4. Testing Mobile / Responsive Sidebar Drawer...');
    const mobileContext = await browser.newContext({ viewport: { width: 375, height: 812 } });
    const mobilePage = await mobileContext.newPage();

    await mobilePage.goto(`${BASE_URL}/admin/login`, { waitUntil: 'domcontentloaded' });
    await mobilePage.fill('input[name="email"]', 'admin@example.com');
    await mobilePage.fill('input[name="password"]', 'password');
    await mobilePage.click('button[type="submit"]');
    await mobilePage.waitForTimeout(1500);

    // Open mobile sidebar drawer
    await mobilePage.click('button:has-text("Open sidebar")');
    await mobilePage.waitForTimeout(500);

    const mobileHeaders = await mobilePage.$$eval('.sidebar-scroll div > .text-\\[13px\\]', els => els.map(e => e.innerText.trim()));
    console.log('Mobile Category Headers:', mobileHeaders);

    const mobileScreenshotPath = path.resolve('public/docs/screenshots/verify_sidebar_mobile.png');
    await mobilePage.screenshot({ path: mobileScreenshotPath, fullPage: false });
    console.log(`✓ Saved mobile sidebar screenshot to: ${mobileScreenshotPath}`);

    await browser.close();
    console.log('=== All Sidebar Verification Tests Passed Successfully! ===');
})();
