import { test, expect } from '@playwright/test';
const SLUG='reilly-inc-9813', BASE='http://localhost:8005';
test('help page shows guided tutorials and one actually starts', async ({ page }) => {
  await page.goto(`${BASE}/${SLUG}/tutorials`);
  if (page.url().includes('login')) {
    await page.locator('input[name="email"]').fill('test@example.com');
    await page.locator('input[name="password"]').fill('password');
    await page.getByRole('button', { name: 'Log in' }).click();
    await page.waitForTimeout(2500);
    await page.goto(`${BASE}/${SLUG}/tutorials`);
  }
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(900);

  await expect(page.getByRole('heading', { name: 'Guided tutorials' })).toBeVisible();
  console.log('cards:', await page.locator('button:has-text("Start tutorial")').count());
  await page.screenshot({ path: 'tests/e2e/screenshots/help-guided-tutorials.png', clip: { x:0, y:0, width:1920, height:1000 } });

  // Start the first one and confirm it lands on the right screen, mid-tour.
  await page.locator('button:has-text("Start tutorial")').first().click();
  await page.waitForURL(/tickets/, { timeout: 10000 });
  await page.waitForLoadState('networkidle');
  await expect(page.locator('[data-gt="panel"]')).toBeVisible({ timeout: 10000 });
  console.log('landed on:', new URL(page.url()).pathname);
  console.log('panel title:', await page.locator('[data-gt="title"]').textContent());
});
