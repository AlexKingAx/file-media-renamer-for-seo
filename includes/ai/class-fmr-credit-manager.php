<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Credit Manager Class
 * 
 * Manages AI credits for users, including balance tracking,
 * deduction, and free credits for new users.
 * 
 * Follows WordPress user meta patterns for data storage and retrieval.
 * Implements comprehensive transaction logging using WordPress database API.
 */
class FMR_Credit_Manager {

    /**
     * @var string Meta key for storing credit data
     */
    private $credit_meta_key = '_fmrseo_ai_credits';

    /**
     * @var int Number of free credits for new users
     */
    private $free_credits = 5;

    /**
     * @var int Maximum number of transactions to keep in history
     */
    private $max_transactions = 100;

    /**
     * Get current credit balance for user
     *
     * @param int $user_id User ID (defaults to current user)
     * @return int Credit balance
     */
    public function get_credit_balance($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        // Validate user ID
        if (!$user_id || !get_userdata($user_id)) {
            return 0;
        }

        $credit_data = $this->get_credit_data($user_id);
        return intval($credit_data['balance']);
    }

    /**
     * Get total credits used by user
     *
     * @param int $user_id User ID (defaults to current user)
     * @return int Total credits used
     */
    public function get_total_credits_used($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        if (!$user_id || !get_userdata($user_id)) {
            return 0;
        }

        $credit_data = $this->get_credit_data($user_id);
        return intval($credit_data['used_total']);
    }

    /**
     * Get credit transaction history for user
     *
     * @param int $user_id User ID (defaults to current user)
     * @param int $limit Number of transactions to return (default: 20)
     * @return array Transaction history
     */
    public function get_transaction_history($user_id = null, $limit = 20) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        if (!$user_id || !get_userdata($user_id)) {
            return array();
        }

        $credit_data = $this->get_credit_data($user_id);
        $transactions = $credit_data['transactions'];

        // Sort by timestamp descending (newest first)
        usort($transactions, function($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });

        return array_slice($transactions, 0, $limit);
    }

    /**
     * Deduct credits from user balance
     *
     * @param int $amount Number of credits to deduct (default: 1)
     * @param int $user_id User ID (defaults to current user)
     * @param int $post_id Optional post ID for tracking
     * @param string $operation Operation type (default: 'ai_rename')
     * @return bool True if credit was deducted successfully
     * @throws Exception
     */
    public function deduct_credit($amount = 1, $user_id = null, $post_id = null, $operation = 'ai_rename') {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        // Validate user ID
        if (!$user_id || !get_userdata($user_id)) {
            throw new Exception(__('Invalid user ID for credit deduction.', 'fmrseo'));
        }

        // Validate amount
        $amount = intval($amount);
        if ($amount <= 0) {
            throw new Exception(__('Invalid credit amount for deduction.', 'fmrseo'));
        }

        $credit_data = $this->get_credit_data($user_id);

        if ($credit_data['balance'] < $amount) {
            throw new Exception(__('Insufficient credits for AI operation.', 'fmrseo'));
        }

        // Deduct credits
        $credit_data['balance'] -= $amount;
        $credit_data['used_total'] += $amount;
        $credit_data['last_updated'] = time();

        // Add transaction record with comprehensive data
        $transaction = array(
            'type' => 'deduct',
            'amount' => $amount,
            'timestamp' => time(),
            'operation' => sanitize_text_field($operation),
            'balance_after' => $credit_data['balance']
        );

        // Add post ID if provided
        if ($post_id) {
            $transaction['post_id'] = intval($post_id);
        }

        $credit_data['transactions'][] = $transaction;

        // Keep only the most recent transactions
        if (count($credit_data['transactions']) > $this->max_transactions) {
            $credit_data['transactions'] = array_slice($credit_data['transactions'], -$this->max_transactions);
        }

        // Update user meta using WordPress database API
        $result = update_user_meta($user_id, $this->credit_meta_key, $credit_data);

        if ($result === false) {
            throw new Exception(__('Failed to update credit balance in database.', 'fmrseo'));
        }

        // Log the transaction for debugging
        error_log(sprintf(
            'FMR Credit Manager: Deducted %d credits from user %d. New balance: %d',
            $amount,
            $user_id,
            $credit_data['balance']
        ));

        return true;
    }

    /**
     * Check if user has sufficient credits
     *
     * @param int $amount Number of credits needed (default: 1)
     * @param int $user_id User ID (defaults to current user)
     * @return bool True if user has sufficient credits
     */
    public function has_sufficient_credits($amount = 1, $user_id = null) {
        $amount = intval($amount);
        if ($amount <= 0) {
            return false;
        }

        return $this->get_credit_balance($user_id) >= $amount;
    }

    /**
     * Add credits to user balance
     *
     * @param int $amount Number of credits to add
     * @param int $user_id User ID (defaults to current user)
     * @param string $operation Operation type (default: 'manual_add')
     * @return bool True if credits were added successfully
     * @throws Exception
     */
    public function add_credits($amount, $user_id = null, $operation = 'manual_add') {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        // Validate user ID
        if (!$user_id || !get_userdata($user_id)) {
            throw new Exception(__('Invalid user ID for credit addition.', 'fmrseo'));
        }

        // Validate amount
        $amount = intval($amount);
        if ($amount <= 0) {
            throw new Exception(__('Invalid credit amount for addition.', 'fmrseo'));
        }

        $credit_data = $this->get_credit_data($user_id);

        // Add credits
        $credit_data['balance'] += $amount;
        $credit_data['last_updated'] = time();

        // Add transaction record
        $transaction = array(
            'type' => 'add',
            'amount' => $amount,
            'timestamp' => time(),
            'operation' => sanitize_text_field($operation),
            'balance_after' => $credit_data['balance']
        );

        $credit_data['transactions'][] = $transaction;

        // Keep only the most recent transactions
        if (count($credit_data['transactions']) > $this->max_transactions) {
            $credit_data['transactions'] = array_slice($credit_data['transactions'], -$this->max_transactions);
        }

        // Update user meta
        $result = update_user_meta($user_id, $this->credit_meta_key, $credit_data);

        if ($result === false) {
            throw new Exception(__('Failed to update credit balance in database.', 'fmrseo'));
        }

        // Log the transaction
        error_log(sprintf(
            'FMR Credit Manager: Added %d credits to user %d. New balance: %d',
            $amount,
            $user_id,
            $credit_data['balance']
        ));

        return true;
    }

    /**
     * Initialize free credits for new users
     *
     * @param int $user_id User ID (defaults to current user)
     * @return bool True if credits were initialized, false if already initialized
     * @throws Exception
     */
    public function initialize_free_credits($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        // Validate user ID
        if (!$user_id || !get_userdata($user_id)) {
            throw new Exception(__('Invalid user ID for free credits initialization.', 'fmrseo'));
        }

        $credit_data = $this->get_credit_data($user_id);

        // Only initialize if not already done
        if ($credit_data['free_credits_initialized']) {
            return false;
        }

        $credit_data['balance'] = $this->free_credits;
        $credit_data['free_credits_initialized'] = true;
        $credit_data['last_updated'] = time();

        // Add transaction record
        $transaction = array(
            'type' => 'add',
            'amount' => $this->free_credits,
            'timestamp' => time(),
            'operation' => 'free_credits_init',
            'balance_after' => $credit_data['balance']
        );

        $credit_data['transactions'][] = $transaction;

        // Update user meta
        $result = update_user_meta($user_id, $this->credit_meta_key, $credit_data);

        if ($result === false) {
            throw new Exception(__('Failed to initialize free credits in database.', 'fmrseo'));
        }

        // Log the initialization
        error_log(sprintf(
            'FMR Credit Manager: Initialized %d free credits for user %d',
            $this->free_credits,
            $user_id
        ));

        return true;
    }

    /**
     * Reset user credits (admin function)
     *
     * @param int $user_id User ID
     * @param int $new_balance New credit balance
     * @return bool True if reset was successful
     * @throws Exception
     */
    public function reset_credits($user_id, $new_balance = 0) {
        // Validate user ID
        if (!$user_id || !get_userdata($user_id)) {
            throw new Exception(__('Invalid user ID for credit reset.', 'fmrseo'));
        }

        // Validate new balance
        $new_balance = intval($new_balance);
        if ($new_balance < 0) {
            throw new Exception(__('Credit balance cannot be negative.', 'fmrseo'));
        }

        $credit_data = $this->get_credit_data($user_id);
        $old_balance = $credit_data['balance'];

        $credit_data['balance'] = $new_balance;
        $credit_data['last_updated'] = time();

        // Add transaction record
        $transaction = array(
            'type' => 'reset',
            'amount' => $new_balance - $old_balance,
            'timestamp' => time(),
            'operation' => 'admin_reset',
            'balance_after' => $new_balance,
            'previous_balance' => $old_balance
        );

        $credit_data['transactions'][] = $transaction;

        // Keep only the most recent transactions
        if (count($credit_data['transactions']) > $this->max_transactions) {
            $credit_data['transactions'] = array_slice($credit_data['transactions'], -$this->max_transactions);
        }

        // Update user meta
        $result = update_user_meta($user_id, $this->credit_meta_key, $credit_data);

        if ($result === false) {
            throw new Exception(__('Failed to reset credits in database.', 'fmrseo'));
        }

        // Log the reset
        error_log(sprintf(
            'FMR Credit Manager: Reset credits for user %d from %d to %d',
            $user_id,
            $old_balance,
            $new_balance
        ));

        return true;
    }

    /**
     * Get credit data for user following WordPress user meta patterns
     *
     * @param int $user_id User ID
     * @return array Credit data with default values merged
     */
    private function get_credit_data($user_id) {
        $default_data = array(
            'balance' => 0,
            'used_total' => 0,
            'last_updated' => 0,
            'free_credits_initialized' => false,
            'transactions' => array(),
            'created_at' => time()
        );

        // Use WordPress database API to retrieve user meta
        $credit_data = get_user_meta($user_id, $this->credit_meta_key, true);

        // If no data exists or data is corrupted, initialize with defaults
        if (!is_array($credit_data)) {
            $credit_data = $default_data;
        }

        // Merge with defaults to ensure all required fields exist
        $credit_data = array_merge($default_data, $credit_data);

        // Validate and sanitize data
        $credit_data['balance'] = intval($credit_data['balance']);
        $credit_data['used_total'] = intval($credit_data['used_total']);
        $credit_data['last_updated'] = intval($credit_data['last_updated']);
        $credit_data['free_credits_initialized'] = (bool) $credit_data['free_credits_initialized'];
        
        // Ensure transactions is an array
        if (!is_array($credit_data['transactions'])) {
            $credit_data['transactions'] = array();
        }

        return $credit_data;
    }

    /**
     * Get credit statistics for user
     *
     * @param int $user_id User ID (defaults to current user)
     * @return array Credit statistics
     */
    public function get_credit_stats($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        if (!$user_id || !get_userdata($user_id)) {
            return array();
        }

        $credit_data = $this->get_credit_data($user_id);
        $transactions = $credit_data['transactions'];

        $stats = array(
            'current_balance' => $credit_data['balance'],
            'total_used' => $credit_data['used_total'],
            'free_credits_initialized' => $credit_data['free_credits_initialized'],
            'last_updated' => $credit_data['last_updated'],
            'total_transactions' => count($transactions),
            'last_30_days' => array(
                'used' => 0,
                'added' => 0
            )
        );

        // Calculate last 30 days statistics
        $thirty_days_ago = time() - (30 * 24 * 60 * 60);
        
        foreach ($transactions as $transaction) {
            if ($transaction['timestamp'] >= $thirty_days_ago) {
                if ($transaction['type'] === 'deduct') {
                    $stats['last_30_days']['used'] += $transaction['amount'];
                } elseif ($transaction['type'] === 'add') {
                    $stats['last_30_days']['added'] += $transaction['amount'];
                }
            }
        }

        return $stats;
    }

    /**
     * Clean up old transaction data (maintenance function)
     *
     * @param int $user_id User ID (optional, if not provided cleans all users)
     * @param int $days_to_keep Number of days of transactions to keep (default: 90)
     * @return int Number of transactions cleaned
     */
    public function cleanup_old_transactions($user_id = null, $days_to_keep = 90) {
        $cutoff_time = time() - ($days_to_keep * 24 * 60 * 60);
        $cleaned_count = 0;

        if ($user_id) {
            // Clean specific user
            $users = array($user_id);
        } else {
            // Get all users with credit data
            global $wpdb;
            $users = $wpdb->get_col($wpdb->prepare(
                "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s",
                $this->credit_meta_key
            ));
        }

        foreach ($users as $uid) {
            $credit_data = $this->get_credit_data($uid);
            $original_count = count($credit_data['transactions']);
            
            // Filter out old transactions
            $credit_data['transactions'] = array_filter($credit_data['transactions'], function($transaction) use ($cutoff_time) {
                return $transaction['timestamp'] >= $cutoff_time;
            });

            $new_count = count($credit_data['transactions']);
            $cleaned_count += ($original_count - $new_count);

            // Update if transactions were removed
            if ($original_count !== $new_count) {
                update_user_meta($uid, $this->credit_meta_key, $credit_data);
            }
        }

        return $cleaned_count;
    }

    /**
     * Deduct credit with external API integration
     * This method handles the complete credit deduction process including external API calls
     * Enhanced with better error handling and retry logic
     *
     * @param int $amount Number of credits to deduct (default: 1)
     * @param int $user_id User ID (defaults to current user)
     * @param int $post_id Optional post ID for tracking
     * @param string $operation Operation type (default: 'ai_rename')
     * @param array $options Additional options for the operation
     * @return array Result array with success status and details
     * @throws Exception
     */
    public function deduct_credit_with_api($amount = 1, $user_id = null, $post_id = null, $operation = 'ai_rename', $options = array()) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        // Validate the operation before proceeding
        $validation = $this->validate_credit_operation($operation, $amount, $user_id);
        if (!$validation['valid']) {
            throw new Exception(implode(' ', $validation['errors']));
        }

        // Initialize free credits for new users if needed
        $this->auto_initialize_free_credits($user_id);

        // Check if user has sufficient credits before making API call
        $current_balance = $this->get_credit_balance($user_id);
        if (!$this->has_sufficient_credits($amount, $user_id)) {
            $error_data = $this->handle_insufficient_credits_error($amount, $current_balance);
            throw new Exception($error_data['message']);
        }

        $max_retries = isset($options['max_retries']) ? intval($options['max_retries']) : 3;
        $retry_count = 0;
        $last_error = null;

        while ($retry_count < $max_retries) {
            try {
                // Make external API call first with retry logic
                $api_result = $this->deduct_credit_external_api($user_id, $amount, $operation, $retry_count);
                
                // If API call succeeds, deduct from local balance
                $local_result = $this->deduct_credit($amount, $user_id, $post_id, $operation);
                
                // Return success result with details
                return array(
                    'success' => true,
                    'credits_deducted' => $amount,
                    'remaining_balance' => $this->get_credit_balance($user_id),
                    'operation' => $operation,
                    'api_response' => $api_result,
                    'retry_count' => $retry_count
                );
                
            } catch (Exception $e) {
                $last_error = $e;
                $retry_count++;
                
                // Log the retry attempt
                error_log(sprintf(
                    'FMR Credit Manager: Retry %d/%d failed for user %d: %s',
                    $retry_count,
                    $max_retries,
                    $user_id,
                    $e->getMessage()
                ));
                
                // Don't retry for certain types of errors
                if ($this->is_non_retryable_error($e)) {
                    break;
                }
                
                // Wait before retrying (exponential backoff)
                if ($retry_count < $max_retries) {
                    sleep(pow(2, $retry_count - 1)); // 1s, 2s, 4s delays
                }
            }
        }

        // All retries failed, handle the error
        $this->handle_api_failure($last_error, $user_id, $amount, $operation);
        
        // This should not be reached due to exception in handle_api_failure
        throw new Exception(__('Credit deduction failed after all retry attempts.', 'fmrseo'));
    }

    /**
     * Make external API call to deduct credit with enhanced error handling
     *
     * @param int $user_id User ID
     * @param int $amount Number of credits to deduct
     * @param string $operation Operation type
     * @param int $retry_count Current retry attempt (for logging)
     * @return array API response data
     * @throws Exception
     */
    private function deduct_credit_external_api($user_id, $amount, $operation, $retry_count = 0) {
        $options = get_option('fmrseo_options', array());
        $api_key = isset($options['ai_api_key']) ? $options['ai_api_key'] : '';
        $api_endpoint = isset($options['ai_api_endpoint']) ? $options['ai_api_endpoint'] : 'https://api.example.com';

        if (empty($api_key)) {
            throw new Exception(__('AI API key not configured. Please check your settings.', 'fmrseo'));
        }

        if (empty($api_endpoint)) {
            throw new Exception(__('AI API endpoint not configured. Please check your settings.', 'fmrseo'));
        }

        $endpoint = trailingslashit($api_endpoint) . 'v1/credits/deduct';
        
        // Generate unique request ID for tracking
        $request_id = wp_generate_uuid4();
        
        $args = array(
            'method' => 'POST',
            'timeout' => 20, // Increased timeout for external API
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
                'User-Agent' => 'FMR-SEO-Plugin/' . $this->get_plugin_version(),
                'X-Request-ID' => $request_id,
                'X-Retry-Count' => $retry_count
            ),
            'body' => wp_json_encode(array(
                'user_id' => $user_id,
                'amount' => $amount,
                'operation' => $operation,
                'timestamp' => time(),
                'site_url' => get_site_url(),
                'request_id' => $request_id,
                'retry_count' => $retry_count,
                'plugin_version' => $this->get_plugin_version()
            ))
        );

        // Log the API request for debugging
        error_log(sprintf(
            'FMR Credit Manager: Making API request to %s for user %d (Request ID: %s, Retry: %d)',
            $endpoint,
            $user_id,
            $request_id,
            $retry_count
        ));

        $response = wp_remote_request($endpoint, $args);

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $error_code = $response->get_error_code();
            
            // Log detailed error information
            error_log(sprintf(
                'FMR Credit Manager: API request failed - Code: %s, Message: %s, Request ID: %s',
                $error_code,
                $error_message,
                $request_id
            ));
            
            if (strpos($error_message, 'timeout') !== false || $error_code === 'http_request_timeout') {
                throw new Exception('Credit deduction API timeout: ' . $error_message);
            } elseif (strpos($error_message, 'connect') !== false || $error_code === 'http_request_failed') {
                throw new Exception('Unable to connect to credit service: ' . $error_message);
            } else {
                throw new Exception('Credit deduction API error: ' . $error_message);
            }
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_headers = wp_remote_retrieve_headers($response);

        // Log response details
        error_log(sprintf(
            'FMR Credit Manager: API response - Code: %d, Request ID: %s',
            $response_code,
            $request_id
        ));

        // Handle different response codes with detailed error messages
        switch ($response_code) {
            case 200:
                // Success - parse response for confirmation
                $data = json_decode($response_body, true);
                if (!$data) {
                    throw new Exception(__('Credit deduction API returned invalid JSON response.', 'fmrseo'));
                }
                
                if (!isset($data['success']) || !$data['success']) {
                    $error_msg = isset($data['message']) ? $data['message'] : 'Unknown API error';
                    throw new Exception(__('Credit deduction failed: ', 'fmrseo') . $error_msg);
                }
                
                // Return the full response data for logging
                return array(
                    'success' => true,
                    'message' => isset($data['message']) ? $data['message'] : 'Credit deducted successfully',
                    'remaining_balance' => isset($data['remaining_balance']) ? $data['remaining_balance'] : null,
                    'transaction_id' => isset($data['transaction_id']) ? $data['transaction_id'] : null,
                    'request_id' => $request_id
                );
                
            case 402: // Payment Required
                $error_data = json_decode($response_body, true);
                $message = isset($error_data['message']) ? $error_data['message'] : 'Insufficient credits on server';
                throw new Exception($message);
                
            case 401: // Unauthorized
                throw new Exception(__('Invalid API key or unauthorized access. Please check your API configuration.', 'fmrseo'));
                
            case 403: // Forbidden
                throw new Exception(__('Access forbidden. Your API key may not have sufficient permissions.', 'fmrseo'));
                
            case 429: // Too Many Requests
                $retry_after = isset($response_headers['retry-after']) ? $response_headers['retry-after'] : 60;
                throw new Exception(sprintf(__('Rate limit exceeded. Please try again in %d seconds.', 'fmrseo'), $retry_after));
                
            case 400: // Bad Request
                $error_data = json_decode($response_body, true);
                $message = isset($error_data['message']) ? $error_data['message'] : 'Invalid request parameters';
                throw new Exception(__('Bad request: ', 'fmrseo') . $message);
                
            case 500:
            case 502:
            case 503:
            case 504:
                throw new Exception(__('Credit service temporarily unavailable. Please try again later.', 'fmrseo'));
                
            default:
                $error_data = json_decode($response_body, true);
                $message = isset($error_data['message']) ? $error_data['message'] : 'Unknown error';
                throw new Exception(sprintf(__('Credit deduction failed with status %d: %s', 'fmrseo'), $response_code, $message));
        }
    }

    /**
     * Initialize free credits for new users with proper error handling
     * Enhanced version with better validation and error handling
     *
     * @param int $user_id User ID (defaults to current user)
     * @return bool True if credits were initialized, false if already initialized
     * @throws Exception
     */
    public function initialize_free_credits_enhanced($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        // Validate user ID
        if (!$user_id || !get_userdata($user_id)) {
            throw new Exception(__('Invalid user ID for free credits initialization.', 'fmrseo'));
        }

        // Check if AI is enabled
        $options = get_option('fmrseo_options', array());
        if (empty($options['ai_enabled'])) {
            error_log('FMR Credit Manager: AI is disabled, skipping free credits initialization for user ' . $user_id);
            return false; // Don't initialize if AI is disabled
        }

        $credit_data = $this->get_credit_data($user_id);

        // Only initialize if not already done
        if ($credit_data['free_credits_initialized']) {
            return false;
        }

        // Check if user is eligible for free credits (e.g., not a spam account)
        if (!$this->is_user_eligible_for_free_credits($user_id)) {
            error_log('FMR Credit Manager: User ' . $user_id . ' is not eligible for free credits');
            return false;
        }

        try {
            // Get free credits amount from settings (with fallback to default)
            $free_credits_amount = isset($options['free_credits_amount']) ? intval($options['free_credits_amount']) : $this->free_credits;
            
            // Ensure minimum of 1 credit
            if ($free_credits_amount < 1) {
                $free_credits_amount = 1;
            }

            // Set initial balance
            $credit_data['balance'] = $free_credits_amount;
            $credit_data['free_credits_initialized'] = true;
            $credit_data['last_updated'] = time();
            $credit_data['free_credits_granted_at'] = time();
            $credit_data['free_credits_amount'] = $free_credits_amount;

            // Add transaction record with more details
            $transaction = array(
                'type' => 'add',
                'amount' => $free_credits_amount,
                'timestamp' => time(),
                'operation' => 'free_credits_init',
                'balance_after' => $credit_data['balance'],
                'user_registration_date' => $this->get_user_registration_date($user_id),
                'initialization_method' => 'auto'
            );

            $credit_data['transactions'][] = $transaction;

            // Update user meta with error checking
            $result = update_user_meta($user_id, $this->credit_meta_key, $credit_data);

            if ($result === false) {
                throw new Exception(__('Failed to initialize free credits in database.', 'fmrseo'));
            }

            // Log successful initialization with more details
            $user_info = get_userdata($user_id);
            error_log(sprintf(
                'FMR Credit Manager: Successfully initialized %d free credits for user %d (%s)',
                $free_credits_amount,
                $user_id,
                $user_info ? $user_info->user_login : 'unknown'
            ));

            // Hook for other plugins/themes to react to free credits initialization
            do_action('fmrseo_free_credits_initialized', $user_id, $free_credits_amount, $credit_data);

            // Send notification email if enabled
            $this->maybe_send_free_credits_notification($user_id, $free_credits_amount);

            return true;

        } catch (Exception $e) {
            error_log('FMR Credit Manager: Failed to initialize free credits for user ' . $user_id . ': ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Check if user is eligible for free credits
     *
     * @param int $user_id User ID
     * @return bool True if user is eligible
     */
    private function is_user_eligible_for_free_credits($user_id) {
        $user = get_userdata($user_id);
        
        if (!$user) {
            return false;
        }

        // Check if user has required capabilities
        if (!user_can($user_id, 'upload_files')) {
            return false;
        }

        // Check user registration date (must be registered for at least 1 hour to prevent spam)
        $registration_time = strtotime($user->user_registered);
        $min_registration_time = time() - (60 * 60); // 1 hour ago
        
        if ($registration_time > $min_registration_time) {
            return false;
        }

        // Check if user has been flagged as spam (if using spam detection plugins)
        if (function_exists('is_user_spammer') && is_user_spammer($user_id)) {
            return false;
        }

        // Allow filtering by other plugins
        return apply_filters('fmrseo_user_eligible_for_free_credits', true, $user_id, $user);
    }

    /**
     * Get user registration date
     *
     * @param int $user_id User ID
     * @return int Registration timestamp
     */
    private function get_user_registration_date($user_id) {
        $user = get_userdata($user_id);
        return $user ? strtotime($user->user_registered) : 0;
    }

    /**
     * Send notification email about free credits (if enabled)
     *
     * @param int $user_id User ID
     * @param int $credits_amount Number of credits granted
     */
    private function maybe_send_free_credits_notification($user_id, $credits_amount) {
        $options = get_option('fmrseo_options', array());
        
        // Check if email notifications are enabled
        if (empty($options['send_free_credits_email'])) {
            return;
        }

        $user = get_userdata($user_id);
        if (!$user || !$user->user_email) {
            return;
        }

        $subject = sprintf(__('Welcome! You have received %d free AI credits', 'fmrseo'), $credits_amount);
        
        $message = sprintf(
            __('Hello %s,

Welcome to the AI-powered media renaming feature!

You have been granted %d free credits to try our AI media renaming service. These credits can be used to automatically generate SEO-optimized filenames for your media files.

To get started:
1. Go to your Media Library
2. Select a media file
3. Click "Rename with AI"

If you need more credits, you can purchase them from your plugin settings.

Best regards,
The FMR SEO Team', 'fmrseo'),
            $user->display_name,
            $credits_amount
        );

        // Send email
        wp_mail($user->user_email, $subject, $message);
        
        // Log email sent
        error_log(sprintf(
            'FMR Credit Manager: Free credits notification email sent to user %d (%s)',
            $user_id,
            $user->user_email
        ));
    }

    /**
     * Manually initialize free credits for a user (admin function)
     *
     * @param int $user_id User ID
     * @param int $amount Number of credits to grant (optional)
     * @param bool $force Force initialization even if already done
     * @return bool True if credits were initialized
     * @throws Exception
     */
    public function manually_initialize_free_credits($user_id, $amount = null, $force = false) {
        // Validate user ID
        if (!$user_id || !get_userdata($user_id)) {
            throw new Exception(__('Invalid user ID for manual free credits initialization.', 'fmrseo'));
        }

        // Check current user permissions
        if (!current_user_can('manage_options')) {
            throw new Exception(__('Insufficient permissions to manually initialize free credits.', 'fmrseo'));
        }

        $credit_data = $this->get_credit_data($user_id);

        // Check if already initialized and not forcing
        if ($credit_data['free_credits_initialized'] && !$force) {
            throw new Exception(__('Free credits already initialized for this user.', 'fmrseo'));
        }

        // Use provided amount or default
        $credits_amount = $amount !== null ? intval($amount) : $this->free_credits;
        
        if ($credits_amount < 0) {
            throw new Exception(__('Credit amount cannot be negative.', 'fmrseo'));
        }

        try {
            // If forcing re-initialization, add to existing balance
            if ($force && $credit_data['free_credits_initialized']) {
                $credit_data['balance'] += $credits_amount;
            } else {
                $credit_data['balance'] = $credits_amount;
                $credit_data['free_credits_initialized'] = true;
            }

            $credit_data['last_updated'] = time();
            $credit_data['free_credits_granted_at'] = time();

            // Add transaction record
            $transaction = array(
                'type' => 'add',
                'amount' => $credits_amount,
                'timestamp' => time(),
                'operation' => $force ? 'manual_free_credits_force' : 'manual_free_credits_init',
                'balance_after' => $credit_data['balance'],
                'admin_user_id' => get_current_user_id(),
                'initialization_method' => 'manual'
            );

            $credit_data['transactions'][] = $transaction;

            // Update user meta
            $result = update_user_meta($user_id, $this->credit_meta_key, $credit_data);

            if ($result === false) {
                throw new Exception(__('Failed to manually initialize free credits in database.', 'fmrseo'));
            }

            // Log the manual initialization
            error_log(sprintf(
                'FMR Credit Manager: Manually initialized %d free credits for user %d by admin %d (Force: %s)',
                $credits_amount,
                $user_id,
                get_current_user_id(),
                $force ? 'yes' : 'no'
            ));

            // Hook for other plugins
            do_action('fmrseo_manual_free_credits_initialized', $user_id, $credits_amount, $force, get_current_user_id());

            return true;

        } catch (Exception $e) {
            error_log('FMR Credit Manager: Failed to manually initialize free credits: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Handle insufficient credits error with user-friendly messaging
     *
     * @param int $required_credits Number of credits required
     * @param int $available_credits Number of credits available
     * @return array Error response data
     */
    public function handle_insufficient_credits_error($required_credits, $available_credits) {
        $shortage = $required_credits - $available_credits;
        
        return array(
            'error' => true,
            'error_code' => 'insufficient_credits',
            'message' => sprintf(
                __('You need %d more credits to perform this operation. You currently have %d credits.', 'fmrseo'),
                $shortage,
                $available_credits
            ),
            'required_credits' => $required_credits,
            'available_credits' => $available_credits,
            'shortage' => $shortage,
            'purchase_url' => $this->get_purchase_credits_url()
        );
    }

    /**
     * Get URL for purchasing credits
     *
     * @return string Purchase URL
     */
    private function get_purchase_credits_url() {
        $options = get_option('fmrseo_options', array());
        return isset($options['credits_purchase_url']) ? $options['credits_purchase_url'] : '#';
    }

    /**
     * Get plugin version for API calls
     *
     * @return string Plugin version
     */
    private function get_plugin_version() {
        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        $plugin_file = dirname(dirname(dirname(__FILE__))) . '/fmrseo.php';
        if (file_exists($plugin_file)) {
            $plugin_data = get_plugin_data($plugin_file);
            return $plugin_data['Version'];
        }
        
        return '1.0.0';
    }

    /**
     * Validate credit operation before execution
     *
     * @param string $operation Operation type
     * @param int $amount Credit amount
     * @param int $user_id User ID
     * @return array Validation result
     */
    public function validate_credit_operation($operation, $amount, $user_id) {
        $validation = array(
            'valid' => true,
            'errors' => array()
        );

        // Validate user
        if (!$user_id || !get_userdata($user_id)) {
            $validation['valid'] = false;
            $validation['errors'][] = __('Invalid user ID.', 'fmrseo');
        }

        // Validate amount
        if (!is_numeric($amount) || $amount <= 0) {
            $validation['valid'] = false;
            $validation['errors'][] = __('Invalid credit amount.', 'fmrseo');
        }

        // Validate operation type
        $allowed_operations = array('ai_rename', 'bulk_rename', 'manual_add', 'free_credits_init', 'admin_reset');
        if (!in_array($operation, $allowed_operations)) {
            $validation['valid'] = false;
            $validation['errors'][] = __('Invalid operation type.', 'fmrseo');
        }

        // Check if AI is enabled for AI operations
        if (strpos($operation, 'ai_') === 0) {
            $options = get_option('fmrseo_options', array());
            if (empty($options['ai_enabled'])) {
                $validation['valid'] = false;
                $validation['errors'][] = __('AI functionality is disabled.', 'fmrseo');
            }
        }

        return $validation;
    }

    /**
     * Auto-initialize free credits for new users
     *
     * @param int $user_id User ID
     * @return bool True if credits were initialized
     */
    private function auto_initialize_free_credits($user_id) {
        try {
            return $this->initialize_free_credits_enhanced($user_id);
        } catch (Exception $e) {
            error_log('FMR Credit Manager: Failed to auto-initialize free credits for user ' . $user_id . ': ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if an error is non-retryable
     *
     * @param Exception $error The error to check
     * @return bool True if error should not be retried
     */
    private function is_non_retryable_error($error) {
        $message = $error->getMessage();
        
        // Don't retry these types of errors
        $non_retryable_patterns = array(
            'Invalid API key',
            'unauthorized access',
            'Insufficient credits',
            'Bad request',
            'forbidden',
            'not configured'
        );
        
        foreach ($non_retryable_patterns as $pattern) {
            if (stripos($message, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Handle API failure after all retries
     *
     * @param Exception $last_error The last error encountered
     * @param int $user_id User ID
     * @param int $amount Credit amount
     * @param string $operation Operation type
     * @throws Exception
     */
    private function handle_api_failure($last_error, $user_id, $amount, $operation) {
        // Log the final failure
        error_log(sprintf(
            'FMR Credit Manager: All retry attempts failed for user %d, operation %s: %s',
            $user_id,
            $operation,
            $last_error->getMessage()
        ));

        // Determine user-friendly error message based on the error type
        $message = $last_error->getMessage();
        
        if (strpos($message, 'insufficient') !== false) {
            throw new Exception(__('Insufficient credits on server. Please contact support or purchase more credits.', 'fmrseo'));
        } elseif (strpos($message, 'timeout') !== false || strpos($message, 'connect') !== false) {
            throw new Exception(__('Credit service is temporarily unavailable. Please try again later.', 'fmrseo'));
        } elseif (strpos($message, 'API key') !== false || strpos($message, 'unauthorized') !== false) {
            throw new Exception(__('API authentication failed. Please check your API key configuration.', 'fmrseo'));
        } elseif (strpos($message, 'rate limit') !== false) {
            throw new Exception(__('Too many requests. Please wait a moment before trying again.', 'fmrseo'));
        } else {
            throw new Exception(__('Credit deduction failed. Please try again or contact support if the problem persists.', 'fmrseo'));
        }
    }

    /**
     * Get detailed credit deduction result for API responses
     *
     * @param bool $success Whether the operation was successful
     * @param int $amount Credits deducted
     * @param int $user_id User ID
     * @param string $operation Operation type
     * @param array $api_response API response data
     * @param string $error_message Error message if failed
     * @return array Detailed result array
     */
    public function get_deduction_result($success, $amount, $user_id, $operation, $api_response = array(), $error_message = '') {
        $result = array(
            'success' => $success,
            'operation' => $operation,
            'user_id' => $user_id,
            'timestamp' => time()
        );

        if ($success) {
            $result['credits_deducted'] = $amount;
            $result['remaining_balance'] = $this->get_credit_balance($user_id);
            $result['api_response'] = $api_response;
        } else {
            $result['error'] = $error_message;
            $result['current_balance'] = $this->get_credit_balance($user_id);
            
            if ($this->get_credit_balance($user_id) < $amount) {
                $result['insufficient_credits'] = true;
                $result['shortage'] = $amount - $this->get_credit_balance($user_id);
                $result['purchase_url'] = $this->get_purchase_credits_url();
            }
        }

        return $result;
    }

    /**
     * Bulk credit deduction for multiple operations
     * Useful for bulk rename operations
     *
     * @param array $operations Array of operations with user_id, amount, operation, post_id
     * @param array $options Additional options
     * @return array Results for each operation
     */
    public function bulk_deduct_credits($operations, $options = array()) {
        $results = array();
        $continue_on_failure = isset($options['continue_on_failure']) ? $options['continue_on_failure'] : true;
        
        foreach ($operations as $index => $op) {
            try {
                $user_id = isset($op['user_id']) ? $op['user_id'] : get_current_user_id();
                $amount = isset($op['amount']) ? $op['amount'] : 1;
                $operation = isset($op['operation']) ? $op['operation'] : 'bulk_rename';
                $post_id = isset($op['post_id']) ? $op['post_id'] : null;
                
                $result = $this->deduct_credit_with_api($amount, $user_id, $post_id, $operation, $options);
                $results[$index] = $result;
                
            } catch (Exception $e) {
                $results[$index] = array(
                    'success' => false,
                    'error' => $e->getMessage(),
                    'operation' => isset($op['operation']) ? $op['operation'] : 'bulk_rename',
                    'user_id' => isset($op['user_id']) ? $op['user_id'] : get_current_user_id()
                );
                
                // Stop processing if continue_on_failure is false
                if (!$continue_on_failure) {
                    break;
                }
            }
        }
        
        return $results;
    }

}