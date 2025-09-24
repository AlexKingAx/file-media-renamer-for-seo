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

?>
    <div id="fmrseo-bulk-rename-modal" style="display: none;">
        <div class="fmrseo-modal-content">
            <div class="fmrseo-modal-header">
                <h2><?php esc_html_e('Rename Selected Media', 'fmrseo'); ?></h2>
                <span class="fmrseo-close">&times;</span>
            </div>
            <div class="fmrseo-modal-body">
                <p><?php  // translators: selected post's number is displayed here  
                printf(esc_html__('You have selected %d files to rename.', 'fmrseo'), count($post_ids)); ?></p>
                <div class="fmrseo-form-group">
                    <label for="fmrseo-bulk-name"><?php esc_html_e('Base name:', 'fmrseo'); ?></label>
                    <input type="text" id="fmrseo-bulk-name" placeholder="<?php esc_html_e('e.g: new name', 'fmrseo'); ?>" />
                    <p class="description"><?php esc_html_e('Files will be renamed as: new-name-1, new-name-2, etc.', 'fmrseo'); ?></p>
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
                <button type="button" class="button button-secondary" id="fmrseo-cancel-bulk"><?php esc_html_e('Cancel', 'fmrseo'); ?></button>
                <button type="button" class="button button-primary" id="fmrseo-start-bulk"><?php esc_html_e('Start Rename', 'fmrseo'); ?></button>
                <button type="button" class="button button-primary" id="fmrseo-close-bulk" style="display: none;" disabled="true">
    <?php esc_html_e('Close', 'fmrseo'); ?>
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
            'completed' => __('Rename completed!', 'fmrseo'),
            'error' => __('Error during rename', 'fmrseo'),
            'success' => __('File renamed successfully', 'fmrseo'),
            'failed' => __('Error renaming file', 'fmrseo')
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

            // Get final seo_name in case it was modified
            $final_seo_name = isset($result['seo_name']) ? $result['seo_name'] : pathinfo($result['new_file_path'], PATHINFO_FILENAME);

            $results[] = array(
                'success' => true,
                'post_id' => $post_id,
                'old_name' => basename($result['old_file_path']),
                'new_name' => $final_seo_name . '.' . $result['file_ext'],
                'message' => __('File renamed successfully', 'fmrseo')
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
            throw new Exception(__('Security verification failed.', 'fmrseo'));
        }

        // Check permissions
        if (!current_user_can('upload_files')) {
            throw new Exception(__('Insufficient permissions.', 'fmrseo'));
        }

        $post_ids = array();
        if (isset($_POST['post_ids']) && is_array($_POST['post_ids'])) {
            $post_ids = array_map('intval', wp_unslash($_POST['post_ids']));
        }

        $base_name = isset($_POST['base_name']) ? sanitize_file_name(wp_unslash($_POST['base_name'])) : '';

        if (empty($post_ids) || empty($base_name)) {
            throw new Exception(__('Missing parameters.', 'fmrseo'));
        }

        // Validate base name
        if (!preg_match('/^[a-zA-Z0-9\-_]+$/', $base_name)) {
            throw new Exception(__('Base name can only contain letters, numbers, hyphens and underscores.', 'fmrseo'));
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

        // Process bulk rename
        $results = fmrseo_bulk_rename_media_files($post_ids, $base_name);

        wp_send_json_success($results);
    } catch (Exception $e) {
        wp_send_json_error(array('message' => $e->getMessage()));
    }
}
add_action('wp_ajax_fmrseo_bulk_rename', 'fmrseo_ajax_bulk_rename');
