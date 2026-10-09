// @ts-check
/**
 * Playwright config for Codeibex browser testing agents.
 *
 * Install: npm install --save-dev @playwright/test
 * Install browsers: npx playwright install chromium
 *
 * Run all agents:    npx playwright test --config=playwright.agents.config.js
 * Run one agent:     npx playwright test tests/Browser/agents/restaurant-manager.spec.js --headed
 * Run in headed:     npx playwright test --config=playwright.agents.config.js --headed
 *
 * Environment variables (can also use .env.testing):
 *   BASE_URL            - App base URL (default: http://localhost:8000)
 *   RESTAURANT_SLUG     - Slug of the seeded restaurant tenant
 *   MEDICAL_SLUG        - Slug of the seeded medical/clinic tenant
 *   HOSPITAL_SLUG       - Slug of the seeded professional hospital tenant
 *   RETAIL_SLUG         - Slug of the seeded general store tenant
 *   MANAGER_EMAIL       - Restaurant manager login
 *   MANAGER_PASSWORD    - Restaurant manager password
 *   MEDICAL_EMAIL       - Medical manager login
 *   HOSPITAL_EMAIL      - Hospital admin login
 *   RETAIL_EMAIL        - Retail manager login
 *   CEO_EMAIL           - CEO login
 *   SUPER_EMAIL         - Super admin login
 *   CUSTOMER_EMAIL      - Pre-seeded customer login (optional)
 */

const { defineConfig, devices } = require('@playwright/test');

module.exports = defineConfig({
  testDir: './tests/Browser/agents',
  timeout: 30_000,
  retries: 1,
  workers: 1, // run agents sequentially to avoid DB conflicts

  reporter: [
    ['list'],
    ['html', { outputFolder: 'tests/Browser/reports', open: 'never' }],
  ],

  use: {
    baseURL: process.env.BASE_URL || 'http://localhost:8000',
    headless: true,
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    trace: 'retain-on-failure',
  },

  projects: [
    {
      name: 'Super Admin',
      testMatch: 'super-admin.spec.js',
      use: { ...devices['Desktop Chrome'] },
    },
    {
      name: 'Restaurant Manager',
      testMatch: 'restaurant-manager.spec.js',
      use: { ...devices['Desktop Chrome'] },
    },
    {
      name: 'Restaurant Customer',
      testMatch: 'restaurant-customer.spec.js',
      use: { ...devices['Desktop Chrome'] },
    },
    {
      name: 'Medical Manager',
      testMatch: 'medical-manager.spec.js',
      use: { ...devices['Desktop Chrome'] },
    },
    {
      name: 'Hospital Admin',
      testMatch: 'hospital-admin.spec.js',
      use: { ...devices['Desktop Chrome'] },
    },
    {
      name: 'Retail Manager',
      testMatch: 'retail-manager.spec.js',
      use: { ...devices['Desktop Chrome'] },
    },
    {
      name: 'CEO',
      testMatch: 'ceo.spec.js',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
});
