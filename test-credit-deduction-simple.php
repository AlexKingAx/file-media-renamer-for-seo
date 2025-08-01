<?php
/**
 * Simple test script for credit deduction system
 * Tests the class methods without full WordPress environment
 */

// Mock WordPress functions for testing
if (!function_exists('__')) {
    function __($text, $domain = 'default') {
        return $text;
    }
}

if (!function_exists('get_current_user_id')) {
    function get_current_user_id() {
        return 1;
    }
}

if (!function_exists('get_userdata')) {
    function get_userdata($user_id) {
        return (object) array(
            'ID' => $user_id,
            'user_login' => 'testuser',
            'user_email' => 'test@example.com',
            'user_registered' => '2024-01-01 00:00:00'
        );
    }
}

if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        $options = array(
            'fmrseo_options' => array(
                'ai_enabled' => true,
                'ai_api_key' => 'test_key',
                'ai_api_endpoint' => 'https://api.example.com'
            )
        );
        return isset($options[$option]) ? $options[$option] : $default;
    }
}

if (!function_exists('get_user_meta')) {
    function get_user_meta($user_id, $key, $single = false) {
        return $single ? array() : array(array());
    }
}

if (!function_exists('update_user_meta')) {
    function update_user_meta($user_id, $key, $value) {
        return true;
    }
}

if (!function_exists('user_can')) {
    function user_can($user_id, $capability) {
        return true;
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can($capability) {
        return true;
    }
}

if (!function_exists('wp_generate_uuid4')) {
    function wp_generate_uuid4() {
        return 'test-uuid-' . uniqid();
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data) {
        return json_encode($data);
    }
}

if (!function_exists('wp_remote_request')) {
    function wp_remote_request($url, $args) {
        // Mock API response for testing
        return array(
            'response' => array('code' => 200),
            'body' => json_encode(array('success' => true, 'message' => 'Test response'))
        );
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return false;
    }
}

if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($response) {
        return 200;
    }
}

if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response) {
        return $response['body'];
    }
}

if (!function_exists('wp_remote_retrieve_headers')) {
    function wp_remote_retrieve_headers($response) {
        return array();
    }
}

if (!function_exists('trailingslashit')) {
    function trailingslashit($string) {
        return rtrim($string, '/') . '/';
    }
}

if (!function_exists('get_site_url')) {
    function get_site_url() {
        return 'https://example.com';
    }
}

if (!function_exists('do_action')) {
    function do_action($hook, ...$args) {
        // Mock action hook
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters($hook, $value, ...$args) {
        return $value;
    }
}

if (!function_exists('wp_mail')) {
    function wp_mail($to, $subject, $message) {
        return true;
    }
}

if (!function_exists('error_log')) {
    function error_log($message) {
        echo "[LOG] " . $message . "\n";
    }
}

// Include the credit manager class
echo "Including credit manager class...\n";
if (file_exists('includes/ai/class-fmr-credit-manager.php')) {
    require_once 'includes/ai/class-fmr-credit-manager.php';
    echo "Credit manager class included successfully.\n";
} else {
    echo "ERROR: Credit manager class file not found!\n";
    exit(1);
}

// Test the credit deduction system
function test_credit_deduction_system() {
    echo "Testing Credit Deduction System\n";
    echo "================================\n\n";
    
    $credit_manager = new FMR_Credit_Manager();
    $test_user_id = 1;
    
    try {
        // Test 1: Validation
        echo "Test 1: Validation\n";
        echo "------------------\n";
        $validation = $credit_manager->validate_credit_operation('ai_rename', 1, $test_user_id);
        echo "Validation result: " . ($validation['valid'] ? "VALID" : "INVALID") . "\n";
        if (!$validation['valid']) {
            echo "Errors: " . implode(', ', $validation['errors']) . "\n";
        }
        echo "\n";
        
        // Test 2: Initialize free credits
        echo "Test 2: Initialize Free Credits\n";
        echo "-------------------------------\n";
        $result = $credit_manager->initialize_free_credits_enhanced($test_user_id);
        echo "Free credits initialization: " . ($result ? "SUCCESS" : "ALREADY INITIALIZED") . "\n";
        echo "Current balance: " . $credit_manager->get_credit_balance($test_user_id) . "\n\n";
        
        // Test 3: Check sufficient credits
        echo "Test 3: Check Sufficient Credits\n";
        echo "--------------------------------\n";
        $has_credits = $credit_manager->has_sufficient_credits(1, $test_user_id);
        echo "Has sufficient credits (1): " . ($has_credits ? "YES" : "NO") . "\n";
        
        $has_many_credits = $credit_manager->has_sufficient_credits(100, $test_user_id);
        echo "Has sufficient credits (100): " . ($has_many_credits ? "YES" : "NO") . "\n";
        echo "\n";
        
        // Test 4: Insufficient credits error handling
        echo "Test 4: Insufficient Credits Error Handling\n";
        echo "-------------------------------------------\n";
        $current_balance = $credit_manager->get_credit_balance($test_user_id);
        $required_credits = $current_balance + 10;
        
        if (!$credit_manager->has_sufficient_credits($required_credits, $test_user_id)) {
            $error_data = $credit_manager->handle_insufficient_credits_error($required_credits, $current_balance);
            echo "Error handled correctly:\n";
            echo "Message: " . $error_data['message'] . "\n";
            echo "Required: " . $error_data['required_credits'] . "\n";
            echo "Available: " . $error_data['available_credits'] . "\n";
            echo "Shortage: " . $error_data['shortage'] . "\n";
        }
        echo "\n";
        
        // Test 5: Credit deduction with API (mocked)
        echo "Test 5: Credit Deduction with API\n";
        echo "---------------------------------\n";
        try {
            $result = $credit_manager->deduct_credit_with_api(1, $test_user_id, null, 'ai_rename');
            echo "Credit deduction: SUCCESS\n";
            echo "Credits deducted: " . $result['credits_deducted'] . "\n";
            echo "Remaining balance: " . $result['remaining_balance'] . "\n";
            echo "Operation: " . $result['operation'] . "\n";
        } catch (Exception $e) {
            echo "Credit deduction failed: " . $e->getMessage() . "\n";
        }
        echo "\n";
        
        // Test 6: Get credit statistics
        echo "Test 6: Credit Statistics\n";
        echo "------------------------\n";
        $stats = $credit_manager->get_credit_stats($test_user_id);
        echo "Current balance: " . $stats['current_balance'] . "\n";
        echo "Total used: " . $stats['total_used'] . "\n";
        echo "Free credits initialized: " . ($stats['free_credits_initialized'] ? 'Yes' : 'No') . "\n";
        echo "Total transactions: " . $stats['total_transactions'] . "\n";
        echo "\n";
        
        // Test 7: Bulk deduction
        echo "Test 7: Bulk Credit Deduction\n";
        echo "-----------------------------\n";
        $operations = array(
            array('user_id' => $test_user_id, 'amount' => 1, 'operation' => 'bulk_rename', 'post_id' => 123),
            array('user_id' => $test_user_id, 'amount' => 1, 'operation' => 'bulk_rename', 'post_id' => 124)
        );
        
        $bulk_results = $credit_manager->bulk_deduct_credits($operations);
        echo "Bulk operations processed: " . count($bulk_results) . "\n";
        foreach ($bulk_results as $index => $result) {
            echo "Operation $index: " . ($result['success'] ? 'SUCCESS' : 'FAILED') . "\n";
            if (!$result['success']) {
                echo "  Error: " . $result['error'] . "\n";
            }
        }
        
        echo "\nAll tests completed successfully!\n";
        
    } catch (Exception $e) {
        echo "Test failed with error: " . $e->getMessage() . "\n";
        echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    }
}

// Run the test
echo "Starting test...\n";
test_credit_deduction_system();
echo "Test completed.\n";
?>