// @ts-check
/**
 * Codeibex Browser Test Agent: Medical / Clinic Manager
 *
 * Simulates a clinic manager: patients, appointments, prescriptions, POS.
 *
 * Run: npx playwright test tests/Browser/agents/medical-manager.spec.js --headed
 * Env vars: BASE_URL, MEDICAL_SLUG, MEDICAL_EMAIL, MEDICAL_PASSWORD
 */
const { test, expect } = require('@playwright/test');

const BASE_URL   = process.env.BASE_URL         || 'http://localhost:8000';
const SLUG       = process.env.MEDICAL_SLUG     || 'foodclinic';
const EMAIL      = process.env.MEDICAL_EMAIL    || 'manager@foodclinic.test';
const PASSWORD   = process.env.MEDICAL_PASSWORD || 'password';
const TENANT_URL = `${BASE_URL}/${SLUG}`;

test.describe('Medical Manager Agent', () => {

  test.beforeEach(async ({ page }) => {
    await page.goto(`${TENANT_URL}/login`);
    await page.fill('input[name="email"]', EMAIL);
    await page.fill('input[name="password"]', PASSWORD);
    await page.click('button[type="submit"]');
    await expect(page).toHaveURL(/dashboard|home/);
  });

  test('1 — Register new patient', async ({ page }) => {
    await page.goto(`${TENANT_URL}/manager/patients/create`);
    await expect(page.locator('body')).not.toContainText('403');
    const nameField = page.locator('input[name="name"]').first();
    if (await nameField.isVisible()) {
      await nameField.fill('Browser Test Patient');
      const phoneField = page.locator('input[name="phone"]').first();
      if (await phoneField.isVisible()) await phoneField.fill('03001112222');
      await page.click('button[type="submit"]');
      await expect(page.locator('[class*="success"]')).toBeVisible();
    }
  });

  test('2 — Book appointment', async ({ page }) => {
    await page.goto(`${TENANT_URL}/manager/appointments/create`);
    await expect(page.locator('body')).not.toContainText('403');
    await expect(page.locator('body')).not.toContainText('500');
    // Fill if form is rendered
    const doctorSelect = page.locator('select[name="doctor_id"]').first();
    if (await doctorSelect.isVisible()) {
      await doctorSelect.selectOption({ index: 1 });
      const dateField = page.locator('input[name="appointment_date"], input[type="date"]').first();
      if (await dateField.isVisible()) {
        await dateField.fill(new Date().toISOString().slice(0, 10));
      }
      await page.click('button[type="submit"]');
      await expect(page.locator('[class*="success"], .alert')).toBeVisible();
    }
  });

  test('3 — Create prescription', async ({ page }) => {
    await page.goto(`${TENANT_URL}/manager/prescriptions/create`);
    await expect(page.locator('body')).not.toContainText('403');
    await expect(page.locator('body')).not.toContainText('500');
  });

  test('4 — Medical POS: dispense medicine', async ({ page }) => {
    await page.goto(`${TENANT_URL}/manager/pos`);
    await expect(page.locator('body')).not.toContainText('403');
    await expect(page.locator('body')).not.toContainText('500');
    const firstItem = page.locator('[data-pos-item], .pos-item, .menu-item-card').first();
    if (await firstItem.isVisible({ timeout: 5000 })) {
      await firstItem.click();
      const checkoutBtn = page.locator('button:has-text("Checkout"), button:has-text("Pay"), #pos-checkout');
      if (await checkoutBtn.isVisible()) {
        await checkoutBtn.click();
      }
    }
  });

  test('5 — View medical records', async ({ page }) => {
    await page.goto(`${TENANT_URL}/manager/medical-records`);
    await expect(page.locator('body')).not.toContainText('500');
    await expect(page.locator('body')).not.toContainText('403');
  });

});
