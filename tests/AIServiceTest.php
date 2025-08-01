<?php
/**
 * Unit Tests for FMR_AI_Service
 * 
 * Tests AI service integration, prompt building, and response handling.
 */

use PHPUnit\Framework\TestCase;

class AIServiceTest extends TestCase {

    private $ai_service;
    private $mock_api_key;

    protected function setUp(): void {
        parent::setUp();
        
        // Initialize AI service
        $this->ai_service = new FMR_AI_Service();
        $this->mock_api_key = 'test_api_key_12345';
        
        // Mock WordPress functions
        $this->mockWordPressFunctions();
    }

    private function mockWordPressFunctions() {
        if (!function_exists('get_option')) {
            function get_option($option_name, $default = false) {
                $options = [
                    'fmr_ai_api_key' => 'test_api_key_12345',
                    'fmr_ai_enabled' => true,
                    'fmr_ai_model' => 'gpt-3.5-turbo',
                    'fmr_ai_timeout' => 30
                ];
                
                return isset($options[$option_name]) ? $options[$option_name] : $default;
            }
        }

        if (!function_exists('wp_remote_post')) {
            function wp_remote_post($url, $args) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode([
                        'choices' => [
                            [
                                'message' => [
                                    'content' => 'professional-business-meeting-2024.jpg'
                                ]
                            ]
                        ],
                        'usage' => [
                            'total_tokens' => 150
                        ]
                    ])
                ];
            }
        }

        if (!function_exists('wp_remote_retrieve_response_code')) {
            function wp_remote_retrieve_response_code($response) {
                return $response['response']['code'];
            }
        }

        if (!function_exists('wp_remote_retrieve_body')) {
            function wp_remote_retrieve_body($response) {
                return $response['body'];
            }
        }

        if (!function_exists('is_wp_error')) {
            function is_wp_error($thing) {
                return false;
            }
        }
    }

    /**
     * Test AI service initialization
     */
    public function testAIServiceInitialization() {
        $this->assertInstanceOf('FMR_AI_Service', $this->ai_service);
        $this->assertTrue(method_exists($this->ai_service, 'generate_filename'));
        $this->assertTrue(method_exists($this->ai_service, 'build_prompt'));
        $this->assertTrue(method_exists($this->ai_service, 'validate_api_key'));
    }

    /**
     * Test API key validation
     */
    public function testAPIKeyValidation() {
        // Test valid API key
        $result = $this->ai_service->validate_api_key($this->mock_api_key);
        $this->assertTrue($result);
        
        // Test invalid API key
        $result = $this->ai_service->validate_api_key('invalid_key');
        $this->assertFalse($result);
        
        // Test empty API key
        $result = $this->ai_service->validate_api_key('');
        $this->assertFalse($result);
    }

    /**
     * Test prompt building functionality
     */
    public function testPromptBuilding() {
        $context_data = [
            'file_content' => 'Business meeting with professionals discussing strategy',
            'post_title' => 'Annual Business Review 2024',
            'seo_keywords' => 'business, meeting, strategy, 2024',
            'file_type' => 'image/jpeg'
        ];
        
        $prompt = $this->ai_service->build_prompt($context_data);
        
        $this->assertIsString($prompt);
        $this->assertNotEmpty($prompt);
        $this->assertStringContainsString('Business meeting', $prompt);
        $this->assertStringContainsString('Annual Business Review', $prompt);
        $this->assertStringContainsString('business', $prompt);
    }

    /**
     * Test filename generation
     */
    public function testFilenameGeneration() {
        $context_data = [
            'file_content' => 'Professional business meeting in conference room',
            'post_title' => 'Company Strategy Meeting 2024',
            'seo_keywords' => 'business, meeting, strategy',
            'file_type' => 'image/jpeg'
        ];
        
        $result = $this->ai_service->generate_filename($context_data);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('filename', $result);
        $this->assertArrayHasKey('tokens_used', $result);
        
        $this->assertTrue($result['success']);
        $this->assertIsString($result['filename']);
        $this->assertNotEmpty($result['filename']);
        $this->assertIsInt($result['tokens_used']);
    }

    /**
     * Test AI service error handling
     */
    public function testAIServiceErrorHandling() {
        // Test with empty context
        $result = $this->ai_service->generate_filename([]);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('error', $result);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('context', strtolower($result['error']));
    }

    /**
     * Test API timeout handling
     */
    public function testAPITimeoutHandling() {
        // Mock timeout scenario
        if (!function_exists('wp_remote_post_timeout')) {
            function wp_remote_post_timeout($url, $args) {
                return new WP_Error('http_request_failed', 'Operation timed out');
            }
        }
        
        $context_data = [
            'file_content' => 'Test content',
            'post_title' => 'Test Title'
        ];
        
        // This should handle timeout gracefully
        $result = $this->ai_service->generate_filename($context_data);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        
        if (!$result['success']) {
            $this->assertArrayHasKey('error', $result);
            $this->assertStringContainsString('timeout', strtolower($result['error']));
        }
    }

    /**
     * Test response parsing
     */
    public function testResponseParsing() {
        $mock_response_body = json_encode([
            'choices' => [
                [
                    'message' => [
                        'content' => 'business-strategy-meeting-2024.jpg'
                    ]
                ]
            ],
            'usage' => [
                'total_tokens' => 125
            ]
        ]);
        
        $parsed_response = $this->ai_service->parse_response($mock_response_body);
        
        $this->assertIsArray($parsed_response);
        $this->assertArrayHasKey('filename', $parsed_response);
        $this->assertArrayHasKey('tokens_used', $parsed_response);
        
        $this->assertEquals('business-strategy-meeting-2024.jpg', $parsed_response['filename']);
        $this->assertEquals(125, $parsed_response['tokens_used']);
    }

    /**
     * Test malformed response handling
     */
    public function testMalformedResponseHandling() {
        $malformed_response = '{"invalid": "json"';
        
        $parsed_response = $this->ai_service->parse_response($malformed_response);
        
        $this->assertIsArray($parsed_response);
        $this->assertArrayHasKey('error', $parsed_response);
        $this->assertStringContainsString('parse', strtolower($parsed_response['error']));
    }

    /**
     * Test retry mechanism
     */
    public function testRetryMechanism() {
        $context_data = [
            'file_content' => 'Test content for retry',
            'post_title' => 'Test Title'
        ];
        
        // Mock initial failure then success
        $attempt_count = 0;
        $this->ai_service->set_retry_callback(function() use (&$attempt_count) {
            $attempt_count++;
            return $attempt_count > 1; // Succeed on second attempt
        });
        
        $result = $this->ai_service->generate_filename($context_data);
        
        $this->assertIsArray($result);
        $this->assertGreaterThan(1, $attempt_count);
    }

    /**
     * Test filename sanitization
     */
    public function testFilenameSanitization() {
        $unsafe_filename = 'file with spaces & special chars!@#.jpg';
        
        $sanitized = $this->ai_service->sanitize_filename($unsafe_filename);
        
        $this->assertIsString($sanitized);
        $this->assertStringNotContainsString(' ', $sanitized);
        $this->assertStringNotContainsString('&', $sanitized);
        $this->assertStringNotContainsString('!', $sanitized);
        $this->assertStringNotContainsString('@', $sanitized);
        $this->assertStringNotContainsString('#', $sanitized);
    }

    /**
     * Test batch filename generation
     */
    public function testBatchFilenameGeneration() {
        $batch_contexts = [
            [
                'file_content' => 'First image content',
                'post_title' => 'First Post'
            ],
            [
                'file_content' => 'Second image content',
                'post_title' => 'Second Post'
            ],
            [
                'file_content' => 'Third image content',
                'post_title' => 'Third Post'
            ]
        ];
        
        $results = $this->ai_service->generate_filenames_batch($batch_contexts);
        
        $this->assertIsArray($results);
        $this->assertCount(3, $results);
        
        foreach ($results as $result) {
            $this->assertIsArray($result);
            $this->assertArrayHasKey('success', $result);
            $this->assertArrayHasKey('filename', $result);
        }
    }

    /**
     * Test AI service configuration
     */
    public function testAIServiceConfiguration() {
        $config = $this->ai_service->get_configuration();
        
        $this->assertIsArray($config);
        $this->assertArrayHasKey('api_key', $config);
        $this->assertArrayHasKey('model', $config);
        $this->assertArrayHasKey('timeout', $config);
        $this->assertArrayHasKey('enabled', $config);
        
        $this->assertEquals($this->mock_api_key, $config['api_key']);
        $this->assertTrue($config['enabled']);
    }

    /**
     * Test AI service performance monitoring
     */
    public function testAIServicePerformanceMonitoring() {
        $context_data = [
            'file_content' => 'Performance test content',
            'post_title' => 'Performance Test'
        ];
        
        $start_time = microtime(true);
        $result = $this->ai_service->generate_filename($context_data);
        $end_time = microtime(true);
        
        $execution_time = $end_time - $start_time;
        
        // Should complete within reasonable time
        $this->assertLessThan(2.0, $execution_time, 'AI service should respond within 2 seconds');
        
        // Check performance metrics
        $metrics = $this->ai_service->get_performance_metrics();
        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('average_response_time', $metrics);
        $this->assertArrayHasKey('total_requests', $metrics);
    }
}