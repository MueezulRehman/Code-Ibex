// @ts-check
/**
 * Codeibex Browser Test Agent: Restaurant Manager
 *
 * Simulates a manager running daily ops on a restaurant tenant:
 * category/menu CRUD, deal creation, POS checkout, stock, reports.
 *
 * Run: npx playwright test tests/Browser/agents/restaurant-manager.spec.js --headed
 * Env vars: BASE_URL, MANAGER_EMAIL, MANAGER_PASSWORD, RESTAURANT_SLUG
 */
const { test, expect } = require('@playwright/test');
const path = require('path');

const BASE_URL    = process.env.BASE_URL         || 'http://localhost:8000';
const SLUG        = process.env.RESTAURANT_SLUG  || 'tastehut';
const EMAIL       = process.env.MANAGER_EMAIL    || 'manager@tastehut.test';
const PASSWORD    = process.env.MANAGER_PASSWORD || 'password';
const TENANT_URL  = `${BASE_URL}/${SLUG}`;

test.describe('Restaurant Manager Agent', () => {

  test.beforeEach(async ({ page }) => {
    // Login
    await page.goto(`${TENANT_URL}/login`);
    await page.fill('input[name="email"]', EMAIL);
    await page.fill('input[name="password"]', PASSWORD);
    await page.click('button[type="submit"]');
    await expect(page).toHaveURL(/dashboard|home/);
  });

  test('1 — Create menu category', async ({ page }) => {
    await page.goto(`${TENANT_URL}/manager/categories/create`);
    await page.fill('input[name="name"]', 'Test Burgers');
    await page.click('button[type="submit"]');
    await expect(page.locator('.alert-success, .flash-success, [class*="success"]')).toBeVisible();
  });

  test('2 — Add menu item', async ({ page }) => {
    await page.goto(`${TENANT_URL}/manager/menu-items/create`);
    await page.fill('input[name="name"]', 'Agent Test Burger');
    await page.fill('input[name="price"]', '499');
    // Pick first available category
    await page.selectOption('select[name="category_id"]', { index: 1 });
    await page.click('button[type="submit"]');
    await expect(page.locator('[class*="success"]')).toBeVisible();
  });

  test('3 — Create a deal', async ({ page }) => {
    await page.goto(`${TENANT_URL}/manager/deals/create`);
    await page.fill('input[name="name"]', 'Agent Test Deal');
    await page.fill('input[name="price"]', '999');
    await page.click('button[type="submit"]');
    await expect(page.locator('[class*="success"]')).toBeVisible();
  });

  test('4 — POS: add item and cash checkout', async ({ page }) => {
    await page.goto(`${TENANT_URL}/manager/pos`);
    // Click first item in the POS grid
    const firstItem = page.locator('[data-pos-item], .pos-item, .menu-item-card').first();
    await expect(firstItem).toBeVisible({ timeout: 8000 });
    await firstItem.click();
    // Cart should show 1 item
    await expect(page.locator('[data-cart-count], .cart-count, #cart-total')).toBeVisible();
    // Checkout
    const checkoutBtn = page.locator('button:has-text("Checkout"), button:has-text("Pay"), #pos-checkout');
    await expect(checkoutBtn).toBeVisible();
    await checkoutBtn.click();
    // Select cash
    const cashOption = page.locator('button:has-text("Cash"), input[value="cash"]').first();
    if (await cashOption.isVisible()) await cashOption.click();
    // Confirm
    const confirmBtn = page.locator('button:has-text("Confirm"), button:has-text("Complete"), button[type="submit"]').last();
    await confirmBtn.click();
    // Expect success receipt or redirect
    await expect(page).not.toHaveURL(/error/);
  });

  test('5 — View sales report', async ({ page }) => {
    await page.goto(`${TENANT_URL}/manager/reports`);
    await expect(page.locator('h1, h2').first()).toBeVisible();
    // Page should load without errors
    await expect(page.locator('body')).not.toContainText('500');
    await expect(page.locator('body')).not.toContainText('Whoops');
  });

  test('6 — Stock adjustment', async ({ page }) => {
    await page.goto(`${TENANT_URL}/manager/stock-adjustments`);
    await expect(page.locator('body')).not.toContainText('403');
    await expect(page.locator('body')).not.toContainText('500');
  });

});
