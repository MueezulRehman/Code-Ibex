// @ts-check
/**
 * Codeibex Browser Test Agent: Hospital Admin
 *
 * Simulates a hospital admin: admissions, beds, lab/radiology, billing, discharge.
 *
 * Run: npx playwright test tests/Browser/agents/hospital-admin.spec.js --headed
 * Env vars: BASE_URL, HOSPITAL_SLUG, HOSPITAL_EMAIL, HOSPITAL_PASSWORD
 */
const { test, expect } = require('@playwright/test');

const BASE_URL   = process.env.BASE_URL           || 'http://localhost:8000';
const SLUG       = process.env.HOSPITAL_SLUG      || 'professional-hospital';
const EMAIL      = process.env.HOSPITAL_EMAIL     || 'admin@hospital.test';
const PASSWORD   = process.env.HOSPITAL_PASSWORD  || 'password';
const TENANT_URL = `${BASE_URL}/${SLUG}`;

async function login(page) {
  await page.goto(`${TENANT_URL}/login`);
  await page.fill('input[name="email"]', EMAIL);
  await page.fill('input[name="password"]', PASSWORD);
  await page.click('button[type="submit"]');
  await expect(page).toHaveURL(/dashboard|home/);
}

test.describe('Hospital Admin Agent', () => {

  test.beforeEach(async ({ page }) => { await login(page); });

  test('1 — Patient list loads', async ({ page }) => {
    await page.goto(`${TENANT_URL}/manager/patients`);
    await expect(page.locator('body')).not.toContainText('500');
    await expect(page.locator('body')).not.toContainText('403');
  });

  test('2 — Ward / bed management', async ({ page }) => {
    await page.goto(`${TENANT_URL}/manager/wards`);
    await expect(page.locator('body')).not.toContainText('500');
    await expect(page.locator('body')).not.toContainText('403');
  });

  test('3 — Admit patient', async ({ page }) => {
    await page.goto(`${TENANT_URL}/manager/admissions/create`);
    await expect(page.locator('body')).not.toContainText('500');
    const patientSelect = page.locator('select[name="patient_id"]').first();
    if (await patientSelect.isVisible({ timeout: 5000 })) {
      await patientSelect.selectOption({ index: 1 });
      const submitBtn = page.locator('button[type="submit"]').first();
      await submitBtn.click();
      await expect(page.locator('[class*="success"], .alert')).toBeVisible();
    }
  });

  test('4 — Lab orders list', async ({ page }) => {
    await page.goto(`${TENANT_URL}/manager/lab-orders`);
    await expect(page.locator('body')).not.toContainText('500');
    await expect(page.locator('body')).not.toContainText('403');
  });

  test('5 — Radiology orders list', async ({ page }) => {
    await page.goto(`${TENANT_URL}/manager/radiology-orders`);
    await expect(page.locator('body')).not.toContainText('500');
    await expect(page.locator('body')).not.toContainText('403');
  });

  test('6 — Hospital billing list', async ({ page }) => {
    await page.goto(`${TENANT_URL}/manager/hospital-billing`);
    await expect(page.locator('body')).not.toContainText('500');
    await expect(page.locator('body')).not.toContainText('403');
  });

  test('7 — Operational reports', async ({ page }) => {
    await page.goto(`${TENANT_URL}/manager/hospital-reports`);
    await expect(page.locator('body')).not.toContainText('500');
    await expect(page.locator('body')).not.toContainText('403');
  });

});
