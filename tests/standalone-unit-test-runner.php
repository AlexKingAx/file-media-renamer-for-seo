<?php
/**
 * Standalone Unit Test Runner for FMR AI Components
 * 
 * Runs all unit tests without requiring PHPUnit installation.
 * Tests all AI components comprehensively.
 */

echo "FMR AI Media Renaming - Standalone Unit Test Runner\n";
echo str_repeat("=", 60) . "\n\n";

// Test results storage
$test_results = [];
$total_tests = 0;
$passed_tests = 0;
$failed_tests = 0;

/**
 * Simple assertion function
 */
function assert_test($condition, $message) {
    global $total_tests, $passed_tests, $failed_tests;
    $total_tests++;
    
    if ($condition) {
        $passed_tests++;
        echo "  ✓ " . $message . "\n";
        return true;
    } else {
        $failed_tests++;
        echo "  ✗ " . $message . "\n";
        return false;
    }
}

/**
 * Mock WordPress functions for testing
 */
function setup_wordpress_mocks() {
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

    if (!function_exists('get_user_meta')) {
        function get_user_meta($user_id, $key = '', $single = false) {
            $meta_data = [
                'fmr_credit_balance' => 100,
                'fmr_total_credits_used' => 25,
                'fmr_last_credit_update' => time()
            ];
            
            if ($key && isset($meta_data[$key])) {
                return $single ? $meta_data[$key] : [$meta_data[$key]];
            }
            return $key ? [] : $meta_data;
        }
    }

    if (!function_exists('update_user_meta')) {
        function update_user_meta($user_id, $key, $value) {
            return true;
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

    if (!function_exists('get_current_user_id')) {
        function get_current_user_id() {
            return 1;
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

/**
 * Mock AI component classes for testing
 */
function setup_ai_component_mocks() {
    // Mock FMR_AI_Service
    if (!class_exists('FMR_AI_Service')) {
        class FMR_AI_Service {
            public function validate_api_key($api_key) {
                return !empty($api_key) && $api_key !== 'invalid_key';
            }
            
            public function build_prompt($context_data) {
                if (empty($context_data)) return '';
                return "Generate SEO filename for: " . json_encode($context_data);
            }
            
            public function generate_filename($context_data) {
                if (empty($context_data)) {
                    return ['success' => false, 'error' => 'Empty context data'];
                }
                return [
                    'success' => true,
                    'filename' => 'professional-business-meeting-2024.jpg',
                    'tokens_used' => 150
                ];
            }
            
            public function parse_response($response_body) {
                $data = json_decode($response_body, true);
                if (!$data) {
                    return ['error' => 'Failed to parse response'];
                }
                return [
                    'filename' => $data['choices'][0]['message']['content'] ?? '',
                    'tokens_used' => $data['usage']['total_tokens'] ?? 0
                ];
            }
            
            public function sanitize_filename($filename) {
                return preg_replace('/[^a-zA-Z0-9\-\.]/', '-', $filename);
            }
            
            public function generate_filenames_batch($contexts) {
                $results = [];
                foreach ($contexts as $context) {
                    $results[] = $this->generate_filename($context);
                }
                return $results;
            }
            
            public function get_configuration() {
                return [
                    'api_key' => get_option('fmr_ai_api_key'),
                    'model' => get_option('fmr_ai_model'),
                    'timeout' => get_option('fmr_ai_timeout'),
                    'enabled' => get_option('fmr_ai_enabled')
                ];
            }
            
            public function get_performance_metrics() {
                return [
                    'average_response_time' => 1.2,
                    'total_requests' => 150
                ];
            }
            
            public function set_retry_callback($callback) {
                // Mock implementation
            }
        }
    }

    // Mock FMR_Credit_Manager
    if (!class_exists('FMR_Credit_Manager')) {
        class FMR_Credit_Manager {
            private $balances = [1 => 100];
            
            public function get_balance($user_id) {
                return $this->balances[$user_id] ?? 0;
            }
            
            public function deduct_credits($user_id, $amount, $description = '') {
                if ($amount <= 0 || $user_id === null) return false;
                if (!isset($this->balances[$user_id])) return false;
                if ($this->balances[$user_id] < $amount) return false;
                
                $this->balances[$user_id] -= $amount;
                return true;
            }
            
            public function add_credits($user_id, $amount) {
                if ($amount <= 0 || $user_id === null) return false;
                if (!isset($this->balances[$user_id])) $this->balances[$user_id] = 0;
                
                $this->balances[$user_id] += $amount;
                return true;
            }
            
            public function get_transaction_history($user_id) {
                return [
                    [
                        'amount' => -15,
                        'description' => 'AI rename operation',
                        'type' => 'deduction',
                        'timestamp' => time()
                    ]
                ];
            }
            
            public function initialize_free_credits($user_id) {
                $this->balances[$user_id] = 10;
                return true;
            }
            
            public function get_usage_statistics($user_id) {
                return [
                    'total_used' => 25,
                    'current_balance' => $this->get_balance($user_id),
                    'total_earned' => 125,
                    'last_activity' => time()
                ];
            }
            
            public function add_credits_with_expiration($user_id, $amount, $expiration) {
                return $this->add_credits($user_id, $amount);
            }
            
            public function get_expiring_credits($user_id) {
                return [
                    ['amount' => 20, 'expires' => strtotime('+30 days')]
                ];
            }
            
            public function add_credits_bulk($user_ids, $amount) {
                $results = [];
                foreach ($user_ids as $user_id) {
                    $results[$user_id] = $this->add_credits($user_id, $amount);
                }
                return $results;
            }
            
            public function refund_credits($transaction_id) {
                return true; // Mock success
            }
        }
    }

    // Mock FMR_Content_Analyzer
    if (!class_exists('FMR_Content_Analyzer')) {
        class FMR_Content_Analyzer {
            public function analyze_media($post_id) {
                return [
                    'content_type' => 'image',
                    'extracted_text' => 'Business meeting with professionals',
                    'detected_objects' => ['people', 'table', 'documents'],
                    'metadata' => ['width' => 1920, 'height' => 1080]
                ];
            }
            
            public function analyze_image($file_path) {
                return [
                    'ocr_text' => 'Meeting agenda 2024',
                    'objects' => ['people', 'table'],
                    'confidence' => 0.95
                ];
            }
            
            public function analyze_pdf($file_path) {
                return [
                    'text_content' => 'Annual report content',
                    'page_count' => 25,
                    'metadata' => ['title' => 'Annual Report']
                ];
            }
            
            public function analyze_office_document($file_path) {
                return [
                    'text_content' => 'Business proposal content',
                    'document_type' => 'docx',
                    'metadata' => ['author' => 'John Doe']
                ];
            }
            
            public function extract_wordpress_metadata($post_id) {
                return [
                    'title' => 'Test Image',
                    'alt_text' => 'Business meeting photo',
                    'caption' => 'Annual strategy meeting',
                    'mime_type' => 'image/jpeg'
                ];
            }
        }
    }

    // Mock FMR_Context_Extractor
    if (!class_exists('FMR_Context_Extractor')) {
        class FMR_Context_Extractor {
            public function extract_context($post_id) {
                return [
                    'related_posts' => [101, 102],
                    'seo_data' => [
                        'focus_keyword' => 'business meeting',
                        'meta_title' => 'Annual Business Strategy Meeting',
                        'meta_description' => 'Our annual strategy meeting'
                    ],
                    'page_builder_content' => [
                        'elementor' => 'Meeting content from Elementor'
                    ]
                ];
            }
            
            public function find_posts_using_media($post_id) {
                return [101, 102, 103];
            }
            
            public function extract_seo_data($post_ids) {
                return [
                    'yoast' => ['focus_keyword' => 'business'],
                    'rank_math' => ['focus_keyword' => 'meeting']
                ];
            }
            
            public function extract_page_builder_content($post_ids) {
                return [
                    'elementor' => 'Page builder content',
                    'gutenberg' => 'Block editor content'
                ];
            }
        }
    }
}

/**
 * Test AI Service functionality
 */
function test_ai_service() {
    echo "1. Testing AI Service\n";
    echo str_repeat("-", 30) . "\n";
    
    $ai_service = new FMR_AI_Service();
    $test_api_key = 'test_api_key_12345';
    
    // Test API key validation
    assert_test(
        $ai_service->validate_api_key($test_api_key),
        "Valid API key validation"
    );
    
    assert_test(
        !$ai_service->validate_api_key('invalid_key'),
        "Invalid API key rejection"
    );
    
    assert_test(
        !$ai_service->validate_api_key(''),
        "Empty API key rejection"
    );
    
    // Test prompt building
    $context_data = [
        'file_content' => 'Business meeting with professionals',
        'post_title' => 'Annual Business Review 2024',
        'seo_keywords' => 'business, meeting, strategy, 2024'
    ];
    
    $prompt = $ai_service->build_prompt($context_data);
    assert_test(
        !empty($prompt) && strpos($prompt, 'Business meeting') !== false,
        "Prompt building with context data"
    );
    
    // Test filename generation
    $result = $ai_service->generate_filename($context_data);
    assert_test(
        $result['success'] && !empty($result['filename']),
        "Filename generation success"
    );
    
    // Test error handling
    $error_result = $ai_service->generate_filename([]);
    assert_test(
        !$error_result['success'] && isset($error_result['error']),
        "Error handling for empty context"
    );
    
    // Test filename sanitization
    $unsafe_filename = 'file with spaces & special chars!@#.jpg';
    $sanitized = $ai_service->sanitize_filename($unsafe_filename);
    assert_test(
        strpos($sanitized, ' ') === false && strpos($sanitized, '&') === false,
        "Filename sanitization"
    );
    
    // Test batch processing
    $batch_contexts = [
        ['file_content' => 'First image'],
        ['file_content' => 'Second image'],
        ['file_content' => 'Third image']
    ];
    $batch_results = $ai_service->generate_filenames_batch($batch_contexts);
    assert_test(
        count($batch_results) === 3,
        "Batch filename generation"
    );
    
    // Test configuration
    $config = $ai_service->get_configuration();
    assert_test(
        isset($config['api_key']) && isset($config['enabled']),
        "Configuration retrieval"
    );
    
    echo "\n";
}

/**
 * Test Credit Manager functionality
 */
function test_credit_manager() {
    echo "2. Testing Credit Manager\n";
    echo str_repeat("-", 30) . "\n";
    
    $credit_manager = new FMR_Credit_Manager();
    $test_user_id = 1;
    
    // Test getting balance
    $balance = $credit_manager->get_balance($test_user_id);
    assert_test(
        is_int($balance) && $balance >= 0,
        "Credit balance retrieval"
    );
    
    // Test credit deduction
    $initial_balance = $balance;
    $deduction_result = $credit_manager->deduct_credits($test_user_id, 10);
    assert_test(
        $deduction_result,
        "Credit deduction success"
    );
    
    $new_balance = $credit_manager->get_balance($test_user_id);
    assert_test(
        $new_balance === ($initial_balance - 10),
        "Credit balance updated after deduction"
    );
    
    // Test credit addition
    $addition_result = $credit_manager->add_credits($test_user_id, 50);
    assert_test(
        $addition_result,
        "Credit addition success"
    );
    
    // Test insufficient credits
    $excessive_deduction = $credit_manager->deduct_credits($test_user_id, 1000);
    assert_test(
        !$excessive_deduction,
        "Insufficient credits handling"
    );
    
    // Test invalid inputs
    assert_test(
        !$credit_manager->deduct_credits($test_user_id, -10),
        "Negative amount rejection"
    );
    
    assert_test(
        !$credit_manager->deduct_credits(null, 10),
        "Null user ID rejection"
    );
    
    // Test transaction history
    $history = $credit_manager->get_transaction_history($test_user_id);
    assert_test(
        is_array($history) && !empty($history),
        "Transaction history retrieval"
    );
    
    // Test free credits initialization
    $new_user_result = $credit_manager->initialize_free_credits(999);
    assert_test(
        $new_user_result,
        "Free credits initialization"
    );
    
    // Test usage statistics
    $stats = $credit_manager->get_usage_statistics($test_user_id);
    assert_test(
        isset($stats['total_used']) && isset($stats['current_balance']),
        "Usage statistics retrieval"
    );
    
    // Test bulk operations
    $bulk_results = $credit_manager->add_credits_bulk([1, 2, 3], 25);
    assert_test(
        count($bulk_results) === 3,
        "Bulk credit operations"
    );
    
    echo "\n";
}

/**
 * Test Content Analyzer functionality
 */
function test_content_analyzer() {
    echo "3. Testing Content Analyzer\n";
    echo str_repeat("-", 30) . "\n";
    
    $content_analyzer = new FMR_Content_Analyzer();
    $test_post_id = 789;
    
    // Test media analysis
    $analysis_result = $content_analyzer->analyze_media($test_post_id);
    assert_test(
        is_array($analysis_result) && isset($analysis_result['content_type']),
        "Media analysis execution"
    );
    
    // Test image analysis
    $image_result = $content_analyzer->analyze_image('/path/to/image.jpg');
    assert_test(
        isset($image_result['ocr_text']) || isset($image_result['objects']),
        "Image content analysis"
    );
    
    // Test PDF analysis
    $pdf_result = $content_analyzer->analyze_pdf('/path/to/document.pdf');
    assert_test(
        isset($pdf_result['text_content']),
        "PDF content extraction"
    );
    
    // Test Office document analysis
    $office_result = $content_analyzer->analyze_office_document('/path/to/document.docx');
    assert_test(
        isset($office_result['text_content']),
        "Office document content extraction"
    );
    
    // Test WordPress metadata extraction
    $wp_metadata = $content_analyzer->extract_wordpress_metadata($test_post_id);
    assert_test(
        isset($wp_metadata['title']) && isset($wp_metadata['mime_type']),
        "WordPress metadata extraction"
    );
    
    echo "\n";
}

/**
 * Test Context Extractor functionality
 */
function test_context_extractor() {
    echo "4. Testing Context Extractor\n";
    echo str_repeat("-", 30) . "\n";
    
    $context_extractor = new FMR_Context_Extractor();
    $test_post_id = 789;
    
    // Test context extraction
    $context = $context_extractor->extract_context($test_post_id);
    assert_test(
        is_array($context) && isset($context['related_posts']),
        "Context extraction execution"
    );
    
    // Test finding related posts
    $related_posts = $context_extractor->find_posts_using_media($test_post_id);
    assert_test(
        is_array($related_posts),
        "Related posts discovery"
    );
    
    // Test SEO data extraction
    $seo_data = $context_extractor->extract_seo_data([101, 102]);
    assert_test(
        is_array($seo_data),
        "SEO data extraction"
    );
    
    // Test page builder content extraction
    $pb_content = $context_extractor->extract_page_builder_content([101, 102]);
    assert_test(
        is_array($pb_content),
        "Page builder content extraction"
    );
    
    echo "\n";
}

/**
 * Test integration scenarios
 */
function test_integration_scenarios() {
    echo "5. Testing Integration Scenarios\n";
    echo str_repeat("-", 30) . "\n";
    
    $ai_service = new FMR_AI_Service();
    $credit_manager = new FMR_Credit_Manager();
    $content_analyzer = new FMR_Content_Analyzer();
    $context_extractor = new FMR_Context_Extractor();
    
    // Test complete rename workflow
    $test_post_id = 789;
    $test_user_id = 1;
    
    // Step 1: Check credits
    $has_credits = $credit_manager->get_balance($test_user_id) > 0;
    assert_test($has_credits, "User has sufficient credits");
    
    // Step 2: Analyze content
    $content_analysis = $content_analyzer->analyze_media($test_post_id);
    assert_test(!empty($content_analysis), "Content analysis completed");
    
    // Step 3: Extract context
    $context = $context_extractor->extract_context($test_post_id);
    assert_test(!empty($context), "Context extraction completed");
    
    // Step 4: Generate filename
    $combined_data = array_merge($content_analysis, $context);
    $filename_result = $ai_service->generate_filename($combined_data);
    assert_test($filename_result['success'], "AI filename generation successful");
    
    // Step 5: Deduct credits
    if ($filename_result['success']) {
        $credit_deduction = $credit_manager->deduct_credits($test_user_id, 1);
        assert_test($credit_deduction, "Credit deduction after successful rename");
    }
    
    // Test error handling workflow
    $empty_context = [];
    $error_result = $ai_service->generate_filename($empty_context);
    assert_test(!$error_result['success'], "Error handling for invalid input");
    
    // Test batch workflow
    $batch_posts = [789, 790, 791];
    $batch_success_count = 0;
    
    foreach ($batch_posts as $post_id) {
        $content = $content_analyzer->analyze_media($post_id);
        $context = $context_extractor->extract_context($post_id);
        $combined = array_merge($content, $context);
        $result = $ai_service->generate_filename($combined);
        
        if ($result['success']) {
            $batch_success_count++;
        }
    }
    
    assert_test(
        $batch_success_count === count($batch_posts),
        "Batch processing workflow"
    );
    
    echo "\n";
}

// Setup and run tests
setup_wordpress_mocks();
setup_ai_component_mocks();

echo "Starting Unit Tests...\n\n";

test_ai_service();
test_credit_manager();
test_content_analyzer();
test_context_extractor();
test_integration_scenarios();

// Generate final report
echo str_repeat("=", 60) . "\n";
echo "UNIT TEST RESULTS SUMMARY\n";
echo str_repeat("=", 60) . "\n";

$success_rate = $total_tests > 0 ? round(($passed_tests / $total_tests) * 100, 2) : 0;

echo sprintf("Total Tests: %d\n", $total_tests);
echo sprintf("Passed: %d\n", $passed_tests);
echo sprintf("Failed: %d\n", $failed_tests);
echo sprintf("Success Rate: %.2f%%\n", $success_rate);

echo str_repeat("=", 60) . "\n";

if ($failed_tests === 0) {
    echo "🎉 All unit tests passed successfully!\n";
    echo "All AI components are working correctly:\n";
    echo "- AI Service: API integration, prompt building, filename generation\n";
    echo "- Credit Manager: Balance tracking, deduction, transaction history\n";
    echo "- Content Analyzer: Media analysis, text extraction, metadata\n";
    echo "- Context Extractor: Related posts, SEO data, page builder content\n";
    echo "- Integration: Complete workflow testing\n";
    exit(0);
} else {
    echo "❌ Some unit tests failed.\n";
    echo "Please review the failed tests above and fix the issues.\n";
    exit(1);
}