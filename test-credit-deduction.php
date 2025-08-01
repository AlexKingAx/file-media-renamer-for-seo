<?php
/**
 * Test script for credit deduction system
 * 
 * This script tests the enhanced credit deduction functionality
 * including external API integration and error handling.
 */

// Include WordPress
require_once dirname(__FILE__) . '/../../../wp-config.php';

// Include the credit manager class
require_once dirname(__FILE__) . '/includes/ai/class-fmr-credit-manager.php';

// Test the credit deduction system
function test_credit_deduction_system() {
    echo "<h2>Testing Credit Deduction System</h2>\n";
    
    $credit_manager = new FMR_Credit_Manager();
    $test_user_id = 1; // Use admin user for testing
    
    try {
        // Test 1: Initialize free credits
        echo "<h3>Test 1: Initialize Free Credits</h3>\n";
        $result = $credit_manager->initialize_free_credits_enhanced($test_user_id);
        echo "Free credits initialization: " . ($result ? "SUCCESS" : "ALREADY INITIALIZED") . "\n";
        echo "Current balance: " . $credit_manager->get_credit_balance($test_user_id) . "\n\n";
        
        // Test 2: Check validation
        echo "<h3>Test 2: Validation Tests</h3>\n";
        $validation = $credit_manager->validate_credit_operation('ai_rename', 1, $test_user_id);
        echo "Validation result: " . ($validation['valid'] ? "VALID" : "INVALID") . "\n";
        if (!$validation['valid']) {
            echo "Errors: " . implode(', ', $validation['errors']) . "\n";
        }
        echo "\n";
        
        // Test 3: Check insufficient credits handling
        echo "<h3>Test 3: Insufficient Credits Handling</h3>\n";
        $current_balance = $credit_manager->get_credit_balance($test_user_id);
        $required_credits = $current_balance + 10; // More than available
        
        if (!$credit_manager->has_sufficient_credits($required_credits, $test_user_id)) {
            $error_data = $credit_manager->handle_insufficient_credits_error($required_credits, $current_balance);
            echo "Insufficient credits error handled correctly:\n";
            echo "Message: " . $error_data['message'] . "\n";
            echo "Shortage: " . $error_data['shortage'] . "\n";
        }
        echo "\n";
        
        // Test 4: Test credit deduction (will fail due to no real API)
        echo "<h3>Test 4: Credit Deduction (Expected to fail - no real API)</h3>\n";
        try {
            $result = $credit_manager->deduct_credit_with_api(1, $test_user_id, null, 'ai_rename');
            echo "Credit deduction: SUCCESS\n";
            echo "Remaining balance: " . $result['remaining_balance'] . "\n";
        } catch (Exception $e) {
            echo "Credit deduction failed (expected): " . $e->getMessage() . "\n";
        }
        echo "\n";
        
        // Test 5: Get credit statistics
        echo "<h3>Test 5: Credit Statistics</h3>\n";
        $stats = $credit_manager->get_credit_stats($test_user_id);
        echo "Current balance: " . $stats['current_balance'] . "\n";
        echo "Total used: " . $stats['total_used'] . "\n";
        echo "Free credits initialized: " . ($stats['free_credits_initialized'] ? 'Yes' : 'No') . "\n";
        echo "Total transactions: " . $stats['total_transactions'] . "\n";
        echo "\n";
        
        // Test 6: Transaction history
        echo "<h3>Test 6: Transaction History</h3>\n";
        $history = $credit_manager->get_transaction_history($test_user_id, 5);
        echo "Recent transactions (" . count($history) . "):\n";
        foreach ($history as $transaction) {
            echo "- " . $transaction['type'] . ": " . $transaction['amount'] . " credits (" . $transaction['operation'] . ")\n";
        }
        
    } catch (Exception $e) {
        echo "Test failed with error: " . $e->getMessage() . "\n";
    }
}

// Run the test if accessed directly
if (basename($_SERVER['PHP_SELF']) === 'test-credit-deduction.php') {
    echo "<html><head><title>Credit Deduction Test</title></head><body><pre>\n";
    test_credit_deduction_system();
    echo "</pre></body></html>\n";
}
?>