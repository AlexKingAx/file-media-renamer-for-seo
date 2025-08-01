<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Error Handler Class
 * 
 * Implements comprehensive error handling and fallback system for AI functionality.
 * Follows WordPress error handling patterns and provides graceful degradation
 * when AI services are unavailable.
 * 
 * Requirements: 6.1, 6.2, 6.3, 6.4
 */
class FMR_Error_Handler {

    /**
     * @var array Error categories and their priorities
     */
    private $error_categories = array(
        'configuration' => 1,
        'ai_service' => 2,
        'content_analysis' => 3,
        'credit' => 4,
        'system' => 5,
        'validation' => 6
    );

    /**
     * @var array Fallback strategies for different error types
     */
    private $fallback_strategies = array(
        'ai_service_error' => 'fallback_to_basic_rename',
        'content_analysis_error' => 'use_metadata_only',
        'credit_error' => 'show_credit_error',
        'configuration_error' => 'disable_ai_features',
        'system_error' => 'generic_error_response',
        'validation_error' => 'validation_error_response'
    );

    /**
     * @var string WordPress option key for storing error logs
     */
    private $error_log_option = 'fmrseo_error_log';

    /**
     * @var int Maximum number of errors to keep in log
     */
    private $max_error_log_entries = 100;

    /**
     * Handle error with appropriate fallback strategy
     *
     * @param string $error_type Type of error (ai_service_error, content_analysis_error, etc.)
     * @param mixed $error_data Error data (Exception, string, array)
     * @param array $context Context information for the error
     * @return array Error response with fallback result
     */
    public function handle_error($error_type, $error_data, $context = array()) {
        // Log the error first
        $this->log_error($error_type, $error_data, $context);

        // Determine fallback strategy
        $strategy = isset($this->fallback_strategies[$error_type]) 
            ? $this->fallback_strategies[$error_type] 
            : 'generic_error_response';

        // Execute fallback strategy
        try {
            $fallback_result = $this->$strategy($error_data, $context);
            
            // Add error handling metadata to result
            $fallback_result['error_handled'] = true;
            $fallback_result['error_type'] = $error_type;
            $fallback_result['fallback_strategy'] = $strategy;
            
            return $fallback_result;
            
        } catch (Exception $fallback_exception) {
            // If fallback fails, return generic error
            $this->log_error('fallback_failure', $fallback_exception, array(
                'original_error_type' => $error_type,
                'failed_strategy' => $strategy
            ));
            
            return $this->generic_error_response($fallback_exception, $context);
        }
    }

    /**
     * Fallback to basic rename functionality when AI fails
     *
     * @param mixed $error_data Original error data
     * @param array $context Context information
     * @return array Fallback result
     */
    private function fallback_to_basic_rename($error_data, $context) {
        $post_id = isset($context['post_id']) ? intval($context['post_id']) : 0;
        
        if (!$post_id) {
            return array(
                'success' => false,
                'message' => __('Cannot perform fallback rename: Invalid media ID.', 'fmrseo'),
                'method' => 'fallback_failed'
            );
        }

        try {
            // Generate a basic SEO-friendly name from existing metadata
            $fallback_name = $this->generate_fallback_name($post_id);
            
            // Use existing rename function
            $result = fmrseo_complete_rename_process($post_id, $fallback_name);
            
            // Show admin notice about fallback
            $this->add_admin_notice(
                sprintf(
                    __('AI rename failed, but file was renamed using fallback method. New name: %s', 'fmrseo'),
                    basename($result['new_file_path'])
                ),
                'warning'
            );

            return array(
                'success' => true,
                'message' => __('File renamed using fallback method (AI unavailable).', 'fmrseo'),
                'url' => $result['new_file_url'],
                'filename' => basename($result['new_file_path']),
                'method' => 'fallback',
                'fallback_reason' => $this->get_error_message($error_data)
            );

        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => sprintf(
                    __('Both AI and fallback rename failed. Error: %s', 'fmrseo'),
                    $e->getMessage()
                ),
                'method' => 'fallback_failed'
            );
        }
    }

    /**
     * Use only WordPress metadata when content analysis fails
     *
     * @param mixed $error_data Original error data
     * @param array $context Context information
     * @return array Metadata-only result
     */
    private function use_metadata_only($error_data, $context) {
        $post_id = isset($context['post_id']) ? intval($context['post_id']) : 0;
        
        if (!$post_id) {
            return array(
                'success' => false,
                'message' => __('Cannot analyze content: Invalid media ID.', 'fmrseo'),
                'method' => 'metadata_failed'
            );
        }

        try {
            // Extract basic WordPress metadata
            $metadata = $this->extract_basic_metadata($post_id);
            
            // Generate name from metadata
            $name = $this->generate_name_from_metadata($metadata);
            
            if (empty($name)) {
                throw new Exception(__('No usable metadata found for naming.', 'fmrseo'));
            }

            return array(
                'success' => true,
                'content' => array(
                    'file_type' => $metadata['mime_type'],
                    'metadata' => $metadata,
                    'extracted_text' => '',
                    'analysis_method' => 'metadata_only'
                ),
                'suggested_name' => $name,
                'method' => 'metadata_only',
                'fallback_reason' => $this->get_error_message($error_data)
            );

        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => sprintf(
                    __('Content analysis and metadata extraction failed. Error: %s', 'fmrseo'),
                    $e->getMessage()
                ),
                'method' => 'metadata_failed'
            );
        }
    }

    /**
     * Handle credit-related errors with user-friendly messaging
     *
     * @param mixed $error_data Original error data
     * @param array $context Context information
     * @return array Credit error response
     */
    private function show_credit_error($error_data, $context) {
        $error_message = $this->get_error_message($error_data);
        
        // Determine specific credit error type
        if (strpos($error_message, 'insufficient') !== false) {
            $credit_manager = new FMR_Credit_Manager();
            $current_balance = $credit_manager->get_credit_balance();
            
            $this->add_admin_notice(
                sprintf(
                    __('Insufficient credits for AI rename. Current balance: %d. Manual rename is still available.', 'fmrseo'),
                    $current_balance
                ),
                'error'
            );
            
            return array(
                'success' => false,
                'message' => sprintf(
                    __('Insufficient credits for AI operation. Current balance: %d credits.', 'fmrseo'),
                    $current_balance
                ),
                'error_code' => 'insufficient_credits',
                'current_balance' => $current_balance,
                'method' => 'credit_error',
                'manual_rename_available' => true
            );
        }
        
        // Generic credit error
        $this->add_admin_notice(
            __('Credit system error occurred. Please try again or use manual rename.', 'fmrseo'),
            'error'
        );
        
        return array(
            'success' => false,
            'message' => __('Credit system error. Please try again later.', 'fmrseo'),
            'error_code' => 'credit_system_error',
            'method' => 'credit_error',
            'manual_rename_available' => true
        );
    }

    /**
     * Disable AI features when configuration is invalid
     *
     * @param mixed $error_data Original error data
     * @param array $context Context information
     * @return array Configuration error response
     */
    private function disable_ai_features($error_data, $context) {
        $error_message = $this->get_error_message($error_data);
        
        // Temporarily disable AI in options
        $options = get_option('fmrseo_options', array());
        $options['ai_temporarily_disabled'] = true;
        $options['ai_disable_reason'] = $error_message;
        $options['ai_disable_timestamp'] = time();
        update_option('fmrseo_options', $options);
        
        $this->add_admin_notice(
            sprintf(
                __('AI functionality has been temporarily disabled due to configuration error: %s. Manual rename is still available.', 'fmrseo'),
                $error_message
            ),
            'error'
        );
        
        return array(
            'success' => false,
            'message' => __('AI functionality is temporarily disabled due to configuration issues.', 'fmrseo'),
            'error_code' => 'ai_disabled',
            'method' => 'configuration_error',
            'manual_rename_available' => true,
            'disable_reason' => $error_message
        );
    }

    /**
     * Handle validation errors
     *
     * @param mixed $error_data Original error data
     * @param array $context Context information
     * @return array Validation error response
     */
    private function validation_error_response($error_data, $context) {
        $error_message = $this->get_error_message($error_data);
        
        return array(
            'success' => false,
            'message' => $error_message,
            'error_code' => 'validation_error',
            'method' => 'validation_error',
            'manual_rename_available' => true
        );
    }

    /**
     * Generic error response for unhandled errors
     *
     * @param mixed $error_data Original error data
     * @param array $context Context information
     * @return array Generic error response
     */
    private function generic_error_response($error_data, $context) {
        $error_message = $this->get_error_message($error_data);
        
        $this->add_admin_notice(
            __('An unexpected error occurred with AI functionality. Manual rename is still available.', 'fmrseo'),
            'error'
        );
        
        return array(
            'success' => false,
            'message' => __('An unexpected error occurred. Please try again or use manual rename.', 'fmrseo'),
            'error_code' => 'generic_error',
            'method' => 'generic_error',
            'manual_rename_available' => true,
            'debug_message' => $error_message
        );
    }

    /**
     * Log error for debugging and monitoring
     *
     * @param string $error_type Type of error
     * @param mixed $error_data Error data
     * @param array $context Context information
     */
    private function log_error($error_type, $error_data, $context = array()) {
        $error_entry = array(
            'timestamp' => time(),
            'type' => $error_type,
            'message' => $this->get_error_message($error_data),
            'context' => $context,
            'user_id' => get_current_user_id(),
            'ip_address' => $this->get_client_ip(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
            'request_uri' => isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : ''
        );

        // Add stack trace for exceptions
        if ($error_data instanceof Exception) {
            $error_entry['stack_trace'] = $error_data->getTraceAsString();
            $error_entry['file'] = $error_data->getFile();
            $error_entry['line'] = $error_data->getLine();
        }

        // Get existing error log
        $error_log = get_option($this->error_log_option, array());
        
        // Add new entry
        array_unshift($error_log, $error_entry);
        
        // Keep only recent entries
        $error_log = array_slice($error_log, 0, $this->max_error_log_entries);
        
        // Update option
        update_option($this->error_log_option, $error_log);

        // Also log to WordPress error log for debugging
        error_log(sprintf(
            'FMR Error Handler [%s]: %s | Context: %s',
            $error_type,
            $this->get_error_message($error_data),
            wp_json_encode($context)
        ));
    }

    /**
     * Add WordPress admin notice
     *
     * @param string $message Notice message
     * @param string $type Notice type (success, warning, error, info)
     */
    private function add_admin_notice($message, $type = 'info') {
        add_action('admin_notices', function() use ($message, $type) {
            printf(
                '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                esc_attr($type),
                esc_html($message)
            );
        });
    }

    /**
     * Generate fallback name from post metadata
     *
     * @param int $post_id Post ID
     * @return string Generated fallback name
     */
    private function generate_fallback_name($post_id) {
        $post = get_post($post_id);
        if (!$post) {
            return 'media-file-' . $post_id;
        }

        // Try different sources for name generation
        $name_sources = array();

        // Post title
        if (!empty($post->post_title) && $post->post_title !== 'Auto Draft') {
            $name_sources[] = $post->post_title;
        }

        // Alt text
        $alt_text = get_post_meta($post_id, '_wp_attachment_image_alt', true);
        if (!empty($alt_text)) {
            $name_sources[] = $alt_text;
        }

        // Caption
        if (!empty($post->post_excerpt)) {
            $name_sources[] = $post->post_excerpt;
        }

        // Description
        if (!empty($post->post_content)) {
            $name_sources[] = $post->post_content;
        }

        // Use the first available source
        foreach ($name_sources as $source) {
            $name = sanitize_title($source);
            if (!empty($name) && strlen($name) >= 3) {
                return $name;
            }
        }

        // Fallback to filename
        $file_path = get_attached_file($post_id);
        if ($file_path) {
            $filename = pathinfo($file_path, PATHINFO_FILENAME);
            $name = sanitize_title($filename);
            if (!empty($name)) {
                return $name;
            }
        }

        // Final fallback
        return 'media-file-' . $post_id;
    }

    /**
     * Extract basic WordPress metadata
     *
     * @param int $post_id Post ID
     * @return array Metadata array
     */
    private function extract_basic_metadata($post_id) {
        $post = get_post($post_id);
        $file_path = get_attached_file($post_id);
        
        $metadata = array(
            'title' => $post ? $post->post_title : '',
            'alt_text' => get_post_meta($post_id, '_wp_attachment_image_alt', true),
            'caption' => $post ? $post->post_excerpt : '',
            'description' => $post ? $post->post_content : '',
            'mime_type' => $post ? $post->post_mime_type : '',
            'file_size' => $file_path ? filesize($file_path) : 0,
            'upload_date' => $post ? $post->post_date : ''
        );

        // Add image-specific metadata
        if (strpos($metadata['mime_type'], 'image/') === 0) {
            $image_meta = wp_get_attachment_metadata($post_id);
            if ($image_meta) {
                $metadata['width'] = isset($image_meta['width']) ? $image_meta['width'] : 0;
                $metadata['height'] = isset($image_meta['height']) ? $image_meta['height'] : 0;
            }
        }

        return $metadata;
    }

    /**
     * Generate name from metadata
     *
     * @param array $metadata Metadata array
     * @return string Generated name
     */
    private function generate_name_from_metadata($metadata) {
        // Priority order for name generation
        $name_candidates = array(
            $metadata['alt_text'],
            $metadata['title'],
            $metadata['caption'],
            $metadata['description']
        );

        foreach ($name_candidates as $candidate) {
            if (!empty($candidate)) {
                $name = sanitize_title($candidate);
                if (!empty($name) && strlen($name) >= 3) {
                    return $name;
                }
            }
        }

        // Generate name based on file type
        if (!empty($metadata['mime_type'])) {
            $type_parts = explode('/', $metadata['mime_type']);
            $file_type = isset($type_parts[0]) ? $type_parts[0] : 'file';
            return $file_type . '-' . time();
        }

        return 'unnamed-file-' . time();
    }

    /**
     * Extract error message from various error data types
     *
     * @param mixed $error_data Error data
     * @return string Error message
     */
    private function get_error_message($error_data) {
        if ($error_data instanceof Exception) {
            return $error_data->getMessage();
        } elseif (is_string($error_data)) {
            return $error_data;
        } elseif (is_array($error_data) && isset($error_data['message'])) {
            return $error_data['message'];
        } elseif (is_wp_error($error_data)) {
            return $error_data->get_error_message();
        }
        
        return 'Unknown error occurred';
    }

    /**
     * Get client IP address
     *
     * @return string Client IP address
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
    }

    /**
     * Check if AI functionality should be available
     *
     * @return array Availability status with details
     */
    public function check_ai_availability() {
        $availability = array(
            'available' => true,
            'errors' => array(),
            'warnings' => array()
        );

        $options = get_option('fmrseo_options', array());

        // Check if AI is enabled
        if (empty($options['ai_enabled'])) {
            $availability['available'] = false;
            $availability['errors'][] = __('AI functionality is disabled in settings.', 'fmrseo');
        }

        // Check if temporarily disabled
        if (!empty($options['ai_temporarily_disabled'])) {
            $availability['available'] = false;
            $availability['errors'][] = sprintf(
                __('AI functionality is temporarily disabled: %s', 'fmrseo'),
                isset($options['ai_disable_reason']) ? $options['ai_disable_reason'] : 'Unknown reason'
            );
        }

        // Check API key
        if (empty($options['ai_api_key'])) {
            $availability['available'] = false;
            $availability['errors'][] = __('AI API key is not configured.', 'fmrseo');
        }

        // Check credits
        if (class_exists('FMR_Credit_Manager')) {
            $credit_manager = new FMR_Credit_Manager();
            if (!$credit_manager->has_sufficient_credits()) {
                $availability['available'] = false;
                $availability['errors'][] = sprintf(
                    __('Insufficient credits. Current balance: %d', 'fmrseo'),
                    $credit_manager->get_credit_balance()
                );
            }
        }

        // Check required classes
        $required_classes = array(
            'FMR_AI_Service',
            'FMR_Content_Analyzer',
            'FMR_Context_Extractor',
            'FMR_Credit_Manager'
        );

        foreach ($required_classes as $class) {
            if (!class_exists($class)) {
                $availability['available'] = false;
                $availability['errors'][] = sprintf(
                    __('Required class %s is not available.', 'fmrseo'),
                    $class
                );
            }
        }

        return $availability;
    }

    /**
     * Get error statistics for admin dashboard
     *
     * @param int $days Number of days to analyze (default: 7)
     * @return array Error statistics
     */
    public function get_error_statistics($days = 7) {
        $error_log = get_option($this->error_log_option, array());
        $cutoff_time = time() - ($days * 24 * 60 * 60);
        
        $stats = array(
            'total_errors' => 0,
            'by_type' => array(),
            'by_day' => array(),
            'recent_errors' => array()
        );

        foreach ($error_log as $error) {
            if ($error['timestamp'] >= $cutoff_time) {
                $stats['total_errors']++;
                
                // Count by type
                $type = $error['type'];
                if (!isset($stats['by_type'][$type])) {
                    $stats['by_type'][$type] = 0;
                }
                $stats['by_type'][$type]++;
                
                // Count by day
                $day = date('Y-m-d', $error['timestamp']);
                if (!isset($stats['by_day'][$day])) {
                    $stats['by_day'][$day] = 0;
                }
                $stats['by_day'][$day]++;
                
                // Keep recent errors (last 10)
                if (count($stats['recent_errors']) < 10) {
                    $stats['recent_errors'][] = array(
                        'timestamp' => $error['timestamp'],
                        'type' => $error['type'],
                        'message' => $error['message']
                    );
                }
            }
        }

        return $stats;
    }

    /**
     * Clear error log (admin function)
     *
     * @return bool True if cleared successfully
     */
    public function clear_error_log() {
        return delete_option($this->error_log_option);
    }

    /**
     * Re-enable AI functionality after configuration fix
     *
     * @return bool True if re-enabled successfully
     */
    public function re_enable_ai() {
        $options = get_option('fmrseo_options', array());
        
        // Remove temporary disable flags
        unset($options['ai_temporarily_disabled']);
        unset($options['ai_disable_reason']);
        unset($options['ai_disable_timestamp']);
        
        $result = update_option('fmrseo_options', $options);
        
        if ($result) {
            $this->add_admin_notice(
                __('AI functionality has been re-enabled.', 'fmrseo'),
                'success'
            );
        }
        
        return $result;
    }

    /**
     * Handle bulk operation errors with partial success support
     *
     * @param array $results Bulk operation results
     * @param array $context Context information
     * @return array Processed bulk results with error handling
     */
    public function handle_bulk_errors($results, $context = array()) {
        $processed_results = array(
            'successful' => array(),
            'failed' => array(),
            'summary' => array(
                'total' => 0,
                'successful_count' => 0,
                'failed_count' => 0,
                'errors_by_type' => array()
            )
        );

        foreach ($results as $post_id => $result) {
            $processed_results['summary']['total']++;
            
            if (isset($result['success']) && $result['success']) {
                $processed_results['successful'][$post_id] = $result;
                $processed_results['summary']['successful_count']++;
            } else {
                // Handle individual failure
                $error_type = isset($result['error_code']) ? $result['error_code'] : 'unknown_error';
                
                // Count errors by type
                if (!isset($processed_results['summary']['errors_by_type'][$error_type])) {
                    $processed_results['summary']['errors_by_type'][$error_type] = 0;
                }
                $processed_results['summary']['errors_by_type'][$error_type]++;
                
                $processed_results['failed'][$post_id] = $result;
                $processed_results['summary']['failed_count']++;
                
                // Log individual error
                $this->log_error('bulk_operation_item_failed', $result, array_merge($context, array(
                    'post_id' => $post_id,
                    'bulk_operation' => true
                )));
            }
        }

        // Add appropriate admin notices for bulk operations
        if ($processed_results['summary']['successful_count'] > 0 && $processed_results['summary']['failed_count'] > 0) {
            $this->add_admin_notice(
                sprintf(
                    __('Bulk operation completed with mixed results: %d successful, %d failed.', 'fmrseo'),
                    $processed_results['summary']['successful_count'],
                    $processed_results['summary']['failed_count']
                ),
                'warning'
            );
        } elseif ($processed_results['summary']['failed_count'] > 0) {
            $this->add_admin_notice(
                sprintf(
                    __('Bulk operation failed for all %d items.', 'fmrseo'),
                    $processed_results['summary']['failed_count']
                ),
                'error'
            );
        }

        return $processed_results;
    }
}