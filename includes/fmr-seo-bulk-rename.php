<?php
/**========================================================
 * Bulk rename functionality for File Media Renamer for SEO
 **========================================================*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Add bulk action to media library
 */
function fmrseo_add_bulk_rename_action($bulk_actions)
{
    $bulk_actions['fmrseo_bulk_rename'] = esc_html__('Rename', 'file-media-renamer-for-seo');
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
    $modal_nonce = wp_create_nonce('fmrseo_bulk_rename_modal');

    return add_query_arg(
        array(
            'fmrseo_bulk_rename' => '1',
            'fmrseo_bulk_rename_nonce' => $modal_nonce,
        ),
        $redirect_to
    );
}
add_filter('handle_bulk_actions-upload', 'fmrseo_handle_bulk_rename', 10, 3);

/**
 * Display bulk rename modal
 */
function fmrseo_display_bulk_rename_modal()
{
    $bulk_flag = '';
    if (isset($_GET['fmrseo_bulk_rename'])) {
        $bulk_flag = sanitize_text_field(wp_unslash($_GET['fmrseo_bulk_rename']));
    }

    if ('1' !== $bulk_flag) {
        return;
    }

    $modal_nonce = '';
    if (isset($_GET['fmrseo_bulk_rename_nonce'])) {
        $modal_nonce = sanitize_text_field(wp_unslash($_GET['fmrseo_bulk_rename_nonce']));
    }

    if (empty($modal_nonce) || !wp_verify_nonce($modal_nonce, 'fmrseo_bulk_rename_modal')) {
        return;
    }

    $post_ids = get_transient('fmrseo_bulk_rename_ids');
    if (!$post_ids || !is_array($post_ids)) {
        return;
    }

    $ai_settings = function_exists('fmrseo_get_ai_settings') ? fmrseo_get_ai_settings() : array();
    $ai_enabled = !empty($ai_settings['enabled']);
    $ai_key_set = !empty($ai_settings['api_key']);
    $ai_ready = $ai_enabled && $ai_key_set;
    $ai_delay = isset($ai_settings['delay']) ? (float) $ai_settings['delay'] : 2;
    $ai_max_files = isset($ai_settings['max_files']) ? absint($ai_settings['max_files']) : 500;
    if ($ai_delay < 0) {
        $ai_delay = 0;
    }

    $ai_notice = '';
    if (!$ai_enabled) {
        $ai_notice = esc_html__('AI rename is disabled in plugin settings.', 'file-media-renamer-for-seo');
    } elseif (!$ai_key_set) {
        $ai_notice = esc_html__('Set your OpenAI API key in AI Rename settings to enable Batch AI Rename.', 'file-media-renamer-for-seo');
    }

?>
    <div id="fmrseo-bulk-rename-modal" style="display: none;">
        <div class="fmrseo-modal-content">
            <div class="fmrseo-modal-header">
                <h2><?php esc_html_e('Rename Selected Media', 'file-media-renamer-for-seo'); ?></h2>
                <span class="fmrseo-close">&times;</span>
            </div>
            <div class="fmrseo-modal-body">
                <p><?php
                /* translators: %d is the number of selected media files. */
                printf(esc_html__('You have selected %d files to rename.', 'file-media-renamer-for-seo'), count($post_ids)); ?></p>
                <div class="fmrseo-form-group">
                    <label for="fmrseo-bulk-name"><?php esc_html_e('Base name:', 'file-media-renamer-for-seo'); ?></label>
                    <input type="text" id="fmrseo-bulk-name" placeholder="<?php esc_html_e('e.g: new name', 'file-media-renamer-for-seo'); ?>" />
                    <p class="description"><?php esc_html_e('Files will be renamed as: new-name-1, new-name-2, etc.', 'file-media-renamer-for-seo'); ?></p>
                </div>
                <div class="fmrseo-form-group">
                    <p class="description">
                        <?php
                        /* translators: %s is the configured AI request delay in seconds. */
                        printf(
                            esc_html__('Batch AI Rename delay: %s seconds between requests.', 'file-media-renamer-for-seo'),
                            esc_html((string) $ai_delay)
                        );
                        ?>
                    </p>
                    <p class="description">
                        <?php
                        if ($ai_max_files > 0) {
                            /* translators: %s is the configured max files limit. */
                            printf(
                                esc_html__('Batch AI Rename max files: %s.', 'file-media-renamer-for-seo'),
                                esc_html(number_format_i18n($ai_max_files))
                            );
                        } else {
                            esc_html_e('Batch AI Rename max files: unlimited.', 'file-media-renamer-for-seo');
                        }
                        ?>
                    </p>
                    <?php if (!empty($ai_notice)) : ?>
                        <p class="description"><?php echo esc_html($ai_notice); ?></p>
                    <?php endif; ?>
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
                <button type="button" class="button button-secondary" id="fmrseo-cancel-bulk"><?php esc_html_e('Cancel', 'file-media-renamer-for-seo'); ?></button>
                <button type="button" class="button button-primary fmrseo-ai-bulk-button" id="fmrseo-start-bulk-ai" title="<?php echo esc_attr__('Automatically rename selected files with AI', 'file-media-renamer-for-seo'); ?>" <?php disabled(!$ai_ready); ?>><span class="dashicons dashicons-superhero" aria-hidden="true"></span><span><?php esc_html_e('Batch AI Rename', 'file-media-renamer-for-seo'); ?></span></button>
                <button type="button" class="button button-primary fmrseo-manual-bulk-button" id="fmrseo-start-bulk" title="<?php echo esc_attr__('Rename selected files with manual base name', 'file-media-renamer-for-seo'); ?>"><span class="dashicons dashicons-edit" aria-hidden="true"></span><span><?php esc_html_e('Start Rename', 'file-media-renamer-for-seo'); ?></span></button>
                <button type="button" class="button button-primary" id="fmrseo-close-bulk" style="display: none;" disabled="true">
    <?php esc_html_e('Close', 'file-media-renamer-for-seo'); ?>
</button>

            </div>
        </div>
    </div>

    <script type="text/javascript">
        var fmrseoBulkRenameIds = <?php echo wp_json_encode($post_ids); ?>;
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

    $ai_settings = function_exists('fmrseo_get_ai_settings') ? fmrseo_get_ai_settings() : array();
    $ai_delay = isset($ai_settings['delay']) ? (float) $ai_settings['delay'] : 2;
    if ($ai_delay < 0) {
        $ai_delay = 0;
    }
    if ($ai_delay > 60) {
        $ai_delay = 60;
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
        'ai_nonce' => wp_create_nonce('fmrseo_bulk_ai_rename_nonce'),
        'ai_enabled' => !empty($ai_settings['enabled']),
        'ai_key_set' => !empty($ai_settings['api_key']),
        'ai_delay_ms' => (int) ($ai_delay * 1000),
        'ai_batch_size' => 1,
        'ai_max_files' => isset($ai_settings['max_files']) ? absint($ai_settings['max_files']) : 500,
        'strings' => array(
            'processing' => esc_html__('Processing...', 'file-media-renamer-for-seo'),
            'completed' => esc_html__('Rename completed!', 'file-media-renamer-for-seo'),
            'error' => esc_html__('Error during rename', 'file-media-renamer-for-seo'),
            'success' => esc_html__('File renamed successfully', 'file-media-renamer-for-seo'),
            'failed' => esc_html__('Error renaming file', 'file-media-renamer-for-seo'),
            'results' => esc_html__('Results:', 'file-media-renamer-for-seo'),
            'error_prefix' => esc_html__('Error:', 'file-media-renamer-for-seo'),
            'cancelled' => esc_html__('Process cancelled.', 'file-media-renamer-for-seo'),
            'cancelling' => esc_html__('Cancelling...', 'file-media-renamer-for-seo'),
            'missing_base_name' => esc_html__('Please enter a base name for the files.', 'file-media-renamer-for-seo'),
            /* translators: %d is the number of selected files. */
            'confirm_manual' => esc_html__('Are you sure you want to rename %d files?', 'file-media-renamer-for-seo'),
            /* translators: %d is the number of selected files. */
            'confirm_ai' => esc_html__('Are you sure you want to generate AI names for %d files?', 'file-media-renamer-for-seo'),
            'ai_unavailable' => esc_html__('Set your OpenAI API key and enable AI Rename in settings first.', 'file-media-renamer-for-seo'),
            'ai_limit_reached' => esc_html__('Batch AI Rename limit exceeded. Reduce selected files or increase max files in settings.', 'file-media-renamer-for-seo')
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
            if (is_wp_error($result)) {
                throw new Exception($result->get_error_message());
            }

            // Get final seo_name in case it was modified
            $final_seo_name = isset($result['seo_name']) ? $result['seo_name'] : pathinfo($result['new_file_path'], PATHINFO_FILENAME);

            $results[] = array(
                'success' => true,
                'post_id' => $post_id,
                'old_name' => basename($result['old_file_path']),
                'new_name' => $final_seo_name . '.' . $result['file_ext'],
                'message' => esc_html__('File renamed successfully', 'file-media-renamer-for-seo')
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
 * AJAX handler for bulk rename
 */
function fmrseo_ajax_bulk_rename()
{
    try {
        // Verify nonce
        if (!check_ajax_referer('fmrseo_bulk_rename_nonce', 'nonce', false)) {
            throw new Exception(esc_html__('Security verification failed.', 'file-media-renamer-for-seo'));
        }

        // Check permissions
        if (!current_user_can('upload_files')) {
            throw new Exception(esc_html__('Insufficient permissions.', 'file-media-renamer-for-seo'));
        }

        $post_ids = array();
        if (isset($_POST['post_ids']) && is_array($_POST['post_ids'])) {
            $post_ids = array_map('intval', wp_unslash($_POST['post_ids']));
        }

        $base_name = isset($_POST['base_name']) ? sanitize_file_name(wp_unslash($_POST['base_name'])) : '';

        if (empty($post_ids) || empty($base_name)) {
            throw new Exception(esc_html__('Missing parameters.', 'file-media-renamer-for-seo'));
        }

        // Validate base name
        if (!preg_match('/^[a-zA-Z0-9\-_]+$/', $base_name)) {
            throw new Exception(esc_html__('Base name can only contain letters, numbers, hyphens and underscores.', 'file-media-renamer-for-seo'));
        }

        // Limit number of files to prevent timeout
        if (count($post_ids) > 50) {
            throw new Exception(esc_html__('You can rename maximum 50 files at once.', 'file-media-renamer-for-seo'));
        }

        // Verify all IDs are valid attachments
        foreach ($post_ids as $post_id) {
            if (get_post_type($post_id) !== 'attachment') {
                throw new Exception(esc_html__('One or more IDs are not valid media files.', 'file-media-renamer-for-seo'));
            }
        }

        // Process bulk rename
        $results = fmrseo_bulk_rename_media_files($post_ids, $base_name);

        wp_send_json_success($results);
    } catch (Exception $e) {
        wp_send_json_error(array('message' => $e->getMessage()));
    }
}
add_action('wp_ajax_fmrseo_bulk_rename', 'fmrseo_ajax_bulk_rename');

/**
 * Processes one step of Batch AI Rename.
 *
 * @param array $post_ids   Attachment IDs.
 * @param int   $offset     Current offset.
 * @param int   $batch_size Items to process in this step.
 *
 * @return array
 */
function fmrseo_bulk_ai_rename_media_files_step($post_ids, $offset, $batch_size)
{
    $total = count($post_ids);
    $offset = max(0, (int) $offset);
    $batch_size = max(1, min(5, (int) $batch_size));

    $slice = array_slice($post_ids, $offset, $batch_size);
    $results = array();

    foreach ($slice as $post_id) {
        $result = fmrseo_ai_rename_attachment($post_id);

        if (is_wp_error($result)) {
            $results[] = array(
                'success' => false,
                'post_id' => $post_id,
                'message' => $result->get_error_message(),
            );
            continue;
        }

        $final_seo_name = isset($result['seo_name']) ? $result['seo_name'] : pathinfo($result['new_file_path'], PATHINFO_FILENAME);

        $results[] = array(
            'success' => true,
            'post_id' => $post_id,
            'old_name' => basename($result['old_file_path']),
            'new_name' => $final_seo_name . '.' . $result['file_ext'],
            'message' => esc_html__('File renamed successfully', 'file-media-renamer-for-seo'),
        );
    }

    $processed = count($slice);
    $next_offset = $offset + $processed;

    return array(
        'results' => $results,
        'processed' => $processed,
        'offset' => $offset,
        'next_offset' => $next_offset,
        'total' => $total,
        'done' => ($next_offset >= $total),
    );
}

/**
 * AJAX handler for iterative Batch AI Rename.
 */
function fmrseo_ajax_bulk_ai_rename_step()
{
    try {
        if (!check_ajax_referer('fmrseo_bulk_ai_rename_nonce', 'nonce', false)) {
            throw new Exception(esc_html__('Security verification failed.', 'file-media-renamer-for-seo'));
        }

        if (!current_user_can('upload_files')) {
            throw new Exception(esc_html__('Insufficient permissions.', 'file-media-renamer-for-seo'));
        }

        $ai_settings = function_exists('fmrseo_get_ai_settings') ? fmrseo_get_ai_settings() : array();
        if (empty($ai_settings['enabled'])) {
            throw new Exception(esc_html__('AI rename is disabled in plugin settings.', 'file-media-renamer-for-seo'));
        }
        if (empty($ai_settings['api_key'])) {
            throw new Exception(esc_html__('OpenAI API key is not configured.', 'file-media-renamer-for-seo'));
        }

        $post_ids = array();
        if (isset($_POST['post_ids']) && is_array($_POST['post_ids'])) {
            $post_ids = array_map('intval', wp_unslash($_POST['post_ids']));
        }

        $offset = isset($_POST['offset']) ? intval(wp_unslash($_POST['offset'])) : 0;
        $batch_size = isset($_POST['batch_size']) ? intval(wp_unslash($_POST['batch_size'])) : 1;

        if (empty($post_ids)) {
            throw new Exception(esc_html__('Missing parameters.', 'file-media-renamer-for-seo'));
        }

        $ai_max_files = isset($ai_settings['max_files']) ? absint($ai_settings['max_files']) : 500;
        if ($ai_max_files > 0 && count($post_ids) > $ai_max_files) {
            // translators: %d is max files allowed in one AI batch.
            throw new Exception(sprintf(esc_html__('You can rename maximum %d files in one Batch AI Rename.', 'file-media-renamer-for-seo'), $ai_max_files));
        }

        foreach ($post_ids as $post_id) {
            if (get_post_type($post_id) !== 'attachment') {
                throw new Exception(esc_html__('One or more IDs are not valid media files.', 'file-media-renamer-for-seo'));
            }
        }

        $step_results = fmrseo_bulk_ai_rename_media_files_step($post_ids, $offset, $batch_size);
        wp_send_json_success($step_results);
    } catch (Exception $e) {
        wp_send_json_error(array('message' => $e->getMessage()));
    }
}
add_action('wp_ajax_fmrseo_bulk_ai_rename_step', 'fmrseo_ajax_bulk_ai_rename_step');
