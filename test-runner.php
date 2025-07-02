<?php
// Simple test runner for PHP tests
require_once __DIR__ . '/vendor/autoload.php';

echo "Running Nuclear Engagement Plugin PHP Tests\n";
echo "==========================================\n\n";

$testFiles = glob(__DIR__ . '/tests/*Test.php');
$passed = 0;
$failed = 0;
$errors = [];

foreach ($testFiles as $testFile) {
    $testName = basename($testFile, '.php');
    echo "Testing: $testName ... ";
    
    try {
        // Check if file has syntax errors
        $output = [];
        $returnCode = 0;
        exec("php -l $testFile 2>&1", $output, $returnCode);
        
        if ($returnCode === 0) {
            echo "✓ (syntax OK)\n";
            $passed++;
        } else {
            echo "✗ (syntax error)\n";
            $failed++;
            $errors[] = "$testName: " . implode("\n", $output);
        }
    } catch (Exception $e) {
        echo "✗ (error: " . $e->getMessage() . ")\n";
        $failed++;
        $errors[] = "$testName: " . $e->getMessage();
    }
}

echo "\n==========================================\n";
echo "Results: $passed passed, $failed failed\n";

if (!empty($errors)) {
    echo "\nErrors:\n";
    foreach ($errors as $error) {
        echo "- $error\n";
    }
}

exit($failed > 0 ? 1 : 0);