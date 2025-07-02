# Testing Guide - Nuclear Engagement Plugin

> **Important Update (January 2025)**: Major testing improvements have been implemented. For the latest comprehensive testing documentation including all fixes, Docker solutions, and quick commands, see:
> - 📚 [TESTING_GUIDE.md](../TESTING_GUIDE.md) - Complete testing documentation
> - 🚀 [TESTING_QUICK_REFERENCE.md](../TESTING_QUICK_REFERENCE.md) - Quick command reference

## Overview

This comprehensive testing guide covers all aspects of testing the Nuclear Engagement WordPress plugin, including unit tests, integration tests, end-to-end tests, and testing strategies for contributors and maintainers.

### Recent Improvements
- ✅ Fixed 1200+ CSS linting errors
- ✅ Resolved PHP test syntax issues
- ✅ Implemented Docker-based testing solution
- ✅ Updated all test configurations
- ✅ Added Playwright browser testing support

## Table of Contents

- [Testing Philosophy](#testing-philosophy)
- [Test Environment Setup](#test-environment-setup)
- [Testing Framework Overview](#testing-framework-overview)
- [Unit Testing](#unit-testing)
- [Integration Testing](#integration-testing)
- [End-to-End Testing](#end-to-end-testing)
- [Performance Testing](#performance-testing)
- [Security Testing](#security-testing)
- [Accessibility Testing](#accessibility-testing)
- [Cross-Browser Testing](#cross-browser-testing)
- [Mobile Testing](#mobile-testing)
- [Test Data Management](#test-data-management)
- [Continuous Integration](#continuous-integration)
- [Writing New Tests](#writing-new-tests)
- [Debugging Tests](#debugging-tests)

## Testing Philosophy

### Testing Pyramid

Our testing strategy follows the testing pyramid approach:

```
     /\
    /  \    E2E Tests (Few, High Value)
   /____\
  /      \   Integration Tests (Some, Medium Value)
 /________\
/          \  Unit Tests (Many, Low Cost)
\__________/
```

### Testing Principles

1. **Fast Feedback**: Unit tests run quickly for immediate feedback
2. **Reliable**: Tests are deterministic and don't have false positives
3. **Maintainable**: Tests are easy to understand and modify
4. **Comprehensive**: Critical functionality is thoroughly tested
5. **Realistic**: Tests use realistic data and scenarios

### Test Categories

- **Unit Tests**: Test individual functions and classes in isolation
- **Integration Tests**: Test component interactions and WordPress integration
- **E2E Tests**: Test complete user workflows in a real browser
- **Performance Tests**: Measure speed, memory usage, and scalability
- **Security Tests**: Verify security measures and vulnerability prevention

## Test Environment Setup

### Requirements

- **PHP**: 7.4+ (same as production requirements)
- **WordPress**: 5.0+ test environment
- **Node.js**: 16+ for JavaScript testing
- **MySQL/MariaDB**: For database testing
- **Chrome/Chromium**: For E2E testing

### Local Development Setup

1. **Install Dependencies**
   ```bash
   # PHP dependencies
   composer install --dev
   
   # JavaScript dependencies
   npm install --dev
   
   # WordPress test environment
   npm run env:start
   ```

2. **Environment Configuration**
   ```bash
   # Copy test configuration
   cp .env.testing.example .env.testing
   
   # Update test database settings
   DB_NAME=nuclear_engagement_test
   DB_USER=root
   DB_PASSWORD=password
   DB_HOST=localhost
   ```

3. **Initialize Test Database**
   ```bash
   # Create test database
   mysql -u root -p -e "CREATE DATABASE nuclear_engagement_test;"
   
   # Install WordPress for testing
   npm run test:install
   ```

### Docker Test Environment

```yaml
# docker-compose.test.yml
version: '3.8'
services:
  wordpress-test:
    image: wordpress:6.2-php8.1-apache
    environment:
      WORDPRESS_DB_HOST: db-test
      WORDPRESS_DB_NAME: nuclear_engagement_test
      WORDPRESS_DB_USER: wp_user
      WORDPRESS_DB_PASSWORD: wp_password
    volumes:
      - ./nuclear-engagement:/var/www/html/wp-content/plugins/nuclear-engagement
    depends_on:
      - db-test
    
  db-test:
    image: mysql:8.0
    environment:
      MYSQL_DATABASE: nuclear_engagement_test
      MYSQL_USER: wp_user
      MYSQL_PASSWORD: wp_password
      MYSQL_ROOT_PASSWORD: root_password
    volumes:
      - test_db_data:/var/lib/mysql

volumes:
  test_db_data:
```

## Testing Framework Overview

### PHP Testing Stack

- **PHPUnit**: Primary testing framework
- **WP_UnitTestCase**: WordPress-specific test case class
- **Mockery**: Mocking framework for dependencies
- **Brain Monkey**: WordPress function mocking

### JavaScript Testing Stack

- **Vitest**: Modern testing framework (Jest alternative)
- **@testing-library/dom**: DOM testing utilities
- **@testing-library/user-event**: User interaction simulation
- **MSW**: API mocking for integration tests

### E2E Testing Stack

- **Playwright**: Cross-browser automation
- **@wordpress/e2e-test-utils**: WordPress-specific E2E utilities

## Unit Testing

### PHP Unit Tests

#### Basic Test Structure

```php
<?php
/**
 * Tests for Quiz class
 * 
 * @package NuclearEngagement\Tests
 */

namespace NuclearEngagement\Tests\Unit;

use NuclearEngagement\Core\Quiz;
use PHPUnit\Framework\TestCase;
use Mockery;

class QuizTest extends TestCase {
    
    protected function setUp(): void {
        parent::setUp();
        \WP_Mock::setUp();
    }
    
    protected function tearDown(): void {
        \WP_Mock::tearDown();
        Mockery::close();
        parent::tearDown();
    }
    
    /**
     * Test quiz creation with valid data
     */
    public function test_create_quiz_with_valid_data() {
        // Arrange
        $quiz_data = [
            'title' => 'Test Quiz',
            'questions' => [
                [
                    'type' => 'multiple_choice',
                    'question' => 'What is 2 + 2?',
                    'answers' => [
                        ['text' => '3', 'correct' => false],
                        ['text' => '4', 'correct' => true],
                        ['text' => '5', 'correct' => false]
                    ]
                ]
            ]
        ];
        
        // Mock WordPress functions
        \WP_Mock::userFunction('wp_insert_post')
            ->once()
            ->with(Mockery::type('array'))
            ->andReturn(123);
            
        \WP_Mock::userFunction('update_post_meta')
            ->once()
            ->with(123, '_nuclear_engagement_questions', Mockery::type('array'));
        
        // Act
        $quiz = new Quiz();
        $result = $quiz->create($quiz_data);
        
        // Assert
        $this->assertEquals(123, $result);
    }
    
    /**
     * Test quiz creation with invalid data
     */
    public function test_create_quiz_with_invalid_data() {
        // Arrange
        $quiz_data = [
            'title' => '', // Invalid: empty title
            'questions' => []
        ];
        
        // Act & Assert
        $quiz = new Quiz();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Quiz title is required');
        $quiz->create($quiz_data);
    }
    
    /**
     * Test quiz scoring calculation
     */
    public function test_calculate_score() {
        // Arrange
        $questions = [
            ['correct_answer' => 'a'],
            ['correct_answer' => 'b'],
            ['correct_answer' => 'c'],
            ['correct_answer' => 'd']
        ];
        
        $user_answers = ['a', 'b', 'x', 'd']; // 3 out of 4 correct
        
        // Act
        $quiz = new Quiz();
        $score = $quiz->calculate_score($questions, $user_answers);
        
        // Assert
        $this->assertEquals(75.0, $score); // 3/4 = 75%
    }
}
```

#### Testing WordPress Integration

```php
<?php
/**
 * Integration tests for Quiz functionality
 * 
 * @package NuclearEngagement\Tests
 */

namespace NuclearEngagement\Tests\Integration;

use WP_UnitTestCase;
use NuclearEngagement\Core\Quiz;

class QuizIntegrationTest extends WP_UnitTestCase {
    
    protected $quiz_id;
    protected $user_id;
    
    public function setUp(): void {
        parent::setUp();
        
        // Create test user
        $this->user_id = $this->factory->user->create([
            'role' => 'editor'
        ]);
        
        // Create test quiz
        $this->quiz_id = $this->factory->post->create([
            'post_type' => 'nuclear_quiz',
            'post_title' => 'Test Quiz',
            'post_status' => 'publish'
        ]);
        
        // Add quiz metadata
        update_post_meta($this->quiz_id, '_nuclear_engagement_questions', [
            [
                'type' => 'multiple_choice',
                'question' => 'What is the capital of France?',
                'answers' => [
                    ['text' => 'London', 'correct' => false],
                    ['text' => 'Paris', 'correct' => true],
                    ['text' => 'Berlin', 'correct' => false]
                ]
            ]
        ]);
    }
    
    /**
     * Test quiz retrieval from database
     */
    public function test_get_quiz_from_database() {
        // Act
        $quiz = new Quiz();
        $quiz_data = $quiz->get($this->quiz_id);
        
        // Assert
        $this->assertIsArray($quiz_data);
        $this->assertEquals('Test Quiz', $quiz_data['title']);
        $this->assertCount(1, $quiz_data['questions']);
    }
    
    /**
     * Test quiz result submission
     */
    public function test_submit_quiz_result() {
        // Arrange
        wp_set_current_user($this->user_id);
        
        $answers = ['0' => '1']; // Selected second answer (Paris)
        
        // Act
        $quiz = new Quiz();
        $result = $quiz->submit_result($this->quiz_id, $answers);
        
        // Assert
        $this->assertIsArray($result);
        $this->assertEquals(100, $result['score']);
        $this->assertTrue($result['passed']);
        
        // Verify database storage
        global $wpdb;
        $stored_result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}nuclear_engagement_results WHERE quiz_id = %d AND user_id = %d",
            $this->quiz_id,
            $this->user_id
        ));
        
        $this->assertNotNull($stored_result);
        $this->assertEquals('100.00', $stored_result->score);
    }
    
    /**
     * Test quiz permissions
     */
    public function test_quiz_permissions() {
        // Test as subscriber (should not be able to edit)
        $subscriber_id = $this->factory->user->create(['role' => 'subscriber']);
        wp_set_current_user($subscriber_id);
        
        $quiz = new Quiz();
        $can_edit = $quiz->can_user_edit($this->quiz_id);
        
        $this->assertFalse($can_edit);
        
        // Test as editor (should be able to edit)
        wp_set_current_user($this->user_id);
        $can_edit = $quiz->can_user_edit($this->quiz_id);
        
        $this->assertTrue($can_edit);
    }
}
```

### JavaScript Unit Tests

#### Basic JavaScript Test

```javascript
// tests/unit/quiz-timer.test.js
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { QuizTimer } from '../../assets/js/quiz-timer.js';

describe('QuizTimer', () => {
    let timer;
    let mockElement;
    
    beforeEach(() => {
        // Create mock DOM element
        mockElement = document.createElement('div');
        mockElement.innerHTML = '<span class="timer-display">00:00</span>';
        
        // Reset timers
        vi.useFakeTimers();
        
        timer = new QuizTimer(mockElement, 300); // 5 minutes
    });
    
    afterEach(() => {
        vi.useRealTimers();
    });
    
    it('should initialize with correct time', () => {
        expect(timer.getTimeRemaining()).toBe(300);
        expect(timer.getFormattedTime()).toBe('05:00');
    });
    
    it('should count down correctly', () => {
        timer.start();
        
        // Advance timer by 1 second
        vi.advanceTimersByTime(1000);
        
        expect(timer.getTimeRemaining()).toBe(299);
        expect(timer.getFormattedTime()).toBe('04:59');
    });
    
    it('should trigger callback when time expires', () => {
        const onExpire = vi.fn();
        timer.onExpire = onExpire;
        
        timer.start();
        
        // Advance timer past expiration
        vi.advanceTimersByTime(301000); // 301 seconds
        
        expect(onExpire).toHaveBeenCalled();
        expect(timer.isExpired()).toBe(true);
    });
    
    it('should pause and resume correctly', () => {
        timer.start();
        
        // Run for 5 seconds
        vi.advanceTimersByTime(5000);
        expect(timer.getTimeRemaining()).toBe(295);
        
        // Pause
        timer.pause();
        
        // Advance timer (should not affect remaining time)
        vi.advanceTimersByTime(10000);
        expect(timer.getTimeRemaining()).toBe(295);
        
        // Resume
        timer.resume();
        vi.advanceTimersByTime(5000);
        expect(timer.getTimeRemaining()).toBe(290);
    });
});
```

#### Testing DOM Interactions

```javascript
// tests/unit/quiz-interface.test.js
import { describe, it, expect, beforeEach, afterEach } from 'vitest';
import { screen, fireEvent, cleanup } from '@testing-library/dom';
import '@testing-library/jest-dom';
import { QuizInterface } from '../../assets/js/quiz-interface.js';

describe('QuizInterface', () => {
    let container;
    
    beforeEach(() => {
        // Setup DOM
        container = document.createElement('div');
        container.innerHTML = `
            <div class="nuclear-quiz" data-quiz-id="123">
                <div class="quiz-question">What is 2 + 2?</div>
                <div class="quiz-answers">
                    <button class="quiz-answer" data-answer="0">3</button>
                    <button class="quiz-answer" data-answer="1">4</button>
                    <button class="quiz-answer" data-answer="2">5</button>
                </div>
                <button class="quiz-submit" disabled>Submit</button>
            </div>
        `;
        document.body.appendChild(container);
        
        new QuizInterface(container.querySelector('.nuclear-quiz'));
    });
    
    afterEach(() => {
        cleanup();
        document.body.removeChild(container);
    });
    
    it('should enable submit button when answer is selected', () => {
        const answerButton = screen.getByText('4');
        const submitButton = screen.getByText('Submit');
        
        expect(submitButton).toBeDisabled();
        
        fireEvent.click(answerButton);
        
        expect(submitButton).toBeEnabled();
        expect(answerButton).toHaveClass('selected');
    });
    
    it('should deselect previous answer when new answer is selected', () => {
        const answer1 = screen.getByText('3');
        const answer2 = screen.getByText('4');
        
        fireEvent.click(answer1);
        expect(answer1).toHaveClass('selected');
        
        fireEvent.click(answer2);
        expect(answer1).not.toHaveClass('selected');
        expect(answer2).toHaveClass('selected');
    });
    
    it('should submit quiz with correct data', async () => {
        // Mock fetch
        global.fetch = vi.fn(() =>
            Promise.resolve({
                ok: true,
                json: () => Promise.resolve({ success: true, score: 100 })
            })
        );
        
        const answerButton = screen.getByText('4');
        const submitButton = screen.getByText('Submit');
        
        fireEvent.click(answerButton);
        fireEvent.click(submitButton);
        
        await new Promise(resolve => setTimeout(resolve, 0)); // Wait for async
        
        expect(fetch).toHaveBeenCalledWith(
            expect.stringContaining('/wp-json/nuclear-engagement/v1/quizzes/123/submit'),
            expect.objectContaining({
                method: 'POST',
                headers: expect.objectContaining({
                    'Content-Type': 'application/json'
                }),
                body: expect.stringContaining('"1"') // Answer index
            })
        );
    });
});
```

## Integration Testing

### API Integration Tests

```php
<?php
/**
 * REST API integration tests
 */

namespace NuclearEngagement\Tests\Integration;

use WP_REST_Request;
use WP_REST_Server;
use WP_Test_REST_TestCase;

class RestAPITest extends WP_Test_REST_TestCase {
    
    protected $server;
    protected $quiz_id;
    protected $admin_user;
    
    public function setUp(): void {
        parent::setUp();
        
        global $wp_rest_server;
        $this->server = $wp_rest_server = new WP_REST_Server;
        do_action('rest_api_init');
        
        // Create test data
        $this->admin_user = $this->factory->user->create(['role' => 'administrator']);
        $this->quiz_id = $this->factory->post->create([
            'post_type' => 'nuclear_quiz',
            'post_status' => 'publish'
        ]);
    }
    
    public function test_get_quizzes_endpoint() {
        wp_set_current_user($this->admin_user);
        
        $request = new WP_REST_Request('GET', '/nuclear-engagement/v1/quizzes');
        $response = $this->server->dispatch($request);
        
        $this->assertEquals(200, $response->get_status());
        
        $data = $response->get_data();
        $this->assertIsArray($data);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('meta', $data);
    }
    
    public function test_create_quiz_endpoint() {
        wp_set_current_user($this->admin_user);
        
        $request = new WP_REST_Request('POST', '/nuclear-engagement/v1/quizzes');
        $request->set_json_params([
            'title' => 'API Test Quiz',
            'questions' => [
                [
                    'type' => 'multiple_choice',
                    'question' => 'Test question?',
                    'answers' => [
                        ['text' => 'Answer 1', 'correct' => true],
                        ['text' => 'Answer 2', 'correct' => false]
                    ]
                ]
            ]
        ]);
        
        $response = $this->server->dispatch($request);
        
        $this->assertEquals(201, $response->get_status());
        
        $data = $response->get_data();
        $this->assertArrayHasKey('id', $data);
        $this->assertEquals('API Test Quiz', $data['title']);
    }
    
    public function test_unauthorized_access() {
        $request = new WP_REST_Request('POST', '/nuclear-engagement/v1/quizzes');
        $request->set_json_params(['title' => 'Unauthorized Quiz']);
        
        $response = $this->server->dispatch($request);
        
        $this->assertEquals(401, $response->get_status());
    }
}
```

### Database Integration Tests

```php
<?php
/**
 * Database integration tests
 */

namespace NuclearEngagement\Tests\Integration;

use WP_UnitTestCase;
use NuclearEngagement\Core\DatabaseManager;

class DatabaseTest extends WP_UnitTestCase {
    
    protected $db_manager;
    
    public function setUp(): void {
        parent::setUp();
        $this->db_manager = new DatabaseManager();
    }
    
    public function test_table_creation() {
        // Drop tables if they exist
        global $wpdb;
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}nuclear_engagement_results");
        
        // Create tables
        $this->db_manager->create_tables();
        
        // Verify table exists
        $table_exists = $wpdb->get_var(
            "SHOW TABLES LIKE '{$wpdb->prefix}nuclear_engagement_results'"
        );
        
        $this->assertEquals(
            $wpdb->prefix . 'nuclear_engagement_results',
            $table_exists
        );
    }
    
    public function test_data_insertion_and_retrieval() {
        global $wpdb;
        
        // Insert test data
        $result = $wpdb->insert(
            $wpdb->prefix . 'nuclear_engagement_results',
            [
                'quiz_id' => 123,
                'user_id' => 456,
                'score' => 85.5,
                'time_taken' => 300,
                'answers' => json_encode(['q1' => 'a1', 'q2' => 'a2']),
                'created_at' => current_time('mysql')
            ],
            ['%d', '%d', '%f', '%d', '%s', '%s']
        );
        
        $this->assertNotFalse($result);
        
        // Retrieve data
        $retrieved = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}nuclear_engagement_results WHERE quiz_id = %d AND user_id = %d",
            123,
            456
        ));
        
        $this->assertNotNull($retrieved);
        $this->assertEquals(85.5, floatval($retrieved->score));
        $this->assertEquals(300, intval($retrieved->time_taken));
    }
    
    public function test_database_migration() {
        // Test migration from older version
        global $wpdb;
        
        // Create old table structure
        $wpdb->query("
            CREATE TABLE IF NOT EXISTS {$wpdb->prefix}nuclear_engagement_results_old (
                id int AUTO_INCREMENT PRIMARY KEY,
                quiz_id int,
                user_id int,
                score float,
                answers text
            )
        ");
        
        // Insert old data
        $wpdb->insert(
            $wpdb->prefix . 'nuclear_engagement_results_old',
            ['quiz_id' => 1, 'user_id' => 1, 'score' => 90, 'answers' => '{}']
        );
        
        // Run migration
        $this->db_manager->migrate_from_old_version();
        
        // Verify data was migrated
        $migrated_data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}nuclear_engagement_results WHERE quiz_id = %d",
            1
        ));
        
        $this->assertNotNull($migrated_data);
        $this->assertEquals(90, floatval($migrated_data->score));
    }
}
```

## End-to-End Testing

### E2E Test Setup

```javascript
// tests/e2e/setup.js
import { chromium } from '@playwright/test';

export async function setupE2E() {
    const browser = await chromium.launch({
        headless: process.env.CI === 'true',
        slowMo: process.env.DEBUG ? 100 : 0
    });
    
    const context = await browser.newContext({
        viewport: { width: 1280, height: 720 },
        // Record video for debugging
        recordVideo: {
            dir: 'tests/e2e/videos/',
            size: { width: 1280, height: 720 }
        }
    });
    
    const page = await context.newPage();
    
    // Login to WordPress admin
    await page.goto('/wp-admin');
    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', 'password');
    await page.click('#wp-submit');
    
    return { browser, context, page };
}
```

### Quiz Creation E2E Test

```javascript
// tests/e2e/quiz-creation.test.js
import { test, expect } from '@playwright/test';
import { setupE2E } from './setup.js';

test.describe('Quiz Creation', () => {
    let page;
    let browser;
    let context;
    
    test.beforeAll(async () => {
        ({ browser, context, page } = await setupE2E());
    });
    
    test.afterAll(async () => {
        await browser.close();
    });
    
    test('should create a new quiz with multiple choice questions', async () => {
        // Navigate to quiz creation page
        await page.goto('/wp-admin/post-new.php?post_type=nuclear_quiz');
        
        // Fill in quiz title
        await page.fill('#title', 'E2E Test Quiz');
        
        // Add first question
        await page.click('[data-testid="add-question"]');
        await page.fill('[data-testid="question-text-0"]', 'What is the capital of France?');
        
        // Add answers
        await page.fill('[data-testid="answer-text-0-0"]', 'London');
        await page.fill('[data-testid="answer-text-0-1"]', 'Paris');
        await page.fill('[data-testid="answer-text-0-2"]', 'Berlin');
        
        // Mark correct answer
        await page.check('[data-testid="answer-correct-0-1"]');
        
        // Add second question
        await page.click('[data-testid="add-question"]');
        await page.fill('[data-testid="question-text-1"]', 'What is 2 + 2?');
        await page.fill('[data-testid="answer-text-1-0"]', '3');
        await page.fill('[data-testid="answer-text-1-1"]', '4');
        await page.fill('[data-testid="answer-text-1-2"]', '5');
        await page.check('[data-testid="answer-correct-1-1"]');
        
        // Publish quiz
        await page.click('#publish');
        
        // Wait for success message
        await expect(page.locator('.notice-success')).toContainText('Quiz published');
        
        // Verify quiz was created
        const quizId = await page.url().match(/post=(\d+)/)[1];
        expect(quizId).toBeTruthy();
        
        // Navigate to front-end to verify quiz displays
        await page.goto(`/?p=${quizId}`);
        
        // Verify quiz content
        await expect(page.locator('.nuclear-quiz')).toBeVisible();
        await expect(page.locator('.quiz-question')).toContainText('What is the capital of France?');
        await expect(page.locator('.quiz-answer')).toHaveCount(3);
    });
    
    test('should take quiz and show results', async () => {
        // Start from a published quiz page
        await page.goto('/test-quiz/'); // Assuming friendly URL
        
        // Verify quiz is loaded
        await expect(page.locator('.nuclear-quiz')).toBeVisible();
        
        // Answer first question
        await page.click('[data-answer="1"]'); // Paris
        await page.click('.quiz-next');
        
        // Answer second question
        await page.click('[data-answer="1"]'); // 4
        await page.click('.quiz-submit');
        
        // Wait for results
        await expect(page.locator('.quiz-results')).toBeVisible();
        
        // Verify score
        await expect(page.locator('.quiz-score')).toContainText('100%');
        await expect(page.locator('.quiz-message')).toContainText('Perfect score!');
        
        // Verify detailed results
        const resultItems = page.locator('.result-item');
        await expect(resultItems).toHaveCount(2);
        
        // Check each question result
        await expect(resultItems.nth(0)).toContainText('Correct');
        await expect(resultItems.nth(1)).toContainText('Correct');
    });
    
    test('should handle quiz timer correctly', async () => {
        // Create quiz with 10-second timer
        await page.goto('/wp-admin/post-new.php?post_type=nuclear_quiz');
        await page.fill('#title', 'Timer Test Quiz');
        
        // Set timer
        await page.click('[data-testid="quiz-settings"]');
        await page.check('[data-testid="enable-timer"]');
        await page.fill('[data-testid="timer-minutes"]', '0');
        await page.fill('[data-testid="timer-seconds"]', '10');
        
        // Add simple question
        await page.click('[data-testid="add-question"]');
        await page.fill('[data-testid="question-text-0"]', 'Simple question?');
        await page.fill('[data-testid="answer-text-0-0"]', 'Yes');
        await page.fill('[data-testid="answer-text-0-1"]', 'No');
        await page.check('[data-testid="answer-correct-0-0"]');
        
        await page.click('#publish');
        
        // Take the quiz
        const quizId = await page.url().match(/post=(\d+)/)[1];
        await page.goto(`/?p=${quizId}`);
        
        // Start quiz and verify timer appears
        await page.click('.quiz-start');
        await expect(page.locator('.quiz-timer')).toBeVisible();
        await expect(page.locator('.timer-display')).toContainText('00:10');
        
        // Wait for timer to countdown
        await page.waitForTimeout(2000);
        await expect(page.locator('.timer-display')).toContainText('00:08');
        
        // Answer quickly
        await page.click('[data-answer="0"]');
        await page.click('.quiz-submit');
        
        // Verify results show time taken
        await expect(page.locator('.time-taken')).toContainText('seconds');
    });
});
```

### Accessibility E2E Tests

```javascript
// tests/e2e/accessibility.test.js
import { test, expect } from '@playwright/test';
import { injectAxe, checkA11y } from 'axe-playwright';

test.describe('Accessibility Tests', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/test-quiz/');
        await injectAxe(page);
    });
    
    test('should have no accessibility violations', async ({ page }) => {
        await checkA11y(page, null, {
            detailedReport: true,
            detailedReportOptions: { html: true }
        });
    });
    
    test('should be keyboard navigable', async ({ page }) => {
        // Tab through quiz elements
        await page.keyboard.press('Tab'); // Focus first answer
        await expect(page.locator('.quiz-answer:first-child')).toBeFocused();
        
        await page.keyboard.press('Tab'); // Focus second answer
        await expect(page.locator('.quiz-answer:nth-child(2)')).toBeFocused();
        
        // Select answer with Enter
        await page.keyboard.press('Enter');
        await expect(page.locator('.quiz-answer:nth-child(2)')).toHaveClass(/selected/);
        
        // Tab to submit button
        await page.keyboard.press('Tab');
        await expect(page.locator('.quiz-submit')).toBeFocused();
        
        // Submit with Enter
        await page.keyboard.press('Enter');
        await expect(page.locator('.quiz-results')).toBeVisible();
    });
    
    test('should have proper ARIA labels', async ({ page }) => {
        // Check quiz container has proper role
        await expect(page.locator('.nuclear-quiz')).toHaveAttribute('role', 'form');
        
        // Check questions have proper labels
        await expect(page.locator('.quiz-question')).toHaveAttribute('role', 'heading');
        
        // Check answers have proper roles
        const answers = page.locator('.quiz-answer');
        for (let i = 0; i < await answers.count(); i++) {
            await expect(answers.nth(i)).toHaveAttribute('role', 'button');
            await expect(answers.nth(i)).toHaveAttribute('aria-pressed');
        }
        
        // Check submit button is properly labeled
        await expect(page.locator('.quiz-submit')).toHaveAttribute('aria-label');
    });
    
    test('should work with screen reader announcements', async ({ page }) => {
        // Check for live regions
        await expect(page.locator('[aria-live="polite"]')).toBeVisible();
        
        // Select answer and verify announcement
        await page.click('.quiz-answer:first-child');
        
        // Check that selection is announced
        const liveRegion = page.locator('[aria-live="polite"]');
        await expect(liveRegion).toContainText('selected');
    });
});
```

## Performance Testing

### Load Testing Setup

```javascript
// tests/performance/load-test.js
import { check, sleep } from 'k6';
import http from 'k6/http';

export let options = {
    stages: [
        { duration: '2m', target: 10 }, // Ramp up to 10 users
        { duration: '5m', target: 10 }, // Stay at 10 users
        { duration: '2m', target: 50 }, // Ramp up to 50 users
        { duration: '5m', target: 50 }, // Stay at 50 users
        { duration: '2m', target: 0 },  // Ramp down to 0 users
    ],
    thresholds: {
        http_req_duration: ['p(95)<2000'], // 95% of requests under 2s
        http_req_failed: ['rate<0.1'],     // Less than 10% failures
    },
};

export default function() {
    // Test quiz loading
    let response = http.get('https://yoursite.com/test-quiz/');
    check(response, {
        'quiz page loads': (r) => r.status === 200,
        'quiz content present': (r) => r.body.includes('nuclear-quiz'),
        'response time < 2s': (r) => r.timings.duration < 2000,
    });
    
    sleep(1);
    
    // Test API endpoint
    let apiResponse = http.get('https://yoursite.com/wp-json/nuclear-engagement/v1/quizzes');
    check(apiResponse, {
        'API responds': (r) => r.status === 200,
        'API response time < 1s': (r) => r.timings.duration < 1000,
    });
    
    sleep(2);
}
```

### Database Performance Tests

```php
<?php
/**
 * Database performance tests
 */

namespace NuclearEngagement\Tests\Performance;

use WP_UnitTestCase;
use NuclearEngagement\Core\DatabaseManager;

class DatabasePerformanceTest extends WP_UnitTestCase {
    
    public function test_large_dataset_query_performance() {
        // Create large dataset
        $this->create_test_data(10000); // 10k quiz results
        
        $start_time = microtime(true);
        
        // Query that should be optimized
        global $wpdb;
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT quiz_id, AVG(score) as avg_score, COUNT(*) as attempts 
             FROM {$wpdb->prefix}nuclear_engagement_results 
             WHERE created_at >= %s 
             GROUP BY quiz_id 
             ORDER BY avg_score DESC 
             LIMIT 20",
            date('Y-m-d', strtotime('-30 days'))
        ));
        
        $execution_time = microtime(true) - $start_time;
        
        // Assert query executes within reasonable time
        $this->assertLessThan(1.0, $execution_time, 'Query should execute in under 1 second');
        $this->assertNotEmpty($results);
    }
    
    public function test_concurrent_quiz_submissions() {
        $quiz_id = $this->create_test_quiz();
        $users = [];
        
        // Create test users
        for ($i = 0; $i < 100; $i++) {
            $users[] = $this->factory->user->create();
        }
        
        $start_time = microtime(true);
        
        // Simulate concurrent submissions
        foreach ($users as $user_id) {
            $this->submit_quiz_result($quiz_id, $user_id, rand(60, 100));
        }
        
        $execution_time = microtime(true) - $start_time;
        
        // Verify all submissions were recorded
        global $wpdb;
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}nuclear_engagement_results WHERE quiz_id = %d",
            $quiz_id
        ));
        
        $this->assertEquals(100, $count);
        $this->assertLessThan(5.0, $execution_time, 'Bulk submissions should complete within 5 seconds');
    }
    
    private function create_test_data($count) {
        global $wpdb;
        
        $values = [];
        for ($i = 0; $i < $count; $i++) {
            $values[] = $wpdb->prepare(
                "(%d, %d, %f, %d, %s, %s)",
                rand(1, 50),           // quiz_id
                rand(1, 1000),         // user_id
                rand(0, 100),          // score
                rand(60, 1800),        // time_taken
                '{}',                  // answers
                date('Y-m-d H:i:s', strtotime("-{$i} hours"))
            );
        }
        
        // Batch insert for performance
        $chunk_size = 1000;
        $chunks = array_chunk($values, $chunk_size);
        
        foreach ($chunks as $chunk) {
            $wpdb->query(
                "INSERT INTO {$wpdb->prefix}nuclear_engagement_results 
                 (quiz_id, user_id, score, time_taken, answers, created_at) 
                 VALUES " . implode(',', $chunk)
            );
        }
    }
}
```

## Security Testing

### Security Test Suite

```php
<?php
/**
 * Security tests
 */

namespace NuclearEngagement\Tests\Security;

use WP_UnitTestCase;
use WP_REST_Request;

class SecurityTest extends WP_UnitTestCase {
    
    public function test_sql_injection_prevention() {
        global $wpdb;
        
        // Attempt SQL injection in quiz ID parameter
        $malicious_input = "1; DROP TABLE {$wpdb->prefix}nuclear_engagement_results; --";
        
        // This should not cause any damage due to prepared statements
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}nuclear_engagement_results WHERE quiz_id = %d",
            $malicious_input
        ));
        
        // Verify table still exists
        $table_exists = $wpdb->get_var(
            "SHOW TABLES LIKE '{$wpdb->prefix}nuclear_engagement_results'"
        );
        
        $this->assertNotNull($table_exists, 'Table should still exist after injection attempt');
    }
    
    public function test_xss_prevention_in_quiz_content() {
        // Create quiz with malicious content
        $malicious_content = '<script>alert("XSS")</script>';
        
        $quiz_id = $this->factory->post->create([
            'post_type' => 'nuclear_quiz',
            'post_title' => $malicious_content,
            'post_content' => $malicious_content
        ]);
        
        // Get quiz and verify content is escaped
        $quiz = get_post($quiz_id);
        $rendered_title = apply_filters('the_title', $quiz->post_title);
        $rendered_content = apply_filters('the_content', $quiz->post_content);
        
        // Should not contain unescaped script tags
        $this->assertStringNotContainsString('<script>', $rendered_title);
        $this->assertStringNotContainsString('<script>', $rendered_content);
    }
    
    public function test_capability_checks() {
        // Create test users with different roles
        $admin_id = $this->factory->user->create(['role' => 'administrator']);
        $editor_id = $this->factory->user->create(['role' => 'editor']);
        $subscriber_id = $this->factory->user->create(['role' => 'subscriber']);
        
        $quiz_id = $this->factory->post->create(['post_type' => 'nuclear_quiz']);
        
        // Test admin access
        wp_set_current_user($admin_id);
        $this->assertTrue(current_user_can('manage_options'));
        
        // Test editor access
        wp_set_current_user($editor_id);
        $this->assertTrue(current_user_can('edit_posts'));
        $this->assertFalse(current_user_can('manage_options'));
        
        // Test subscriber access
        wp_set_current_user($subscriber_id);
        $this->assertFalse(current_user_can('edit_posts'));
        $this->assertFalse(current_user_can('manage_options'));
    }
    
    public function test_nonce_verification() {
        // Test AJAX endpoint without nonce
        $request = new WP_REST_Request('POST', '/nuclear-engagement/v1/quizzes/1/submit');
        $request->set_json_params(['answers' => ['q1' => 'a1']]);
        
        // Should fail without proper nonce
        $response = rest_do_request($request);
        $this->assertEquals(403, $response->get_status());
    }
    
    public function test_file_upload_security() {
        // Test malicious file upload
        $upload_dir = wp_upload_dir();
        $malicious_file = $upload_dir['path'] . '/malicious.php';
        
        // Create malicious PHP file
        file_put_contents($malicious_file, '<?php echo "hacked"; ?>');
        
        // Plugin should not allow direct access to uploaded PHP files
        $response = wp_remote_get($upload_dir['url'] . '/malicious.php');
        
        // Should not execute PHP or return PHP content
        $this->assertNotEquals(200, wp_remote_retrieve_response_code($response));
        
        // Cleanup
        unlink($malicious_file);
    }
    
    public function test_data_sanitization() {
        // Test various input sanitization
        $test_inputs = [
            'normal_input' => 'Normal text input',
            'script_tag' => '<script>alert("xss")</script>',
            'sql_injection' => "'; DROP TABLE users; --",
            'html_entities' => '&lt;script&gt;alert("test")&lt;/script&gt;',
            'unicode_attack' => '\u003cscript\u003ealert("xss")\u003c/script\u003e'
        ];
        
        foreach ($test_inputs as $type => $input) {
            $sanitized = sanitize_text_field($input);
            
            // Should not contain dangerous characters
            $this->assertStringNotContainsString('<script>', $sanitized);
            $this->assertStringNotContainsString('DROP TABLE', $sanitized);
            $this->assertStringNotContainsString('javascript:', strtolower($sanitized));
        }
    }
}
```

## Writing New Tests

### Test Guidelines

1. **Test Naming**: Use descriptive names that explain what is being tested
2. **AAA Pattern**: Arrange, Act, Assert - structure tests clearly
3. **Single Responsibility**: Each test should test one specific behavior
4. **Independence**: Tests should not depend on other tests
5. **Deterministic**: Tests should always produce the same result

### Test Templates

#### PHP Unit Test Template

```php
<?php
/**
 * Test for [ClassName]
 * 
 * @package NuclearEngagement\Tests
 */

namespace NuclearEngagement\Tests\Unit;

use PHPUnit\Framework\TestCase;
use NuclearEngagement\Core\[ClassName];

class [ClassName]Test extends TestCase {
    
    protected $instance;
    
    protected function setUp(): void {
        parent::setUp();
        \WP_Mock::setUp();
        $this->instance = new [ClassName]();
    }
    
    protected function tearDown(): void {
        \WP_Mock::tearDown();
        parent::tearDown();
    }
    
    /**
     * Test [specific behavior]
     */
    public function test_[specific_behavior]() {
        // Arrange
        $input = 'test_input';
        
        // Mock WordPress functions if needed
        \WP_Mock::userFunction('wp_function')
            ->once()
            ->with($input)
            ->andReturn('expected_result');
        
        // Act
        $result = $this->instance->method_under_test($input);
        
        // Assert
        $this->assertEquals('expected_result', $result);
    }
}
```

#### JavaScript Test Template

```javascript
// tests/unit/[component-name].test.js
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { [ComponentName] } from '../../assets/js/[component-name].js';

describe('[ComponentName]', () => {
    let instance;
    let mockElement;
    
    beforeEach(() => {
        // Setup
        mockElement = document.createElement('div');
        mockElement.innerHTML = '<div class="test-element"></div>';
        document.body.appendChild(mockElement);
        
        instance = new [ComponentName](mockElement);
    });
    
    afterEach(() => {
        // Cleanup
        document.body.removeChild(mockElement);
    });
    
    it('should [expected behavior]', () => {
        // Arrange
        const input = 'test_input';
        
        // Act
        const result = instance.methodUnderTest(input);
        
        // Assert
        expect(result).toBe('expected_result');
    });
});
```

### Running Tests

```bash
# Run all tests
npm run test

# Run specific test suites
npm run test:unit
npm run test:integration
npm run test:e2e

# Run tests with coverage
npm run test:coverage

# Run tests in watch mode
npm run test:watch

# Run PHP tests
composer test

# Run specific PHP test file
./vendor/bin/phpunit tests/Unit/QuizTest.php

# Run tests with debugging
npm run test:debug
```

### Test Coverage

Monitor test coverage to ensure comprehensive testing:

```bash
# Generate coverage report
npm run test:coverage

# View coverage report
open coverage/index.html
```

Coverage targets:
- **Unit Tests**: 90%+ coverage
- **Integration Tests**: 80%+ coverage
- **Critical Paths**: 100% coverage

### Continuous Integration

Tests run automatically on:
- Pull requests
- Main branch commits
- Release tags
- Scheduled nightly builds

CI configuration includes:
- Multiple PHP versions (7.4, 8.0, 8.1, 8.2)
- Multiple WordPress versions (5.0+)
- Different database configurations
- Browser testing matrix

This comprehensive testing approach ensures the Nuclear Engagement plugin maintains high quality, security, and reliability across all supported environments and use cases.