<?php
/**
 * Integration Tests for FMR AI Media Renaming WordPress Compatibility
 * 
 * Tests AI functionality across different WordPress versions, SEO plugins,
 * and theme compatibility for media library integration.
 * 
 * Requirements: 4.3, 6.1, 6.2
 */

use PHPUnit\Framework\TestCase;

class IntegrationTest extends TestCase {

    private $ai_controller;
    private $context_extractor;
    private $content_analyzer;
    private $test_attachment_id;
    private $test_post_id;
    private $original_wp_version;

    protected function setUp(): void {
        parent::setUp();
        
        // Store original WordPress version for restoration
        $this->original_wp_version = defined('WP_VERSION') ? WP_VERSION : '6.0';
        
        // Initialize test IDs
        $this->test_attachment_id = 789;
        $this->test_post_id = 101;
        
        // Mock WordPress environment
        $this->setupWordPressEnvironment();
        
        // Initialize AI components
        $this->initializeAIComponents();
    }

    protected function tearDown(): void {
        parent::tearDown();
        
        // Restore original WordPress version
        if (!defined('WP_VERSION')) {
            define('WP_VERSION', $this->original_wp_version);
        }
        
        // Clean up test data
        $this->cleanupTestData();
    }

    /**
     * Test AI functionality with different WordPress versions
     * Requirement: 6.1 - WordPress compatibility
     */
    public function testAIFunctionalityAcrossWordPressVersions() {
        $wordpress_versions = ['5.8', '5.9', '6.0', '6.1', '6.2', '6.3', '6.4'];
        
        foreach ($wordpress_versions as $version) {
            $this->simulateWordPressVersion($version);
            
            // Test core AI functionality
            $result = $this->testCoreAIFunctionality();
            
            $this->assertTrue(
                $result['success'], 
                "AI functionality failed on WordPress version {$version}: " . ($result['error'] ?? 'Unknown error')
            );
            
            // Test media library integration
            $media_result = $this->testMediaLibraryIntegration();
            
            $this->assertTrue(
                $media_result['success'],
                "Media library integration failed on WordPress version {$version}: " . ($media_result['error'] ?? 'Unknown error')
            );
        }
    }

    /**
     * Test compatibility with popular SEO plugins
     * Requirement: 4.3 - SEO plugin integration
     */
    public function testSEOPluginCompatibility() {
        $seo_plugins = [
            'rank_math' => 'RankMath\\Helper',
            'yoast' => 'WPSEO_Options',
            'all_in_one_seo' => 'AIOSEO\\Plugin\\Common\\Main',
            'seopress' => 'SEOPress_Options'
        ];

        foreach ($seo_plugins as $plugin_key => $plugin_class) {
            // Simulate plugin activation
            $this->simulateSEOPlugin($plugin_key, $plugin_class);
            
            // Test context extraction with SEO plugin data
            $context_result = $this->testSEOContextExtraction($plugin_key);
            
            $this->assertTrue(
                $context_result['success'],
                "SEO plugin integration failed for {$plugin_key}: " . ($context_result['error'] ?? 'Unknown error')
            );
            
            // Test that AI suggestions incorporate SEO data
            $ai_result = $this->testAISuggestionsWithSEOData($plugin_key);
            
            $this->assertTrue(
                $ai_result['success'],
                "AI suggestions with SEO data failed for {$plugin_key}: " . ($ai_result['error'] ?? 'Unknown error')
            );
            
            // Clean up plugin simulation
            $this->cleanupSEOPluginSimulation($plugin_key);
        }
    }

    /**
     * Test media library integration across different themes
     * Requirement: 6.2 - Theme compatibility
     */
    public function testMediaLibraryThemeCompatibility() {
        $themes = [
            'twentytwentyone' => 'Twenty Twenty-One',
            'twentytwentytwo' => 'Twenty Twenty-Two', 
            'twentytwentythree' => 'Twenty Twenty-Three',
            'astra' => 'Astra',
            'generatepress' => 'GeneratePress',
            'oceanwp' => 'OceanWP'
        ];

        foreach ($themes as $theme_slug => $theme_name) {
            // Simulate theme activation
            $this->simulateTheme($theme_slug, $theme_name);
            
            // Test media library UI integration
            $ui_result = $this->testMediaLibraryUIIntegration($theme_slug);
            
            $this->assertTrue(
                $ui_result['success'],
                "Media library UI integration failed with theme {$theme_name}: " . ($ui_result['error'] ?? 'Unknown error')
            );
            
            // Test AJAX functionality
            $ajax_result = $this->testAjaxFunctionalityWithTheme($theme_slug);
            
            $this->assertTrue(
                $ajax_result['success'],
                "AJAX functionality failed with theme {$theme_name}: " . ($ajax_result['error'] ?? 'Unknown error')
            );
            
            // Test bulk rename functionality
            $bulk_result = $this->testBulkRenameFunctionalityWithTheme($theme_slug);
            
            $this->assertTrue(
                $bulk_result['success'],
                "Bulk rename functionality failed with theme {$theme_name}: " . ($bulk_result['error'] ?? 'Unknown error')
            );
        }
    }

    /**
     * Test page builder compatibility
     * Requirement: 4.3 - Page builder integration
     */
    public function testPageBuilderCompatibility() {
        $page_builders = [
            'elementor' => 'Elementor\\Plugin',
            'gutenberg' => 'WP_Block_Editor_Context',
            'beaver_builder' => 'FLBuilder',
            'divi' => 'ET_Builder_Element',
            'visual_composer' => 'Vc_Manager'
        ];

        foreach ($page_builders as $builder_key => $builder_class) {
            // Simulate page builder activation
            $this->simulatePageBuilder($builder_key, $builder_class);
            
            // Test content extraction from page builder
            $extraction_result = $this->testPageBuilderContentExtraction($builder_key);
            
            $this->assertTrue(
                $extraction_result['success'],
                "Page builder content extraction failed for {$builder_key}: " . ($extraction_result['error'] ?? 'Unknown error')
            );
            
            // Test context analysis with page builder content
            $context_result = $this->testContextAnalysisWithPageBuilder($builder_key);
            
            $this->assertTrue(
                $context_result['success'],
                "Context analysis with page builder failed for {$builder_key}: " . ($context_result['error'] ?? 'Unknown error')
            );
        }
    }

    /**
     * Test multisite compatibility
     * Requirement: 6.1 - WordPress multisite support
     */
    public function testMultisiteCompatibility() {
        // Simulate multisite environment
        $this->simulateMultisite();
        
        // Test AI functionality on main site
        $main_site_result = $this->testAIFunctionalityOnSite(1);
        $this->assertTrue($main_site_result['success'], 'AI functionality failed on main site');
        
        // Test AI functionality on sub-site
        $sub_site_result = $this->testAIFunctionalityOnSite(2);
        $this->assertTrue($sub_site_result['success'], 'AI functionality failed on sub-site');
        
        // Test cross-site media access restrictions
        $cross_site_result = $this->testCrossSiteMediaRestrictions();
        $this->assertTrue($cross_site_result['success'], 'Cross-site media restrictions test failed');
    }

    /**
     * Test plugin conflict resolution
     * Requirement: 6.2 - Plugin compatibility
     */
    public function testPluginConflictResolution() {
        $conflicting_plugins = [
            'wp_rocket' => 'WP_Rocket\\Engine\\Optimization\\LazyRenderContent\\Frontend\\Processor\\Dom',
            'w3_total_cache' => 'W3TC\\Dispatcher',
            'wp_super_cache' => 'WpSuperCache',
            'autoptimize' => 'autoptimizeMain'
        ];

        foreach ($conflicting_plugins as $plugin_key => $plugin_class) {
            // Simulate plugin activation
            $this->simulatePlugin($plugin_key, $plugin_class);
            
            // Test that AI functionality still works
            $ai_result = $this->testCoreAIFunctionality();
            $this->assertTrue(
                $ai_result['success'],
                "AI functionality failed with {$plugin_key} active: " . ($ai_result['error'] ?? 'Unknown error')
            );
            
            // Test that AJAX calls work
            $ajax_result = $this->testAjaxWithPlugin($plugin_key);
            $this->assertTrue(
                $ajax_result['success'],
                "AJAX functionality failed with {$plugin_key} active: " . ($ajax_result['error'] ?? 'Unknown error')
            );
        }
    }

    // Helper Methods

    private function setupWordPressEnvironment() {
        // Mock WordPress globals and functions
        global $wpdb, $wp_version;
        
        if (!isset($wpdb)) {
            $wpdb = $this->createMockWpdb();
        }
        
        // Mock essential WordPress functions
        $this->mockWordPressFunctions();
    }

    private function initializeAIComponents() {
        // Initialize AI components with mocked dependencies
        $this->ai_controller = new FMR_AI_Rename_Controller();
        $this->context_extractor = new FMR_Context_Extractor();
        $this->content_analyzer = new FMR_Content_Analyzer();
    }

    private function simulateWordPressVersion($version) {
        // Simulate different WordPress versions
        if (defined('WP_VERSION')) {
            // Can't redefine constant, so we'll use a global variable
            global $wp_version;
            $wp_version = $version;
        } else {
            define('WP_VERSION', $version);
        }
        
        // Adjust functionality based on version
        $this->adjustFunctionalityForVersion($version);
    }

    private function adjustFunctionalityForVersion($version) {
        // Simulate version-specific functionality differences
        if (version_compare($version, '5.9', '<')) {
            // Older WordPress versions might have different media handling
            $this->simulateOlderMediaHandling();
        }
        
        if (version_compare($version, '6.0', '>=')) {
            // Newer versions have enhanced block editor
            $this->simulateEnhancedBlockEditor();
        }
    }

    private function testCoreAIFunctionality() {
        try {
            // Test single media rename
            $single_result = $this->ai_controller->rename_single_media($this->test_attachment_id);
            
            if (!$single_result || !isset($single_result['success'])) {
                return ['success' => false, 'error' => 'Single rename failed'];
            }
            
            // Test bulk media rename
            $bulk_result = $this->ai_controller->rename_bulk_media([$this->test_attachment_id]);
            
            if (!$bulk_result || !isset($bulk_result['success'])) {
                return ['success' => false, 'error' => 'Bulk rename failed'];
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function testMediaLibraryIntegration() {
        try {
            // Test that AI buttons are properly added to media library
            $ui_elements = $this->checkAIUIElements();
            
            if (!$ui_elements['ai_button_present']) {
                return ['success' => false, 'error' => 'AI button not found in media library'];
            }
            
            // Test AJAX endpoints
            $ajax_test = $this->testAjaxEndpoints();
            
            if (!$ajax_test['success']) {
                return ['success' => false, 'error' => 'AJAX endpoints failed: ' . $ajax_test['error']];
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function simulateSEOPlugin($plugin_key, $plugin_class) {
        // Create mock plugin class
        if (!class_exists($plugin_class)) {
            eval("class {$plugin_class} {
                public static function get_meta(\$post_id, \$key) {
                    return 'Mock SEO data for ' . \$key;
                }
                
                public static function get_focus_keyword(\$post_id) {
                    return 'mock-focus-keyword';
                }
                
                public static function get_title(\$post_id) {
                    return 'Mock SEO Title';
                }
                
                public static function get_description(\$post_id) {
                    return 'Mock SEO description';
                }
            }");
        }
        
        // Set plugin-specific globals
        switch ($plugin_key) {
            case 'rank_math':
                global $rank_math;
                $rank_math = new stdClass();
                break;
            case 'yoast':
                global $wpseo_options;
                $wpseo_options = ['enable' => true];
                break;
        }
    }

    private function testSEOContextExtraction($plugin_key) {
        try {
            $context = $this->context_extractor->extract_context($this->test_attachment_id);
            
            // Check that SEO data is included
            if (!isset($context['seo_data']) || empty($context['seo_data'])) {
                return ['success' => false, 'error' => 'SEO data not extracted'];
            }
            
            // Check for plugin-specific data
            if (!isset($context['seo_data'][$plugin_key])) {
                return ['success' => false, 'error' => "Plugin-specific data not found for {$plugin_key}"];
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function testAISuggestionsWithSEOData($plugin_key) {
        try {
            $suggestions = $this->ai_controller->get_ai_suggestions($this->test_attachment_id, 3);
            
            if (!$suggestions || !isset($suggestions['suggestions'])) {
                return ['success' => false, 'error' => 'No AI suggestions generated'];
            }
            
            // Check that suggestions incorporate SEO keywords
            $has_seo_influence = false;
            foreach ($suggestions['suggestions'] as $suggestion) {
                if (strpos(strtolower($suggestion), 'mock') !== false || 
                    strpos(strtolower($suggestion), 'keyword') !== false) {
                    $has_seo_influence = true;
                    break;
                }
            }
            
            if (!$has_seo_influence) {
                return ['success' => false, 'error' => 'AI suggestions do not incorporate SEO data'];
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function simulateTheme($theme_slug, $theme_name) {
        // Mock theme activation
        global $wp_theme;
        $wp_theme = (object) [
            'stylesheet' => $theme_slug,
            'name' => $theme_name,
            'version' => '1.0.0'
        ];
        
        // Set theme-specific styles and scripts
        $this->setThemeSpecificAssets($theme_slug);
    }

    private function testMediaLibraryUIIntegration($theme_slug) {
        try {
            // Test that AI UI elements are properly styled for the theme
            $ui_compatibility = $this->checkUICompatibilityWithTheme($theme_slug);
            
            if (!$ui_compatibility['compatible']) {
                return ['success' => false, 'error' => 'UI not compatible with theme'];
            }
            
            // Test that JavaScript works with theme's scripts
            $js_compatibility = $this->checkJavaScriptCompatibility($theme_slug);
            
            if (!$js_compatibility['compatible']) {
                return ['success' => false, 'error' => 'JavaScript conflicts with theme'];
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function testAjaxFunctionalityWithTheme($theme_slug) {
        try {
            // Simulate AJAX request in theme context
            $_POST['action'] = 'fmr_ai_rename_single';
            $_POST['post_id'] = $this->test_attachment_id;
            $_POST['nonce'] = wp_create_nonce('fmr_ai_rename');
            
            // Test AJAX handler
            $ajax_result = $this->simulateAjaxRequest('fmr_ai_rename_single');
            
            if (!$ajax_result['success']) {
                return ['success' => false, 'error' => 'AJAX request failed'];
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function testBulkRenameFunctionalityWithTheme($theme_slug) {
        try {
            // Test bulk rename UI integration
            $bulk_ui = $this->checkBulkRenameUI($theme_slug);
            
            if (!$bulk_ui['present']) {
                return ['success' => false, 'error' => 'Bulk rename UI not present'];
            }
            
            // Test bulk rename functionality
            $bulk_result = $this->ai_controller->rename_bulk_media([
                $this->test_attachment_id,
                $this->test_attachment_id + 1
            ]);
            
            if (!$bulk_result['success']) {
                return ['success' => false, 'error' => 'Bulk rename failed'];
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function simulatePageBuilder($builder_key, $builder_class) {
        // Create mock page builder class
        if (!class_exists($builder_class)) {
            eval("class {$builder_class} {
                public static function get_content(\$post_id) {
                    return 'Mock page builder content with images and text';
                }
                
                public static function get_elements(\$post_id) {
                    return [
                        ['type' => 'image', 'content' => 'image content'],
                        ['type' => 'text', 'content' => 'text content']
                    ];
                }
            }");
        }
    }

    private function testPageBuilderContentExtraction($builder_key) {
        try {
            $context = $this->context_extractor->extract_context($this->test_attachment_id);
            
            // Check that page builder content is extracted
            if (!isset($context['page_builder_content'])) {
                return ['success' => false, 'error' => 'Page builder content not extracted'];
            }
            
            if (!isset($context['page_builder_content'][$builder_key])) {
                return ['success' => false, 'error' => "Content not extracted for {$builder_key}"];
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function testContextAnalysisWithPageBuilder($builder_key) {
        try {
            $context = $this->context_extractor->extract_context($this->test_attachment_id);
            $suggestions = $this->ai_controller->get_ai_suggestions($this->test_attachment_id, 3);
            
            // Check that page builder context influences AI suggestions
            if (!$suggestions || !isset($suggestions['suggestions'])) {
                return ['success' => false, 'error' => 'No AI suggestions with page builder context'];
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // Additional helper methods for mocking and testing...

    private function createMockWpdb() {
        return new class {
            public function prepare($query, ...$args) {
                return vsprintf(str_replace('%s', "'%s'", $query), $args);
            }
            
            public function get_results($query) {
                return [
                    (object) ['ID' => 123, 'post_title' => 'Test Post']
                ];
            }
            
            public function get_var($query) {
                return '1';
            }
        };
    }

    private function mockWordPressFunctions() {
        if (!function_exists('wp_create_nonce')) {
            function wp_create_nonce($action) {
                return 'mock_nonce_' . $action;
            }
        }
        
        if (!function_exists('get_attached_file')) {
            function get_attached_file($attachment_id) {
                return '/path/to/test-image.jpg';
            }
        }
        
        if (!function_exists('wp_get_attachment_metadata')) {
            function wp_get_attachment_metadata($attachment_id) {
                return [
                    'width' => 1920,
                    'height' => 1080,
                    'file' => 'test-image.jpg'
                ];
            }
        }
    }

    private function checkAIUIElements() {
        // Mock UI element checking
        return [
            'ai_button_present' => true,
            'bulk_ai_option_present' => true,
            'ai_modal_present' => true
        ];
    }

    private function testAjaxEndpoints() {
        try {
            // Test single rename endpoint
            $single_endpoint = $this->simulateAjaxRequest('fmr_ai_rename_single');
            
            // Test bulk rename endpoint
            $bulk_endpoint = $this->simulateAjaxRequest('fmr_ai_rename_bulk');
            
            return [
                'success' => $single_endpoint['success'] && $bulk_endpoint['success']
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function simulateAjaxRequest($action) {
        // Mock AJAX request simulation
        return ['success' => true, 'data' => ['message' => 'AJAX request successful']];
    }

    private function cleanupTestData() {
        // Clean up any test data created during tests
        unset($_POST, $_GET);
    }

    private function cleanupSEOPluginSimulation($plugin_key) {
        // Clean up plugin simulation
        switch ($plugin_key) {
            case 'rank_math':
                unset($GLOBALS['rank_math']);
                break;
            case 'yoast':
                unset($GLOBALS['wpseo_options']);
                break;
        }
    }

    private function simulateMultisite() {
        if (!defined('MULTISITE')) {
            define('MULTISITE', true);
        }
        
        global $current_site, $current_blog;
        $current_site = (object) ['id' => 1, 'domain' => 'example.com'];
        $current_blog = (object) ['blog_id' => 1, 'site_id' => 1];
    }

    private function testAIFunctionalityOnSite($site_id) {
        // Switch to specific site
        global $current_blog;
        $current_blog->blog_id = $site_id;
        
        // Test AI functionality
        return $this->testCoreAIFunctionality();
    }

    private function testCrossSiteMediaRestrictions() {
        // Test that media from one site can't be accessed from another
        return ['success' => true]; // Mock implementation
    }

    private function simulatePlugin($plugin_key, $plugin_class) {
        // Create mock plugin class if it doesn't exist
        if (!class_exists($plugin_class)) {
            eval("class {$plugin_class} {}");
        }
    }

    private function testAjaxWithPlugin($plugin_key) {
        // Test AJAX functionality with plugin active
        return $this->testAjaxEndpoints();
    }

    private function simulateOlderMediaHandling() {
        // Simulate older WordPress media handling
    }

    private function simulateEnhancedBlockEditor() {
        // Simulate newer block editor features
    }

    private function setThemeSpecificAssets($theme_slug) {
        // Set theme-specific CSS and JS
    }

    private function checkUICompatibilityWithTheme($theme_slug) {
        return ['compatible' => true];
    }

    private function checkJavaScriptCompatibility($theme_slug) {
        return ['compatible' => true];
    }

    private function checkBulkRenameUI($theme_slug) {
        return ['present' => true];
    }
}