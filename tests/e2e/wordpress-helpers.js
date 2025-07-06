/**
 * WordPress-specific E2E test utilities
 */
import { test as base, expect } from '@playwright/test';
import { 
  Admin, 
  Editor, 
  RequestUtils,
  login,
  logout,
  visitAdminPage,
  createNewPost
} from '@wordpress/e2e-test-utils-playwright';

export const test = base.extend({
  admin: async ({ page, requestUtils }, use) => {
    await use(new Admin({ page, requestUtils }));
  },
  editor: async ({ page }, use) => {
    await use(new Editor({ page }));
  },
  requestUtils: async ({ playwright }, use) => {
    const requestUtils = await RequestUtils.setup({
      playwright,
      baseURL: process.env.WP_BASE_URL || 'http://localhost:8080',
    });
    await use(requestUtils);
  },
});

export { expect };

/**
 * WordPress login helper
 */
export async function wpLogin(page, username = 'admin', password = 'password') {
  await login(page, username, password);
}

/**
 * WordPress logout helper
 */
export async function wpLogout(page) {
  await logout(page);
}

/**
 * Navigate to admin page helper
 */
export async function visitWPAdminPage(page, path) {
  await visitAdminPage(page, path);
}

/**
 * Create new WordPress post helper
 */
export async function createWPPost(page, title, content = '') {
  await createNewPost(page, { title, content });
}

/**
 * Nuclear Engagement specific helpers
 */
export class NuclearEngagementHelpers {
  constructor(page) {
    this.page = page;
  }

  async visitNuclearEngagementSettings() {
    await visitWPAdminPage(this.page, 'admin.php?page=nuclear-engagement');
  }

  async generateQuiz(postId) {
    await this.page.click(`[data-post-id="${postId}"] .generate-quiz`);
    await this.page.waitForSelector('.quiz-generated', { timeout: 30000 });
  }

  async generateSummary(postId) {
    await this.page.click(`[data-post-id="${postId}"] .generate-summary`);
    await this.page.waitForSelector('.summary-generated', { timeout: 30000 });
  }

  async expectQuizElements() {
    await expect(this.page.locator('.quiz-question')).toBeVisible();
    await expect(this.page.locator('.quiz-options')).toBeVisible();
  }

  async expectSummaryElements() {
    await expect(this.page.locator('.summary-content')).toBeVisible();
  }
}