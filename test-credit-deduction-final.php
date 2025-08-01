<?php
/**
 * Final test script for credit deduction system
 */

// Define WordPress constants to prevent exit
define('ABSPATH', dirname(__FILE__) . '/');

// Mock WordPress functions for testing
function __($text, $domain = 'default') {
    return $text;
}

function get_current_user_id() {
    return 1;
}

function get_userdata($user_id) {
    return (object) array(
        'ID' => $user_id,
        'user_login' => 'testuser',
        'user_email' => 'test@example.com',
        'display_name' => 'Test User',
        'user_registered' => '2024-01-01 00:00:00'
    );
}

function get_option($option, $default = false) {
    $options = array(
        'fmrseo_options' => array(
            'ai_enabled' => true,
            'ai_api_key' => 'test_key_123',
            'ai_api_endpoint' => 'https://api.example.com'
        )
    );
    return isset($options[$option]) ? $options[$option] : $default;
}

// Global variable to store user meta for testing
$test_user_meta = array();

function get_user_meta($user_id, $key, $single = false) {
    global $test_user_meta;
    $user_key = $user_id . '_' . $key;
    
    if (isset($test_user_meta[$user_key])) {
        return $single ? $test_user_meta[$user_key] : array($test_user_meta[$user_key]);
    }
    
    return $single ? false : array();
}

function update_user_meta($user_id, $key, $value) {
    global $test_user_meta;
    $user_key = $user_id . '_' . $key;
    $test_user_meta[$user_key] = $value;
    echo "[DB] Updated user meta for user $user_id: $key (balance: " . (isset($value['balance']) ? $value['balance'] : 'N/A') . ")\n";
    return true;
}

function user_can($user_id, $capability) {
    return true;
}

function current_user_can($capability) {
    return true;
}

function wp_generate_uuid4() {
    return 'test-uuid-' . uniqid();
}

function wp_json_encode($data) {
    return json_encode($data);
}

function wp_remote_request($url, $args) {
    echo "[API] Request to: $url\n";
    echo "[API] Method: " . $args['method'] . "\n";
    
    // Mock successful API response
    return array(
        'response' => array('code' => 200),
        'body' => json_encode(array(
            'success' => true, 
            'message' => 'Credit deducted successfully',
            'remaining_balance' => 4,
            'transaction_id' => 'txn_' . uniqid()
        ))
    );
}

function is_wp_error($thing) {
    return false;
}

function wp_remote_retrieve_response_code($response) {
    return $response['response']['code'];
}

function wp_remote_retrieve_body($response) {
    return $response['body'];
}

function wp_remote_retrieve_headers($response) {
    return array();
}

function trailingslashit($string) {
    return rtrim($string, '/') . '/';
}

function get_site_url() {
    return 'https://example.com';
}

function do_action($hook, ...$args) {
    echo "[HOOK] Action: $hook\n";
}

function apply_filters($hook, $value, ...$args) {
    return $value;
}

function wp_mail($to, $subject, $message) {
    echo "[EMAIL] Sent to: $to, Subject: $subject\n";
    return true;
}

if (!function_exists('error_log')) {
    function error_log($message) {
        echo "[LOG] " . $message . "\n";
    }
}

function get_plugin_data($plugin_file) {
    return array('Version' => '1.0.0');
}

function sanitize_text_field($str) {
    return trim($str);
}

// Include the credit manager class
require_once 'includes/ai/class-fmr-credit-manager.php';

echo "=== Credit Deduction System Test ===\n\n";

try {
    $credit_manager = new FMR_Credit_Manager();
    $test_user_id = 1;
    
    echo "1. Testing validation system...\n";
    $validation = $credit_manager->validate_credit_operation('ai_rename', 1, $test_user_id);
    echo "   Validation: " . ($validation['valid'] ? "PASSED" : "FAILED") . "\n";
    
    echo "\n2. Testing free credits initialization...\n";
    $result = $credit_manager->initialize_free_credits_enhanced($test_user_id);
    echo "   Initialization: " . ($result ? "SUCCESS" : "ALREADY DONE") . "\n";
    echo "   Balance after init: " . $credit_manager->get_credit_balance($test_user_id) . "\n";
    
    echo "\n3. Testing credit deduction with API...\n";
    $deduction_result = $credit_manager->deduct_credit_with_api(1, $test_user_id, 123, 'ai_rename');
    echo "   Deduction: " . ($deduction_result['success'] ? "SUCCESS" : "FAILED") . "\n";
    echo "   Credits deducted: " . $deduction_result['credits_deducted'] . "\n";
    echo "   Remaining balance: " . $deduction_result['remaining_balance'] . "\n";
    
    echo "\n4. Testing insufficient credits handling...\n";
    $current_balance = $credit_manager->get_credit_balance($test_user_id);
    $error_data = $credit_manager->handle_insufficient_credits_error(100, $current_balance);
    echo "   Error message: " . $error_data['message'] . "\n";
    echo "   Shortage: " . $error_data['shortage'] . "\n";
    
    echo "\n5. Testing credit statistics...\n";
    $stats = $credit_manager->get_credit_stats($test_user_id);
    echo "   Current balance: " . $stats['current_balance'] . "\n";
    echo "   Total used: " . $stats['total_used'] . "\n";
    echo "   Total transactions: " . $stats['total_transactions'] . "\n";
    
    echo "\n=== ALL TESTS COMPLETED SUCCESSFULLY ===\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>