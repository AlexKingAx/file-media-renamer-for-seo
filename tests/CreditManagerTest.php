<?php
/**
 * Unit Tests for FMR_Credit_Manager
 * 
 * Tests credit tracking, deduction, and management functionality.
 */

use PHPUnit\Framework\TestCase;

class CreditManagerTest extends TestCase {

    private $credit_manager;
    private $test_user_id;

    protected function setUp(): void {
        parent::setUp();
        
        // Initialize credit manager
        $this->credit_manager = new FMR_Credit_Manager();
        $this->test_user_id = 1;
        
        // Mock WordPress functions
        $this->mockWordPressFunctions();
    }

    private function mockWordPressFunctions() {
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

        if (!function_exists('get_current_user_id')) {
            function get_current_user_id() {
                return 1;
            }
        }

        if (!function_exists('wp_insert_post')) {
            function wp_insert_post($post_data) {
                return 123; // Mock post ID
            }
        }
    }

    /**
     * Test credit manager initialization
     */
    public function testCreditManagerInitialization() {
        $this->assertInstanceOf('FMR_Credit_Manager', $this->credit_manager);
        $this->assertTrue(method_exists($this->credit_manager, 'get_balance'));
        $this->assertTrue(method_exists($this->credit_manager, 'deduct_credits'));
        $this->assertTrue(method_exists($this->credit_manager, 'add_credits'));
    }

    /**
     * Test getting credit balance
     */
    public function testGetCreditBalance() {
        $balance = $this->credit_manager->get_balance($this->test_user_id);
        
        $this->assertIsInt($balance);
        $this->assertGreaterThanOrEqual(0, $balance);
        $this->assertEquals(100, $balance);
    }

    /**
     * Test credit deduction
     */
    public function testCreditDeduction() {
        $initial_balance = $this->credit_manager->get_balance($this->test_user_id);
        $deduction_amount = 10;
        
        $result = $this->credit_manager->deduct_credits($this->test_user_id, $deduction_amount);
        
        $this->assertTrue($result);
        
        $new_balance = $this->credit_manager->get_balance($this->test_user_id);
        $this->assertEquals($initial_balance - $deduction_amount, $new_balance);
    }

    /**
     * Test credit addition
     */
    public function testCreditAddition() {
        $initial_balance = $this->credit_manager->get_balance($this->test_user_id);
        $addition_amount = 50;
        
        $result = $this->credit_manager->add_credits($this->test_user_id, $addition_amount);
        
        $this->assertTrue($result);
        
        $new_balance = $this->credit_manager->get_balance($this->test_user_id);
        $this->assertEquals($initial_balance + $addition_amount, $new_balance);
    }

    /**
     * Test insufficient credits handling
     */
    public function testInsufficientCreditsHandling() {
        // Try to deduct more credits than available
        $balance = $this->credit_manager->get_balance($this->test_user_id);
        $excessive_amount = $balance + 50;
        
        $result = $this->credit_manager->deduct_credits($this->test_user_id, $excessive_amount);
        
        $this->assertFalse($result);
        
        // Balance should remain unchanged
        $new_balance = $this->credit_manager->get_balance($this->test_user_id);
        $this->assertEquals($balance, $new_balance);
    }

    /**
     * Test credit transaction logging
     */
    public function testCreditTransactionLogging() {
        $amount = 15;
        $description = 'AI rename operation';
        
        $result = $this->credit_manager->deduct_credits($this->test_user_id, $amount, $description);
        
        $this->assertTrue($result);
        
        // Check if transaction was logged
        $transactions = $this->credit_manager->get_transaction_history($this->test_user_id);
        
        $this->assertIsArray($transactions);
        $this->assertNotEmpty($transactions);
        
        $latest_transaction = $transactions[0];
        $this->assertEquals($amount, abs($latest_transaction['amount']));
        $this->assertEquals($description, $latest_transaction['description']);
        $this->assertEquals('deduction', $latest_transaction['type']);
    }

    /**
     * Test free credits initialization for new users
     */
    public function testFreeCreditsInitialization() {
        $new_user_id = 999;
        
        // Simulate new user with no credits
        $balance = $this->credit_manager->get_balance($new_user_id);
        $this->assertEquals(0, $balance);
        
        // Initialize free credits
        $result = $this->credit_manager->initialize_free_credits($new_user_id);
        
        $this->assertTrue($result);
        
        $new_balance = $this->credit_manager->get_balance($new_user_id);
        $this->assertGreaterThan(0, $new_balance);
        $this->assertEquals(10, $new_balance); // Assuming 10 free credits
    }

    /**
     * Test credit usage statistics
     */
    public function testCreditUsageStatistics() {
        $stats = $this->credit_manager->get_usage_statistics($this->test_user_id);
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_used', $stats);
        $this->assertArrayHasKey('current_balance', $stats);
        $this->assertArrayHasKey('total_earned', $stats);
        $this->assertArrayHasKey('last_activity', $stats);
        
        $this->assertIsInt($stats['total_used']);
        $this->assertIsInt($stats['current_balance']);
    }

    /**
     * Test credit expiration handling
     */
    public function testCreditExpirationHandling() {
        // Add credits with expiration
        $amount = 20;
        $expiration_date = strtotime('+30 days');
        
        $result = $this->credit_manager->add_credits_with_expiration(
            $this->test_user_id, 
            $amount, 
            $expiration_date
        );
        
        $this->assertTrue($result);
        
        // Check expiring credits
        $expiring_credits = $this->credit_manager->get_expiring_credits($this->test_user_id);
        
        $this->assertIsArray($expiring_credits);
        $this->assertNotEmpty($expiring_credits);
    }

    /**
     * Test bulk credit operations
     */
    public function testBulkCreditOperations() {
        $user_ids = [1, 2, 3];
        $amount = 25;
        
        $results = $this->credit_manager->add_credits_bulk($user_ids, $amount);
        
        $this->assertIsArray($results);
        $this->assertCount(3, $results);
        
        foreach ($results as $user_id => $result) {
            $this->assertTrue($result);
            $this->assertContains($user_id, $user_ids);
        }
    }

    /**
     * Test credit balance validation
     */
    public function testCreditBalanceValidation() {
        // Test negative amount validation
        $result = $this->credit_manager->deduct_credits($this->test_user_id, -10);
        $this->assertFalse($result);
        
        // Test zero amount validation
        $result = $this->credit_manager->deduct_credits($this->test_user_id, 0);
        $this->assertFalse($result);
        
        // Test valid amount
        $result = $this->credit_manager->deduct_credits($this->test_user_id, 5);
        $this->assertTrue($result);
    }

    /**
     * Test credit manager error handling
     */
    public function testCreditManagerErrorHandling() {
        // Test with invalid user ID
        $balance = $this->credit_manager->get_balance(null);
        $this->assertEquals(0, $balance);
        
        $result = $this->credit_manager->deduct_credits(null, 10);
        $this->assertFalse($result);
        
        // Test with non-existent user
        $balance = $this->credit_manager->get_balance(99999);
        $this->assertEquals(0, $balance);
    }

    /**
     * Test credit refund functionality
     */
    public function testCreditRefund() {
        // First deduct some credits
        $deduction_amount = 15;
        $transaction_id = $this->credit_manager->deduct_credits($this->test_user_id, $deduction_amount, 'Test operation');
        
        $this->assertNotFalse($transaction_id);
        
        // Then refund them
        $result = $this->credit_manager->refund_credits($transaction_id);
        
        $this->assertTrue($result);
        
        // Check if credits were restored
        $balance = $this->credit_manager->get_balance($this->test_user_id);
        $this->assertGreaterThanOrEqual($deduction_amount, $balance);
    }

    /**
     * Test credit manager performance
     */
    public function testCreditManagerPerformance() {
        $start_time = microtime(true);
        
        // Perform multiple operations
        for ($i = 0; $i < 100; $i++) {
            $this->credit_manager->get_balance($this->test_user_id);
        }
        
        $end_time = microtime(true);
        $execution_time = $end_time - $start_time;
        
        // Should complete within reasonable time
        $this->assertLessThan(1.0, $execution_time, 'Credit operations should be performant');
    }
}