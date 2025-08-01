<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * AI Rename Controller
 * 
 * Orchestrates the AI-powered media renaming process by coordinating
 * content analysis, context extraction, and AI service integration.
 */
class FMR_AI_Rename_Controller {

    /**
     * @var FMR_Content_Analyzer
     */
    private $content_analyzer;

    /**
     * @var FMR_Context_Extractor
     */
    private $context_extractor;

    /**
     * @var FMR_AI_Service
     */
    private $ai_service;

    /**
     * @var FMR_Credit_Manager
     */
    private $credit_manager;

    /**
     * @var FMR_Error_Handler
     */
    private $error_handler;

    /**
     * @var FMR_Security_Manager
     */
    private $security_manager;

    /**
     * @var FMR_Performance_Optimizer
     */
    private $performance_optimizer;

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
        $this->init_dependencies();
    }

    /**
     * Initialize WordPress hooks and filters
     */
    private function init_hooks() {
        // Add AI rename button to media attachment fields
        add_filter('attachment_fields_to_edit', array($this, 'add_ai_rename_button'), 11, 2);
        
        // Register AJAX handlers
        add_action('wp_ajax_fmr_ai_rename_single', array($this, 'handle_ai_rename_single_ajax'));
        add_action('wp_ajax_fmr_ai_get_suggestions', array($this, 'handle_ai_suggestions_ajax'));
        
        // Register error handling AJAX handlers
        add_action('wp_ajax_fmr_check_ai_availability', array($this, 'handle_check_ai_availability_ajax'));
        add_action('wp_ajax_fmr_check_credits', array($this, 'handle_check_credits_ajax'));
        add_action('wp_ajax_fmr_reenable_ai', array($this, 'handle_reenable_ai_ajax'));
        add_action('wp_ajax_fmr_test_ai_connection', array($this, 'handle_test_connection_ajax'));
        
        // Enqueue AI-specific scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_ai_assets'));
        
        // Add AI suggestions modal to admin pages
        add_action('admin_footer-upload.php', array($this, 'display_ai_suggestions_modal'));
        add_action('admin_footer-post.php', array($this, 'display_ai_suggestions_modal'));
        add_action('admin_footer-media.php', array($this, 'display_ai_suggestions_modal'));
    }

    /**
     * Initialize dependencies
     */
    private function init_dependencies() {
        // Dependencies will be initialized when other classes are implemented
        // For now, we'll use lazy loading in the getter methods
    }

    /**
     * Add AI rename button to media attachment edit form
     *
     * @param array $form_fields The form fields
     * @param object $post The post object
     * @return array Modified form fields
     */
    public function add_ai_rename_button($form_fields, $post) {
        // Only add AI button if AI is enabled and user has credits
        if (!$this->is_ai_enabled() || !$this->has_sufficient_credits()) {
            return $form_fields;
        }

        $form_fields['ai_rename_button'] = array(
            'input' => 'html',
            'html' => '<button id="ai-rename-button" class="button button-secondary" media-id="' . esc_attr($post->ID) . '">' . 
                     __('Rename with AI', 'fmrseo') . '</button>',
            'label' => ''
        );

        return $form_fields;
    }

    /**
     * Handle AJAX request for single AI rename
     */
    public function handle_ai_rename_single_ajax() {
        $start_time = microtime(true);
        
        try {
            // Security validation
            $security_manager = $this->get_security_manager();
            
            // Validate nonce
            $nonce = isset($_POST['_ajax_nonce']) ? $_POST['_ajax_nonce'] : '';
            $security_manager->validate_nonce($nonce);

            // Validate user permissions
            $security_manager->validate_user_permissions('ai_rename_single');

            // Check rate limits
            $security_manager->check_rate_limit('ai_rename_single');

            // Sanitize input
            $sanitized_input = $security_manager->sanitize_ai_input($_POST, 'ai_rename');
            $post_id = $sanitized_input['post_id'];

            // Check AI availability
            $availability = $this->get_error_handler()->check_ai_availability();
            if (!$availability['available']) {
                throw new Exception(implode(' ', $availability['errors']));
            }

            // Get selected name from sanitized input
            $selected_name = isset($sanitized_input['selected_name']) ? $sanitized_input['selected_name'] : null;
            
            $result = $this->rename_single_media($post_id, $selected_name);

            // Log performance metrics
            $this->get_performance_optimizer()->log_performance_metrics(
                'ai_rename_single',
                $start_time,
                array('post_id' => $post_id, 'success' => true)
            );

            wp_send_json_success($result);

        } catch (Exception $e) {
            // Log security events for certain error types
            $security_manager = $this->get_security_manager();
            $error_message = $e->getMessage();
            
            if (strpos($error_message, 'Rate limit') !== false) {
                $security_manager->log_security_event('rate_limit_exceeded', array(
                    'operation' => 'ai_rename_single',
                    'error' => $error_message
                ));
            } elseif (strpos($error_message, 'Nonce') !== false) {
                $security_manager->log_security_event('invalid_nonce', array(
                    'operation' => 'ai_rename_single',
                    'error' => $error_message
                ));
            } elseif (strpos($error_message, 'permission') !== false) {
                $security_manager->log_security_event('permission_denied', array(
                    'operation' => 'ai_rename_single',
                    'error' => $error_message
                ));
            }

            // Log performance metrics for failed operations
            $this->get_performance_optimizer()->log_performance_metrics(
                'ai_rename_single_failed',
                $start_time,
                array('error' => $error_message, 'success' => false)
            );

            // Use error handler for comprehensive error handling
            $context = array(
                'post_id' => isset($post_id) ? $post_id : 0,
                'action' => 'single_ai_rename',
                'user_id' => get_current_user_id()
            );

            // Determine error type
            $error_type = $this->classify_error($e);
            
            // Handle error with fallback
            $error_result = $this->get_error_handler()->handle_error($error_type, $e, $context);
            
            if ($error_result['success']) {
                wp_send_json_success($error_result);
            } else {
                wp_send_json_error($error_result);
            }
        }
    }

    /**
     * Handle AJAX request for AI suggestions
     */
    public function handle_ai_suggestions_ajax() {
        $start_time = microtime(true);
        
        try {
            // Security validation
            $security_manager = $this->get_security_manager();
            
            // Validate nonce
            $nonce = isset($_POST['_ajax_nonce']) ? $_POST['_ajax_nonce'] : '';
            $security_manager->validate_nonce($nonce);

            // Validate user permissions
            $security_manager->validate_user_permissions('ai_suggestions');

            // Check rate limits
            $security_manager->check_rate_limit('ai_suggestions');

            // Sanitize input
            $sanitized_input = $security_manager->sanitize_ai_input($_POST, 'ai_suggestions');
            $post_id = $sanitized_input['post_id'];
            $count = isset($sanitized_input['count']) ? $sanitized_input['count'] : 3;

            // Check AI availability
            $availability = $this->get_error_handler()->check_ai_availability();
            if (!$availability['available']) {
                throw new Exception(implode(' ', $availability['errors']));
            }

            $suggestions = $this->get_ai_suggestions($post_id, $count);

            // Log performance metrics
            $this->get_performance_optimizer()->log_performance_metrics(
                'ai_suggestions',
                $start_time,
                array('post_id' => $post_id, 'count' => $count, 'success' => true)
            );

            wp_send_json_success(array('suggestions' => $suggestions));

        } catch (Exception $e) {
            // Log security events for certain error types
            $security_manager = $this->get_security_manager();
            $error_message = $e->getMessage();
            
            if (strpos($error_message, 'Rate limit') !== false) {
                $security_manager->log_security_event('rate_limit_exceeded', array(
                    'operation' => 'ai_suggestions',
                    'error' => $error_message
                ));
            }

            // Log performance metrics for failed operations
            $this->get_performance_optimizer()->log_performance_metrics(
                'ai_suggestions_failed',
                $start_time,
                array('error' => $error_message, 'success' => false)
            );

            // Use error handler for suggestions
            $context = array(
                'post_id' => isset($post_id) ? $post_id : 0,
                'action' => 'ai_suggestions',
                'user_id' => get_current_user_id()
            );

            $error_type = $this->classify_error($e);
            $error_result = $this->get_error_handler()->handle_error($error_type, $e, $context);
            
            wp_send_json_error($error_result);
        }
    }

    /**
     * Rename a single media file using AI
     *
     * @param int $post_id The media post ID
     * @param string $selected_name Optional pre-selected name from suggestions
     * @return array Result of the rename operation
     * @throws Exception
     */
    public function rename_single_media($post_id, $selected_name = null) {
        try {
            $ai_name = $selected_name;
            $suggestions = array();

            // If no name was pre-selected, get AI suggestions
            if (empty($ai_name)) {
                $suggestions = $this->get_ai_suggestions($post_id, 1);
                
                if (empty($suggestions)) {
                    throw new Exception(__('Failed to generate AI suggestions.', 'fmrseo'));
                }

                $ai_name = $suggestions[0];
            } else {
                // If a name was pre-selected, we still want to track it as a suggestion
                $suggestions = array($selected_name);
            }

            // Use existing rename function
            $result = fmrseo_complete_rename_process($post_id, $ai_name);

            // Deduct credit only on successful rename
            $this->get_credit_manager()->deduct_credit(1, null, $post_id, 'ai_rename');

            // Add AI-specific metadata to history
            $this->update_ai_history($post_id, $suggestions, $ai_name);

            return array(
                'success' => true,
                'message' => __('File renamed successfully with AI.', 'fmrseo'),
                'url' => $result['new_file_url'],
                'filename' => basename($result['new_file_path']),
                'method' => 'ai',
                'suggestions_used' => $suggestions
            );

        } catch (Exception $e) {
            // Handle specific AI rename errors
            $context = array(
                'post_id' => $post_id,
                'action' => 'ai_rename_process'
            );

            $error_type = $this->classify_error($e);
            
            // If it's a credit error, don't try fallback
            if ($error_type === 'credit_error') {
                throw $e;
            }

            // For other errors, let the error handler manage fallback
            throw $e;
        }
    }

    /**
     * Rename multiple media files using AI (bulk operation)
     *
     * @param array $post_ids Array of media post IDs
     * @param array $options Bulk operation options
     * @return array Results of bulk rename operation
     */
    public function rename_bulk_media($post_ids, $options = array()) {
        $results = array();
        $successful = 0;
        $failed = 0;

        foreach ($post_ids as $post_id) {
            try {
                if (!$this->has_sufficient_credits()) {
                    $results[$post_id] = array(
                        'success' => false,
                        'message' => __('Insufficient credits.', 'fmrseo')
                    );
                    $failed++;
                    continue;
                }

                $result = $this->rename_single_media($post_id);
                $results[$post_id] = $result;
                $successful++;

            } catch (Exception $e) {
                $results[$post_id] = array(
                    'success' => false,
                    'message' => $e->getMessage()
                );
                $failed++;
            }
        }

        return array(
            'results' => $results,
            'summary' => array(
                'total' => count($post_ids),
                'successful' => $successful,
                'failed' => $failed
            )
        );
    }

    /**
     * Get AI suggestions for a media file
     *
     * @param int $post_id The media post ID
     * @param int $count Number of suggestions to generate
     * @return array Array of suggested names
     * @throws Exception
     */
    public function get_ai_suggestions($post_id, $count = 3) {
        $performance_optimizer = $this->get_performance_optimizer();
        
        // Check cache first
        $cache_key = $performance_optimizer->generate_content_cache_key($post_id) . '_' . $count;
        $cached_suggestions = $performance_optimizer->get_cached_ai_result('suggestions', $cache_key);
        
        if ($cached_suggestions !== false) {
            return $cached_suggestions;
        }

        // Analyze content with caching
        $content_cache_key = $performance_optimizer->generate_content_cache_key($post_id);
        $content = $performance_optimizer->get_cached_ai_result('content_analysis', $content_cache_key);
        
        if ($content === false) {
            $content = $this->get_content_analyzer()->analyze_media($post_id);
            $performance_optimizer->cache_ai_result('content_analysis', $content_cache_key, $content);
        }
        
        // Extract context with optimization
        $context_cache_key = $performance_optimizer->generate_context_cache_key($post_id);
        $context = $performance_optimizer->get_cached_ai_result('context', $context_cache_key);
        
        if ($context === false) {
            $context = $performance_optimizer->optimize_context_queries($post_id);
            $performance_optimizer->cache_ai_result('context', $context_cache_key, $context);
        }
        
        // Generate AI suggestions
        $suggestions = $this->get_ai_service()->generate_names($content, $context, $count);
        
        // Cache the suggestions
        $performance_optimizer->cache_ai_result('suggestions', $cache_key, $suggestions);
        
        return $suggestions;
    }

    /**
     * Handle AI failure by falling back to basic rename
     *
     * @param int $post_id The media post ID
     * @param string $fallback_name Fallback name to use
     * @return array Result of fallback operation
     */
    private function handle_ai_failure($post_id, $fallback_name) {
        try {
            // If no fallback name provided, generate a basic one
            if (empty($fallback_name)) {
                $file_path = get_attached_file($post_id);
                $fallback_name = pathinfo($file_path, PATHINFO_FILENAME);
                $fallback_name = sanitize_title($fallback_name);
            }

            // Use existing rename function without AI
            $result = fmrseo_complete_rename_process($post_id, $fallback_name);

            return array(
                'success' => true,
                'message' => __('File renamed using fallback method (AI unavailable).', 'fmrseo'),
                'url' => $result['new_file_url'],
                'filename' => basename($result['new_file_path']),
                'method' => 'fallback'
            );

        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }

    /**
     * Update AI-specific history metadata using the comprehensive history manager
     *
     * @param int $post_id The media post ID
     * @param array $suggestions AI suggestions that were generated
     * @param string $selected_name The name that was selected
     * @param array $additional_data Additional operation data
     */
    private function update_ai_history($post_id, $suggestions, $selected_name, $additional_data = array()) {
        // Use the comprehensive history manager instead of direct meta updates
        if (class_exists('FMR_AI_History_Manager')) {
            $history_manager = new FMR_AI_History_Manager();
            
            // Prepare operation data for comprehensive tracking
            $operation_data = array_merge(array(
                'method' => 'ai',
                'ai_suggestions' => $suggestions,
                'selected_suggestion_index' => $this->find_selected_suggestion_index($suggestions, $selected_name),
                'credits_used' => 1,
                'processing_time' => isset($additional_data['processing_time']) ? $additional_data['processing_time'] : 0,
                'content_analysis' => isset($additional_data['content_analysis']) ? $additional_data['content_analysis'] : array(),
                'context_data' => isset($additional_data['context_data']) ? $additional_data['context_data'] : array(),
                'fallback_used' => isset($additional_data['fallback_used']) ? $additional_data['fallback_used'] : false,
                'error_occurred' => isset($additional_data['error_occurred']) ? $additional_data['error_occurred'] : false,
                'error_message' => isset($additional_data['error_message']) ? $additional_data['error_message'] : ''
            ), $additional_data);
            
            // Get the rename result for history tracking
            $rename_result = array(
                'old_file_path' => get_attached_file($post_id),
                'old_file_url' => wp_get_attachment_url($post_id),
                'seo_name' => $selected_name,
                'file_ext' => pathinfo(get_attached_file($post_id), PATHINFO_EXTENSION)
            );
            
            // Track the operation
            $history_manager->track_rename_operation($post_id, $rename_result, $operation_data);
        } else {
            // Fallback to old method if history manager is not available
            $history = get_post_meta($post_id, '_fmrseo_rename_history', true);
            
            if (is_array($history) && !empty($history)) {
                // Update the most recent history entry with AI metadata
                $history[0]['method'] = 'ai';
                $history[0]['ai_suggestions'] = $suggestions;
                $history[0]['credits_used'] = 1;
                
                update_post_meta($post_id, '_fmrseo_rename_history', $history);
            }
        }
    }

    /**
     * Find the index of the selected suggestion in the suggestions array
     *
     * @param array $suggestions Array of AI suggestions
     * @param string $selected_name The selected name
     * @return int Index of selected suggestion or 0 if not found
     */
    private function find_selected_suggestion_index($suggestions, $selected_name) {
        if (!is_array($suggestions)) {
            return 0;
        }
        
        foreach ($suggestions as $index => $suggestion) {
            if (is_array($suggestion) && isset($suggestion['name']) && $suggestion['name'] === $selected_name) {
                return $index;
            } elseif (is_string($suggestion) && $suggestion === $selected_name) {
                return $index;
            }
        }
        
        return 0;
    }

    /**
     * Enqueue AI-specific assets
     *
     * @param string $hook_suffix The current admin page
     */
    public function enqueue_ai_assets($hook_suffix) {
        // Only load on media pages
        if (!in_array($hook_suffix, array('upload.php', 'post.php', 'media.php'))) {
            return;
        }

        // Enqueue AI JavaScript
        wp_enqueue_script(
            'fmr-ai-rename',
            plugin_dir_url(dirname(dirname(__FILE__))) . 'assets/js/ai/ai-rename.js',
            array('jquery'),
            '1.0.0',
            true
        );

        // Enqueue Error Handling JavaScript
        wp_enqueue_script(
            'fmr-error-handling',
            plugin_dir_url(dirname(dirname(__FILE__))) . 'assets/js/ai/error-handling.js',
            array('jquery', 'fmr-ai-rename'),
            '1.0.0',
            true
        );

        // Enqueue AI CSS
        wp_enqueue_style(
            'fmr-ai-rename',
            plugin_dir_url(dirname(dirname(__FILE__))) . 'assets/css/ai/ai-rename.css',
            array(),
            '1.0.0'
        );

        // Enqueue Error Handling CSS
        wp_enqueue_style(
            'fmr-error-handling',
            plugin_dir_url(dirname(dirname(__FILE__))) . 'assets/css/ai/error-handling.css',
            array('fmr-ai-rename'),
            '1.0.0'
        );

        // Localize script with AJAX data
        wp_localize_script('fmr-ai-rename', 'fmrAI', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('save_seo_name_nonce'),
            'strings' => array(
                'processing' => __('Processing with AI...', 'fmrseo'),
                'error' => __('AI processing failed', 'fmrseo'),
                'success' => __('Renamed successfully', 'fmrseo'),
                'insufficient_credits' => __('Insufficient credits', 'fmrseo'),
                'rename_with_ai' => __('Rename with AI', 'fmrseo'),
                'ai_suggestions_title' => __('AI Rename Suggestions', 'fmrseo'),
                'select_suggestion' => __('Select a suggested name for your media file:', 'fmrseo'),
                'preview_label' => __('Preview:', 'fmrseo'),
                'cancel' => __('Cancel', 'fmrseo'),
                'apply_rename' => __('Apply Rename', 'fmrseo'),
                'please_select' => __('Please select a suggestion', 'fmrseo'),
                'applying' => __('Applying...', 'fmrseo'),
                'no_suggestions' => __('No suggestions available', 'fmrseo'),
                'generating_suggestions' => __('Generating AI suggestions...', 'fmrseo'),
                'suggestion_error' => __('Failed to generate suggestions', 'fmrseo')
            )
        ));

        // Localize error handler script
        wp_localize_script('fmr-error-handling', 'fmrErrorHandler', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('save_seo_name_nonce'),
            'debug' => defined('WP_DEBUG') && WP_DEBUG,
            'strings' => array(
                'retry' => __('Retry', 'fmrseo'),
                'retrying' => __('Retrying...', 'fmrseo'),
                'max_retries' => __('Maximum retries reached', 'fmrseo'),
                'fallback_suggested' => __('AI is experiencing issues. Switch to manual rename?', 'fmrseo'),
                'connection_test' => __('Testing connection...', 'fmrseo'),
                'checking_credits' => __('Checking credits...', 'fmrseo'),
                'reenabling_ai' => __('Re-enabling AI...', 'fmrseo')
            )
        ));
    }

    /**
     * Check if AI functionality is enabled
     *
     * @return bool
     */
    private function is_ai_enabled() {
        $options = get_option('fmrseo_options', array());
        return !empty($options['ai_enabled']);
    }

    /**
     * Check if user has sufficient credits
     *
     * @return bool
     */
    private function has_sufficient_credits() {
        return $this->get_credit_manager()->has_sufficient_credits();
    }

    /**
     * Get content analyzer instance (lazy loading)
     *
     * @return FMR_Content_Analyzer
     */
    private function get_content_analyzer() {
        if (!$this->content_analyzer) {
            $this->content_analyzer = new FMR_Content_Analyzer();
        }
        return $this->content_analyzer;
    }

    /**
     * Get context extractor instance (lazy loading)
     *
     * @return FMR_Context_Extractor
     */
    private function get_context_extractor() {
        if (!$this->context_extractor) {
            $this->context_extractor = new FMR_Context_Extractor();
        }
        return $this->context_extractor;
    }

    /**
     * Get AI service instance (lazy loading)
     *
     * @return FMR_AI_Service
     */
    private function get_ai_service() {
        if (!$this->ai_service) {
            $this->ai_service = new FMR_AI_Service();
        }
        return $this->ai_service;
    }

    /**
     * Get credit manager instance (lazy loading)
     *
     * @return FMR_Credit_Manager
     */
    private function get_credit_manager() {
        if (!$this->credit_manager) {
            $this->credit_manager = new FMR_Credit_Manager();
        }
        return $this->credit_manager;
    }

    /**
     * Get error handler instance (lazy loading)
     *
     * @return FMR_Error_Handler
     */
    private function get_error_handler() {
        if (!$this->error_handler) {
            $this->error_handler = new FMR_Error_Handler();
        }
        return $this->error_handler;
    }

    /**
     * Get security manager instance (lazy loading)
     *
     * @return FMR_Security_Manager
     */
    private function get_security_manager() {
        if (!$this->security_manager) {
            $this->security_manager = new FMR_Security_Manager();
        }
        return $this->security_manager;
    }

    /**
     * Get performance optimizer instance (lazy loading)
     *
     * @return FMR_Performance_Optimizer
     */
    private function get_performance_optimizer() {
        if (!$this->performance_optimizer) {
            $this->performance_optimizer = new FMR_Performance_Optimizer();
        }
        return $this->performance_optimizer;
    }

    /**
     * Classify error type for appropriate handling
     *
     * @param Exception $exception The exception to classify
     * @return string Error type
     */
    private function classify_error($exception) {
        $message = $exception->getMessage();
        
        // Credit-related errors
        if (strpos($message, 'credit') !== false || strpos($message, 'insufficient') !== false) {
            return 'credit_error';
        }
        
        // Configuration errors
        if (strpos($message, 'API key') !== false || strpos($message, 'configuration') !== false) {
            return 'configuration_error';
        }
        
        // AI service errors
        if (strpos($message, 'AI service') !== false || strpos($message, 'timeout') !== false || strpos($message, 'API') !== false) {
            return 'ai_service_error';
        }
        
        // Content analysis errors
        if (strpos($message, 'content') !== false || strpos($message, 'analysis') !== false) {
            return 'content_analysis_error';
        }
        
        // Validation errors
        if (strpos($message, 'Invalid') !== false || strpos($message, 'verification') !== false) {
            return 'validation_error';
        }
        
        // Default to system error
        return 'system_error';
    }

    /**
     * Handle AJAX request to check AI availability
     */
    public function handle_check_ai_availability_ajax() {
        try {
            // Verify nonce
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'save_seo_name_nonce')) {
                throw new Exception(__('Nonce verification failed.', 'fmrseo'));
            }

            $availability = $this->get_error_handler()->check_ai_availability();
            wp_send_json_success($availability);

        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * Handle AJAX request to check credit balance
     */
    public function handle_check_credits_ajax() {
        try {
            // Verify nonce
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'save_seo_name_nonce')) {
                throw new Exception(__('Nonce verification failed.', 'fmrseo'));
            }

            $credit_manager = $this->get_credit_manager();
            $balance = $credit_manager->get_credit_balance();
            $stats = $credit_manager->get_credit_stats();

            wp_send_json_success(array(
                'balance' => $balance,
                'stats' => $stats
            ));

        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * Handle AJAX request to re-enable AI
     */
    public function handle_reenable_ai_ajax() {
        try {
            // Verify nonce
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'save_seo_name_nonce')) {
                throw new Exception(__('Nonce verification failed.', 'fmrseo'));
            }

            // Check user capabilities
            if (!current_user_can('manage_options')) {
                throw new Exception(__('Insufficient permissions to re-enable AI.', 'fmrseo'));
            }

            $result = $this->get_error_handler()->re_enable_ai();
            
            if ($result) {
                wp_send_json_success(array('message' => __('AI functionality has been re-enabled.', 'fmrseo')));
            } else {
                throw new Exception(__('Failed to re-enable AI functionality.', 'fmrseo'));
            }

        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * Handle AJAX request to test AI connection
     */
    public function handle_test_connection_ajax() {
        try {
            // Verify nonce
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'save_seo_name_nonce')) {
                throw new Exception(__('Nonce verification failed.', 'fmrseo'));
            }

            $ai_service = $this->get_ai_service();
            $connection_test = $ai_service->test_connection();

            if ($connection_test) {
                wp_send_json_success(array('message' => __('AI service connection successful.', 'fmrseo')));
            } else {
                throw new Exception(__('AI service connection failed.', 'fmrseo'));
            }

        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * Display AI suggestions modal HTML in admin footer
     */
    public function display_ai_suggestions_modal() {
        // Only display if AI is enabled
        if (!$this->is_ai_enabled()) {
            return;
        }

        ?>
        <div id="fmrseo-ai-suggestions-modal" class="fmrseo-ai-modal" style="display: none;">
            <div class="fmrseo-ai-modal-content">
                <div class="fmrseo-ai-modal-header">
                    <h2><?php _e('AI Rename Suggestions', 'fmrseo'); ?></h2>
                    <button class="fmrseo-ai-modal-close" type="button">&times;</button>
                </div>
                <div class="fmrseo-ai-modal-body">
                    <div class="fmrseo-ai-modal-loading">
                        <span class="fmrseo-loading-spinner"></span>
                        <?php _e('Processing with AI...', 'fmrseo'); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}