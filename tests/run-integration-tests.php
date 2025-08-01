<?php
/**
 * Integration Test Runner for FMR AI Media Renaming
 * 
 * Runs comprehensive integration tests for WordPress compatibility,
 * SEO plugin integration, and theme compatibility.
 */

// Load the bootstrap
require_once __DIR__ . '/bootstrap.php';

echo "FMR AI Media Renaming - Integration Test Runner\n";
echo str_repeat("=", 60) . "\n\n";

// Initialize the integration test class
$integration_test = new IntegrationTest();

// Run individual test suites
$test_results = [];

echo "1. Testing WordPress Version Compatibility...\n";
echo str_repeat("-", 40) . "\n";
try {
    $integration_test->setUp();
    $integration_test->testAIFunctionalityAcrossWordPressVersions();
    $test_results['wordpress_versions'] = ['status' => 'PASSED', 'error' => null];
    echo "✓ WordPress version compatibility tests PASSED\n\n";
} catch (Exception $e) {
    $test_results['wordpress_versions'] = ['status' => 'FAILED', 'error' => $e->getMessage()];
    echo "✗ WordPress version compatibility tests FAILED: " . $e->getMessage() . "\n\n";
} finally {
    $integration_test->tearDown();
}

echo "2. Testing SEO Plugin Compatibility...\n";
echo str_repeat("-", 40) . "\n";
try {
    $integration_test->setUp();
    $integration_test->testSEOPluginCompatibility();
    $test_results['seo_plugins'] = ['status' => 'PASSED', 'error' => null];
    echo "✓ SEO plugin compatibility tests PASSED\n\n";
} catch (Exception $e) {
    $test_results['seo_plugins'] = ['status' => 'FAILED', 'error' => $e->getMessage()];
    echo "✗ SEO plugin compatibility tests FAILED: " . $e->getMessage() . "\n\n";
} finally {
    $integration_test->tearDown();
}

echo "3. Testing Theme Compatibility...\n";
echo str_repeat("-", 40) . "\n";
try {
    $integration_test->setUp();
    $integration_test->testMediaLibraryThemeCompatibility();
    $test_results['theme_compatibility'] = ['status' => 'PASSED', 'error' => null];
    echo "✓ Theme compatibility tests PASSED\n\n";
} catch (Exception $e) {
    $test_results['theme_compatibility'] = ['status' => 'FAILED', 'error' => $e->getMessage()];
    echo "✗ Theme compatibility tests FAILED: " . $e->getMessage() . "\n\n";
} finally {
    $integration_test->tearDown();
}

echo "4. Testing Page Builder Compatibility...\n";
echo str_repeat("-", 40) . "\n";
try {
    $integration_test->setUp();
    $integration_test->testPageBuilderCompatibility();
    $test_results['page_builders'] = ['status' => 'PASSED', 'error' => null];
    echo "✓ Page builder compatibility tests PASSED\n\n";
} catch (Exception $e) {
    $test_results['page_builders'] = ['status' => 'FAILED', 'error' => $e->getMessage()];
    echo "✗ Page builder compatibility tests FAILED: " . $e->getMessage() . "\n\n";
} finally {
    $integration_test->tearDown();
}

echo "5. Testing Multisite Compatibility...\n";
echo str_repeat("-", 40) . "\n";
try {
    $integration_test->setUp();
    $integration_test->testMultisiteCompatibility();
    $test_results['multisite'] = ['status' => 'PASSED', 'error' => null];
    echo "✓ Multisite compatibility tests PASSED\n\n";
} catch (Exception $e) {
    $test_results['multisite'] = ['status' => 'FAILED', 'error' => $e->getMessage()];
    echo "✗ Multisite compatibility tests FAILED: " . $e->getMessage() . "\n\n";
} finally {
    $integration_test->tearDown();
}

echo "6. Testing Plugin Conflict Resolution...\n";
echo str_repeat("-", 40) . "\n";
try {
    $integration_test->setUp();
    $integration_test->testPluginConflictResolution();
    $test_results['plugin_conflicts'] = ['status' => 'PASSED', 'error' => null];
    echo "✓ Plugin conflict resolution tests PASSED\n\n";
} catch (Exception $e) {
    $test_results['plugin_conflicts'] = ['status' => 'FAILED', 'error' => $e->getMessage()];
    echo "✗ Plugin conflict resolution tests FAILED: " . $e->getMessage() . "\n\n";
} finally {
    $integration_test->tearDown();
}

// Generate final report
echo str_repeat("=", 60) . "\n";
echo "INTEGRATION TEST RESULTS SUMMARY\n";
echo str_repeat("=", 60) . "\n";

$total_tests = count($test_results);
$passed_tests = 0;
$failed_tests = 0;

foreach ($test_results as $test_name => $result) {
    $status_icon = $result['status'] === 'PASSED' ? '✓' : '✗';
    $test_display_name = ucwords(str_replace('_', ' ', $test_name));
    
    echo sprintf("%-30s: %s %s\n", $test_display_name, $status_icon, $result['status']);
    
    if ($result['status'] === 'PASSED') {
        $passed_tests++;
    } else {
        $failed_tests++;
        if ($result['error']) {
            echo "  Error: " . $result['error'] . "\n";
        }
    }
}

echo str_repeat("-", 60) . "\n";
echo sprintf("Total Tests: %d | Passed: %d | Failed: %d\n", $total_tests, $passed_tests, $failed_tests);

$success_rate = $total_tests > 0 ? round(($passed_tests / $total_tests) * 100, 2) : 0;
echo sprintf("Success Rate: %.2f%%\n", $success_rate);

echo str_repeat("=", 60) . "\n";

// Exit with appropriate code
if ($failed_tests > 0) {
    echo "Integration tests completed with failures.\n";
    exit(1);
} else {
    echo "All integration tests passed successfully!\n";
    exit(0);
}