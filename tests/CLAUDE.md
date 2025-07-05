# Tests Directory

This directory contains all test files for the Nuclear Engagement plugin, organized by test type.

## Test Categories

### Unit Tests (Root Level PHP Files)
- Individual class and component tests
- Mocked dependencies using Brain Monkey and WP_Mock
- Fast execution, no database required
- Examples: `AdminTest.php`, `SettingsTest.php`, `ServiceContainerTest.php`

### Frontend Tests (`frontend/`)
- TypeScript/JavaScript unit tests using Vitest
- Tests for admin UI, quiz functionality, TOC interactions
- API mocking and DOM testing
- Examples: `quiz.test.ts`, `generation-results.test.ts`, `toc-analytics.test.ts`

### Integration Tests (`integration/`)
- Tests requiring WordPress environment
- Database interactions and multi-component workflows
- Examples: `ContentGenerationWorkflowTest.php`, `ThemeSystemIntegrationTest.php`

### E2E Tests (`e2e/`)
- End-to-end browser tests using Playwright
- User workflow testing
- Examples: `simple.spec.js`, `web-workflows.spec.js`

### Performance Tests (`performance/`)
- Load testing and performance benchmarks
- Database query optimization tests
- Examples: `BackgroundProcessorTest.php`, `DatabasePerformanceTest.php`

### Security Tests (`security/`)
- Security vulnerability testing
- Input sanitization verification
- Example: `SecurityTest.php`

### Accessibility Tests (`accessibility/`)
- WCAG compliance testing
- Keyboard navigation tests
- Example: `AccessibilityTest.spec.cjs`

## Test Infrastructure

### Configuration Files
- `bootstrap.php` - PHPUnit bootstrap
- `wp-stubs.php` - WordPress function stubs
- `brain-monkey-mock.php` - Brain Monkey setup

### Mock Objects (`mocks/`)
- Reusable mock implementations
- Example: `OptinDataMock.php`

### Test Utilities
- Isolated test files (`.isolated`) for tests requiring clean environment
- Disabled tests (`.disabled`) for temporary exclusion

## Running Tests

1. **Unit Tests**: `composer test:unit`
2. **Integration Tests**: `composer test:integration`
3. **Frontend Tests**: `npm test`
4. **E2E Tests**: `npm run test:e2e`
5. **All Tests**: `composer test`

## Test Guidelines

1. **Isolation** - Tests should not depend on each other
2. **Mocking** - Use appropriate mocking for external dependencies
3. **Assertions** - Clear, specific assertions
4. **Coverage** - Aim for high code coverage
5. **Performance** - Keep unit tests fast (<100ms each)