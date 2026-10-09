// @ts-check
/**
 * Codeibex Browser Test Agent: Restaurant Customer
 *
 * Simulates a customer on the public storefront:
 * browse menu → cart → checkout → order tracking → register/login.
 *
 * Run: npx playwright test tests/Browser/agents/restaurant-customer.spec.js --headed
 * Env vars: BASE_URL, RESTAURANT_SLUG
 */
const { test, expect } = require('@playwright/test');

const BASE_URL   = process.env.BASE_URL        || 'http://localhost:8000';
const SLUG       = process.env.RESTAURANT_SLUG || 'tastehut';
const TENANT_URL = `${BASE_URL}/${SLUG}`;

// Unique email per run so registration doesn't clash
const TEST_EMAIL = `customer_${Date.now()}@test.local`;
const TEST_PASS  = 'TestPass123!';

test.describe('Restaurant Customer Agent', () => {

  test('1 — Browse storefront homepage', async ({ page }) => {
    await page.goto(TENANT_URL);
    await expect(page.locator('body')).not.toContainText('500');
    await expect(page.locator('body')).not.toContainText('Whoops');
    // Some content should be visible
    const menuSection = page.locator('.menu, [class*="menu"], [class*="item"]').first();
    await expect(menuSection).toBeVisible({ timeout: 8000 });
  });

  test('2 — Add items to cart and checkout', async ({ page }) => {
    await page.goto(TENANT_URL);
    // Add first available item
    const addBtn = page.locator('button:has-text("Add"), .add-to-cart, [data-add-to-cart]').first();
    await expect(addBtn).toBeVisible({ timeout: 8000 });
    await addBtn.click();
    // Go to checkout
    await page.goto(`${TENANT_URL}/checkout`);
    await expect(page.locator('body')).not.toContainText('500');
    // Fill checkout form if present
    const nameField = page.locator('input[name="customer_name"], input[placeholder*="name" i]').first();
    if (await nameField.isVisible()) {
      await nameField.fill('Test Customer');
    }
    const phoneField = page.locator('input[name="phone"], input[type="tel"]').first();
    if (await phoneField.isVisible()) {
      await phoneField.fill('03001234567');
    }
    const submitBtn = page.locator('button[type="submit"]:has-text("Order"), button:has-text("Place Order"), button:has-text("Confirm")').first();
    if (await submitBtn.isVisible()) {
      await submitBtn.click();
      // Should land on confirmation or tracking page
      await expect(page).not.toHaveURL(/error/);
    }
  });

  test('3 — Order lookup / tracking', async ({ page }) => {
    await page.goto(`${BASE_URL}/track`);
    await expect(page.locator('body')).not.toContainText('500');
    // Fill lookup form
    const lookupField = page.locator('input[name="phone"], input[name="order_number"], input[placeholder*="phone" i], input[placeholder*="order" i]').first();
    if (await lookupField.isVisible()) {
      await lookupField.fill('03001234567');
      const searchBtn = page.locator('button[type="submit"]').first();
      await searchBtn.click();
    }
  });

  test('4 — Customer registration', async ({ page }) => {
    await page.goto(`${TENANT_URL}/register`);
    await expect(page.locator('body')).not.toContainText('500');
    const nameField = page.locator('input[name="name"]').first();
    if (await nameField.isVisible()) {
      await nameField.fill('Browser Test Customer');
      await page.fill('input[name="email"]', TEST_EMAIL);
      await page.fill('input[name="phone"]', '03009876543');
      await page.fill('input[name="password"]', TEST_PASS);
      const confirmField = page.locator('input[name="password_confirmation"]');
      if (await confirmField.isVisible()) {
        await confirmField.fill(TEST_PASS);
      }
      await page.click('button[type="submit"]');
      // Should be redirected or show success
      await expect(page).not.toHaveURL(/register/);
    }
  });

  test('5 — Customer login', async ({ page }) => {
    await page.goto(`${TENANT_URL}/login`);
    // Use a pre-seeded customer if TEST_EMAIL registration isn't guaranteed
    const email = process.env.CUSTOMER_EMAIL || TEST_EMAIL;
    const pass  = process.env.CUSTOMER_PASSWORD || TEST_PASS;
    await page.fill('input[name="email"]', email);
    await page.fill('input[name="password"]', pass);
    await page.click('button[type="submit"]');
    await expect(page.locator('body')).not.toContainText('500');
  });

});
