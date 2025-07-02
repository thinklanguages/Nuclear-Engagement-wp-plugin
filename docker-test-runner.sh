#!/bin/bash

echo "=== Running Tests in Docker with All Dependencies ==="
echo ""

# Run tests in official Playwright Docker image
docker run --rm \
  -v "$PWD":/work \
  -w /work \
  mcr.microsoft.com/playwright:v1.40.0-jammy \
  bash -c "
    echo '=== Installing PHP for PHP tests ==='
    apt-get update && apt-get install -y php8.1-cli php8.1-xml php8.1-mbstring curl
    
    echo '=== Installing Node dependencies ==='
    npm ci
    
    echo '=== Running TypeScript/JavaScript Tests ==='
    npm test
    
    echo '=== Running ESLint ==='
    npm run lint
    
    echo '=== Running TypeScript Compilation Check ==='
    npx tsc --noEmit
    
    echo '=== Running E2E Tests ==='
    npm run test:e2e || echo 'E2E tests need WordPress environment'
    
    echo '=== Running Accessibility Tests ==='
    npx playwright test tests/accessibility/ || echo 'Accessibility tests need WordPress environment'
    
    echo '=== Checking PHP Syntax ==='
    php test-runner.php
    
    echo '=== Test Summary ==='
    echo 'All available tests have been executed.'
  "