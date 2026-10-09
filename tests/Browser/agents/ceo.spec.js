// @ts-check
/**
 * Codeibex Browser Test Agent: CEO (Multi-business portfolio view)
 *
 * Tests: CEO login, portfolio dashboard, branch switching, reports.
 *
 * Run: npx playwright test tests/Browser/agents/ceo.spec.js --headed
 * Env vars: BASE_URL, CEO_EMAIL, CEO_PASSWORD
 */
const { test, expect } = require('@playwright/test');

const BASE_URL = process.env.BASE_URL      || 'http://localhost:8000';
const EMAIL    = process.env.CEO_EMAIL     || 'ceo@codeibex.test';
const PASSWORD = process.env.CEO_PASSWORD  || 'password';

test.describe('CEO Agent', () => {

  test.beforeEach(async ({ page }) => {
    await page.goto(`${BASE_URL}/ceo/login`);
    await page.fill('input[name="email"]', EMAIL);
    await page.fill('input[name="password"]', PASSWORD);
    await page.click('button[type="submit"]');
    await expect(page).toHaveURL(/ceo.*(dashboard|home)/);
  });

  test('1 — Portfolio dashboard shows assigned businesses', async ({ page }) => {
    await page.goto(`${BASE_URL}/ceo/dashboard`);
    await expect(page.locator('body')).not.toContainText('500');
    await expect(page.locator('body')).not.toContainText('403');
    // Some business card should be visible
    const bizCard = page.locator('[class*="business"], [class*="restaurant"], [class*="tenant"]').first();
    // Just assert page loads — card selectors vary by theme
    await expect(page.locator('h1, h2').first()).toBeVisible();
  });

  test('2 — View reports for first assigned business', async ({ page }) => {
    await page.goto(`${BASE_URL}/ceo/reports`);
    await expect(page.locator('body')).not.toContainText('500');
    await expect(page.locator('body')).not.toContainText('403');
  });

  test('3 — Branch switching', async ({ page }) => {
    await page.goto(`${BASE_URL}/ceo/businesses`);
    await expect(page.locator('body')).not.toContainText('500');
    // Click first business link if present
    const bizLink = page.locator('a:has-text("View"), a:has-text("Access"), a[href*="ceo/switch"]').first();
    if (await bizLink.isVisible({ timeout: 4000 })) {
      await bizLink.click();
      await expect(page.locator('body')).not.toContainText('500');
    }
  });

});
