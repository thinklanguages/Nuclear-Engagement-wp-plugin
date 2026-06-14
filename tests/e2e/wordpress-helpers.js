/**
 * WordPress-specific E2E test utilities
 *
 * Updated for @wordpress/e2e-test-utils-playwright >= 1.x, which exposes only
 * class-based utilities (Admin/Editor/PageUtils/RequestUtils). The old free
 * functions (login, logout, visitAdminPage, createNewPost) were removed, so the
 * page-based helpers below are implemented against the class API / raw page.
 */
import { test as base, expect } from '@playwright/test';
import {
  Admin,
  Editor,
  PageUtils,
  RequestUtils,
} from '@wordpress/e2e-test-utils-playwright';

const BASE_URL = process.env.WP_BASE_URL || 'http://localhost:8080';

export const test = base.extend({
  pageUtils: async ({ page }, use) => {
    await use(new PageUtils({ page }));
  },
  editor: async ({ page }, use) => {
    await use(new Editor({ page }));
  },
  requestUtils: async ({ playwright }, use) => {
    const requestUtils = await RequestUtils.setup({
      playwright,
      baseURL: BASE_URL,
    });
    await use(requestUtils);
  },
  admin: async ({ page, pageUtils, editor }, use) => {
    await use(new Admin({ page, pageUtils, editor }));
  },
});

export { expect };

/**
 * WordPress login helper.
 *
 * Submits the wp-login.php form directly so it works against any install
 * without relying on the removed library `login` free function.
 */
export async function wpLogin(page, username = 'admin', password = 'password') {
  await page.goto(`${BASE_URL}/wp-login.php`);
  await page.fill('#user_login', username);
  await page.fill('#user_pass', password);
  await page.click('#wp-submit');
  await page.waitForURL(/wp-admin/);
}

/**
 * WordPress logout helper.
 */
export async function wpLogout(page) {
  await page.goto(`${BASE_URL}/wp-login.php?action=logout`);
  const confirm = page.locator('a:has-text("log out")');
  if (await confirm.count()) {
    await confirm.first().click();
  }
}

/**
 * Navigate to admin page helper.
 */
export async function visitWPAdminPage(page, path) {
  await page.goto(`${BASE_URL}/wp-admin/${path}`);
}

/**
 * Create new WordPress post helper.
 */
export async function createWPPost(page, title, content = '') {
  const admin = new Admin({
    page,
    pageUtils: new PageUtils({ page }),
    editor: new Editor({ page }),
  });
  await admin.createNewPost({ title, content });
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
