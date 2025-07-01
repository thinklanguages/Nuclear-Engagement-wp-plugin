import { test, expect } from '@playwright/test';

test.describe('Simple Playwright Test', () => {
  test('basic test to verify playwright works', async ({ page }) => {
    // Skip if no WordPress environment
    test.skip(!process.env.CI, 'Skipping without WordPress environment');
    
    await page.goto('https://playwright.dev/');
    await expect(page).toHaveTitle(/Playwright/);
  });
});