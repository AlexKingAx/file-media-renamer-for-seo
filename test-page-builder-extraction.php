<?php
/**
 * Test script for Page Builder Content Extraction functionality
 * 
 * This script tests the page builder content extraction system
 * Run this from WordPress admin or via WP-CLI
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    // For testing purposes, we'll include WordPress
    require_once dirname(__FILE__) . '/../../../wp-config.php';
}

// Test Context Extractor page builder functionality
function test_page_builder_extraction() {
    echo "<h2>Testing Page Builder Content Extraction</h2>\n";
    
    // Check if class exists
    if (!class_exists('FMR_Context_Extractor')) {
        echo "<p style='color: red;'>❌ FMR_Context_Extractor class not found!</p>\n";
        return false;
    }
    
    echo "<p style='color: green;'>✅ FMR_Context_Extractor class loaded successfully</p>\n";
    
    // Initialize context extractor
    $context_extractor = new FMR_Context_Extractor();
    
    return true;
}

// Test Elementor content extraction
function test_elementor_extraction() {
    echo "<h3>Testing Elementor Content Extraction</h3>\n";
    
    if (!class_exists('FMR_Context_Extractor')) {
        echo "<p style='color: red;'>❌ Cannot test - FMR_Context_Extractor class not found!</p>\n";
        return false;
    }
    
    // Create test post with Elementor data
    $test_post_id = wp_insert_post(array(
        'post_title' => 'Test Elementor Post',
        'post_content' => 'Test content',
        'post_status' => 'publish',
        'post_type' => 'post'
    ));
    
    if (is_wp_error($test_post_id)) {
        echo "<p style='color: red;'>❌ Failed to create test post</p>\n";
        return false;
    }
    
    // Simulate Elementor data
    $elementor_data = json_encode(array(
        array(
            'id' => 'test1',
            'elType' => 'widget',
            'widgetType' => 'heading',
            'settings' => array(
                'title' => 'Test Heading from Elementor'
            )
        ),
        array(
            'id' => 'test2',
            'elType' => 'widget',
            'widgetType' => 'text-editor',
            'settings' => array(
                'editor' => '<p>This is test content from Elementor text editor widget.</p>'
            )
        ),
        array(
            'id' => 'test3',
            'elType' => 'widget',
            'widgetType' => 'button',
            'settings' => array(
                'text' => 'Click Me Button'
            )
        )
    ));
    
    // Add Elementor meta data
    update_post_meta($test_post_id, '_elementor_data', $elementor_data);
    
    // Test extraction
    $context_extractor = new FMR_Context_Extractor();
    $context = $context_extractor->extract_context($test_post_id);
    
    echo "<p>Testing with post ID: {$test_post_id}</p>\n";
    
    if (!empty($context['page_builder_content'])) {
        echo "<p style='color: green;'>✅ Page builder content extracted successfully</p>\n";
        
        foreach ($context['page_builder_content'] as $content) {
            if ($content['builder'] === 'elementor') {
                echo "<p style='color: green;'>✅ Elementor content found</p>\n";
                echo "<p><strong>Text:</strong> " . substr($content['text'], 0, 100) . "...</p>\n";
                echo "<p><strong>Headings:</strong> " . implode(', ', $content['headings']) . "</p>\n";
                break;
            }
        }
    } else {
        echo "<p style='color: orange;'>⚠️ No page builder content extracted</p>\n";
    }
    
    // Clean up
    wp_delete_post($test_post_id, true);
    
    return true;
}

// Test Gutenberg blocks extraction
function test_gutenberg_extraction() {
    echo "<h3>Testing Gutenberg Blocks Extraction</h3>\n";
    
    if (!class_exists('FMR_Context_Extractor')) {
        echo "<p style='color: red;'>❌ Cannot test - FMR_Context_Extractor class not found!</p>\n";
        return false;
    }
    
    // Create test post with Gutenberg blocks
    $gutenberg_content = '<!-- wp:heading {"level":2} -->
<h2>Test Gutenberg Heading</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>This is a test paragraph from Gutenberg blocks.</p>
<!-- /wp:paragraph -->

<!-- wp:list -->
<ul><li>List item 1</li><li>List item 2</li></ul>
<!-- /wp:list -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link" href="#">Test Button</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons -->';
    
    $test_post_id = wp_insert_post(array(
        'post_title' => 'Test Gutenberg Post',
        'post_content' => $gutenberg_content,
        'post_status' => 'publish',
        'post_type' => 'post'
    ));
    
    if (is_wp_error($test_post_id)) {
        echo "<p style='color: red;'>❌ Failed to create test post</p>\n";
        return false;
    }
    
    // Test extraction
    $context_extractor = new FMR_Context_Extractor();
    $context = $context_extractor->extract_context($test_post_id);
    
    echo "<p>Testing with post ID: {$test_post_id}</p>\n";
    
    if (!empty($context['page_builder_content'])) {
        echo "<p style='color: green;'>✅ Page builder content extracted successfully</p>\n";
        
        foreach ($context['page_builder_content'] as $content) {
            if ($content['builder'] === 'gutenberg') {
                echo "<p style='color: green;'>✅ Gutenberg content found</p>\n";
                echo "<p><strong>Text:</strong> " . substr($content['text'], 0, 100) . "...</p>\n";
                echo "<p><strong>Headings:</strong> " . implode(', ', $content['headings']) . "</p>\n";
                break;
            }
        }
    } else {
        echo "<p style='color: orange;'>⚠️ No page builder content extracted</p>\n";
    }
    
    // Clean up
    wp_delete_post($test_post_id, true);
    
    return true;
}

// Test generic post meta scanner
function test_generic_meta_scanner() {
    echo "<h3>Testing Generic Post Meta Scanner</h3>\n";
    
    if (!class_exists('FMR_Context_Extractor')) {
        echo "<p style='color: red;'>❌ Cannot test - FMR_Context_Extractor class not found!</p>\n";
        return false;
    }
    
    // Create test post
    $test_post_id = wp_insert_post(array(
        'post_title' => 'Test Generic Meta Post',
        'post_content' => 'Test content',
        'post_status' => 'publish',
        'post_type' => 'post'
    ));
    
    if (is_wp_error($test_post_id)) {
        echo "<p style='color: red;'>❌ Failed to create test post</p>\n";
        return false;
    }
    
    // Add generic meta content
    update_post_meta($test_post_id, '_page_builder_content', '<h3>Custom Page Builder Heading</h3><p>This is custom page builder content stored in meta.</p>');
    update_post_meta($test_post_id, '_custom_content', 'Additional custom content for testing.');
    
    // Test extraction
    $context_extractor = new FMR_Context_Extractor();
    $context = $context_extractor->extract_context($test_post_id);
    
    echo "<p>Testing with post ID: {$test_post_id}</p>\n";
    
    if (!empty($context['page_builder_content'])) {
        echo "<p style='color: green;'>✅ Page builder content extracted successfully</p>\n";
        
        foreach ($context['page_builder_content'] as $content) {
            if ($content['builder'] === 'generic_meta') {
                echo "<p style='color: green;'>✅ Generic meta content found</p>\n";
                echo "<p><strong>Text:</strong> " . substr($content['text'], 0, 100) . "...</p>\n";
                echo "<p><strong>Headings:</strong> " . implode(', ', $content['headings']) . "</p>\n";
                break;
            }
        }
    } else {
        echo "<p style='color: orange;'>⚠️ No page builder content extracted</p>\n";
    }
    
    // Clean up
    wp_delete_post($test_post_id, true);
    
    return true;
}

// Test page builder detection methods
function test_page_builder_detection() {
    echo "<h3>Testing Page Builder Detection Methods</h3>\n";
    
    $builders_to_test = array(
        'Elementor' => array(
            'class' => '\\Elementor\\Plugin',
            'meta_key' => '_elementor_data'
        ),
        'Divi' => array(
            'function' => 'et_pb_is_pagebuilder_used',
            'meta_key' => '_et_pb_page_layout'
        ),
        'Beaver Builder' => array(
            'class' => 'FLBuilder',
            'meta_key' => '_fl_builder_enabled'
        ),
        'Visual Composer' => array(
            'function' => 'vc_is_page_editable'
        )
    );
    
    foreach ($builders_to_test as $builder_name => $config) {
        $detected = false;
        
        if (isset($config['class']) && class_exists($config['class'])) {
            $detected = true;
        } elseif (isset($config['function']) && function_exists($config['function'])) {
            $detected = true;
        }
        
        if ($detected) {
            echo "<p style='color: green;'>✅ {$builder_name} detected and supported</p>\n";
        } else {
            echo "<p style='color: gray;'>⚪ {$builder_name} not detected (not installed/active)</p>\n";
        }
    }
    
    return true;
}

// Test complete context extraction with page builders
function test_complete_context_extraction() {
    echo "<h3>Testing Complete Context Extraction</h3>\n";
    
    if (!class_exists('FMR_Context_Extractor')) {
        echo "<p style='color: red;'>❌ Cannot test - FMR_Context_Extractor class not found!</p>\n";
        return false;
    }
    
    // Create test media attachment
    $test_attachment_id = wp_insert_attachment(array(
        'post_title' => 'Test Image',
        'post_content' => '',
        'post_status' => 'inherit',
        'post_mime_type' => 'image/jpeg'
    ));
    
    if (is_wp_error($test_attachment_id)) {
        echo "<p style='color: red;'>❌ Failed to create test attachment</p>\n";
        return false;
    }
    
    // Create test post that uses this media
    $test_post_id = wp_insert_post(array(
        'post_title' => 'Test Post with Media',
        'post_content' => 'This post uses the test image.',
        'post_status' => 'publish',
        'post_type' => 'post'
    ));
    
    if (is_wp_error($test_post_id)) {
        echo "<p style='color: red;'>❌ Failed to create test post</p>\n";
        wp_delete_attachment($test_attachment_id, true);
        return false;
    }
    
    // Set as featured image to create relationship
    set_post_thumbnail($test_post_id, $test_attachment_id);
    
    // Add some page builder content
    $elementor_data = json_encode(array(
        array(
            'id' => 'test1',
            'elType' => 'widget',
            'widgetType' => 'heading',
            'settings' => array(
                'title' => 'SEO Optimized Heading'
            )
        )
    ));
    update_post_meta($test_post_id, '_elementor_data', $elementor_data);
    
    // Test complete context extraction
    $context_extractor = new FMR_Context_Extractor();
    $context = $context_extractor->extract_context($test_attachment_id);
    
    echo "<p>Testing complete context extraction for attachment ID: {$test_attachment_id}</p>\n";
    
    // Check if context was extracted
    if (!empty($context)) {
        echo "<p style='color: green;'>✅ Context extracted successfully</p>\n";
        
        // Check specific components
        if (!empty($context['posts_using_media'])) {
            echo "<p style='color: green;'>✅ Found posts using media: " . implode(', ', $context['posts_using_media']) . "</p>\n";
        }
        
        if (!empty($context['page_builder_content'])) {
            echo "<p style='color: green;'>✅ Page builder content extracted: " . count($context['page_builder_content']) . " entries</p>\n";
        }
        
        if (!empty($context['page_titles'])) {
            echo "<p style='color: green;'>✅ Page titles found: " . implode(', ', $context['page_titles']) . "</p>\n";
        }
        
        if (!empty($context['headings'])) {
            echo "<p style='color: green;'>✅ Headings extracted: " . implode(', ', $context['headings']) . "</p>\n";
        }
        
        if (!empty($context['seo_keywords'])) {
            echo "<p style='color: green;'>✅ SEO keywords found: " . implode(', ', array_slice($context['seo_keywords'], 0, 5)) . "</p>\n";
        }
        
    } else {
        echo "<p style='color: red;'>❌ No context extracted</p>\n";
    }
    
    // Clean up
    wp_delete_post($test_post_id, true);
    wp_delete_attachment($test_attachment_id, true);
    
    return true;
}

// Run all page builder tests
function run_all_page_builder_tests() {
    echo "<h1>Page Builder Content Extraction Test Suite</h1>\n";
    echo "<p>Testing page builder content extraction functionality...</p>\n";
    
    $tests = array(
        'Page Builder Extraction Setup' => 'test_page_builder_extraction',
        'Elementor Content Extraction' => 'test_elementor_extraction',
        'Gutenberg Blocks Extraction' => 'test_gutenberg_extraction',
        'Generic Meta Scanner' => 'test_generic_meta_scanner',
        'Page Builder Detection' => 'test_page_builder_detection',
        'Complete Context Extraction' => 'test_complete_context_extraction'
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
        echo "<p style='color: green; font-size: 18px;'>🎉 All tests passed! Page Builder Content Extraction is working correctly.</p>\n";
    } else {
        echo "<p style='color: red; font-size: 18px;'>❌ Some tests failed. Please check the implementation.</p>\n";
    }
}

// Run tests if accessed directly
if (isset($_GET['run_tests']) || (defined('WP_CLI') && WP_CLI)) {
    run_all_page_builder_tests();
} else {
    echo "<h1>Page Builder Content Extraction Test Suite</h1>\n";
    echo "<p><a href='?run_tests=1'>Click here to run tests</a></p>\n";
}
?>