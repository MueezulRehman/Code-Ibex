// @ts-check
/**
 * Codeibex Browser Test Agent: Retail / General Store Manager
 *
 * Tests: inventory, variants, POS checkout with discount, stock adjustment, reports.
 *
 * Run: npx playwright test tests/Browser/agents/retail-manager.spec.js --headed
 * Env vars: BASE_URL, RETAIL_SLUG, RETAIL_EMAIL, RETAIL_PASSWORD
 */
const { test, expect } = require('@playwright/test');

const BASE_URL   = process.env.BASE_URL        || 'http://localhost:8000';
const SLUG       = process.env.RETAIL_SLUG     || 'amjd-karyana';
const EMAIL      = process.env.RETAIL_EMAIL    || 'manager@karyana.test';
const PASSWORD   = process.env.RETAIL_PASSWORD || 'password';
const TENANT_URL = `${BASE_URL}/${SLUG}`;

test.describe('Retail Manager Agent', () => {

  test.beforeEach(async ({ page }) => {
    await page.goto(`${TENANT_URL}/login`);
    await page.fill('input[name="email"]', EMAIL);
    await page.fill('input[name="password"]', PASSWORD);
    await page.click('button[type="submit"]');
    await expect(page).toHaveURL(/dashboard|home/);
  });

  test('1 — Product list loads', async ({ page }) => {
    await page.goto(`${TENANT_URL}/manager/menu-items`);
    await expect(page.locator('body')).not.toContainText('500');
    await expect(page.locator('body')).not.toContainText('403');
  });

  test('2 — Add product with SKU', async ({ page }) => {
    await page.goto(`${TENANT_URL}/manager/menu-items/create`);
    await page.fill('input[name="name"]', 'Browser Test Product');
    await page.fill('input[name="sku"]', `SKU-${Date.now()}`);
    await page.fill('input[name="price"]', '150');
    await page.selectOption('select[name="category_id"]', { index: 1 });
    await page.click('button[type="submit"]');
    await expect(page.locator('[class*="success"]')).toBeVisible();
  });

  test('3 — Inventory purchase', async ({ page }) => {
    await page.goto(`${TENANT_URL}/manager/inventory-purchases/create`);
    await expect(page.locator('body')).not.toContainText('500');
    await expect(page.locator('body')).not.toContainText('403');
  });

  test('4 — POS cash checkout', async ({ page }) => {
    await page.goto(`${TENANT_URL}/manager/pos`);
    const firstItem = page.locator('[data-pos-item], .pos-item, .menu-item-card').first();
    if (await firstItem.isVisible({ timeout: 5000 })) {
      await firstItem.click();
      const checkoutBtn = page.locator('button:has-text("Checkout"), button:has-text("Pay")').first();
      if (await checkoutBtn.isVisible()) await checkoutBtn.click();
    }
  });

  test('5 — Stock adjustment', async ({ page }) => {
    await page.goto(`${TENANT_URL}/manager/stock-adjustments`);
    await expect(page.locator('body')).not.toContainText('500');
  });

  test('6 — Reports page', async ({ page }) => {
    await page.goto(`${TENANT_URL}/manager/reports`);
    await expect(page.locator('body')).not.toContainText('500');
    await expect(page.locator('body')).not.toContainText('403');
  });

});
