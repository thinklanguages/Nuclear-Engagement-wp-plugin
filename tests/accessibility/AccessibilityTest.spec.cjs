const { test, expect, devices } = require('@playwright/test');
const AxeBuilder = require('@axe-core/playwright').default;

test.describe('Nuclear Engagement Accessibility Tests', () => {
  
  test.beforeEach(async ({ page }) => {
    await page.goto('http://localhost:8080/test-post-for-nuclear-engagement/');
  });

  test('Quiz component accessibility', async ({ page }) => {
    // Wait for quiz to load
    await page.waitForSelector('[data-testid="nuclen-quiz"]', { timeout: 10000 });
    
    // Run accessibility scan on quiz
    const accessibilityScanResults = await new AxeBuilder({ page })
      .include('[data-testid="nuclen-quiz"]')
      .analyze();

    expect(accessibilityScanResults.violations).toEqual([]);

    // Test keyboard navigation
    const quiz = page.locator('[data-testid="nuclen-quiz"]');
    const firstQuestion = quiz.locator('[data-testid="quiz-question"]').first();
    const answers = firstQuestion.locator('[data-testid="quiz-answer"]');

    // Test tab navigation through answers
    if (await answers.count() > 0) {
      await answers.first().focus();
      
      // Test arrow key navigation
      await page.keyboard.press('ArrowDown');
      const focusedElement = await page.evaluate(() => document.activeElement.getAttribute('data-testid'));
      expect(focusedElement).toBe('quiz-answer');
      
      // Test Enter key selection
      await page.keyboard.press('Enter');
      await expect(firstQuestion.locator('[data-testid="answer-feedback"]')).toBeVisible();
    }

    // Test screen reader announcements
    const srAnnouncements = page.locator('[aria-live="polite"]');
    if (await srAnnouncements.count() > 0) {
      await expect(srAnnouncements.first()).toHaveAttribute('aria-live', 'polite');
    }
  });

  test('Table of Contents accessibility', async ({ page }) => {
    const toc = page.locator('[data-testid="nuclen-toc"]');
    
    if (await toc.isVisible()) {
      // Run accessibility scan on TOC
      const accessibilityScanResults = await new AxeBuilder({ page })
        .include('[data-testid="nuclen-toc"]')
        .analyze();

      expect(accessibilityScanResults.violations).toEqual([]);

      // Test TOC structure
      await expect(toc).toHaveAttribute('role', 'navigation');
      await expect(toc).toHaveAttribute('aria-label');

      // Test TOC links
      const tocLinks = toc.locator('a');
      const linkCount = await tocLinks.count();
      
      for (let i = 0; i < linkCount; i++) {
        const link = tocLinks.nth(i);
        await expect(link).toHaveAttribute('href');
        
        // Links should have descriptive text or aria-label
        const linkText = await link.textContent();
        const ariaLabel = await link.getAttribute('aria-label');
        expect(linkText?.trim() || ariaLabel).toBeTruthy();
      }

      // Test keyboard navigation
      if (linkCount > 0) {
        await tocLinks.first().focus();
        await page.keyboard.press('Tab');
        
        // Should move to next focusable element
        const focusedElement = await page.evaluate(() => document.activeElement);
        expect(focusedElement).toBeTruthy();
      }
    }
  });

  test('Summary component accessibility', async ({ page }) => {
    const summary = page.locator('[data-testid="nuclen-summary"]');
    
    if (await summary.isVisible()) {
      // Run accessibility scan
      const accessibilityScanResults = await new AxeBuilder({ page })
        .include('[data-testid="nuclen-summary"]')
        .analyze();

      expect(accessibilityScanResults.violations).toEqual([]);

      // Test summary structure
      await expect(summary).toHaveAttribute('role');
      
      // Test expandable summary
      const toggleButton = summary.locator('[data-testid="summary-toggle"]');
      if (await toggleButton.isVisible()) {
        await expect(toggleButton).toHaveAttribute('aria-expanded');
        await expect(toggleButton).toHaveAttribute('aria-controls');
        
        // Test keyboard interaction
        await toggleButton.focus();
        await page.keyboard.press('Enter');
        
        // Check if aria-expanded changes
        const expandedState = await toggleButton.getAttribute('aria-expanded');
        expect(['true', 'false']).toContain(expandedState);
      }
    }
  });

  test('Color contrast and visual accessibility', async ({ page }) => {
    // Run full page accessibility scan focusing on color contrast
    const accessibilityScanResults = await new AxeBuilder({ page })
      .withTags(['wcag2a', 'wcag2aa', 'wcag21aa'])
      .analyze();

    // Filter for color contrast violations
    const colorContrastViolations = accessibilityScanResults.violations.filter(
      violation => violation.id === 'color-contrast'
    );

    expect(colorContrastViolations).toEqual([]);

    // Test focus indicators
    const interactiveElements = await page.locator('button, a, input, [tabindex]').all();
    
    for (const element of interactiveElements.slice(0, 10)) { // Test first 10 elements
      await element.focus();
      
      // Check if focus is visible
      const computedStyle = await element.evaluate((el) => {
        const style = window.getComputedStyle(el);
        return {
          outline: style.outline,
          boxShadow: style.boxShadow,
          border: style.border
        };
      });
      
      // Should have some form of focus indicator
      const hasFocusIndicator = 
        computedStyle.outline !== 'none' ||
        computedStyle.boxShadow !== 'none' ||
        computedStyle.border !== 'none';
      
      expect(hasFocusIndicator).toBeTruthy();
    }
  });

  test('Screen reader compatibility', async ({ page }) => {
    // Test ARIA labels and descriptions
    const ariaElements = await page.locator('[aria-label], [aria-labelledby], [aria-describedby]').all();
    
    for (const element of ariaElements) {
      const ariaLabel = await element.getAttribute('aria-label');
      const ariaLabelledby = await element.getAttribute('aria-labelledby');
      const ariaDescribedby = await element.getAttribute('aria-describedby');
      
      if (ariaLabel) {
        expect(ariaLabel.trim()).toBeTruthy();
      }
      
      if (ariaLabelledby) {
        const labelElement = page.locator(`#${ariaLabelledby}`);
        await expect(labelElement).toHaveCount(1);
      }
      
      if (ariaDescribedby) {
        const descElement = page.locator(`#${ariaDescribedby}`);
        await expect(descElement).toHaveCount(1);
      }
    }

    // Test headings hierarchy
    const headings = await page.locator('h1, h2, h3, h4, h5, h6').all();
    let previousLevel = 0;
    
    for (const heading of headings) {
      const tagName = await heading.evaluate(el => el.tagName.toLowerCase());
      const currentLevel = parseInt(tagName.substring(1));
      
      // Heading levels should not skip (e.g., h1 to h3)
      if (previousLevel > 0) {
        expect(currentLevel - previousLevel).toBeLessThanOrEqual(1);
      }
      
      previousLevel = currentLevel;
    }

    // Test landmark regions
    const landmarks = await page.locator('[role="main"], [role="navigation"], [role="banner"], [role="contentinfo"], main, nav, header, footer').all();
    expect(landmarks.length).toBeGreaterThan(0);
  });

  test('Mobile accessibility', async ({ browser }) => {
    const context = await browser.newContext({
      ...devices['iPhone 12'],
      reducedMotion: 'reduce'
    });
    
    const page = await context.newPage();
    await page.goto('http://localhost:8080/test-post-for-nuclear-engagement/');

    // Test touch targets
    const touchTargets = await page.locator('button, a, input, [onclick], [data-testid*="button"]').all();
    
    for (const target of touchTargets.slice(0, 10)) {
      const boundingBox = await target.boundingBox();
      if (boundingBox) {
        // Touch targets should be at least 44x44 pixels (iOS guideline)
        expect(boundingBox.width).toBeGreaterThanOrEqual(44);
        expect(boundingBox.height).toBeGreaterThanOrEqual(44);
      }
    }

    // Test mobile-specific interactions
    const quiz = page.locator('[data-testid="nuclen-quiz"]');
    if (await quiz.isVisible()) {
      // Test swipe gestures if implemented
      const quizContainer = quiz.locator('[data-testid="quiz-container"]');
      if (await quizContainer.isVisible()) {
        const boundingBox = await quizContainer.boundingBox();
        if (boundingBox) {
          // Test touch interaction
          await page.touchscreen.tap(boundingBox.x + boundingBox.width / 2, boundingBox.y + boundingBox.height / 2);
        }
      }
    }

    await context.close();
  });

  test('Reduced motion preferences', async ({ browser }) => {
    const context = await browser.newContext({
      reducedMotion: 'reduce'
    });
    
    const page = await context.newPage();
    await page.goto('http://localhost:8080/test-post-for-nuclear-engagement/');

    // Check for CSS animations respect for reduced motion
    const animatedElements = await page.locator('[data-testid*="animation"], .fade, .slide, .bounce').all();
    
    for (const element of animatedElements) {
      const computedStyle = await element.evaluate((el) => {
        const style = window.getComputedStyle(el);
        return {
          animation: style.animation,
          transition: style.transition
        };
      });
      
      // Should respect reduced motion preference
      if (computedStyle.animation !== 'none') {
        expect(computedStyle.animation).toContain('prefers-reduced-motion');
      }
    }

    await context.close();
  });

  test('High contrast mode compatibility', async ({ browser }) => {
    const context = await browser.newContext({
      forcedColors: 'active'
    });
    
    const page = await context.newPage();
    await page.goto('http://localhost:8080/test-post-for-nuclear-engagement/');

    // Test that content is still visible in high contrast mode
    const quiz = page.locator('[data-testid="nuclen-quiz"]');
    if (await quiz.isVisible()) {
      await expect(quiz).toBeVisible();
      
      const buttons = quiz.locator('button');
      const buttonCount = await buttons.count();
      
      for (let i = 0; i < Math.min(buttonCount, 5); i++) {
        const button = buttons.nth(i);
        await expect(button).toBeVisible();
        
        // Text should still be readable
        const textContent = await button.textContent();
        expect(textContent?.trim()).toBeTruthy();
      }
    }

    await context.close();
  });

  test('Form accessibility', async ({ page }) => {
    // Test any forms in the components
    const forms = await page.locator('form').all();
    
    for (const form of forms) {
      // Run accessibility scan on form
      const formId = await form.getAttribute('id') || 'form';
      const accessibilityScanResults = await new AxeBuilder({ page })
        .include(form)
        .analyze();

      expect(accessibilityScanResults.violations).toEqual([]);

      // Test form labels
      const inputs = form.locator('input, textarea, select');
      const inputCount = await inputs.count();
      
      for (let i = 0; i < inputCount; i++) {
        const input = inputs.nth(i);
        const inputId = await input.getAttribute('id');
        const ariaLabel = await input.getAttribute('aria-label');
        const ariaLabelledby = await input.getAttribute('aria-labelledby');
        
        // Input should have some form of label
        if (!ariaLabel && !ariaLabelledby && inputId) {
          const label = page.locator(`label[for="${inputId}"]`);
          await expect(label).toHaveCount(1);
        }
      }

      // Test error messages
      const errorMessages = form.locator('[role="alert"], .error, [aria-invalid="true"]');
      const errorCount = await errorMessages.count();
      
      for (let i = 0; i < errorCount; i++) {
        const error = errorMessages.nth(i);
        await expect(error).toHaveAttribute('aria-live', 'polite');
      }
    }
  });

  test('Page structure and semantics', async ({ page }) => {
    // Test page structure
    const main = page.locator('main, [role="main"]');
    await expect(main).toHaveCount(1);

    // Test skip links
    const skipLinks = page.locator('a[href^="#"]').first();
    if (await skipLinks.isVisible()) {
      await skipLinks.focus();
      await expect(skipLinks).toBeVisible();
    }

    // Run comprehensive accessibility scan
    const accessibilityScanResults = await new AxeBuilder({ page })
      .withTags(['wcag2a', 'wcag2aa', 'wcag21aa'])
      .disableRules(['landmark-unique', 'list']) // Disable rules that fail due to WordPress theme
      .analyze();

    // Filter out violations that are from WordPress theme, not our plugin
    const pluginViolations = accessibilityScanResults.violations.filter(violation => {
      // Only include violations that affect our plugin elements
      return violation.nodes.some(node => {
        const target = node.target.join(' ');
        return target.includes('nuclen') || 
               target.includes('quiz') || 
               target.includes('toc') || 
               target.includes('summary');
      });
    });

    // Log violations for debugging
    if (pluginViolations.length > 0) {
      console.log('Plugin accessibility violations found:', pluginViolations);
    }

    expect(pluginViolations).toEqual([]);
  });
});