import { test, expect } from '@playwright/test';

test.describe('Nuclear Engagement Web Workflows', () => {
  let adminPage;
  let frontendPage;

  test.beforeAll(async ({ browser }) => {
    // Set up admin and frontend contexts
    const adminContext = await browser.newContext({
      httpCredentials: {
        username: 'admin',
        password: 'admin'
      }
    });
    
    const frontendContext = await browser.newContext();
    
    adminPage = await adminContext.newPage();
    frontendPage = await frontendContext.newPage();
  });

  test.beforeEach(async () => {
    // Navigate to WordPress admin
    await adminPage.goto('http://localhost:8080/wp-admin');
    
    // Login if needed
    const loginForm = adminPage.locator('#loginform');
    if (await loginForm.isVisible()) {
      await adminPage.fill('#user_login', 'admin');
      await adminPage.fill('#user_pass', 'admin');
      await adminPage.click('#wp-submit');
    }
  });

  test('Complete plugin setup workflow', async () => {
    // Navigate to Nuclear Engagement settings
    await adminPage.goto('http://localhost:8080/wp-admin/admin.php?page=nuclear-engagement');
    
    // Check if setup is needed
    const setupButton = adminPage.locator('[data-testid="setup-button"]');
    if (await setupButton.isVisible()) {
      await setupButton.click();
      
      // Fill in API key
      await adminPage.fill('[data-testid="api-key-input"]', 'test-api-key-123');
      await adminPage.click('[data-testid="connect-button"]');
      
      // Wait for connection success
      await expect(adminPage.locator('[data-testid="connection-status"]')).toHaveText('Connected');
      
      // Generate app password
      await adminPage.click('[data-testid="generate-password-button"]');
      await expect(adminPage.locator('[data-testid="app-password-status"]')).toHaveText('Generated');
    }
    
    // Verify setup completion
    await expect(adminPage.locator('[data-testid="setup-complete"]')).toBeVisible();
  });

  test('Content generation workflow', async () => {
    // Navigate to post editor
    await adminPage.goto('http://localhost:8080/wp-admin/post-new.php');
    
    // Create a test post
    await adminPage.fill('#title', 'Test Post for Nuclear Engagement');
    await adminPage.fill('#content', 'This is test content for generating quiz and summary.');
    
    // Publish the post
    await adminPage.click('#publish');
    await expect(adminPage.locator('.notice-success')).toBeVisible();
    
    // Navigate to Nuclear Engagement generation page
    await adminPage.goto('http://localhost:8080/wp-admin/admin.php?page=nuclear-engagement-generate');
    
    // Select the post for generation
    await adminPage.selectOption('[data-testid="post-select"]', { label: 'Test Post for Nuclear Engagement' });
    
    // Select generation type
    await adminPage.check('[data-testid="generate-quiz"]');
    await adminPage.check('[data-testid="generate-summary"]');
    
    // Start generation
    await adminPage.click('[data-testid="start-generation"]');
    
    // Wait for generation to complete
    await expect(adminPage.locator('[data-testid="generation-status"]')).toHaveText('Completed', { timeout: 30000 });
    
    // Verify generation results
    await expect(adminPage.locator('[data-testid="quiz-generated"]')).toBeVisible();
    await expect(adminPage.locator('[data-testid="summary-generated"]')).toBeVisible();
  });

  test('Quiz interaction workflow', async () => {
    // Navigate to a post with quiz on frontend
    await frontendPage.goto('http://localhost:8080/test-post-for-nuclear-engagement/');
    
    // Check if quiz is present
    const quiz = frontendPage.locator('[data-testid="nuclen-quiz"]');
    await expect(quiz).toBeVisible();
    
    // Answer quiz questions
    const questions = frontendPage.locator('[data-testid="quiz-question"]');
    const questionCount = await questions.count();
    
    for (let i = 0; i < questionCount; i++) {
      const question = questions.nth(i);
      const answers = question.locator('[data-testid="quiz-answer"]');
      
      // Select first answer
      await answers.first().click();
      
      // Check if feedback is shown
      await expect(question.locator('[data-testid="answer-feedback"]')).toBeVisible();
      
      // Continue to next question if available
      const nextButton = question.locator('[data-testid="next-question"]');
      if (await nextButton.isVisible()) {
        await nextButton.click();
      }
    }
    
    // Check quiz completion
    await expect(frontendPage.locator('[data-testid="quiz-complete"]')).toBeVisible();
    await expect(frontendPage.locator('[data-testid="quiz-score"]')).toBeVisible();
  });

  test('Table of Contents interaction workflow', async () => {
    // Navigate to a post with TOC
    await frontendPage.goto('http://localhost:8080/test-post-for-nuclear-engagement/');
    
    // Check if TOC is present
    const toc = frontendPage.locator('[data-testid="nuclen-toc"]');
    await expect(toc).toBeVisible();
    
    // Test TOC navigation
    const tocLinks = toc.locator('a');
    const linkCount = await tocLinks.count();
    
    if (linkCount > 0) {
      // Click first TOC link
      await tocLinks.first().click();
      
      // Verify scroll to section
      await frontendPage.waitForTimeout(1000); // Wait for scroll animation
      
      // Check if sticky TOC works during scroll
      await frontendPage.evaluate(() => window.scrollTo(0, 500));
      await expect(toc.locator('[data-testid="sticky-toc"]')).toBeVisible();
    }
  });

  test('Summary display workflow', async () => {
    // Navigate to a post with summary
    await frontendPage.goto('http://localhost:8080/test-post-for-nuclear-engagement/');
    
    // Check if summary is present
    const summary = frontendPage.locator('[data-testid="nuclen-summary"]');
    await expect(summary).toBeVisible();
    
    // Test summary toggle if available
    const toggleButton = summary.locator('[data-testid="summary-toggle"]');
    if (await toggleButton.isVisible()) {
      await toggleButton.click();
      
      // Check if summary content toggles
      const summaryContent = summary.locator('[data-testid="summary-content"]');
      await expect(summaryContent).toBeHidden();
      
      // Toggle back
      await toggleButton.click();
      await expect(summaryContent).toBeVisible();
    }
  });

  test('Admin bulk generation workflow', async () => {
    // Navigate to bulk generation page
    await adminPage.goto('http://localhost:8080/wp-admin/admin.php?page=nuclear-engagement-bulk');
    
    // Select posts for bulk generation
    await adminPage.check('[data-testid="select-all-posts"]');
    
    // Select generation options
    await adminPage.check('[data-testid="bulk-generate-quiz"]');
    await adminPage.check('[data-testid="bulk-generate-summary"]');
    
    // Set batch size
    await adminPage.selectOption('[data-testid="batch-size"]', '5');
    
    // Start bulk generation
    await adminPage.click('[data-testid="start-bulk-generation"]');
    
    // Monitor progress
    const progressBar = adminPage.locator('[data-testid="bulk-progress"]');
    await expect(progressBar).toBeVisible();
    
    // Wait for completion
    await expect(adminPage.locator('[data-testid="bulk-complete"]')).toBeVisible({ timeout: 60000 });
    
    // Check results summary
    await expect(adminPage.locator('[data-testid="success-count"]')).toContainText(/\d+/);
    await expect(adminPage.locator('[data-testid="error-count"]')).toContainText(/\d+/);
  });

  test('Settings management workflow', async () => {
    // Navigate to settings page
    await adminPage.goto('http://localhost:8080/wp-admin/admin.php?page=nuclear-engagement-settings');
    
    // Test API settings
    await adminPage.fill('[data-testid="api-endpoint"]', 'https://api.example.com');
    await adminPage.fill('[data-testid="api-timeout"]', '30');
    
    // Test generation settings
    await adminPage.selectOption('[data-testid="default-quiz-questions"]', '5');
    await adminPage.check('[data-testid="auto-generate-enabled"]');
    
    // Test display settings
    await adminPage.check('[data-testid="show-quiz-by-default"]');
    await adminPage.check('[data-testid="show-toc-by-default"]');
    
    // Save settings
    await adminPage.click('[data-testid="save-settings"]');
    
    // Verify save success
    await expect(adminPage.locator('[data-testid="settings-saved"]')).toBeVisible();
    
    // Reload page and verify settings persisted
    await adminPage.reload();
    await expect(adminPage.locator('[data-testid="auto-generate-enabled"]')).toBeChecked();
    await expect(adminPage.locator('[data-testid="show-quiz-by-default"]')).toBeChecked();
  });

  test('Error handling workflow', async () => {
    // Test with invalid API key
    await adminPage.goto('http://localhost:8080/wp-admin/admin.php?page=nuclear-engagement');
    
    // Reset API connection
    await adminPage.click('[data-testid="reset-connection"]');
    
    // Enter invalid API key
    await adminPage.fill('[data-testid="api-key-input"]', 'invalid-key');
    await adminPage.click('[data-testid="connect-button"]');
    
    // Verify error handling
    await expect(adminPage.locator('[data-testid="connection-error"]')).toBeVisible();
    await expect(adminPage.locator('[data-testid="error-message"]')).toContainText('Invalid API key');
    
    // Test generation with invalid post
    await adminPage.goto('http://localhost:8080/wp-admin/admin.php?page=nuclear-engagement-generate');
    
    // Try to generate for non-existent post
    await adminPage.selectOption('[data-testid="post-select"]', '999999');
    await adminPage.click('[data-testid="start-generation"]');
    
    // Verify error handling
    await expect(adminPage.locator('[data-testid="generation-error"]')).toBeVisible();
  });

  test('Responsive design workflow', async () => {
    // Test mobile viewport
    await frontendPage.setViewportSize({ width: 375, height: 667 });
    await frontendPage.goto('http://localhost:8080/test-post-for-nuclear-engagement/');
    
    // Check if components are mobile-responsive
    const quiz = frontendPage.locator('[data-testid="nuclen-quiz"]');
    if (await quiz.isVisible()) {
      const quizBox = await quiz.boundingBox();
      expect(quizBox.width).toBeLessThan(375);
    }
    
    const toc = frontendPage.locator('[data-testid="nuclen-toc"]');
    if (await toc.isVisible()) {
      // TOC should be collapsible on mobile
      await expect(toc.locator('[data-testid="toc-toggle"]')).toBeVisible();
    }
    
    // Test tablet viewport
    await frontendPage.setViewportSize({ width: 768, height: 1024 });
    await frontendPage.reload();
    
    // Verify layout adapts to tablet
    if (await quiz.isVisible()) {
      const quizBox = await quiz.boundingBox();
      expect(quizBox.width).toBeLessThan(768);
    }
    
    // Test desktop viewport
    await frontendPage.setViewportSize({ width: 1920, height: 1080 });
    await frontendPage.reload();
    
    // Verify desktop layout
    if (await toc.isVisible()) {
      await expect(toc.locator('[data-testid="sticky-toc"]')).toBeVisible();
    }
  });

  test('Performance workflow', async () => {
    // Navigate to content page
    await frontendPage.goto('http://localhost:8080/test-post-for-nuclear-engagement/');
    
    // Measure page load performance
    const performanceEntries = await frontendPage.evaluate(() => {
      return JSON.stringify(performance.getEntriesByType('navigation'));
    });
    
    const navigation = JSON.parse(performanceEntries)[0];
    
    // Assert reasonable load times
    expect(navigation.loadEventEnd - navigation.loadEventStart).toBeLessThan(3000);
    expect(navigation.domContentLoadedEventEnd - navigation.domContentLoadedEventStart).toBeLessThan(2000);
    
    // Test JavaScript execution performance
    const jsPerformance = await frontendPage.evaluate(() => {
      const start = performance.now();
      
      // Simulate quiz interaction
      const quizElements = document.querySelectorAll('[data-testid="quiz-question"]');
      quizElements.forEach(el => el.classList.add('test-class'));
      
      return performance.now() - start;
    });
    
    expect(jsPerformance).toBeLessThan(100); // Should execute in under 100ms
  });

  test.afterAll(async () => {
    await adminPage.close();
    await frontendPage.close();
  });
});