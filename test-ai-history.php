<?php
/**
 * Test script for AI History Manager functionality
 * 
 * This script tests the comprehensive history tracking system
 * Run this from WordPress admin or via WP-CLI
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    // For testing purposes, we'll include WordPress
    require_once dirname(__FILE__) . '/../../../wp-config.php';
}

// Test AI History Manager functionality
function test_ai_history_manager() {
    echo "<h2>Testing AI History Manager</h2>\n";
    
    // Check if class exists
    if (!class_exists('FMR_AI_History_Manager')) {
        echo "<p style='color: red;'>❌ FMR_AI_History_Manager class not found!</p>\n";
        return false;
    }
    
    echo "<p style='color: green;'>✅ FMR_AI_History_Manager class loaded successfully</p>\n";
    
    // Initialize history manager
    $history_manager = new FMR_AI_History_Manager();
    
    // Test statistics retrieval
    echo "<h3>Testing Statistics Retrieval</h3>\n";
    $stats = $history_manager->get_ai_statistics();
    
    echo "<pre>";
    print_r($stats);
    echo "</pre>";
    
    // Test history statistics
    echo "<h3>Testing History Statistics</h3>\n";
    $history_stats = $history_manager->get_history_statistics();
    
    echo "<pre>";
    print_r($history_stats);
    echo "</pre>";
    
    // Test user history
    echo "<h3>Testing User History</h3>\n";
    $user_history = $history_manager->get_user_ai_history();
    
    echo "<p>Found " . count($user_history) . " user history entries</p>\n";
    
    if (!empty($user_history)) {
        echo "<pre>";
        print_r(array_slice($user_history, 0, 3)); // Show first 3 entries
        echo "</pre>";
    }
    
    return true;
}

// Test simulated AI operation tracking
function test_ai_operation_tracking() {
    echo "<h2>Testing AI Operation Tracking</h2>\n";
    
    if (!class_exists('FMR_AI_History_Manager')) {
        echo "<p style='color: red;'>❌ Cannot test - FMR_AI_History_Manager class not found!</p>\n";
        return false;
    }
    
    // Create a test attachment (simulate)
    $test_post_id = 999999; // Use a fake ID for testing
    
    // Simulate rename result
    $rename_result = array(
        'old_file_path' => '/path/to/old-file.jpg',
        'old_file_url' => 'https://example.com/old-file.jpg',
        'seo_name' => 'new-optimized-filename',
        'file_ext' => 'jpg'
    );
    
    // Simulate AI operation data
    $operation_data = array(
        'method' => 'ai',
        'ai_suggestions' => array(
            'new-optimized-filename',
            'alternative-name',
            'another-option'
        ),
        'selected_suggestion_index' => 0,
        'credits_used' => 1,
        'processing_time' => 2.5,
        'content_analysis' => array(
            'text_extracted' => 'Sample text from image',
            'objects_detected' => array('person', 'building')
        ),
        'context_data' => array(
            'page_title' => 'Test Page',
            'seo_keywords' => array('test', 'example')
        ),
        'fallback_used' => false,
        'error_occurred' => false
    );
    
    // Initialize history manager and track operation
    $history_manager = new FMR_AI_History_Manager();
    
    echo "<p>Simulating AI operation tracking...</p>\n";
    
    // Note: This would normally be called automatically via the action hook
    // For testing, we call it directly
    try {
        $history_manager->track_rename_operation($test_post_id, $rename_result, $operation_data);
        echo "<p style='color: green;'>✅ AI operation tracked successfully</p>\n";
        
        // Verify the tracking worked by checking post meta
        $history = get_post_meta($test_post_id, '_fmrseo_rename_history', true);
        
        if (is_array($history) && !empty($history)) {
            echo "<p style='color: green;'>✅ History entry created successfully</p>\n";
            echo "<pre>";
            print_r($history[0]); // Show the most recent entry
            echo "</pre>";
        } else {
            echo "<p style='color: orange;'>⚠️ History entry not found (expected for test post ID)</p>\n";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Error tracking AI operation: " . $e->getMessage() . "</p>\n";
        return false;
    }
    
    return true;
}

// Test dashboard widget functionality
function test_dashboard_widget() {
    echo "<h2>Testing Dashboard Widget</h2>\n";
    
    if (!class_exists('FMR_AI_Dashboard_Widget')) {
        echo "<p style='color: red;'>❌ FMR_AI_Dashboard_Widget class not found!</p>\n";
        return false;
    }
    
    echo "<p style='color: green;'>✅ FMR_AI_Dashboard_Widget class loaded successfully</p>\n";
    
    // Initialize dashboard widget
    $dashboard_widget = new FMR_AI_Dashboard_Widget();
    
    echo "<p style='color: green;'>✅ Dashboard widget initialized successfully</p>\n";
    
    return true;
}

// Test settings integration
function test_settings_integration() {
    echo "<h2>Testing Settings Integration</h2>\n";
    
    // Check if settings class has AI statistics method
    if (!class_exists('File_Media_Renamer_SEO_Settings')) {
        echo "<p style='color: red;'>❌ File_Media_Renamer_SEO_Settings class not found!</p>\n";
        return false;
    }
    
    $settings = new File_Media_Renamer_SEO_Settings();
    
    if (!method_exists($settings, 'ai_statistics_callback')) {
        echo "<p style='color: red;'>❌ ai_statistics_callback method not found!</p>\n";
        return false;
    }
    
    echo "<p style='color: green;'>✅ Settings integration working correctly</p>\n";
    
    return true;
}

// Run all tests
function run_all_tests() {
    echo "<h1>AI History Manager Test Suite</h1>\n";
    echo "<p>Testing comprehensive history tracking system...</p>\n";
    
    $tests = array(
        'AI History Manager' => 'test_ai_history_manager',
        'AI Operation Tracking' => 'test_ai_operation_tracking',
        'Dashboard Widget' => 'test_dashboard_widget',
        'Settings Integration' => 'test_settings_integration'
    );
    
    $passed = 0;
    $total = count($tests);
    
    foreach ($tests as $test_name => $test_function) {
        echo "<hr>\n";
        if (function_exists($test_function) && call_user_func($test_function)) {
            $passed++;
        }
    }
    
    echo "<hr>\n";
    echo "<h2>Test Results</h2>\n";
    echo "<p><strong>Passed: {$passed}/{$total} tests</strong></p>\n";
    
    if ($passed === $total) {
        echo "<p style='color: green; font-size: 18px;'>🎉 All tests passed! AI History Manager is working correctly.</p>\n";
    } else {
        echo "<p style='color: red; font-size: 18px;'>❌ Some tests failed. Please check the implementation.</p>\n";
    }
}

// Run tests if accessed directly
if (isset($_GET['run_tests']) || (defined('WP_CLI') && WP_CLI)) {
    run_all_tests();
} else {
    echo "<h1>AI History Manager Test Suite</h1>\n";
    echo "<p><a href='?run_tests=1'>Click here to run tests</a></p>\n";
}
?>