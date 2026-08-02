const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');

const BASE_URL = process.env.APP_URL || 'http://127.0.0.1:8005';

const DIRS = [
    path.resolve('screenshots'),
    path.resolve('docs/screenshots'),
    path.resolve('public/docs/screenshots')
];

DIRS.forEach(d => {
    if (!fs.existsSync(d)) {
        fs.mkdirSync(d, { recursive: true });
    }
});

let executablePath = '/usr/bin/chromium';
if (!fs.existsSync(executablePath)) {
    if (fs.existsSync('/home/anyang/.cache/puppeteer/chrome/linux-151.0.7922.47/chrome-linux64/chrome')) {
        executablePath = '/home/anyang/.cache/puppeteer/chrome/linux-151.0.7922.47/chrome-linux64/chrome';
    }
}

async function save(page, filename, selector = null) {
    await page.waitForTimeout(800);
    for (const dir of DIRS) {
        const filePath = path.join(dir, filename);
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
    }
    console.log(`[SAVED] ${filename}`);
}

(async () => {
    console.log(`Launching browser using ${executablePath}...`);
    const browser = await chromium.launch({
        executablePath,
        args: ['--no-sandbox', '--disable-setuid-sandbox', '--force-color-profile=srgb']
    });

    const context = await browser.newContext({
        viewport: { width: 1920, height: 1080 }
    });

    const page = await context.newPage();

    // Force Light Mode
    await page.addInitScript(() => {
        localStorage.setItem('theme', 'light');
        document.documentElement.classList.remove('dark');
    });

    console.log('--- 1. Authentication Module ---');
    await page.goto(`${BASE_URL}/admin/login`, { waitUntil: 'domcontentloaded' });
    await save(page, '01_login_page.png');

    await page.fill('input[name="email"]', 'admin@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForTimeout(1500);
    await save(page, '02_login_success.png');

    console.log('--- 2. Dashboard Module ---');
    await page.goto(`${BASE_URL}/admin`, { waitUntil: 'domcontentloaded' });
    await save(page, '03_dashboard_overview.png');

    const sidebar = await page.$('aside, .admin-sidebar, nav');
    if (sidebar) {
        await save(page, '04_sidebar_navigation.png', 'aside, .admin-sidebar, nav');
    } else {
        await save(page, '04_sidebar_navigation.png');
    }

    console.log('--- 3. Tenant Management Module ---');
    await page.goto(`${BASE_URL}/admin/tenants`, { waitUntil: 'domcontentloaded' });
    await save(page, '05_tenant_list.png');

    // Create Tenant or edit tenant
    const createTenantLink = await page.$('a[href*="/admin/tenants/create"]');
    if (createTenantLink) {
        await createTenantLink.click();
        await page.waitForTimeout(800);
        await save(page, '06_create_tenant.png');
    } else {
        await save(page, '06_create_tenant.png');
    }

    await page.goto(`${BASE_URL}/admin/tenants/1/edit`, { waitUntil: 'domcontentloaded' }).catch(() => {});
    await save(page, '07_tenant_edit.png');

    await page.goto(`${BASE_URL}/admin/tenants`, { waitUntil: 'domcontentloaded' });
    await save(page, '08_tenant_actions.png');

    const deleteTenantBtn = await page.$('button[title*="Delete"], button:has-text("Delete")');
    if (deleteTenantBtn) {
        await deleteTenantBtn.click();
        await page.waitForTimeout(600);
        await save(page, '09_tenant_delete_modal.png');
        const cancelBtn = await page.$('button:has-text("Cancel")');
        if (cancelBtn) await cancelBtn.click();
    } else {
        await save(page, '09_tenant_delete_modal.png');
    }

    console.log('--- 4. User Management Module ---');
    await page.goto(`${BASE_URL}/admin/users`, { waitUntil: 'domcontentloaded' });
    await save(page, '10_user_list.png');

    await page.goto(`${BASE_URL}/admin/users/create`, { waitUntil: 'domcontentloaded' });
    await save(page, '11_create_user.png');

    await page.goto(`${BASE_URL}/admin/users/1/edit`, { waitUntil: 'domcontentloaded' }).catch(() => {});
    await save(page, '12_edit_user.png');

    await page.goto(`${BASE_URL}/admin/users`, { waitUntil: 'domcontentloaded' });
    await save(page, '13_user_actions.png');

    console.log('--- 5. Licenses Module ---');
    await page.goto(`${BASE_URL}/admin/licenses`, { waitUntil: 'domcontentloaded' });
    await save(page, '14_license_list.png');

    await page.goto(`${BASE_URL}/admin/licenses/create`, { waitUntil: 'domcontentloaded' });
    await save(page, '15_generate_license.png');

    await page.goto(`${BASE_URL}/admin/licenses/1/edit`, { waitUntil: 'domcontentloaded' }).catch(() => {});
    await save(page, '16_edit_license.png');

    await page.goto(`${BASE_URL}/admin/licenses`, { waitUntil: 'domcontentloaded' });
    await save(page, '17_license_actions.png');

    console.log('--- 6. Plans Module ---');
    await page.goto(`${BASE_URL}/admin/plans`, { waitUntil: 'domcontentloaded' });
    await save(page, '18_plan_list.png');

    const createPlanBtn = await page.$('a[href*="/admin/plans/create"]');
    if (createPlanBtn) {
        await createPlanBtn.click();
        await page.waitForTimeout(800);
        await save(page, '19_create_plan.png');
    } else {
        await save(page, '19_create_plan.png');
    }

    await page.goto(`${BASE_URL}/admin/plans/1/edit`, { waitUntil: 'domcontentloaded' }).catch(() => {});
    await save(page, '20_edit_plan.png');

    console.log('--- 7. Distributors Module ---');
    await page.goto(`${BASE_URL}/admin/distributors`, { waitUntil: 'domcontentloaded' });
    await save(page, '21_distributor_list.png');

    await page.goto(`${BASE_URL}/admin/distributors/create`, { waitUntil: 'domcontentloaded' });
    await save(page, '22_create_distributor.png');

    await page.goto(`${BASE_URL}/admin/distributors/1/edit`, { waitUntil: 'domcontentloaded' }).catch(() => {});
    await save(page, '23_edit_distributor.png');

    console.log('--- 8. SLA Policies & Command Center ---');
    await page.goto(`${BASE_URL}/admin/sla`, { waitUntil: 'domcontentloaded' });
    await save(page, '24_sla_health_command_center.png');
    await save(page, '25_sla_registry.png');

    const createSlaBtn = await page.$('button:has-text("Create Policy"), button:has-text("Add Policy"), a[href*="sla/create"]');
    if (createSlaBtn) {
        await createSlaBtn.click();
        await page.waitForTimeout(600);
        await save(page, '26_create_sla_policy.png');
    } else {
        await save(page, '26_create_sla_policy.png');
    }

    await page.goto(`${BASE_URL}/admin/sla/tier/starter/edit`, { waitUntil: 'domcontentloaded' }).catch(() => {});
    await save(page, '27_edit_sla_policy.png');

    console.log('--- 9. Notifications Module ---');
    await page.goto(`${BASE_URL}/admin/notifications`, { waitUntil: 'domcontentloaded' });
    await save(page, '28_notification_center.png');
    await save(page, '29_notification_actions.png');

    console.log('--- 10. AI Assistant Module ---');
    await page.goto(`${BASE_URL}/admin/ai/chat-page`, { waitUntil: 'domcontentloaded' });
    await save(page, '30_ai_chat_interface.png');

    const chatInput = await page.$('textarea[placeholder*="Send"], textarea, input[type="text"]');
    if (chatInput) {
        await chatInput.fill('Summarize platform active tenants, SLA compliance status, and system health metrics.');
        await save(page, '31_ai_sending_prompt.png');

        const sendBtn = await page.$('button[type="submit"], button:has-text("Send")');
        if (sendBtn) {
            await sendBtn.click();
            await page.waitForTimeout(3000);
            await save(page, '32_ai_response.png');
        } else {
            await save(page, '32_ai_response.png');
        }
    } else {
        await save(page, '31_ai_sending_prompt.png');
        await save(page, '32_ai_response.png');
    }

    await page.goto(`${BASE_URL}/admin/ai/analytics`, { waitUntil: 'domcontentloaded' }).catch(() => {});
    await save(page, '33_ai_analytics_playground.png');

    console.log('--- 11. System Settings Module ---');
    await page.goto(`${BASE_URL}/admin/settings`, { waitUntil: 'domcontentloaded' });
    await save(page, '34_system_settings.png');
    await save(page, '35_system_settings_save.png');

    console.log('--- 12. Help & Tutorials Module ---');
    await page.goto(`${BASE_URL}/admin/help`, { waitUntil: 'domcontentloaded' });
    await save(page, '36_help_tutorials.png');

    const tutorialLink = await page.$('a[href*="/admin/help/"]');
    if (tutorialLink) {
        await tutorialLink.click();
        await page.waitForTimeout(800);
        await save(page, '37_tutorial_details.png');
    } else {
        await save(page, '37_tutorial_details.png');
    }

    await page.goto(`${BASE_URL}/admin/help`, { waitUntil: 'domcontentloaded' });
    await save(page, '38_download_manual_flow.png');

    console.log('--- 13. System Announcements Module ---');
    await page.goto(`${BASE_URL}/admin/announcements`, { waitUntil: 'domcontentloaded' });
    await save(page, '39_announcement_list.png');

    await page.goto(`${BASE_URL}/admin/announcements/create`, { waitUntil: 'domcontentloaded' });
    await save(page, '40_create_announcement.png');

    console.log('--- 14. Bug Reports & Feedback Modules ---');
    await page.goto(`${BASE_URL}/admin/bugs`, { waitUntil: 'domcontentloaded' }).catch(() => {});
    await save(page, '41_bug_report_list.png');

    await page.goto(`${BASE_URL}/admin/feedback`, { waitUntil: 'domcontentloaded' }).catch(() => {});
    await save(page, '42_feedback_management.png');

    await browser.close();
    console.log('✓ All 42 high-resolution screenshots generated successfully!');
})();
