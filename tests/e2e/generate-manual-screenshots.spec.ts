import { test } from '@playwright/test';
import path from 'path';
import fs from 'fs';

const BASE_URL = process.env.APP_URL || 'http://127.0.0.1:8005';

const DIRS = [
    path.resolve('screenshots'),
    path.resolve('docs/screenshots'),
    path.resolve('public/docs/screenshots')
];

DIRS.forEach(d => fs.mkdirSync(d, { recursive: true }));

async function save(page, filename, selector = null) {
    await page.waitForTimeout(600);
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

test('Generate User Manual Screenshots with Playwright', async ({ page }) => {
    test.setTimeout(180000);

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

    const editTenantLink = await page.$('a[href*="/admin/tenants/"][href*="/edit"]');
    if (editTenantLink) {
        await editTenantLink.click();
        await page.waitForTimeout(800);
        await save(page, '06_tenant_edit.png');
    } else {
        await page.goto(`${BASE_URL}/admin/tenants/1/edit`, { waitUntil: 'domcontentloaded' }).catch(() => {});
        await save(page, '06_tenant_edit.png');
    }

    await page.goto(`${BASE_URL}/admin/tenants`, { waitUntil: 'domcontentloaded' });
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

    console.log('--- 4. User Management Module ---');
    await page.goto(`${BASE_URL}/admin/users`, { waitUntil: 'domcontentloaded' });
    await save(page, '08_user_list.png');

    await page.goto(`${BASE_URL}/admin/users/create`, { waitUntil: 'domcontentloaded' });
    await save(page, '09_create_user.png');

    await page.goto(`${BASE_URL}/admin/users`, { waitUntil: 'domcontentloaded' });
    const editUserLink = await page.$('a[href*="/admin/users/"][href*="/edit"]');
    if (editUserLink) {
        await editUserLink.click();
        await page.waitForTimeout(800);
        await save(page, '10_edit_user.png');
    } else {
        await page.goto(`${BASE_URL}/admin/users/1/edit`, { waitUntil: 'domcontentloaded' }).catch(() => {});
        await save(page, '10_edit_user.png');
    }

    console.log('--- 5. Licenses Module ---');
    await page.goto(`${BASE_URL}/admin/licenses`, { waitUntil: 'domcontentloaded' });
    await save(page, '11_license_list.png');

    await page.goto(`${BASE_URL}/admin/licenses/create`, { waitUntil: 'domcontentloaded' });
    await save(page, '12_generate_license.png');

    await page.goto(`${BASE_URL}/admin/licenses`, { waitUntil: 'domcontentloaded' });
    const editLicenseBtn = await page.$('a[href*="/admin/licenses/"][href*="/edit"]');
    if (editLicenseBtn) {
        await editLicenseBtn.click();
        await page.waitForTimeout(800);
        await save(page, '13_edit_license.png');
    } else {
        await page.goto(`${BASE_URL}/admin/licenses/1/edit`, { waitUntil: 'domcontentloaded' }).catch(() => {});
        await save(page, '13_edit_license.png');
    }

    console.log('--- 6. Plans Module ---');
    await page.goto(`${BASE_URL}/admin/plans`, { waitUntil: 'domcontentloaded' });
    await save(page, '14_plan_list.png');

    const createPlanBtn = await page.$('a[href*="/admin/plans/create"]');
    if (createPlanBtn) {
        await createPlanBtn.click();
        await page.waitForTimeout(800);
        await save(page, '15_create_plan.png');
    } else {
        await page.goto(`${BASE_URL}/admin/plans/1/edit`, { waitUntil: 'domcontentloaded' }).catch(() => {});
        await save(page, '15_create_plan.png');
    }

    await page.goto(`${BASE_URL}/admin/plans/1/edit`, { waitUntil: 'domcontentloaded' }).catch(() => {});
    await save(page, '16_edit_plan.png');

    console.log('--- 7. Distributors Module ---');
    await page.goto(`${BASE_URL}/admin/distributors`, { waitUntil: 'domcontentloaded' });
    await save(page, '17_distributor_list.png');

    await page.goto(`${BASE_URL}/admin/distributors/create`, { waitUntil: 'domcontentloaded' });
    await save(page, '18_create_distributor.png');

    await page.goto(`${BASE_URL}/admin/distributors`, { waitUntil: 'domcontentloaded' });
    const editDistributorBtn = await page.$('a[href*="/admin/distributors/"][href*="/edit"]');
    if (editDistributorBtn) {
        await editDistributorBtn.click();
        await page.waitForTimeout(800);
        await save(page, '19_edit_distributor.png');
    } else {
        await page.goto(`${BASE_URL}/admin/distributors/1/edit`, { waitUntil: 'domcontentloaded' }).catch(() => {});
        await save(page, '19_edit_distributor.png');
    }

    console.log('--- 8. SLA Policies & Command Center ---');
    await page.goto(`${BASE_URL}/admin/sla`, { waitUntil: 'domcontentloaded' });
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

    await page.goto(`${BASE_URL}/admin/sla/tier/starter/edit`, { waitUntil: 'domcontentloaded' }).catch(() => {});
    await save(page, '23_edit_sla_policy.png');

    console.log('--- 9. Notifications Module ---');
    await page.goto(`${BASE_URL}/admin/notifications`, { waitUntil: 'domcontentloaded' });
    await save(page, '24_notification_center.png');

    console.log('--- 10. AI Assistant Chat Module ---');
    await page.goto(`${BASE_URL}/admin/ai/chat-page`, { waitUntil: 'domcontentloaded' });
    await save(page, '25_ai_chat_interface.png');

    const chatInput = await page.$('textarea[placeholder*="Send"], textarea, input[type="text"]');
    if (chatInput) {
        await chatInput.fill('Summarize real-time tenant performance and SLA compliance metrics.');
        await save(page, '26_ai_sending_prompt.png');

        const sendBtn = await page.$('button[type="submit"], button:has-text("Send")');
        if (sendBtn) {
            await sendBtn.click();
            await page.waitForTimeout(2500);
            await save(page, '27_ai_response.png');
        } else {
            await save(page, '27_ai_response.png');
        }
    } else {
        await save(page, '26_ai_sending_prompt.png');
        await save(page, '27_ai_response.png');
    }

    console.log('--- 11. System Settings Module ---');
    await page.goto(`${BASE_URL}/admin/settings`, { waitUntil: 'domcontentloaded' });
    await save(page, '28_system_settings.png');

    console.log('--- 12. Help & Tutorials Module ---');
    await page.goto(`${BASE_URL}/admin/help`, { waitUntil: 'domcontentloaded' });
    await save(page, '29_help_tutorials.png');

    const tutorialLink = await page.$('a[href*="/admin/help/"]');
    if (tutorialLink) {
        await tutorialLink.click();
        await page.waitForTimeout(800);
        await save(page, '30_tutorial_details.png');
    } else {
        await save(page, '30_tutorial_details.png');
    }

    console.log('--- 13. System Announcements Module ---');
    await page.goto(`${BASE_URL}/admin/announcements`, { waitUntil: 'domcontentloaded' });
    await save(page, '31_announcement_list.png');

    await page.goto(`${BASE_URL}/admin/announcements/create`, { waitUntil: 'domcontentloaded' });
    await save(page, '32_create_announcement.png');

    console.log('✓ All manual screenshots generated successfully with Playwright!');
});
