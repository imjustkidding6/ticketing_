import { chromium } from '@playwright/test';
import path from 'path';
import fs from 'fs';

const BASE_URL = process.env.APP_URL || 'http://127.0.0.1:8005';

const DIRS = [
    path.resolve('screenshots'),
    path.resolve('docs/screenshots'),
    path.resolve('public/docs/screenshots')
];

DIRS.forEach(d => fs.mkdirSync(d, { recursive: true }));

function log(msg) {
    process.stdout.write(`${msg}\n`);
}

async function safeGoto(page, url) {
    try {
        await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 10000 });
    } catch (e) {
        log(`Goto warning for ${url}: ${e.message}`);
    }
    await page.waitForTimeout(800);
}

async function save(page, filename, selector = null) {
    await page.waitForTimeout(500);
    for (const dir of DIRS) {
        const filePath = path.join(dir, filename);
        try {
            if (selector) {
                const el = await page.$(selector);
                if (el) {
                    await el.screenshot({ path: filePath });
                } else {
                    await page.screenshot({ path: filePath, fullPage: false });
                }
            } else {
                await page.screenshot({ path: filePath, fullPage: false });
            }
        } catch (e) {
            log(`Failed to save ${filename}: ${e.message}`);
        }
    }
    log(`[SAVED] ${filename}`);
}

(async () => {
    log('=== STARTING PLAYWRIGHT DOCKER SCREENSHOT GENERATOR ===');
    log(`Target URL: ${BASE_URL}`);

    const browser = await chromium.launch({
        headless: true,
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--force-color-profile=srgb',
            '--window-size=1920,1080',
            '--disable-dev-shm-usage'
        ]
    });

    const context = await browser.newContext({
        viewport: { width: 1920, height: 1080 },
        deviceScaleFactor: 1
    });

    const page = await context.newPage();

    // Force Light Mode
    await page.addInitScript(() => {
        localStorage.setItem('theme', 'light');
        document.documentElement.classList.remove('dark');
    });

    try {
        // 1. Authentication
        log('--- 1. Authentication ---');
        await safeGoto(page, `${BASE_URL}/admin/login`);
        await save(page, '01_login_page.png');

        await page.fill('#email', 'admin@example.com');
        await page.fill('#password', 'password');
        await page.click('button[type="submit"]');
        await page.waitForTimeout(2000);
        await save(page, '02_login_success.png');

        // 2. Executive Dashboard
        log('--- 2. Executive Dashboard ---');
        await safeGoto(page, `${BASE_URL}/admin`);
        await save(page, '03_dashboard_overview.png');

        const sidebar = await page.$('aside, .admin-sidebar, nav');
        if (sidebar) {
            await save(page, '04_sidebar_navigation.png', 'aside, .admin-sidebar, nav');
        } else {
            await save(page, '04_sidebar_navigation.png');
        }

        // 3. Tenant Management
        log('--- 3. Tenant Management ---');
        await safeGoto(page, `${BASE_URL}/admin/tenants`);
        await save(page, '05_tenant_list.png');

        const editTenantBtn = await page.$('a[href*="/admin/tenants/"][href*="/edit"]');
        if (editTenantBtn) {
            await editTenantBtn.click();
            await page.waitForTimeout(1000);
            await save(page, '06_tenant_edit.png');
        } else {
            await safeGoto(page, `${BASE_URL}/admin/tenants/1/edit`);
            await save(page, '06_tenant_edit.png');
        }

        await safeGoto(page, `${BASE_URL}/admin/tenants`);
        const deleteTenantBtn = await page.$('button[title*="Delete"], button:has-text("Delete")');
        if (deleteTenantBtn) {
            await deleteTenantBtn.click();
            await page.waitForTimeout(500);
            await save(page, '07_tenant_delete_modal.png');
            const cancelBtn = await page.$('button:has-text("Cancel")');
            if (cancelBtn) await cancelBtn.click();
        } else {
            await save(page, '07_tenant_delete_modal.png');
        }

        // 4. Global User Management
        log('--- 4. Global User Management ---');
        await safeGoto(page, `${BASE_URL}/admin/users`);
        await save(page, '08_user_list.png');

        await safeGoto(page, `${BASE_URL}/admin/users/create`);
        await save(page, '09_create_user.png');

        await safeGoto(page, `${BASE_URL}/admin/users`);
        const editUserBtn = await page.$('a[href*="/admin/users/"][href*="/edit"]');
        if (editUserBtn) {
            await editUserBtn.click();
            await page.waitForTimeout(1000);
            await save(page, '10_edit_user.png');
        } else {
            await safeGoto(page, `${BASE_URL}/admin/users/1/edit`);
            await save(page, '10_edit_user.png');
        }

        // 5. Subscription Licenses
        log('--- 5. Subscription Licenses ---');
        await safeGoto(page, `${BASE_URL}/admin/licenses`);
        await save(page, '11_license_list.png');

        await safeGoto(page, `${BASE_URL}/admin/licenses/create`);
        await save(page, '12_generate_license.png');

        await safeGoto(page, `${BASE_URL}/admin/licenses`);
        const editLicenseBtn = await page.$('a[href*="/admin/licenses/"][href*="/edit"]');
        if (editLicenseBtn) {
            await editLicenseBtn.click();
            await page.waitForTimeout(1000);
            await save(page, '13_edit_license.png');
        } else {
            await safeGoto(page, `${BASE_URL}/admin/licenses/1/edit`);
            await save(page, '13_edit_license.png');
        }

        // 6. Subscription Plans
        log('--- 6. Subscription Plans ---');
        await safeGoto(page, `${BASE_URL}/admin/plans`);
        await save(page, '14_plan_list.png');

        const createPlanBtn = await page.$('a[href*="/admin/plans/create"]');
        if (createPlanBtn) {
            await createPlanBtn.click();
            await page.waitForTimeout(1000);
            await save(page, '15_create_plan.png');
        } else {
            await safeGoto(page, `${BASE_URL}/admin/plans/1/edit`);
            await save(page, '15_create_plan.png');
        }

        await safeGoto(page, `${BASE_URL}/admin/plans/1/edit`);
        await save(page, '16_edit_plan.png');

        // 7. Distributor Management
        log('--- 7. Distributor Management ---');
        await safeGoto(page, `${BASE_URL}/admin/distributors`);
        await save(page, '17_distributor_list.png');

        await safeGoto(page, `${BASE_URL}/admin/distributors/create`);
        await save(page, '18_create_distributor.png');

        await safeGoto(page, `${BASE_URL}/admin/distributors`);
        const editDistributorBtn = await page.$('a[href*="/admin/distributors/"][href*="/edit"]');
        if (editDistributorBtn) {
            await editDistributorBtn.click();
            await page.waitForTimeout(1000);
            await save(page, '19_edit_distributor.png');
        } else {
            await safeGoto(page, `${BASE_URL}/admin/distributors/1/edit`);
            await save(page, '19_edit_distributor.png');
        }

        // 8. SLA Policies & Command Center
        log('--- 8. SLA Policies ---');
        await safeGoto(page, `${BASE_URL}/admin/sla`);
        await save(page, '20_sla_health_command_center.png');
        await save(page, '21_sla_registry.png');

        const createSlaBtn = await page.$('button:has-text("Create Policy"), button:has-text("Add Policy"), a[href*="sla/create"]');
        if (createSlaBtn) {
            await createSlaBtn.click();
            await page.waitForTimeout(500);
            await save(page, '22_create_sla_policy.png');
        } else {
            await save(page, '22_create_sla_policy.png');
        }

        await safeGoto(page, `${BASE_URL}/admin/sla/tier/starter/edit`);
        await save(page, '23_edit_sla_policy.png');

        // 9. System Notifications & Alerts
        log('--- 9. System Notifications ---');
        await safeGoto(page, `${BASE_URL}/admin/notifications`);
        await save(page, '24_notification_center.png');

        // 10. AI Assistant & Copilot
        log('--- 10. AI Assistant ---');
        await safeGoto(page, `${BASE_URL}/admin/ai/chat-page`);
        await save(page, '25_ai_chat_interface.png');

        const chatInput = await page.$('textarea[placeholder*="Send"], textarea, input[type="text"]');
        if (chatInput) {
            await chatInput.fill('Summarize real-time tenant performance and SLA compliance metrics.');
            await save(page, '26_ai_sending_prompt.png');

            const sendBtn = await page.$('button[type="submit"], button:has-text("Send")');
            if (sendBtn) {
                await sendBtn.click();
                await page.waitForTimeout(3000);
                await save(page, '27_ai_response.png');
            } else {
                await save(page, '27_ai_response.png');
            }
        } else {
            await save(page, '26_ai_sending_prompt.png');
            await save(page, '27_ai_response.png');
        }

        // 11. System Settings & Configuration
        log('--- 11. System Settings ---');
        await safeGoto(page, `${BASE_URL}/admin/settings`);
        await save(page, '28_system_settings.png');

        // 12. Help Center & Tutorials
        log('--- 12. Help & Tutorials ---');
        await safeGoto(page, `${BASE_URL}/admin/help`);
        await save(page, '29_help_tutorials.png');

        const tutorialLink = await page.$('a[href*="/admin/help/"]');
        if (tutorialLink) {
            await tutorialLink.click();
            await page.waitForTimeout(1000);
            await save(page, '30_tutorial_details.png');
        } else {
            await save(page, '30_tutorial_details.png');
        }

        // 13. System Announcements
        log('--- 13. System Announcements ---');
        await safeGoto(page, `${BASE_URL}/admin/announcements`);
        await save(page, '31_announcement_list.png');

        await safeGoto(page, `${BASE_URL}/admin/announcements/create`);
        await save(page, '32_create_announcement.png');

        log('\n=== ALL PLAYWRIGHT SCREENSHOTS CAPTURED SUCCESSFULLY! ===');
    } catch (err) {
        log(`Error during screenshot generation: ${err.stack || err.message}`);
    } finally {
        await browser.close();
        process.exit(0);
    }
})();
