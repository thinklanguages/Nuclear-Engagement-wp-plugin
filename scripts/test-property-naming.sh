#!/bin/bash
# Test script to verify property naming consistency and catch dynamic property issues

echo "🧪 Running Property Naming Consistency Tests"
echo "============================================="

echo ""
echo "1. Running comprehensive property naming tests..."
./vendor/bin/phpunit tests/PropertyNamingConsistencyTest.php --verbose

echo ""
echo "2. Running PostsCountRequest tests..."
./vendor/bin/phpunit tests/PostsCountRequestTest.php --verbose

echo ""
echo "3. Running property syntax check..."
php -l nuclear-engagement/inc/Requests/PostsCountRequest.php
php -l nuclear-engagement/inc/Requests/GenerateRequest.php
php -l nuclear-engagement/inc/Services/PostsQueryService.php
php -l nuclear-engagement/inc/Services/GenerationService.php

echo ""
echo "✅ All property naming tests completed!"
echo ""
echo "These tests catch:"
echo "  - Property naming mismatches between definitions and usage"
echo "  - Snake_case vs camelCase inconsistencies"
echo "  - Dynamic property creation (PHP 8.2+ deprecation warnings)"
echo "  - Request object instantiation issues"
echo "  - Service layer property access patterns"