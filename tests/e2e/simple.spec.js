import { test, expect } from '@playwright/test';

test.describe('Simple Playwright Test', () => {
  test('basic test to verify playwright works', async ({ page }) => {
    // This test verifies that Playwright is working correctly
    await page.goto('https://playwright.dev/');
    await expect(page).toHaveTitle(/Playwright/);
  });
});