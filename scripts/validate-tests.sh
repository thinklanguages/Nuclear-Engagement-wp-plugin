#!/bin/bash

# Nuclear Engagement Plugin - Test Validation Script
# This script validates the test infrastructure without running actual tests

echo "🧪 Nuclear Engagement Plugin - Test Validation"
echo "=============================================="
echo ""

# Check test files exist
echo "📁 Checking test file structure..."
test_dirs=("tests/frontend" "tests/integration" "tests/performance" "tests/e2e" "tests/accessibility")
missing_dirs=0

for dir in "${test_dirs[@]}"; do
    if [ -d "$dir" ]; then
        echo "✅ $dir - exists"
        file_count=$(find "$dir" -name "*.test.*" -o -name "*.spec.*" | wc -l)
        echo "   📄 $file_count test files found"
    else
        echo "❌ $dir - missing"
        ((missing_dirs++))
    fi
done

echo ""

# Check configuration files
echo "⚙️  Checking configuration files..."
config_files=(
    "package.json"
    "vitest.config.ts"
    "playwright.config.js"
    "phpunit.xml"
    "eslint.config.js"
    ".github/workflows/test.yml"
    "docker-compose.test.yml"
    "composer.json"
)

missing_configs=0
for file in "${config_files[@]}"; do
    if [ -f "$file" ]; then
        echo "✅ $file - exists"
    else
        echo "❌ $file - missing"
        ((missing_configs++))
    fi
done

echo ""

# Check npm dependencies
echo "📦 Checking npm dependencies..."
if [ -f "package.json" ]; then
    if grep -q "vitest" package.json; then
        echo "✅ Vitest - configured"
    else
        echo "❌ Vitest - missing"
    fi
    
    if grep -q "@playwright/test" package.json; then
        echo "✅ Playwright - configured"
    else
        echo "❌ Playwright - missing"
    fi
    
    if grep -q "@axe-core/playwright" package.json; then
        echo "✅ Axe-core - configured"
    else
        echo "❌ Axe-core - missing"
    fi
else
    echo "❌ package.json - missing"
fi

echo ""

# Check PHP dependencies
echo "🐘 Checking PHP configuration..."
if [ -f "composer.json" ]; then
    echo "✅ composer.json - exists"
    if grep -q "phpunit" composer.json; then
        echo "✅ PHPUnit - configured"
    else
        echo "❌ PHPUnit - missing from composer.json"
    fi
else
    echo "❌ composer.json - missing"
fi

if [ -f "phpunit.xml" ]; then
    echo "✅ phpunit.xml - exists"
else
    echo "❌ phpunit.xml - missing"
fi

echo ""

# Check test scripts
echo "🔧 Checking test scripts..."
if [ -f "package.json" ]; then
    if grep -q '"test":' package.json; then
        echo "✅ npm test script - configured"
    fi
    
    if grep -q '"test:e2e":' package.json; then
        echo "✅ npm run test:e2e script - configured"
    fi
    
    if grep -q '"test:accessibility":' package.json; then
        echo "✅ npm run test:accessibility script - configured"
    fi
    
    if grep -q '"lint":' package.json; then
        echo "✅ npm run lint script - configured"
    fi
    
    if grep -q '"build":' package.json; then
        echo "✅ npm run build script - configured"
    fi
fi

echo ""

# Summary
echo "📊 Validation Summary"
echo "===================="
total_errors=$((missing_dirs + missing_configs))

if [ $total_errors -eq 0 ]; then
    echo "🎉 All test infrastructure files are present!"
    echo ""
    echo "Next steps:"
    echo "1. Run 'npm install' to install dependencies"
    echo "2. Run 'npm test' for JavaScript unit tests"
    echo "3. Run 'npm run build' to verify TypeScript compilation"
    echo "4. Run 'npm run lint' to check code quality"
    echo "5. Set up PHP environment for integration tests"
else
    echo "⚠️  Found $total_errors missing components"
    echo "Please ensure all test files and configurations are in place"
fi

echo ""
echo "For detailed test status, see TEST_SUMMARY.md"