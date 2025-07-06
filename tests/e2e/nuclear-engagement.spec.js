import { test, expect, wpLogin, NuclearEngagementHelpers } from './wordpress-helpers.js';

test.describe('Nuclear Engagement Plugin E2E Tests', () => {
  let helpers;

  test.beforeEach(async ({ page }) => {
    helpers = new NuclearEngagementHelpers(page);
    await wpLogin(page);
  });

  test('should access Nuclear Engagement settings page', async ({ page }) => {
    await helpers.visitNuclearEngagementSettings();
    await expect(page.locator('h1')).toContainText('Nuclear Engagement');
  });

  test('should display plugin dashboard elements', async ({ page }) => {
    await helpers.visitNuclearEngagementSettings();
    
    // Check for main dashboard elements
    await expect(page.locator('.nuclear-engagement-dashboard')).toBeVisible();
    await expect(page.locator('.generation-controls')).toBeVisible();
  });

  test('should handle quiz generation workflow', async ({ page }) => {
    // This test requires a WordPress installation with posts
    await page.goto('/wp-admin/edit.php');
    
    // Check if there are posts to work with
    const hasPosts = await page.locator('.wp-list-table tbody tr').count() > 0;
    
    if (hasPosts) {
      // Get first post ID
      const firstRow = page.locator('.wp-list-table tbody tr').first();
      const postId = await firstRow.getAttribute('id');
      
      if (postId) {
        await helpers.generateQuiz(postId.replace('post-', ''));
        await helpers.expectQuizElements();
      }
    } else {
      test.skip('No posts available for testing');
    }
  });

  test('should handle summary generation workflow', async ({ page }) => {
    await page.goto('/wp-admin/edit.php');
    
    const hasPosts = await page.locator('.wp-list-table tbody tr').count() > 0;
    
    if (hasPosts) {
      const firstRow = page.locator('.wp-list-table tbody tr').first();
      const postId = await firstRow.getAttribute('id');
      
      if (postId) {
        await helpers.generateSummary(postId.replace('post-', ''));
        await helpers.expectSummaryElements();
      }
    } else {
      test.skip('No posts available for testing');
    }
  });
});