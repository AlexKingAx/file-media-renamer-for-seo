<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Security Manager Class
 * 
 * Handles security hardening for AI-related operations including
 * input sanitization, rate limiting, and security validation.
 */
class FMR_Security_Manager {

    /**
     * @var string Rate limiting transient prefix
     */
    private $rate_limit_prefix = 'fmr_ai_rate_limit_';

    /**
     * @var array Default rate limits per operation type
     */
    private $default_rate_limits = array(
        'ai_rename_single' => array('requests' => 10, 'window' => 300), // 10 requests per 5 minutes
        'ai_suggestions' => array('requests' => 20, 'window' => 300),   // 20 requests per 5 minutes
        'ai_bulk_rename' => array('requests' => 3, 'window' => 600),    // 3 requests per 10 minutes
        'ai_test_connection' => array('requests' => 5, 'window' => 60)  // 5 requests per minute
    );

    /**
     * @var array Allowed file types for AI processing
     */
    private $allowed_file_types = array(
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/msword',
        'application/vnd.ms-excel',
        'application/vnd.ms-powerpoint'
    );

    /**
     * Sanitize AI-related inputs with comprehensive validation
     *
     * @param array $input Raw input data
     * @param string $context Context of the input (e.g., 'ai_rename', 'ai_suggestions')
     * @return array Sanitized input data
     * @throws Exception If input validation fails
     */
    public function sanitize_ai_input($input, $context = 'general') {
        if (!is_array($input)) {
            throw new Exception(__('Invalid input format for AI operation.', 'fmrseo'));
        }

        $sanitized = array();

        // Sanitize post ID
        if (isset($input['post_id'])) {
            $post_id = intval($input['post_id']);
            if ($post_id <= 0) {
                throw new Exception(__('Invalid media ID provided.', 'fmrseo'));
            }
            
            // Verify post exists and is an attachment
            $post = get_post($post_id);
            if (!$post || $post->post_type !== 'attachment') {
                throw new Exception(__('Invalid media file specified.', 'fmrseo'));
            }
            
            // Verify file type is allowed for AI processing
            $mime_type = get_post_mime_type($post_id);
            if (!in_array($mime_type, $this->allowed_file_types)) {
                throw new Exception(__('File type not supported for AI processing.', 'fmrseo'));
            }
            
            $sanitized['post_id'] = $post_id;
        }

        // Sanitize selected name
        if (isset($input['selected_name'])) {
            $selected_name = sanitize_text_field($input['selected_name']);
            
            // Additional validation for filename
            if (!empty($selected_name)) {
                // Remove any path separators
                $selected_name = basename($selected_name);
                
                // Remove file extensions
                $selected_name = pathinfo($selected_name, PATHINFO_FILENAME);
                
                // Validate filename characters
                if (!preg_match('/^[a-zA-Z0-9\-_]+$/', $selected_name)) {
                    throw new Exception(__('Invalid characters in selected filename.', 'fmrseo'));
                }
                
                // Validate length
                if (strlen($selected_name) < 2 || strlen($selected_name) > 100) {
                    throw new Exception(__('Filename must be between 2 and 100 characters.', 'fmrseo'));
                }
                
                $sanitized['selected_name'] = strtolower($selected_name);
            }
        }

        // Sanitize count parameter
        if (isset($input['count'])) {
            $count = intval($input['count']);
            if ($count < 1 || $count > 5) {
                throw new Exception(__('Invalid suggestion count. Must be between 1 and 5.', 'fmrseo'));
            }
            $sanitized['count'] = $count;
        }

        // Sanitize bulk operation data
        if (isset($input['post_ids']) && is_array($input['post_ids'])) {
            $post_ids = array();
            foreach ($input['post_ids'] as $id) {
                $post_id = intval($id);
                if ($post_id > 0) {
                    // Verify each post exists and is an attachment
                    $post = get_post($post_id);
                    if ($post && $post->post_type === 'attachment') {
                        $mime_type = get_post_mime_type($post_id);
                        if (in_array($mime_type, $this->allowed_file_types)) {
                            $post_ids[] = $post_id;
                        }
                    }
                }
            }
            
            // Limit bulk operations to prevent abuse
            if (count($post_ids) > 50) {
                throw new Exception(__('Bulk operations limited to 50 files maximum.', 'fmrseo'));
            }
            
            $sanitized['post_ids'] = $post_ids;
        }

        // Sanitize options array
        if (isset($input['options']) && is_array($input['options'])) {
            $sanitized['options'] = $this->sanitize_options_array($input['options']);
        }

        // Context-specific validation
        switch ($context) {
            case 'ai_rename':
                if (!isset($sanitized['post_id'])) {
                    throw new Exception(__('Media ID is required for AI rename operation.', 'fmrseo'));
                }
                break;
                
            case 'ai_bulk_rename':
                if (!isset($sanitized['post_ids']) || empty($sanitized['post_ids'])) {
                    throw new Exception(__('At least one valid media ID is required for bulk rename.', 'fmrseo'));
                }
                break;
                
            case 'ai_suggestions':
                if (!isset($sanitized['post_id'])) {
                    throw new Exception(__('Media ID is required for AI suggestions.', 'fmrseo'));
                }
                break;
        }

        return $sanitized;
    }

    /**
     * Sanitize options array
     *
     * @param array $options Raw options array
     * @return array Sanitized options
     */
    private function sanitize_options_array($options) {
        $sanitized = array();
        
        // Allowed option keys with their sanitization methods
        $allowed_options = array(
            'max_retries' => 'intval',
            'timeout' => 'intval',
            'fallback_enabled' => 'boolval',
            'cache_enabled' => 'boolval'
        );
        
        foreach ($options as $key => $value) {
            if (array_key_exists($key, $allowed_options)) {
                $sanitize_func = $allowed_options[$key];
                $sanitized[$key] = $sanitize_func($value);
                
                // Additional validation for specific options
                switch ($key) {
                    case 'max_retries':
                        if ($sanitized[$key] < 0 || $sanitized[$key] > 5) {
                            $sanitized[$key] = 3; // Default
                        }
                        break;
                        
                    case 'timeout':
                        if ($sanitized[$key] < 5 || $sanitized[$key] > 120) {
                            $sanitized[$key] = 30; // Default
                        }
                        break;
                }
            }
        }
        
        return $sanitized;
    }

    /**
     * Check rate limits for AI operations
     *
     * @param string $operation Operation type
     * @param int $user_id User ID (defaults to current user)
     * @return bool True if within rate limits
     * @throws Exception If rate limit exceeded
     */
    public function check_rate_limit($operation, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        // Skip rate limiting for administrators in debug mode
        if (defined('WP_DEBUG') && WP_DEBUG && current_user_can('manage_options')) {
            return true;
        }

        // Get rate limit configuration
        $rate_limits = $this->get_rate_limits();
        
        if (!isset($rate_limits[$operation])) {
            // No rate limit defined for this operation
            return true;
        }

        $limit_config = $rate_limits[$operation];
        $max_requests = $limit_config['requests'];
        $time_window = $limit_config['window'];

        // Create unique key for this user and operation
        $cache_key = $this->rate_limit_prefix . $operation . '_' . $user_id;
        
        // Get current request count
        $current_requests = get_transient($cache_key);
        
        if ($current_requests === false) {
            // First request in this time window
            set_transient($cache_key, 1, $time_window);
            return true;
        }

        if ($current_requests >= $max_requests) {
            // Rate limit exceeded
            $remaining_time = $this->get_rate_limit_reset_time($cache_key);
            
            throw new Exception(sprintf(
                __('Rate limit exceeded for %s. Please try again in %d seconds.', 'fmrseo'),
                $operation,
                $remaining_time
            ));
        }

        // Increment request count
        set_transient($cache_key, $current_requests + 1, $time_window);
        
        return true;
    }

    /**
     * Get remaining time until rate limit resets
     *
     * @param string $cache_key Rate limit cache key
     * @return int Seconds until reset
     */
    private function get_rate_limit_reset_time($cache_key) {
        global $wpdb;
        
        // Query the options table to get the transient expiration
        $transient_key = '_transient_timeout_' . $cache_key;
        $expiration = $wpdb->get_var($wpdb->prepare(
            "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
            $transient_key
        ));
        
        if ($expiration) {
            $remaining = intval($expiration) - time();
            return max(0, $remaining);
        }
        
        return 0;
    }

    /**
     * Get rate limit configuration
     *
     * @return array Rate limit configuration
     */
    private function get_rate_limits() {
        $options = get_option('fmrseo_options', array());
        
        // Allow customization of rate limits through settings
        $custom_limits = isset($options['ai_rate_limits']) ? $options['ai_rate_limits'] : array();
        
        return array_merge($this->default_rate_limits, $custom_limits);
    }

    /**
     * Validate user permissions for AI operations
     *
     * @param string $operation Operation type
     * @param int $user_id User ID (defaults to current user)
     * @return bool True if user has permission
     * @throws Exception If permission denied
     */
    public function validate_user_permissions($operation, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        // Check if user is logged in
        if (!$user_id) {
            throw new Exception(__('User must be logged in to perform AI operations.', 'fmrseo'));
        }

        // Check basic upload capability
        if (!user_can($user_id, 'upload_files')) {
            throw new Exception(__('User does not have permission to manage media files.', 'fmrseo'));
        }

        // Operation-specific permission checks
        switch ($operation) {
            case 'ai_bulk_rename':
                // Bulk operations require additional permissions
                if (!user_can($user_id, 'edit_posts')) {
                    throw new Exception(__('User does not have permission for bulk operations.', 'fmrseo'));
                }
                break;
                
            case 'ai_test_connection':
                // Connection testing requires manage options capability
                if (!user_can($user_id, 'manage_options')) {
                    throw new Exception(__('User does not have permission to test AI connection.', 'fmrseo'));
                }
                break;
        }

        return true;
    }

    /**
     * Validate nonce for AI operations
     *
     * @param string $nonce Nonce value
     * @param string $action Nonce action
     * @return bool True if nonce is valid
     * @throws Exception If nonce validation fails
     */
    public function validate_nonce($nonce, $action = 'save_seo_name_nonce') {
        if (empty($nonce)) {
            throw new Exception(__('Security token is missing.', 'fmrseo'));
        }

        if (!wp_verify_nonce($nonce, $action)) {
            throw new Exception(__('Security token validation failed.', 'fmrseo'));
        }

        return true;
    }

    /**
     * Sanitize API response data
     *
     * @param mixed $response Raw API response
     * @return array Sanitized response data
     * @throws Exception If response is invalid
     */
    public function sanitize_api_response($response) {
        if (!is_array($response)) {
            throw new Exception(__('Invalid API response format.', 'fmrseo'));
        }

        $sanitized = array();

        // Sanitize suggestions array
        if (isset($response['suggestions']) && is_array($response['suggestions'])) {
            $sanitized['suggestions'] = array();
            
            foreach ($response['suggestions'] as $suggestion) {
                if (is_string($suggestion)) {
                    $clean_suggestion = $this->sanitize_filename_suggestion($suggestion);
                    if (!empty($clean_suggestion)) {
                        $sanitized['suggestions'][] = $clean_suggestion;
                    }
                } elseif (is_array($suggestion) && isset($suggestion['name'])) {
                    $clean_suggestion = $this->sanitize_filename_suggestion($suggestion['name']);
                    if (!empty($clean_suggestion)) {
                        $sanitized['suggestions'][] = $clean_suggestion;
                    }
                }
            }
        }

        // Sanitize other response fields
        if (isset($response['success'])) {
            $sanitized['success'] = (bool) $response['success'];
        }

        if (isset($response['message'])) {
            $sanitized['message'] = sanitize_text_field($response['message']);
        }

        if (isset($response['remaining_balance'])) {
            $sanitized['remaining_balance'] = intval($response['remaining_balance']);
        }

        return $sanitized;
    }

    /**
     * Sanitize filename suggestion from AI
     *
     * @param string $suggestion Raw filename suggestion
     * @return string Sanitized filename
     */
    private function sanitize_filename_suggestion($suggestion) {
        if (empty($suggestion) || !is_string($suggestion)) {
            return '';
        }

        // Remove any path separators and dangerous characters
        $suggestion = basename($suggestion);
        
        // Remove file extensions if present
        $suggestion = pathinfo($suggestion, PATHINFO_FILENAME);
        
        // Use WordPress sanitization
        $suggestion = sanitize_file_name($suggestion);
        
        // Additional cleanup for SEO-friendly names
        $suggestion = preg_replace('/[^a-zA-Z0-9\-_]/', '-', $suggestion);
        $suggestion = preg_replace('/-+/', '-', $suggestion);
        $suggestion = trim($suggestion, '-');
        
        // Ensure reasonable length
        if (strlen($suggestion) > 50) {
            $suggestion = substr($suggestion, 0, 50);
            $suggestion = rtrim($suggestion, '-');
        }
        
        // Ensure minimum length
        if (strlen($suggestion) < 2) {
            return '';
        }

        return strtolower($suggestion);
    }

    /**
     * Log security events
     *
     * @param string $event_type Type of security event
     * @param array $details Event details
     * @param int $user_id User ID (defaults to current user)
     */
    public function log_security_event($event_type, $details = array(), $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        $log_entry = array(
            'timestamp' => time(),
            'event_type' => sanitize_text_field($event_type),
            'user_id' => intval($user_id),
            'ip_address' => $this->get_client_ip(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '',
            'details' => $details
        );

        // Log to WordPress error log
        error_log('FMR Security Event: ' . wp_json_encode($log_entry));

        // Store in database for serious security events
        if (in_array($event_type, array('rate_limit_exceeded', 'invalid_nonce', 'permission_denied'))) {
            $this->store_security_event($log_entry);
        }
    }

    /**
     * Get client IP address
     *
     * @return string Client IP address
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = sanitize_text_field($_SERVER[$key]);
                // Handle comma-separated IPs (from proxies)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : 'unknown';
    }

    /**
     * Store security event in database
     *
     * @param array $log_entry Log entry data
     */
    private function store_security_event($log_entry) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fmrseo_security_log';
        
        // Create table if it doesn't exist
        $this->create_security_log_table();
        
        $wpdb->insert(
            $table_name,
            array(
                'timestamp' => $log_entry['timestamp'],
                'event_type' => $log_entry['event_type'],
                'user_id' => $log_entry['user_id'],
                'ip_address' => $log_entry['ip_address'],
                'user_agent' => $log_entry['user_agent'],
                'details' => wp_json_encode($log_entry['details'])
            ),
            array('%d', '%s', '%d', '%s', '%s', '%s')
        );
    }

    /**
     * Create security log table
     */
    private function create_security_log_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fmrseo_security_log';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            timestamp int(10) unsigned NOT NULL,
            event_type varchar(50) NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            ip_address varchar(45) NOT NULL,
            user_agent text,
            details longtext,
            PRIMARY KEY (id),
            KEY timestamp (timestamp),
            KEY event_type (event_type),
            KEY user_id (user_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Clean up old security log entries
     *
     * @param int $days_to_keep Number of days to keep logs (default: 30)
     * @return int Number of entries cleaned
     */
    public function cleanup_security_logs($days_to_keep = 30) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fmrseo_security_log';
        $cutoff_time = time() - ($days_to_keep * 24 * 60 * 60);
        
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name WHERE timestamp < %d",
            $cutoff_time
        ));
        
        return $deleted ? $deleted : 0;
    }
}