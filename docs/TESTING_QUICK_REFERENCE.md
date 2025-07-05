# Nuclear Engagement - Testing Quick Reference

## 🚀 Quick Test Commands

### Run All Tests (Docker - Recommended)
```bash
# TypeScript/JavaScript Tests
docker run --rm -v "$PWD":/work -w /work mcr.microsoft.com/playwright:v1.53.2-jammy npm test

# PHP Syntax Check
docker run --rm -v "$PWD":/app -w /app php:8.2-cli php test-runner.php

# E2E Browser Test
docker run --rm -v "$PWD":/work -w /work --ipc=host mcr.microsoft.com/playwright:v1.53.2-jammy npx playwright test tests/e2e/simple.spec.js
```

### Local Testing
```bash
# JavaScript/TypeScript
npm test                    # Run Vitest tests
npm run lint               # Run ESLint
npx tsc --noEmit          # TypeScript check

# CSS (in nuclear-engagement directory)
cd nuclear-engagement
npm run lint:css:check    # Check CSS
npm run lint:css          # Fix CSS issues
npm run build             # Build CSS

# E2E/Accessibility
npm run test:e2e          # Requires WordPress
npm run test:accessibility # Requires WordPress
```

## ✅ Test Status Summary

| Test Type | Command | Status | Notes |
|-----------|---------|--------|-------|
| TypeScript/JS | `npm test` | ✅ 110 passing | Vitest |
| ESLint | `npm run lint` | ✅ Passing | TypeScript linting |
| CSS Lint | `npm run lint:css:check` | ✅ 0 errors | Fixed 1200+ |
| TypeScript | `npx tsc --noEmit` | ✅ Passing | Type checking |
| PHP Syntax | `php test-runner.php` | ✅ 80 valid | All syntax valid |
| E2E | `npm run test:e2e` | ✅ Working | Needs WordPress |
| Build | `npm run build` | ✅ Working | CSS compilation |

## 🐛 Common Fixes

### CSS Linting Errors
```bash
cd nuclear-engagement
npm run lint:css  # Auto-fix most issues
```

### Missing Dependencies
```bash
# Use Docker instead of local install
docker run --rm -v "$PWD":/work -w /work mcr.microsoft.com/playwright:v1.53.2-jammy [command]
```

### E2E Test Failures
```bash
# Start WordPress first
docker-compose -f docker-compose.test.yml up -d
# Then run tests
npm run test:e2e
```

## 📊 Coverage Goals
- Unit Tests: 80%+ coverage
- E2E Tests: Critical user paths
- Accessibility: WCAG 2.1 AA compliance

## 🔧 Key Config Files
- `vitest.config.ts` - JavaScript test config
- `nuclear-engagement/.stylelintrc.json` - CSS linting
- `eslint.config.js` - TypeScript linting
- `playwright.config.js` - E2E test config
- `phpunit.xml` - PHP test config

## 📝 Test Locations
- TypeScript/JS: `tests/frontend/`
- PHP: `tests/`
- E2E: `tests/e2e/`
- Accessibility: `tests/accessibility/`
- CSS: `nuclear-engagement/assets/css/`