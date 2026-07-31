import { chromium } from 'playwright';
import path from 'path';
import fs from 'fs';
import os from 'os';

const BASE_URL = process.env.APP_URL || 'http://127.0.0.1:8000';
const PUBLIC_SCREENSHOT_DIR = path.resolve('public/docs/screenshots');
const DOCS_SCREENSHOT_DIR = path.resolve('docs/screenshots');

// Ensure output directories exist
fs.mkdirSync(PUBLIC_SCREENSHOT_DIR, { recursive: true });
fs.mkdirSync(DOCS_SCREENSHOT_DIR, { recursive: true });

function findChromeExecutable() {
    const candidateDirs = [
        path.resolve('chrome'),
        path.resolve('node_modules/puppeteer/.local-browser'),
        path.join(os.homedir(), '.cache/puppeteer')
    ];

    for (const baseDir of candidateDirs) {
        if (!fs.existsSync(baseDir)) continue;
        const walkSync = (dir) => {
            const files = fs.readdirSync(dir);
            for (const file of files) {
                const fullPath = path.join(dir, file);
                const stat = fs.statSync(fullPath);
                if (stat.isDirectory()) {
                    const result = walkSync(fullPath);
                    if (result) return result;
                } else if (file === 'chrome' || file === 'chrome.exe') {
                    return fullPath;
                }
            }
            return null;
        };
        const found = walkSync(baseDir);
        if (found) return found;
    }
    return null;
}

async function saveScreenshot(page, filename) {
    await page.waitForTimeout(600);
    const pubPath = path.join(PUBLIC_SCREENSHOT_DIR, filename);
    const docPath = path.join(DOCS_SCREENSHOT_DIR, filename);
    await page.screenshot({ path: pubPath, fullPage: false });
    fs.copyFileSync(pubPath, docPath);
    console.log(`✓ Screenshot captured: ${filename}`);
}

(async () => {
    console.log('Starting Playwright automated screenshot generator...');
    const chromePath = findChromeExecutable();
    const launchOptions = {
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage']
    };
    if (chromePath) {
        console.log(`Using Chrome binary at: ${chromePath}`);
        launchOptions.executablePath = chromePath;
    } else {
        console.log('Using default Playwright browser bundle...');
    }

    const browser = await chromium.launch(launchOptions);
    const context = await browser.newContext({
        viewport: { width: 1920, height: 1080 },
        deviceScaleFactor: 1,
    });
    const page = await context.newPage();

    // Ensure Light Mode
    await page.addInitScript(() => {
        localStorage.setItem('theme', 'light');
        document.documentElement.classList.remove('dark');
    });

    try {
        console.log('\n--- 1. Authentication Module ---');
        await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' });
        await saveScreenshot(page, '01_login_page.png');

        console.log('Logging in as System Administrator...');
        await page.fill('input[name="email"]', 'admin@example.com');
        await page.fill('input[name="password"]', 'password');
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle' }),
            page.click('button[type="submit"]'),
        ]);
        await saveScreenshot(page, '02_login_success.png');

        console.log('\n--- 2. Dashboard Module ---');
        await page.goto(`${BASE_URL}/admin/dashboard`, { waitUntil: 'networkidle' });
        await saveScreenshot(page, '03_dashboard_overview.png');

        console.log('\n--- 3. Tenant Management Module ---');
        await page.goto(`${BASE_URL}/admin/tenants`, { waitUntil: 'networkidle' });
        await saveScreenshot(page, '05_tenant_list.png');

        // Edit Tenant View
        const editTenantLink = await page.$('a[href*="/admin/tenants/"][href*="/edit"]');
        if (editTenantLink) {
            await Promise.all([
                page.waitForNavigation({ waitUntil: 'networkidle' }),
                editTenantLink.click(),
            ]);
            await saveScreenshot(page, '06_tenant_edit.png');
        }

        // Delete Tenant Confirmation Modal
        await page.goto(`${BASE_URL}/admin/tenants`, { waitUntil: 'networkidle' });
        const deleteTenantBtn = await page.$('button[title="Delete Tenant"], button:has-text("Delete")');
        if (deleteTenantBtn) {
            await deleteTenantBtn.click();
            await page.waitForTimeout(400);
            await saveScreenshot(page, '07_tenant_delete_modal.png');
            const cancelBtn = await page.$('button:has-text("Cancel")');
            if (cancelBtn) await cancelBtn.click();
        }

        console.log('\n--- 4. User Management Module ---');
        await page.goto(`${BASE_URL}/admin/users`, { waitUntil: 'networkidle' });
        await saveScreenshot(page, '08_user_list.png');

        await page.goto(`${BASE_URL}/admin/users/create`, { waitUntil: 'networkidle' });
        await saveScreenshot(page, '09_create_user.png');

        await page.goto(`${BASE_URL}/admin/users`, { waitUntil: 'networkidle' });
        const editUserLink = await page.$('a[href*="/admin/users/"][href*="/edit"]');
        if (editUserLink) {
            await Promise.all([
                page.waitForNavigation({ waitUntil: 'networkidle' }),
                editUserLink.click(),
            ]);
            await saveScreenshot(page, '10_edit_user.png');
        }

        console.log('\n--- 5. Licenses Module ---');
        await page.goto(`${BASE_URL}/admin/licenses`, { waitUntil: 'networkidle' });
        await saveScreenshot(page, '11_license_list.png');

        await page.goto(`${BASE_URL}/admin/licenses/create`, { waitUntil: 'networkidle' });
        await saveScreenshot(page, '12_generate_license.png');

        console.log('\n--- 6. Plans Module ---');
        await page.goto(`${BASE_URL}/admin/plans`, { waitUntil: 'networkidle' });
        await saveScreenshot(page, '14_plan_list.png');

        await page.goto(`${BASE_URL}/admin/plans/create`, { waitUntil: 'networkidle' });
        await saveScreenshot(page, '15_create_plan.png');

        console.log('\n--- 7. Distributors Module ---');
        await page.goto(`${BASE_URL}/admin/distributors`, { waitUntil: 'networkidle' });
        await saveScreenshot(page, '17_distributor_list.png');

        await page.goto(`${BASE_URL}/admin/distributors/create`, { waitUntil: 'networkidle' });
        await saveScreenshot(page, '18_create_distributor.png');

        console.log('\n--- 8. SLA Policies & Command Center ---');
        try {
            await page.goto(`${BASE_URL}/admin/sla`, { waitUntil: 'networkidle' });
            await saveScreenshot(page, '20_sla_health_command_center.png');
        } catch (e) {}

        try {
            await page.goto(`${BASE_URL}/admin/sla/policies`, { waitUntil: 'networkidle' });
            await saveScreenshot(page, '21_sla_registry.png');
        } catch (e) {}

        try {
            await page.goto(`${BASE_URL}/admin/sla/policies/create`, { waitUntil: 'networkidle' });
            await saveScreenshot(page, '22_create_sla_policy.png');
        } catch (e) {}

        console.log('\n--- 9. Notifications Module ---');
        try {
            await page.goto(`${BASE_URL}/admin/notifications`, { waitUntil: 'networkidle' });
            await saveScreenshot(page, '24_notification_center.png');
        } catch (e) {}

        console.log('\n--- 10. AI Assistant Chat Module ---');
        await page.goto(`${BASE_URL}/admin/ai/chat-page`, { waitUntil: 'networkidle' });
        await saveScreenshot(page, '25_ai_chat_interface.png');

        // Type & send prompt
        await page.fill('textarea[placeholder*="Send a message"]', 'Summarize key platform metrics.');
        await saveScreenshot(page, '26_ai_sending_prompt.png');

        await page.click('button[type="submit"]');
        await page.waitForTimeout(2500);
        await saveScreenshot(page, '27_ai_response.png');

        console.log('\n--- 11. System Settings Module ---');
        await page.goto(`${BASE_URL}/admin/settings`, { waitUntil: 'networkidle' });
        await saveScreenshot(page, '28_system_settings.png');

        console.log('\n--- 12. Help & Tutorials Module ---');
        await page.goto(`${BASE_URL}/admin/help`, { waitUntil: 'networkidle' });
        await saveScreenshot(page, '29_help_tutorials.png');

        console.log('\n--- 13. System Announcements Module ---');
        try {
            await page.goto(`${BASE_URL}/admin/announcements`, { waitUntil: 'networkidle' });
            await saveScreenshot(page, '31_announcement_list.png');
        } catch (e) {}

        try {
            await page.goto(`${BASE_URL}/admin/announcements/create`, { waitUntil: 'networkidle' });
            await saveScreenshot(page, '32_create_announcement.png');
        } catch (e) {}

        console.log('\n✓ All Playwright screenshot generation steps finished successfully!');
    } catch (err) {
        console.error('Playwright Screenshot Generation Error:', err);
    } finally {
        await browser.close();
    }
})();
