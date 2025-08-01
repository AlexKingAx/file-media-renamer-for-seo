<?php

/**
 * Bulk rename functionality for File Media Renamer for SEO
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Add bulk action to media library
 */
function fmrseo_add_bulk_rename_action($bulk_actions)
{
    $bulk_actions['fmrseo_bulk_rename'] = __('Rename', 'fmrseo');
    return $bulk_actions;
}
add_filter('bulk_actions-upload', 'fmrseo_add_bulk_rename_action');

/**
 * Handle bulk rename action
 */
function fmrseo_handle_bulk_rename($redirect_to, $doaction, $post_ids)
{
    if ($doaction !== 'fmrseo_bulk_rename') {
        return $redirect_to;
    }

    // Store selected IDs in transient for modal processing
    set_transient('fmrseo_bulk_rename_ids', $post_ids, 300); // 5 minutes

    // Redirect to custom page with modal
    return add_query_arg('fmrseo_bulk_rename', '1', $redirect_to);
}
add_filter('handle_bulk_actions-upload', 'fmrseo_handle_bulk_rename', 10, 3);

/**
 * Display bulk rename modal
 */
function fmrseo_display_bulk_rename_modal()
{
    if (!isset($_GET['fmrseo_bulk_rename']) || $_GET['fmrseo_bulk_rename'] !== '1') {
        return;
    }

    $post_ids = get_transient('fmrseo_bulk_rename_ids');
    if (!$post_ids || !is_array($post_ids)) {
        return;
    }

?>
    <div id="fmrseo-bulk-rename-modal" style="display: none;">
        <div class="fmrseo-modal-content">
            <div class="fmrseo-modal-header">
                <h2><?php _e('Rename Selected Media', 'fmrseo'); ?></h2>
                <span class="fmrseo-close">&times;</span>
            </div>
            <div class="fmrseo-modal-body">
                <p><?php printf(__('You have selected %d files to rename.', 'fmrseo'), count($post_ids)); ?></p>
                <div class="fmrseo-rename-method">
                    <h4><?php _e('Rename Method:', 'fmrseo'); ?></h4>
                    <label class="fmrseo-method-option">
                        <input type="radio" name="fmrseo-rename-method" value="manual" checked>
                        <span><?php _e('Manual Rename', 'fmrseo'); ?></span>
                    </label>
                    <?php if (fmrseo_is_ai_available()): ?>
                    <label class="fmrseo-method-option">
                        <input type="radio" name="fmrseo-rename-method" value="ai">
                        <span><?php _e('AI Rename', 'fmrseo'); ?></span>
                        <small class="fmrseo-credits-info">
                            <?php printf(__('Credits available: %d', 'fmrseo'), fmrseo_get_credit_balance()); ?>
                        </small>
                    </label>
                    <?php endif; ?>
                </div>

                <div class="fmrseo-form-group" id="fmrseo-manual-options">
                    <label for="fmrseo-bulk-name"><?php _e('Base name:', 'fmrseo'); ?></label>
                    <input type="text" id="fmrseo-bulk-name" placeholder="<?php _e('e.g: new name', 'fmrseo'); ?>" />
                    <p class="description"><?php _e('Files will be renamed as: new-name-1, new-name-2, etc.', 'fmrseo'); ?></p>
                </div>

                <div class="fmrseo-form-group" id="fmrseo-ai-options" style="display: none;">
                    <p class="description"><?php _e('AI will analyze each file individually and generate SEO-optimized names based on content and context.', 'fmrseo'); ?></p>
                    <p class="fmrseo-ai-cost-info">
                        <?php printf(__('Cost: 1 credit per successfully renamed file (max %d files)', 'fmrseo'), count($post_ids)); ?>
                    </p>
                </div>
                <div class="fmrseo-progress" style="display: none;">
                    <div class="fmrseo-progress-bar">
                        <div class="fmrseo-progress-fill"></div>
                    </div>
                    <div class="fmrseo-progress-text">0%</div>
                </div>
                <div class="fmrseo-results" style="display: none;"></div>
            </div>
            <div class="fmrseo-modal-footer">
                <button type="button" class="button button-secondary" id="fmrseo-cancel-bulk"><?php _e('Cancel', 'fmrseo'); ?></button>
                <button type="button" class="button button-primary" id="fmrseo-start-bulk"><?php _e('Start Rename', 'fmrseo'); ?></button>
                <button type="button" class="button button-primary" id="fmrseo-close-bulk" style="display: none;" disabled="true">
    <?php _e('Close', 'fmrseo'); ?>
</button>

            </div>
        </div>
    </div>

    <script type="text/javascript">
        var fmrseoBulkRenameIds = <?php echo json_encode($post_ids); ?>;
    </script>
<?php

    // Clean up transient
    delete_transient('fmrseo_bulk_rename_ids');
}
add_action('admin_footer-upload.php', 'fmrseo_display_bulk_rename_modal');

/**
 * Enqueue bulk rename assets
 */
function fmrseo_enqueue_bulk_rename_assets($hook)
{
    if ($hook !== 'upload.php') {
        return;
    }

    wp_enqueue_script(
        'fmrseo-bulk-rename',
        plugin_dir_url(dirname(__FILE__)) . 'assets/js/bulk-rename.js',
        array('jquery'),
        '1.0.0',
        true
    );

    wp_localize_script('fmrseo-bulk-rename', 'fmrseoBulkRename', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('fmrseo_bulk_rename_nonce'),
        'strings' => array(
            'processing' => __('Processing...', 'fmrseo'),
            'processing_ai' => __('Processing with AI...', 'fmrseo'),
            'completed' => __('Rename completed!', 'fmrseo'),
            'error' => __('Error during rename', 'fmrseo'),
            'success' => __('File renamed successfully', 'fmrseo'),
            'failed' => __('Error renaming file', 'fmrseo'),
            'ai_processing' => __('AI is analyzing files...', 'fmrseo'),
            'credits_used' => __('Credits used', 'fmrseo')
        )
    ));

    wp_enqueue_style(
        'fmrseo-bulk-rename',
        plugin_dir_url(dirname(__FILE__)) . 'assets/css/bulk-rename.css',
        array(),
        '1.0.0'
    );
}
add_action('admin_enqueue_scripts', 'fmrseo_enqueue_bulk_rename_assets');

/**
 * AI bulk rename function with individual file handling and comprehensive error handling
 */
function fmrseo_bulk_ai_rename_media_files($post_ids)
{
    if (!class_exists('FMR_AI_Rename_Controller')) {
        throw new Exception(__('AI functionality is not available.', 'fmrseo'));
    }

    if (!class_exists('FMR_Error_Handler')) {
        throw new Exception(__('Error handling system is not available.', 'fmrseo'));
    }

    $error_handler = new FMR_Error_Handler();
    $results = array();
    $successful_count = 0;
    $failed_count = 0;
    $credits_used = 0;

    // Check initial AI availability
    $availability = $error_handler->check_ai_availability();
    if (!$availability['available']) {
        throw new Exception(implode(' ', $availability['errors']));
    }

    // Process each file individually
    foreach ($post_ids as $post_id) {
        try {
            // Validate post ID
            if (!$post_id || get_post_type($post_id) !== 'attachment') {
                $results[$post_id] = array(
                    'success' => false,
                    'post_id' => $post_id,
                    'message' => __('Invalid media file.', 'fmrseo'),
                    'method' => 'ai',
                    'error_code' => 'invalid_media'
                );
                $failed_count++;
                continue;
            }

            // Get original filename for comparison
            $file_path = get_attached_file($post_id);
            $original_filename = $file_path ? basename($file_path) : get_the_title($post_id);

            // Process individual file with AI using error handler
            $ai_controller = new FMR_AI_Rename_Controller();
            
            try {
                $result = $ai_controller->rename_single_media($post_id);

                if ($result['success']) {
                    // Track successful AI bulk operation
                    if (class_exists('FMR_AI_History_Manager')) {
                        $history_manager = new FMR_AI_History_Manager();
                        $operation_data = array(
                            'method' => 'ai',
                            'bulk_operation' => true,
                            'bulk_batch_id' => 'ai_bulk_' . time(),
                            'bulk_total_files' => count($post_ids),
                            'bulk_file_index' => array_search($post_id, $post_ids),
                            'ai_suggestions' => isset($result['suggestions']) ? $result['suggestions'] : array(),
                            'credits_used' => 1,
                            'processing_time' => isset($result['processing_time']) ? $result['processing_time'] : 0,
                            'content_analysis' => isset($result['content_analysis']) ? $result['content_analysis'] : array(),
                            'context_data' => isset($result['context_data']) ? $result['context_data'] : array(),
                            'fallback_used' => isset($result['fallback_used']) ? $result['fallback_used'] : false
                        );
                        
                        $rename_result = array(
                            'old_file_path' => get_attached_file($post_id),
                            'old_file_url' => wp_get_attachment_url($post_id),
                            'seo_name' => $result['filename'],
                            'file_ext' => pathinfo(get_attached_file($post_id), PATHINFO_EXTENSION)
                        );
                        
                        $history_manager->track_rename_operation($post_id, $rename_result, $operation_data);
                    }
                    
                    $results[$post_id] = array(
                        'success' => true,
                        'post_id' => $post_id,
                        'old_name' => $original_filename,
                        'new_name' => $result['filename'],
                        'message' => $result['message'],
                        'method' => 'ai',
                        'credits_used' => 1
                    );
                    $successful_count++;
                    $credits_used++;
                } else {
                    // Track failed AI bulk operation
                    if (class_exists('FMR_AI_History_Manager')) {
                        $history_manager = new FMR_AI_History_Manager();
                        $operation_data = array(
                            'method' => 'ai',
                            'bulk_operation' => true,
                            'bulk_batch_id' => 'ai_bulk_' . time(),
                            'bulk_total_files' => count($post_ids),
                            'bulk_file_index' => array_search($post_id, $post_ids),
                            'error_occurred' => true,
                            'error_message' => $result['message'],
                            'credits_used' => 0
                        );
                        
                        $rename_result = array(
                            'old_file_path' => get_attached_file($post_id),
                            'old_file_url' => wp_get_attachment_url($post_id),
                            'seo_name' => '',
                            'file_ext' => pathinfo(get_attached_file($post_id), PATHINFO_EXTENSION)
                        );
                        
                        $history_manager->track_rename_operation($post_id, $rename_result, $operation_data);
                    }
                    
                    $results[$post_id] = array(
                        'success' => false,
                        'post_id' => $post_id,
                        'old_name' => $original_filename,
                        'message' => $result['message'],
                        'method' => 'ai',
                        'error_code' => isset($result['error_code']) ? $result['error_code'] : 'ai_processing_failed'
                    );
                    $failed_count++;
                }
            } catch (Exception $ai_exception) {
                // Use error handler for individual file failures
                $context = array(
                    'post_id' => $post_id,
                    'action' => 'bulk_ai_rename_item',
                    'bulk_operation' => true,
                    'original_filename' => $original_filename
                );

                // Classify error type
                $error_type = fmrseo_classify_bulk_error($ai_exception);
                
                // Handle error with potential fallback
                $error_result = $error_handler->handle_error($error_type, $ai_exception, $context);
                
                // Process error result
                if ($error_result['success']) {
                    // Fallback succeeded
                    $results[$post_id] = array_merge($error_result, array(
                        'post_id' => $post_id,
                        'old_name' => $original_filename
                    ));
                    $successful_count++;
                } else {
                    // Both AI and fallback failed
                    $results[$post_id] = array_merge($error_result, array(
                        'post_id' => $post_id,
                        'old_name' => $original_filename
                    ));
                    $failed_count++;
                }
            }

        } catch (Exception $e) {
            // Handle individual file errors without stopping the entire process
            $file_path = get_attached_file($post_id);
            $original_filename = $file_path ? basename($file_path) : "ID: $post_id";
            
            $results[$post_id] = array(
                'success' => false,
                'post_id' => $post_id,
                'old_name' => $original_filename,
                'message' => $e->getMessage(),
                'method' => 'ai',
                'error_code' => 'system_error'
            );
            $failed_count++;
            
            // Log the error for debugging
            error_log("FMR Bulk AI Rename Error for post $post_id: " . $e->getMessage());
        }
    }

    // Process bulk results through error handler for comprehensive reporting
    $processed_results = $error_handler->handle_bulk_errors($results, array(
        'operation' => 'bulk_ai_rename',
        'total_files' => count($post_ids)
    ));

    // Add summary information
    $processed_results['_summary'] = array(
        'total_processed' => count($post_ids),
        'successful' => $successful_count,
        'failed' => $failed_count,
        'credits_used' => $credits_used
    );
    
    return $processed_results;
}

/**
 * Classify bulk operation errors
 *
 * @param Exception $exception The exception to classify
 * @return string Error type
 */
function fmrseo_classify_bulk_error($exception) {
    $message = $exception->getMessage();
    
    // Credit-related errors
    if (strpos($message, 'credit') !== false || strpos($message, 'insufficient') !== false) {
        return 'credit_error';
    }
    
    // AI service errors
    if (strpos($message, 'AI service') !== false || strpos($message, 'timeout') !== false) {
        return 'ai_service_error';
    }
    
    // Content analysis errors
    if (strpos($message, 'content') !== false || strpos($message, 'analysis') !== false) {
        return 'content_analysis_error';
    }
    
    // Default to system error
    return 'system_error';
}

/**
 * Reusable bulk rename function
 */
function fmrseo_bulk_rename_media_files($post_ids, $base_name)
{
    $results = array();
    $counter = 1;

    foreach ($post_ids as $post_id) {
        try {
            // Generate unique name for each file
            $seo_name = $base_name . '-' . $counter;

            // Use the complete rename process wrapper function
            $result = fmrseo_complete_rename_process($post_id, $seo_name);

            // Get final seo_name in case it was modified
            $final_seo_name = isset($result['seo_name']) ? $result['seo_name'] : pathinfo($result['new_file_path'], PATHINFO_FILENAME);

            // Track bulk operation in comprehensive history
            if (class_exists('FMR_AI_History_Manager')) {
                $history_manager = new FMR_AI_History_Manager();
                $operation_data = array(
                    'method' => 'manual',
                    'bulk_operation' => true,
                    'bulk_batch_id' => 'bulk_' . time(),
                    'bulk_total_files' => count($post_ids),
                    'bulk_file_index' => $counter - 1
                );
                $history_manager->track_rename_operation($post_id, $result, $operation_data);
            }

            $results[] = array(
                'success' => true,
                'post_id' => $post_id,
                'old_name' => basename($result['old_file_path']),
                'new_name' => $final_seo_name . '.' . $result['file_ext'],
                'message' => __('File renamed successfully', 'fmrseo'),
                'method' => 'manual'
            );

            $counter++;
        } catch (Exception $e) {
            $results[] = array(
                'success' => false,
                'post_id' => $post_id,
                'message' => $e->getMessage()
            );
        }
    }

    return $results;
}

/**
 * Check if AI functionality is available
 */
function fmrseo_is_ai_available()
{
    if (!class_exists('FMR_Credit_Manager')) {
        return false;
    }
    
    $options = get_option('fmrseo_options', array());
    $ai_enabled = !empty($options['ai_enabled']);
    
    if (!$ai_enabled) {
        return false;
    }
    
    $credit_manager = new FMR_Credit_Manager();
    return $credit_manager->has_sufficient_credits();
}

/**
 * Get current credit balance
 */
function fmrseo_get_credit_balance()
{
    if (!class_exists('FMR_Credit_Manager')) {
        return 0;
    }
    
    $credit_manager = new FMR_Credit_Manager();
    return $credit_manager->get_credit_balance();
}

/**
 * AJAX handler for bulk rename
 */
function fmrseo_ajax_bulk_rename()
{
    try {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'fmrseo_bulk_rename_nonce')) {
            throw new Exception(__('Security verification failed.', 'fmrseo'));
        }

        // Check permissions
        if (!current_user_can('upload_files')) {
            throw new Exception(__('Insufficient permissions.', 'fmrseo'));
        }

        $post_ids = isset($_POST['post_ids']) ? array_map('intval', $_POST['post_ids']) : array();
        $rename_method = isset($_POST['rename_method']) ? sanitize_text_field($_POST['rename_method']) : 'manual';
        $base_name = isset($_POST['base_name']) ? sanitize_file_name($_POST['base_name']) : '';

        if (empty($post_ids)) {
            throw new Exception(__('No files selected.', 'fmrseo'));
        }

        // Validate parameters based on rename method
        if ($rename_method === 'manual') {
            if (empty($base_name)) {
                throw new Exception(__('Base name is required for manual rename.', 'fmrseo'));
            }
            
            // Validate base name
            if (!preg_match('/^[a-zA-Z0-9\-_]+$/', $base_name)) {
                throw new Exception(__('Base name can only contain letters, numbers, hyphens and underscores.', 'fmrseo'));
            }
        } elseif ($rename_method === 'ai') {
            // Check if AI is available
            if (!fmrseo_is_ai_available()) {
                throw new Exception(__('AI functionality is not available.', 'fmrseo'));
            }
        } else {
            throw new Exception(__('Invalid rename method.', 'fmrseo'));
        }

        // Limit number of files to prevent timeout
        if (count($post_ids) > 50) {
            throw new Exception(__('You can rename maximum 50 files at once.', 'fmrseo'));
        }

        // Verify all IDs are valid attachments
        foreach ($post_ids as $post_id) {
            if (get_post_type($post_id) !== 'attachment') {
                throw new Exception(__('One or more IDs are not valid media files.', 'fmrseo'));
            }
        }

        // Process bulk rename based on method
        if ($rename_method === 'ai') {
            $results = fmrseo_bulk_ai_rename_media_files($post_ids);
        } else {
            $results = fmrseo_bulk_rename_media_files($post_ids, $base_name);
        }

        wp_send_json_success($results);
    } catch (Exception $e) {
        wp_send_json_error(array('message' => $e->getMessage()));
    }
}
add_action('wp_ajax_fmrseo_bulk_rename', 'fmrseo_ajax_bulk_rename');

/**
 * AJAX handler for progressive bulk AI rename
 */
function fmrseo_ajax_bulk_ai_rename_progressive()
{
    $start_time = microtime(true);
    
    try {
        // Enhanced security validation using security manager
        if (class_exists('FMR_Security_Manager')) {
            $security_manager = new FMR_Security_Manager();
            
            // Validate nonce
            $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : '';
            $security_manager->validate_nonce($nonce, 'fmrseo_bulk_rename_nonce');
            
            // Validate user permissions for bulk operations
            $security_manager->validate_user_permissions('ai_bulk_rename');
            
            // Check rate limits for bulk operations
            $security_manager->check_rate_limit('ai_bulk_rename');
            
            // Sanitize input data
            $sanitized_input = $security_manager->sanitize_ai_input($_POST, 'ai_bulk_rename');
            $post_id = $sanitized_input['post_id'];
        } else {
            // Fallback security validation
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'fmrseo_bulk_rename_nonce')) {
                throw new Exception(__('Security verification failed.', 'fmrseo'));
            }

            if (!current_user_can('upload_files')) {
                throw new Exception(__('Insufficient permissions.', 'fmrseo'));
            }

            $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        }

        $batch_index = isset($_POST['batch_index']) ? intval($_POST['batch_index']) : 0;
        $total_files = isset($_POST['total_files']) ? intval($_POST['total_files']) : 0;

        if (!$post_id) {
            throw new Exception(__('Invalid media ID.', 'fmrseo'));
        }

        // Check if AI is available
        if (!fmrseo_is_ai_available()) {
            throw new Exception(__('AI functionality is not available.', 'fmrseo'));
        }

        // Validate post ID
        if (get_post_type($post_id) !== 'attachment') {
            throw new Exception(__('Invalid media file.', 'fmrseo'));
        }

        // Get original filename
        $file_path = get_attached_file($post_id);
        $original_filename = $file_path ? basename($file_path) : get_the_title($post_id);

        // Process single file with AI
        $ai_controller = new FMR_AI_Rename_Controller();
        $result = $ai_controller->rename_single_media($post_id);

        $response = array(
            'post_id' => $post_id,
            'batch_index' => $batch_index,
            'total_files' => $total_files,
            'progress' => round(($batch_index + 1) / $total_files * 100),
            'old_name' => $original_filename,
            'success' => $result['success'],
            'message' => $result['message'],
            'method' => 'ai'
        );

        if ($result['success']) {
            $response['new_name'] = $result['filename'];
            $response['credits_used'] = 1;
        }

        // Log performance metrics
        if (class_exists('FMR_Performance_Optimizer')) {
            $performance_optimizer = new FMR_Performance_Optimizer();
            $performance_optimizer->log_performance_metrics(
                'bulk_ai_rename_progressive',
                $start_time,
                array(
                    'post_id' => $post_id,
                    'batch_index' => $batch_index,
                    'success' => true
                )
            );
        }

        wp_send_json_success($response);

    } catch (Exception $e) {
        // Log security events for certain error types
        if (class_exists('FMR_Security_Manager')) {
            $security_manager = new FMR_Security_Manager();
            $error_message = $e->getMessage();
            
            if (strpos($error_message, 'Rate limit') !== false) {
                $security_manager->log_security_event('rate_limit_exceeded', array(
                    'operation' => 'bulk_ai_rename_progressive',
                    'error' => $error_message
                ));
            } elseif (strpos($error_message, 'Security') !== false || strpos($error_message, 'Nonce') !== false) {
                $security_manager->log_security_event('invalid_nonce', array(
                    'operation' => 'bulk_ai_rename_progressive',
                    'error' => $error_message
                ));
            } elseif (strpos($error_message, 'permission') !== false) {
                $security_manager->log_security_event('permission_denied', array(
                    'operation' => 'bulk_ai_rename_progressive',
                    'error' => $error_message
                ));
            }
        }

        // Log performance metrics for failed operations
        if (class_exists('FMR_Performance_Optimizer')) {
            $performance_optimizer = new FMR_Performance_Optimizer();
            $performance_optimizer->log_performance_metrics(
                'bulk_ai_rename_progressive_failed',
                $start_time,
                array(
                    'post_id' => isset($post_id) ? $post_id : 0,
                    'error' => $e->getMessage(),
                    'success' => false
                )
            );
        }

        wp_send_json_error(array(
            'post_id' => isset($post_id) ? $post_id : 0,
            'batch_index' => isset($batch_index) ? $batch_index : 0,
            'message' => $e->getMessage(),
            'method' => 'ai'
        ));
    }
}
add_action('wp_ajax_fmrseo_bulk_ai_rename_progressive', 'fmrseo_ajax_bulk_ai_rename_progressive');
