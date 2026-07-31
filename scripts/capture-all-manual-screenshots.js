import puppeteer from 'puppeteer';
import path from 'path';
import fs from 'fs';

const BASE_URL = process.env.APP_URL || 'http://127.0.0.1:8000';
const PUBLIC_SCREENSHOT_DIR = path.resolve('public/docs/screenshots');
const DOCS_SCREENSHOT_DIR = path.resolve('docs/screenshots');

fs.mkdirSync(PUBLIC_SCREENSHOT_DIR, { recursive: true });
fs.mkdirSync(DOCS_SCREENSHOT_DIR, { recursive: true });

async function saveScreenshot(page, filename) {
    await new Promise(r => setTimeout(r, 600));
    const pubPath = path.join(PUBLIC_SCREENSHOT_DIR, filename);
    const docPath = path.join(DOCS_SCREENSHOT_DIR, filename);
    await page.screenshot({ path: pubPath, fullPage: false });
    fs.copyFileSync(pubPath, docPath);
    console.log(`✓ Screenshot captured: ${filename}`);
}

(async () => {
    console.log('Starting automated screenshot generator...');
    
    let browser;
    if (process.platform === 'win32') {
        const edgePaths = [
            'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
            'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
            'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe'
        ];
        let foundPath = null;
        for (const p of edgePaths) {
            if (fs.existsSync(p)) {
                foundPath = p;
                break;
            }
        }
        if (foundPath) {
            console.log(`Using Windows browser: ${foundPath}`);
            browser = await puppeteer.launch({
                executablePath: foundPath,
                headless: 'new',
                args: ['--no-sandbox', '--window-size=1920,1080', '--force-color-profile=srgb']
            });
        } else {
            browser = await puppeteer.launch({ headless: 'new', args: ['--no-sandbox'] });
        }
    } else {
        browser = await puppeteer.launch({ headless: 'new', args: ['--no-sandbox', '--disable-setuid-sandbox'] });
    }

    const page = await browser.newPage();
    await page.setViewport({ width: 1920, height: 1080 });

    // Force Light Mode
    await page.evaluateOnNewDocument(() => {
        localStorage.setItem('theme', 'light');
        document.documentElement.classList.remove('dark');
    });

    try {
        console.log('\n--- 1. Authentication Module ---');
        await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle0' });
        await saveScreenshot(page, '01_login_page.png');

        console.log('Logging in as System Administrator...');
        await page.type('input[name="email"]', 'admin@example.com');
        await page.type('input[name="password"]', 'password');
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle0' }),
            page.click('button[type="submit"]'),
        ]);
        await saveScreenshot(page, '02_login_success.png');

        console.log('\n--- 2. Dashboard Module ---');
        await page.goto(`${BASE_URL}/admin/dashboard`, { waitUntil: 'networkidle0' });
        await saveScreenshot(page, '03_dashboard_overview.png');

        console.log('\n--- 3. Tenant Management Module ---');
        await page.goto(`${BASE_URL}/admin/tenants`, { waitUntil: 'networkidle0' });
        await saveScreenshot(page, '05_tenant_list.png');

        // Edit Tenant View
        const editTenantLink = await page.$('a[href*="/admin/tenants/"][href*="/edit"]');
        if (editTenantLink) {
            await Promise.all([
                page.waitForNavigation({ waitUntil: 'networkidle0' }),
                editTenantLink.click(),
            ]);
            await saveScreenshot(page, '06_tenant_edit.png');
        }

        // Delete Tenant Confirmation Modal
        await page.goto(`${BASE_URL}/admin/tenants`, { waitUntil: 'networkidle0' });
        const deleteTenantBtn = await page.$('button[title="Delete Tenant"]');
        if (deleteTenantBtn) {
            await deleteTenantBtn.click();
            await new Promise(r => setTimeout(r, 400));
            await saveScreenshot(page, '07_tenant_delete_modal.png');
            const cancelBtn = await page.$('button:has-text("Cancel")');
            if (cancelBtn) await cancelBtn.click();
        }

        console.log('\n--- 4. User Management Module ---');
        await page.goto(`${BASE_URL}/admin/users`, { waitUntil: 'networkidle0' });
        await saveScreenshot(page, '08_user_list.png');

        await page.goto(`${BASE_URL}/admin/users/create`, { waitUntil: 'networkidle0' });
        await saveScreenshot(page, '09_create_user.png');

        await page.goto(`${BASE_URL}/admin/users`, { waitUntil: 'networkidle0' });
        const editUserLink = await page.$('a[href*="/admin/users/"][href*="/edit"]');
        if (editUserLink) {
            await Promise.all([
                page.waitForNavigation({ waitUntil: 'networkidle0' }),
                editUserLink.click(),
            ]);
            await saveScreenshot(page, '10_edit_user.png');
        }

        console.log('\n--- 5. Licenses Module ---');
        await page.goto(`${BASE_URL}/admin/licenses`, { waitUntil: 'networkidle0' });
        await saveScreenshot(page, '11_license_list.png');

        await page.goto(`${BASE_URL}/admin/licenses/create`, { waitUntil: 'networkidle0' });
        await saveScreenshot(page, '12_generate_license.png');

        console.log('\n--- 6. Plans Module ---');
        await page.goto(`${BASE_URL}/admin/plans`, { waitUntil: 'networkidle0' });
        await saveScreenshot(page, '14_plan_list.png');

        await page.goto(`${BASE_URL}/admin/plans/create`, { waitUntil: 'networkidle0' });
        await saveScreenshot(page, '15_create_plan.png');

        console.log('\n--- 7. Distributors Module ---');
        await page.goto(`${BASE_URL}/admin/distributors`, { waitUntil: 'networkidle0' });
        await saveScreenshot(page, '17_distributor_list.png');

        await page.goto(`${BASE_URL}/admin/distributors/create`, { waitUntil: 'networkidle0' });
        await saveScreenshot(page, '18_create_distributor.png');

        console.log('\n--- 8. SLA Policies & Command Center ---');
        try {
            await page.goto(`${BASE_URL}/admin/sla`, { waitUntil: 'networkidle0' });
            await saveScreenshot(page, '20_sla_health_command_center.png');
        } catch (e) {}

        try {
            await page.goto(`${BASE_URL}/admin/sla/policies`, { waitUntil: 'networkidle0' });
            await saveScreenshot(page, '21_sla_registry.png');
        } catch (e) {}

        try {
            await page.goto(`${BASE_URL}/admin/sla/policies/create`, { waitUntil: 'networkidle0' });
            await saveScreenshot(page, '22_create_sla_policy.png');
        } catch (e) {}

        console.log('\n--- 9. Notifications Module ---');
        try {
            await page.goto(`${BASE_URL}/admin/notifications`, { waitUntil: 'networkidle0' });
            await saveScreenshot(page, '24_notification_center.png');
        } catch (e) {}

        console.log('\n--- 10. AI Assistant Chat Module ---');
        await page.goto(`${BASE_URL}/admin/ai/chat-page`, { waitUntil: 'networkidle0' });
        await saveScreenshot(page, '25_ai_chat_interface.png');

        // Type & send prompt
        await page.type('textarea[placeholder*="Send a message"]', 'Summarize key platform metrics.');
        await saveScreenshot(page, '26_ai_sending_prompt.png');

        await page.click('button[type="submit"]');
        await new Promise(r => setTimeout(r, 2500));
        await saveScreenshot(page, '27_ai_response.png');

        console.log('\n--- 11. System Settings Module ---');
        await page.goto(`${BASE_URL}/admin/settings`, { waitUntil: 'networkidle0' });
        await saveScreenshot(page, '28_system_settings.png');

        console.log('\n--- 12. Help & Tutorials Module ---');
        await page.goto(`${BASE_URL}/admin/help`, { waitUntil: 'networkidle0' });
        await saveScreenshot(page, '29_help_tutorials.png');

        console.log('\n--- 13. System Announcements Module ---');
        try {
            await page.goto(`${BASE_URL}/admin/announcements`, { waitUntil: 'networkidle0' });
            await saveScreenshot(page, '31_announcement_list.png');
        } catch (e) {}

        try {
            await page.goto(`${BASE_URL}/admin/announcements/create`, { waitUntil: 'networkidle0' });
            await saveScreenshot(page, '32_create_announcement.png');
        } catch (e) {}

        console.log('\n✓ All screenshots generated successfully!');
    } catch (err) {
        console.error('Screenshot Generation Error:', err);
    } finally {
        await browser.close();
    }
})();
