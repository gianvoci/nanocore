<?php

declare(strict_types=1);

// Test runner entry point — executes all test files and reports a summary

$testFiles = glob(__DIR__ . '/cases/*.php');

if ($testFiles === false || empty($testFiles)) {
    echo "No test files found in cases/\n";
    exit(1);
}

sort($testFiles);

$total = count($testFiles);
$passed = 0;
$failed = 0;
$failedFiles = [];

echo "NanoCore Test Suite\n";
echo "===================\n\n";

foreach ($testFiles as $i => $file) {
    $num = $i + 1;
    $display = basename($file);
    echo "[$num/$total] $display\n";

    $output = '';
    $returnCode = 0;

    // Run each test in its own process and capture output + exit code
    exec('php "' . $file . '" 2>&1', $outputLines, $returnCode);

    if (!empty($outputLines)) {
        echo implode("\n", $outputLines) . "\n";
    }

    if ($returnCode === 0) {
        $passed++;
        echo "--- PASSED ---\n\n";
    } else {
        $failed++;
        $failedFiles[] = $display;
        echo "--- FAILED ---\n\n";
    }
}

echo "===================\n";

if ($failed === 0) {
    echo "Results: $total/$total passed, 0 failed.\n";
    exit(0);
}

echo "Results: $passed/$total passed, $failed failed.\n";
echo "Failed files:\n";
foreach ($failedFiles as $file) {
    echo "  - $file\n";
}
exit(1);
