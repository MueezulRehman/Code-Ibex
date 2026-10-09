// @ts-check
/**
 * Codeibex Browser Test Agent: Super Admin / Platform Admin
 *
 * Tests: tenant provisioning, subscription management, module config.
 *
 * Run: npx playwright test tests/Browser/agents/super-admin.spec.js --headed
 * Env vars: BASE_URL, SUPER_EMAIL, SUPER_PASSWORD
 */
const { test, expect } = require('@playwright/test');

const BASE_URL = process.env.BASE_URL       || 'http://localhost:8000';
const EMAIL    = process.env.SUPER_EMAIL    || 'superadmin@codeibex.test';
const PASSWORD = process.env.SUPER_PASSWORD || 'password';

test.describe('Super Admin Agent', () => {

  test.beforeEach(async ({ page }) => {
    await page.goto(`${BASE_URL}/super-admin/login`);
    await page.fill('input[name="email"]', EMAIL);
    await page.fill('input[name="password"]', PASSWORD);
    await page.click('button[type="submit"]');
    await expect(page).toHaveURL(/super-admin.*(dashboard|restaurants)/);
  });

  test('1 — Tenant list loads', async ({ page }) => {
    await page.goto(`${BASE_URL}/super-admin/restaurants`);
    await expect(page.locator('body')).not.toContainText('500');
    await expect(page.locator('body')).not.toContainText('403');
    await expect(page.locator('table, [class*="list"], [class*="tenant"]').first()).toBeVisible();
  });

  test('2 — Create new restaurant tenant', async ({ page }) => {
    await page.goto(`${BASE_URL}/super-admin/restaurants/create`);
    await expect(page.locator('body')).not.toContainText('500');
    const nameField = page.locator('input[name="name"]').first();
    if (await nameField.isVisible()) {
      await nameField.fill(`Browser Test Restaurant ${Date.now()}`);
      const slugField = page.locator('input[name="slug"]').first();
      if (await slugField.isVisible()) {
        await slugField.fill(`browser-test-${Date.now()}`);
      }
      // Select business type if present
      const typeSelect = page.locator('select[name="business_type_id"], select[name="type"]').first();
      if (await typeSelect.isVisible()) await typeSelect.selectOption({ index: 1 });
      await page.click('button[type="submit"]');
      await expect(page.locator('[class*="success"], .alert-success')).toBeVisible({ timeout: 10000 });
    }
  });

  test('3 — Subscription plans list', async ({ page }) => {
    await page.goto(`${BASE_URL}/super-admin/subscription-plans`);
    await expect(page.locator('body')).not.toContainText('500');
    await expect(page.locator('body')).not.toContainText('403');
  });

  test('4 — Platform settings accessible', async ({ page }) => {
    await page.goto(`${BASE_URL}/super-admin/settings`);
    await expect(page.locator('body')).not.toContainText('500');
    await expect(page.locator('body')).not.toContainText('403');
  });

  test('5 — User credentials management', async ({ page }) => {
    await page.goto(`${BASE_URL}/super-admin/users`);
    await expect(page.locator('body')).not.toContainText('500');
    await expect(page.locator('body')).not.toContainText('403');
  });

});
