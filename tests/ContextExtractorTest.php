<?php
/**
 * Unit Tests for FMR_Context_Extractor
 * 
 * Tests context extraction functionality including post relationships,
 * SEO plugin integration, and page builder content extraction.
 */

use PHPUnit\Framework\TestCase;

class ContextExtractorTest extends TestCase {

    private $context_extractor;
    private $mock_post_id;
    private $mock_attachment_id;

    protected function setUp(): void {
        parent::setUp();
        
        // Initialize the context extractor
        $this->context_extractor = new FMR_Context_Extractor();
        
        // Set up mock IDs
        $this->mock_post_id = 123;
        $this->mock_attachment_id = 456;
        
        // Mock WordPress functions if needed
        $this->mockWordPressFunctions();
    }

    protected function tearDown(): void {
        parent::tearDown();
        // Clean up any test data
    }

    /**
     * Mock essential WordPress functions for testing
     */
    private function mockWordPressFunctions() {
        if (!function_exists('get_post')) {
            function get_post($id) {
                return (object) [
                    'ID' => $id,
                    'post_title' => 'Test Post Title',
                    'post_content' => 'Test post content with some keywords',
                    'post_excerpt' => 'Test excerpt',
                    'post_type' => 'post',
                    'post_status' => 'publish'
                ];
            }
        }
        
        if (!function_exists('get_post_meta')) {
            function get_post_meta($post_id, $key = '', $single = false) {
                $meta_data = [
                    '_yoast_wpseo_title' => 'SEO Title',
                    '_yoast_wpseo_metadesc' => 'SEO Description',
                    '_yoast_wpseo_focuskw' => 'focus keyword',
                    'custom_field' => 'custom value'
                ];
                
                if ($key && isset($meta_data[$key])) {
                    return $single ? $meta_data[$key] : [$meta_data[$key]];
                }
                
                return $key ? [] : $meta_data;
            }
        }
    }

    /**
     * Test basic context extraction from post
     */
    public function testExtractBasicPostContext() {
        $context = $this->context_extractor->extract_context($this->mock_post_id);
        
        $this->assertIsArray($context);
        $this->assertArrayHasKey('post_title', $context);
        $this->assertArrayHasKey('post_content', $context);
        $this->assertArrayHasKey('post_excerpt', $context);
        
        $this->assertEquals('Test Post Title', $context['post_title']);
        $this->assertStringContainsString('Test post content', $context['post_content']);
    }

    /**
     * Test SEO plugin integration (Yoast)
     */
    public function testExtractSEOContext() {
        $context = $this->context_extractor->extract_seo_context($this->mock_post_id);
        
        $this->assertIsArray($context);
        $this->assertArrayHasKey('seo_title', $context);
        $this->assertArrayHasKey('seo_description', $context);
        $this->assertArrayHasKey('focus_keyword', $context);
        
        $this->assertEquals('SEO Title', $context['seo_title']);
        $this->assertEquals('SEO Description', $context['seo_description']);
        $this->assertEquals('focus keyword', $context['focus_keyword']);
    }

    /**
     * Test custom field extraction
     */
    public function testExtractCustomFields() {
        $context = $this->context_extractor->extract_custom_fields($this->mock_post_id);
        
        $this->assertIsArray($context);
        $this->assertArrayHasKey('custom_field', $context);
        $this->assertEquals('custom value', $context['custom_field']);
    }

    /**
     * Test context extraction with invalid post ID
     */
    public function testExtractContextWithInvalidPostId() {
        $context = $this->context_extractor->extract_context(999999);
        
        $this->assertIsArray($context);
        $this->assertEmpty($context);
    }

    /**
     * Test context extraction for attachment
     */
    public function testExtractAttachmentContext() {
        // Mock attachment-specific functions
        if (!function_exists('wp_get_attachment_metadata')) {
            function wp_get_attachment_metadata($attachment_id) {
                return [
                    'width' => 1920,
                    'height' => 1080,
                    'file' => 'uploads/2024/01/test-image.jpg',
                    'image_meta' => [
                        'camera' => 'Canon EOS R5',
                        'keywords' => ['nature', 'landscape']
                    ]
                ];
            }
        }
        
        $context = $this->context_extractor->extract_attachment_context($this->mock_attachment_id);
        
        $this->assertIsArray($context);
        $this->assertArrayHasKey('dimensions', $context);
        $this->assertArrayHasKey('file_info', $context);
        $this->assertArrayHasKey('image_meta', $context);
    }
 
   /**
     * Test page builder content extraction (Elementor)
     */
    public function testExtractPageBuilderContent() {
        // Mock Elementor data
        if (!function_exists('get_post_meta')) {
            // Already mocked above, but we'll extend it
        }
        
        $context = $this->context_extractor->extract_page_builder_content($this->mock_post_id);
        
        $this->assertIsArray($context);
        // Test should verify that page builder content is properly extracted
    }

    /**
     * Test taxonomy extraction
     */
    public function testExtractTaxonomyContext() {
        // Mock taxonomy functions
        if (!function_exists('wp_get_post_terms')) {
            function wp_get_post_terms($post_id, $taxonomy) {
                return [
                    (object) ['name' => 'Category 1', 'slug' => 'category-1'],
                    (object) ['name' => 'Tag 1', 'slug' => 'tag-1']
                ];
            }
        }
        
        $context = $this->context_extractor->extract_taxonomy_context($this->mock_post_id);
        
        $this->assertIsArray($context);
        $this->assertArrayHasKey('categories', $context);
        $this->assertArrayHasKey('tags', $context);
    }

    /**
     * Test context filtering and sanitization
     */
    public function testContextFiltering() {
        $raw_context = [
            'post_title' => 'Test Title with <script>alert("xss")</script>',
            'post_content' => 'Content with [shortcode] and HTML <strong>tags</strong>',
            'custom_field' => 'Normal text content'
        ];
        
        $filtered_context = $this->context_extractor->filter_context($raw_context);
        
        $this->assertIsArray($filtered_context);
        $this->assertStringNotContainsString('<script>', $filtered_context['post_title']);
        $this->assertStringNotContainsString('[shortcode]', $filtered_context['post_content']);
    }

    /**
     * Test context weight calculation
     */
    public function testContextWeightCalculation() {
        $context_data = [
            'post_title' => 'Important Title',
            'seo_title' => 'SEO Optimized Title',
            'post_content' => 'Regular content',
            'custom_field' => 'Additional info'
        ];
        
        $weighted_context = $this->context_extractor->calculate_context_weights($context_data);
        
        $this->assertIsArray($weighted_context);
        $this->assertArrayHasKey('weights', $weighted_context);
        
        // Title should have higher weight than content
        $this->assertGreaterThan(
            $weighted_context['weights']['post_content'],
            $weighted_context['weights']['post_title']
        );
    }

    /**
     * Test context merging from multiple sources
     */
    public function testMergeContextSources() {
        $post_context = ['post_title' => 'Title', 'post_content' => 'Content'];
        $seo_context = ['seo_title' => 'SEO Title', 'focus_keyword' => 'keyword'];
        $custom_context = ['custom_field' => 'Custom Value'];
        
        $merged_context = $this->context_extractor->merge_context_sources([
            $post_context,
            $seo_context,
            $custom_context
        ]);
        
        $this->assertIsArray($merged_context);
        $this->assertCount(5, $merged_context);
        $this->assertArrayHasKey('post_title', $merged_context);
        $this->assertArrayHasKey('seo_title', $merged_context);
        $this->assertArrayHasKey('custom_field', $merged_context);
    }

    /**
     * Test context extraction performance
     */
    public function testContextExtractionPerformance() {
        $start_time = microtime(true);
        
        // Extract context multiple times
        for ($i = 0; $i < 10; $i++) {
            $this->context_extractor->extract_context($this->mock_post_id);
        }
        
        $end_time = microtime(true);
        $execution_time = $end_time - $start_time;
        
        // Should complete within reasonable time (adjust threshold as needed)
        $this->assertLessThan(1.0, $execution_time, 'Context extraction should be performant');
    }

    /**
     * Test context caching mechanism
     */
    public function testContextCaching() {
        // First extraction
        $context1 = $this->context_extractor->extract_context($this->mock_post_id);
        
        // Second extraction (should use cache)
        $context2 = $this->context_extractor->extract_context($this->mock_post_id);
        
        $this->assertEquals($context1, $context2);
        
        // Verify cache was used (implementation dependent)
        $this->assertTrue($this->context_extractor->was_cache_used());
    }

    /**
     * Test error handling for malformed data
     */
    public function testErrorHandlingForMalformedData() {
        // Test with null post ID
        $context = $this->context_extractor->extract_context(null);
        $this->assertIsArray($context);
        $this->assertEmpty($context);
        
        // Test with string post ID
        $context = $this->context_extractor->extract_context('invalid');
        $this->assertIsArray($context);
        $this->assertEmpty($context);
        
        // Test with negative post ID
        $context = $this->context_extractor->extract_context(-1);
        $this->assertIsArray($context);
        $this->assertEmpty($context);
    }

    /**
     * Test context extraction with different post types
     */
    public function testContextExtractionForDifferentPostTypes() {
        $post_types = ['post', 'page', 'product', 'custom_post_type'];
        
        foreach ($post_types as $post_type) {
            $context = $this->context_extractor->extract_context_by_post_type($this->mock_post_id, $post_type);
            
            $this->assertIsArray($context);
            $this->assertArrayHasKey('post_type', $context);
            $this->assertEquals($post_type, $context['post_type']);
        }
    }
}