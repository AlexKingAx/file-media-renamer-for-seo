<?php

/**
 * Plugin Name: File Media Renamer for SEO
 * Description: Simple and speedy plug-in for help your SEO
 * Version: 0.7.0
 * Author: Alex 
 * Text Domain: fmrseo
 * Domain Path: /languages
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Upload the text domain
function fmrseo_load_textdomain()
{
    load_plugin_textdomain('fmrseo', false, dirname(plugin_basename(__FILE__)) . '/languages/');
}
add_action('plugins_loaded', 'fmrseo_load_textdomain');

// Include the settings file
require_once plugin_dir_path(__FILE__) . 'includes/class-fmr-seo-settings.php';
require_once plugin_dir_path(__FILE__) . 'includes/fmr-seo-redirects.php';
require_once plugin_dir_path(__FILE__) . 'includes/fmr-seo-bulk-rename.php';

// Autoloader for AI classes
function fmrseo_autoload_ai_classes($class_name)
{
    // Only handle FMR AI classes
    if (strpos($class_name, 'FMR_') !== 0) {
        return;
    }

    // Convert class name to file name
    $file_name = 'class-' . strtolower(str_replace('_', '-', $class_name)) . '.php';
    $file_path = plugin_dir_path(__FILE__) . 'includes/ai/' . $file_name;

    if (file_exists($file_path)) {
        require_once $file_path;
    }
}
spl_autoload_register('fmrseo_autoload_ai_classes');

// Initialize the settings
function fmrseo_init_settings()
{
    new File_Media_Renamer_SEO_Settings();

    // Initialize AI History Manager if AI functionality is available
    if (class_exists('FMR_AI_History_Manager')) {
        new FMR_AI_History_Manager();
    }

    // Initialize AI Dashboard Widget if AI functionality is available
    if (class_exists('FMR_AI_Dashboard_Widget')) {
        new FMR_AI_Dashboard_Widget();
    }

    // Initialize AI Statistics Page if AI functionality is available
    if (class_exists('FMR_AI_Statistics_Page')) {
        new FMR_AI_Statistics_Page();
    }

    // Initialize AI Help System
    if (class_exists('FMR_AI_Help_System')) {
        new FMR_AI_Help_System();
    }

    // Initialize AI Settings Extension for security and performance
    if (class_exists('FMR_AI_Settings_Extension')) {
        new FMR_AI_Settings_Extension();
    }

    // Initialize Maintenance Scheduler
    if (class_exists('FMR_Maintenance_Scheduler')) {
        new FMR_Maintenance_Scheduler();
    }
}
add_action('init', 'fmrseo_init_settings');

// Initialize AI functionality
function fmrseo_init_ai()
{
    // Only initialize AI if classes are available and we're in admin
    if (is_admin() && class_exists('FMR_AI_Rename_Controller') && class_exists('FMR_Error_Handler')) {
        try {
            new FMR_AI_Rename_Controller();
        } catch (Exception $e) {
            // Use error handler if available
            if (class_exists('FMR_Error_Handler')) {
                $error_handler = new FMR_Error_Handler();
                $error_handler->handle_error('system_error', $e, array(
                    'context' => 'ai_initialization',
                    'user_id' => get_current_user_id()
                ));
            } else {
                // Fallback to basic error logging
                error_log('FMR SEO AI initialization error: ' . $e->getMessage());
            }
        }
    }
}
add_action('init', 'fmrseo_init_ai');

// Add activation hook to initialize AI settings
function fmrseo_activate_ai()
{
    // Initialize default AI settings
    $options = get_option('fmrseo_options', array());

    // Set default AI options if not already set
    if (!isset($options['ai_enabled'])) {
        $options['ai_enabled'] = false; // Disabled by default
    }
    if (!isset($options['ai_timeout'])) {
        $options['ai_timeout'] = 30;
    }
    if (!isset($options['ai_max_retries'])) {
        $options['ai_max_retries'] = 2;
    }

    update_option('fmrseo_options', $options);
}
register_activation_hook(__FILE__, 'fmrseo_activate_ai');

/**
 * Add a settings link to the plugin action links.
 */
function frmseo_setting_link($links)
{
    // Add a settings link to the plugin action links
    $settings_link = '<a href="upload.php?page=fmrseo">' . __('Settings', 'fmrseo') . '</a>';
    // Add the settings link to the beginning of the links array
    array_unshift($links, $settings_link);

    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'frmseo_setting_link');


/**
 * Main function to handle plugin hooks and filters.
 */
function rename_media_seo_activation()
{
    // Register the filter to add the custom field
    add_filter('attachment_fields_to_edit', 'frmseo_add_image_seo_name_attachment_field_to_attachment_fields_to_edit', 10, 2);

    // Load the custom JS file
    add_action('admin_enqueue_scripts', 'frmseo_frmseo_enqueue_custom_admin_script');

    // Register the AJAX action to save the SEO name
    add_action('wp_ajax_save_seo_name', 'frmseo_save_seo_name_ajax');

    // Hook to execute the update function when the event is scheduled
    add_action('update_content_image_references_event', 'frmseo_update_content_image_references_background', 10, 4);

    // Make sure WP-Cron is active
    if (!wp_next_scheduled('wp_cron_hook')) {
        wp_schedule_event(time(), 'hourly', 'wp_cron_hook');
    }

    // Clean up scheduled events when they are no longer needed
    add_action('frmseo_clear_scheduled_update_content_image_references', 'frmseo_clear_scheduled_update_content_image_references');
}

// Hook to activate the main function
add_action('plugins_loaded', 'rename_media_seo_activation');

/**
 * Add a custom text field to the media attachment.
 *
 * @param array $form_fields The form fields.
 * @param object $post The post object.
 * @return array The modified form fields.
 */
function frmseo_add_image_seo_name_attachment_field_to_attachment_fields_to_edit($form_fields, $post)
{
    // Get the full name of the file
    $file_name_with_extension = basename(get_attached_file($post->ID));

    // Remove the extension from the file name
    $file_name = pathinfo($file_name_with_extension, PATHINFO_FILENAME);

    $image_seo_name = get_post_meta($post->ID, 'image_seo_name', true);

    // If the custom field is empty, use the file name as the default
    if (empty($image_seo_name)) {
        $image_seo_name = $file_name;
    }

    $form_fields['image_seo_name'] = array(
        'label' => __('SEO Name', 'fmrseo'),
        'input' => 'text',
        'value' => esc_attr($image_seo_name),
        'helps' => __('Change image file name and path for better SEO', 'fmrseo')
    );

    $form_fields['save_button'] = array(
        'input' => 'html',
        'html' => '<button id="save-seo-name" class="button" media-id="' . esc_attr($post->ID) . '">' . __('Save SEO Name', 'fmrseo') . '</button>',
        'label' => ''
    );

    // Add Undo button if there is history
    $history = get_post_meta($post->ID, '_fmrseo_rename_history', true);
    if (is_array($history) && count($history) > 0) {

        // building the list of last two versions
        $ul  = '<ol id="history-fmrseo" style="margin:0;margin-top:.5rem;padding-left:4%;">';
        foreach ($history as $version) {
            // esc_html per sicurezza
            $ul .= '<li style="cursor: pointer; width: fit-content;" onmouseover="this.style.color=\'#EB8B47\'" onmouseout="this.style.color=\'\'" media-id="' . esc_attr($post->ID) . '">' . esc_html($version['seo_name']) . '</li>';
        }
        $ul .= '</ol>';

        // Add the history to the form fields
        $form_fields['fmrseo_rename_history'] = array(
            'label' => __('SEO name history', 'fmrseo'),
            'input' => 'html',
            'html'  => $ul,
        );

        // $form_fields['undo_button'] = array(
        //     'input' => 'html',
        //     'html' => '<button id="undo-seo-rename" class="button" media-id="' . esc_attr($post->ID) . '">' . __('Undo Rename', 'fmrseo') . '</button>',
        //     'label' => ''
        // );
    }

    return $form_fields;
}

/**
 * Enqueue the plug-in script.
 */
function frmseo_frmseo_enqueue_custom_admin_script()
{
    wp_enqueue_script('jquery');
    wp_enqueue_script('rename-media', plugin_dir_url(__FILE__) . 'assets/js/rename-media.js', array('jquery'), null, true);
    wp_localize_script('rename-media', 'renameMedia', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('save_seo_name_nonce') // pass nonce
    ));
}

/**
 * Reusable function to rename media file and thumbnails.
 *
 * @param int $post_id
 * @param string $seo_name
 * @param bool $is_restore
 * @return array
 * @throws Exception
 */
function fmrseo_rename_media_file($post_id, $seo_name, $is_restore = false)
{
    error_log("miao");
    error_log("Is_____restore: " . ($is_restore ? 'true' : 'false'));
    $file_path = get_attached_file($post_id);

    if (!$file_path) {
        throw new Exception(__('File path not found.', 'fmrseo'));
    }

    $file_dir = pathinfo($file_path, PATHINFO_DIRNAME);
    $file_ext = pathinfo($file_path, PATHINFO_EXTENSION);
    $new_file_path = trailingslashit($file_dir) . $seo_name . '.' . $file_ext;

    if (!file_exists($file_path)) {
        throw new Exception(__('Original file does not exist.', 'fmrseo'));
    }

    $unique = null;
    // Check if the new file path is the same as the current file path
    if (file_exists($new_file_path) && $file_path !== $new_file_path) {
        // If the new file already exists and if not restoring, return an exception
        if (!$is_restore) throw new Exception(__('A file with the new name and the same file extension already exists. Change it or add a number!', 'fmrseo'));
        else {
            $unique = wp_unique_filename($file_dir, $seo_name . '.' . $file_ext);
            $new_file_path = trailingslashit($file_dir) . $unique;

            // Update the SEO name based on the unique filename generated
            $seo_name = pathinfo($unique, PATHINFO_FILENAME);
        }
    }

    // Rename file if needed
    if ($file_path !== $new_file_path) {
        if (!rename($file_path, $new_file_path)) {
            throw new Exception(__('Failed to rename the file.', 'fmrseo'));
        }
    }

    $wp_upload_dir = wp_upload_dir();
    $old_file_url = str_replace($wp_upload_dir['basedir'], $wp_upload_dir['baseurl'], $file_path);
    $new_file_url = str_replace($wp_upload_dir['basedir'], $wp_upload_dir['baseurl'], $new_file_path);

    update_attached_file($post_id, $new_file_path);

    // redirect full size image
    fmrseo_add_redirect($old_file_url, $new_file_url);

    // Rename thumbnails
    $metadata = wp_get_attachment_metadata($post_id);
    if (!empty($metadata['sizes'])) {
        foreach ($metadata['sizes'] as $size => $size_data) {
            $old_thumbnail_path = trailingslashit($file_dir) . $size_data['file'];
            $thumbnail_ext = pathinfo($size_data['file'], PATHINFO_EXTENSION);
            $thumbnail_name = $seo_name . '-' . $size_data['width'] . 'x' . $size_data['height'] . '.' . $thumbnail_ext;
            $new_thumbnail_path = trailingslashit($file_dir) . $thumbnail_name;

            if (file_exists($old_thumbnail_path) && $old_thumbnail_path !== $new_thumbnail_path) {
                rename($old_thumbnail_path, $new_thumbnail_path);

                $old_thumbnail_url = str_replace($wp_upload_dir['basedir'], $wp_upload_dir['baseurl'], $old_thumbnail_path);
                $new_thumbnail_url = str_replace($wp_upload_dir['basedir'], $wp_upload_dir['baseurl'], $new_thumbnail_path);

                fmrseo_add_redirect($old_thumbnail_url, $new_thumbnail_url);

                $metadata['sizes'][$size]['file'] = $thumbnail_name;
                frmseo_schedule_update_content_image_references($old_thumbnail_url, $new_thumbnail_url, $seo_name, null);
            }
        }
    }

    // Update the attachment metadata
    wp_update_attachment_metadata($post_id, $metadata);

    // Schedule background refresh for full-size image
    frmseo_schedule_update_content_image_references($old_file_url, $new_file_url, $seo_name, $post_id);

    if ($unique) {
        return [
            'old_file_path' => $file_path,
            'old_file_url'  => $old_file_url,
            'new_file_path' => $new_file_path,
            'new_file_url'  => $new_file_url,
            'file_ext'      => $file_ext,
            'seo_name'      => $seo_name,
        ];
    }

    return [
        'old_file_path' => $file_path,
        'old_file_url'  => $old_file_url,
        'new_file_path' => $new_file_path,
        'new_file_url'  => $new_file_url,
        'file_ext'      => $file_ext,
    ];
}

/**
 * Wrapper function for complete rename process (reusable)
 */
function fmrseo_complete_rename_process($post_id, $seo_name, $is_restore = false)
{
    $history = get_post_meta($post_id, '_fmrseo_rename_history', true);
    $restore = $is_restore;

    if (!is_array($history)) $history = [];
    elseif (count($history) > 0 && !$is_restore) {
        // Check if the new name is already in history
        foreach ($history as $version) {
            if ($version['seo_name'] === $seo_name) {
                $restore = true;
                break;
            }
        }
    }

    // If not restoring, sanitize the SEO name
    if (!$restore) $seo_name = sanitize_title($seo_name);

    // Use the rename function
    $result = fmrseo_rename_media_file($post_id, $seo_name, $restore);

    // Update the post name to match the new SEO name if unique role is used
    if (isset($result['seo_name'])) {
        $seo_name = $result['seo_name'];
    }

    // save image_seo_name Custom Field of the plugin
    update_post_meta($post_id, 'image_seo_name', $seo_name);

    // --- Begin: Save rename history ---
    // Add current file info to history before renaming
    array_unshift($history, [
        'file_path' => $result['old_file_path'],
        'file_url'  => $result['old_file_url'],
        'seo_name'  => basename($result['old_file_path'], '.' . $result['file_ext']),
        'timestamp' => time(),
    ]);

    // Keep only the last 2 versions (will be extended by AI history manager)
    $history = array_slice($history, 0, 2);
    update_post_meta($post_id, '_fmrseo_rename_history', $history);
    // --- End: Save rename history ---

    // Fire action for comprehensive history tracking
    do_action('fmrseo_after_rename', $post_id, $result, array('method' => 'manual'));

    return $result;
}

/**
 * Save the SEO name via AJAX.
 */
function frmseo_save_seo_name_ajax()
{
    try {
        if (!isset($_POST['_ajax_nonce']) || !wp_verify_nonce($_POST['_ajax_nonce'], 'save_seo_name_nonce')) {
            throw new Exception(__('Nonce verification failed.', 'fmrseo'));
        }

        $post_id = intval($_POST['post_id']);
        $seo_name = sanitize_text_field($_POST['seo_name']);
        $seo_name = sanitize_file_name($seo_name);

        if (!$post_id || !$seo_name) {
            throw new Exception(__('File path not found.', 'fmrseo'));
        }

        // Use the wrapper function
        $result = fmrseo_complete_rename_process($post_id, $seo_name);

        // Get final seo_name in case it was modified
        $final_seo_name = isset($result['seo_name']) ? $result['seo_name'] : pathinfo($result['new_file_path'], PATHINFO_FILENAME);

        wp_send_json_success([
            'message'  => __('File and thumbnails renamed successfully.', 'fmrseo'),
            'url'      => $result['new_file_url'],
            'filename' => $final_seo_name . '.' . $result['file_ext']
        ]);
    } catch (Exception $e) {
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}

/**
 * Update content image references in the background.
 *
 * @param string $old_url The old media URL.
 * @param string $new_url The new media URL.
 * @param string $seo_name The SEO name.
 */
function frmseo_update_content_image_references_background($old_url, $new_url, $seo_name, $post_id)
{
    global $wpdb;
    error_log("POST");
    error_log(json_encode($post_id));

    // Update post_name, and metadata if post_id is provided
    if ($post_id) {

        $post_data = get_post($post_id);

        // Aggiorna il post_name e il guid
        wp_update_post([
            'ID' => $post_id,
            'post_name' => $seo_name,
        ]);


        // Get and update attachment metadata
        $wp_attachment_metadata = get_post_meta($post_id, '_wp_attachment_metadata', true);
        if (!empty($wp_attachment_metadata['file'])) {
            $file_extension = pathinfo($wp_attachment_metadata['file'], PATHINFO_EXTENSION);
            $wp_attachment_metadata['file'] = str_replace(basename($wp_attachment_metadata['file']), $seo_name . '.' . $file_extension, $wp_attachment_metadata['file']);
        }

        // Log updated metadata
        error_log("attached_metadata updated");
        error_log(json_encode($wp_attachment_metadata));

        update_post_meta($post_id, '_wp_attachment_metadata', $wp_attachment_metadata);

        // Log updated post data
        error_log("POST DATA updated");
        error_log(json_encode(get_post($post_id)));

        // Retrieve saved settings
        $options = get_option('fmrseo_options');

        // if rename_title or rename_alt_text options are set to true, create a readable SEO name and update the post title and alt text
        // This is useful for SEO purposes, as it makes the title and alt text more readable for crawlers and users
        $should_rename_title = !empty($options['rename_title']);
        $should_rename_alt_text = !empty($options['rename_alt_text']);

        if ($should_rename_title || $should_rename_alt_text) {
            $readable_seo_name = str_replace(['-', '_'], ' ', $seo_name);
            $readable_seo_name = ucfirst(trim($readable_seo_name));

            // Update post title if option is set
            if ($should_rename_title) {
                wp_update_post(['ID' => $post_id, 'post_title' => $readable_seo_name]);
            }

            // Update alt text if option is set
            if ($should_rename_alt_text) {
                update_post_meta($post_id, '_wp_attachment_image_alt', $readable_seo_name);
            }
        }


        clean_post_cache($post_data->ID);
        wp_cache_delete($post_data->post_name, 'posts');
    }

    error_log('Test di logging: questa è una prova!');

    $old_url_escaped = '%' . $wpdb->esc_like($old_url) . '%';

    // Search for the old URL in post_content of wp_posts
    $sql = "SELECT ID, post_content FROM {$wpdb->posts} WHERE
                post_content LIKE '{$old_url_escaped}'";

    error_log($sql);

    $posts = $wpdb->get_results($sql);

    error_log(json_encode($posts));

    foreach ($posts as $post) {
        $updated_content = frmseo_update_serialized_data($post->post_content, $old_url, $new_url);

        $wpdb->update(
            $wpdb->posts,
            ['post_content' => $updated_content],
            ['ID' => $post->ID],
            ['%s'],
            ['%d']
        );

        clean_post_cache($post->ID);
        wp_cache_delete($post->ID, 'posts');
    }

    $query_meta = "SELECT meta_id, post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE meta_value LIKE '{$old_url_escaped}'";

    error_log("query_meta");
    error_log($query_meta);

    $postmeta = $wpdb->get_results($query_meta);

    error_log(json_encode($postmeta));

    foreach ($postmeta as $meta) {
        $updated_meta_value = frmseo_update_serialized_data($meta->meta_value, $old_url, $new_url);
        error_log($meta->meta_value);
        error_log($updated_meta_value);

        $wpdb->update(
            $wpdb->postmeta,
            ['meta_value' => $updated_meta_value],
            ['meta_id' => $meta->meta_id],
            ['%s'],
            ['%d']
        );

        clean_post_cache($meta->post_id);
        wp_cache_delete($meta->post_id, 'postmeta');
    }
}

/**
 * Schedule the update of content image references.
 *
 * @param string $old_url The old media URL.
 * @param string $new_url The new media URL.
 * @param string $seo_name The SEO name.
 */
function frmseo_schedule_update_content_image_references($old_url, $new_url, $seo_name, $post_id)
{
    if (!wp_next_scheduled('update_content_image_references_event')) {
        wp_schedule_single_event(time(), 'update_content_image_references_event', [$old_url, $new_url, $seo_name, $post_id]);
    }
}

/**
 * Clear scheduled update content image references.
 */
function frmseo_clear_scheduled_update_content_image_references()
{
    $timestamp = wp_next_scheduled('update_content_image_references_event');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'update_content_image_references_event');
    }
}

/**
 * Update serialized data.
 *
 * @param mixed $data The data to update.
 * @param string $old_value The old value.
 * @param string $new_value The new value.
 * @return mixed The updated data.
 */
function frmseo_update_serialized_data($data, $old_value, $new_value)
{
    if (is_serialized($data)) {
        $unserialized_data = unserialize($data);

        $unserialized_data = frmseo_update_value_in_array($unserialized_data, $old_value, $new_value);

        $data = serialize($unserialized_data);
    } else {
        $data = str_replace($old_value, $new_value, $data);
    }

    return $data;
}

/**
 * Recursively update value in array.
 *
 * @param array $array The array to update.
 * @param string $old_value The old media value.
 * @param string $new_value The new media value.
 * @return array The updated array.
 */
function frmseo_update_value_in_array($array, $old_value, $new_value)
{
    foreach ($array as $key => $value) {
        if (is_array($value)) {
            $array[$key] = frmseo_update_value_in_array($value, $old_value, $new_value);
        } elseif (is_string($value)) {
            $array[$key] = str_replace($old_value, $new_value, $value);
        }
    }
    return $array;
}

/**
 * Create the custom redirects table on plugin activation.
 */
function fmrseo_create_redirects_table()
{
    global $wpdb;

    $table_name = $wpdb->prefix . 'fmrseo_redirects';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        old_url TEXT NOT NULL,
        new_url TEXT NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY old_url (old_url(255))
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'fmrseo_create_redirects_table');

/**
 * Drop the custom redirects table on plugin deactivation.
 */
function fmrseo_drop_redirects_table()
{
    global $wpdb;

    $table_name = $wpdb->prefix . 'fmrseo_redirects';
    $wpdb->query("DROP TABLE IF EXISTS $table_name");
}
register_deactivation_hook(__FILE__, 'fmrseo_drop_redirects_table');

// /**
//  * AJAX handler to undo the last rename (revert to previous version).
//  */
// function fmrseo_undo_last_rename_ajax() {
//     try {
//         if (!isset($_POST['_ajax_nonce']) || !wp_verify_nonce($_POST['_ajax_nonce'], 'save_seo_name_nonce')) {
//             throw new Exception(__('Nonce verification failed.', 'fmrseo'));
//         }
//         $post_id = intval($_POST['post_id']);
//         if (!$post_id) throw new Exception(__('Invalid media ID.', 'fmrseo'));

//         $history = get_post_meta($post_id, '_fmrseo_rename_history', true);
//         if (!is_array($history) || count($history) < 1) {
//             throw new Exception(__('No previous version to revert to.', 'fmrseo'));
//         }
//         $previous = array_shift($history); // Get the most recent previous version

//         $current_file_path = get_attached_file($post_id);
//         $current_file_url = str_replace(wp_upload_dir()['basedir'], wp_upload_dir()['baseurl'], $current_file_path);

//         // Rename current file back to previous
//         if (!file_exists($previous['file_path'])) {
//             // If the previous file does not exist, try to rename current to previous
//             if (!rename($current_file_path, $previous['file_path'])) {
//                 throw new Exception(__('Failed to revert file name.', 'fmrseo'));
//             }
//         } else {
//             // If previous file exists, swap files (optional: could delete current)
//             @unlink($current_file_path);
//         }

//         update_attached_file($post_id, $previous['file_path']);

//         // Update post meta and title if needed
//         update_post_meta($post_id, 'image_seo_name', $previous['seo_name']);
//         $options = get_option('fmrseo_options');
//         if (isset($options['rename_title']) && $options['rename_title'] == 1) {
//             wp_update_post(['ID' => $post_id, 'post_title' => $previous['seo_name']]);
//         }
//         if (isset($options['rename_alt_text']) && $options['rename_alt_text'] == 1) {
//             update_post_meta($post_id, '_wp_attachment_image_alt', $previous['seo_name']);
//         }

//         // Remove this version from history and update
//         update_post_meta($post_id, '_fmrseo_rename_history', $history);

//         // Add redirect from current to previous
//         fmrseo_add_redirect($current_file_url, $previous['file_url']);

//         wp_send_json_success([
//             'message' => __('Reverted to previous version.', 'fmrseo'),
//             'url'     => $previous['file_url'],
//             'filename'=> basename($previous['file_path']),
//         ]);
//     } catch (Exception $e) {
//         wp_send_json_error(['message' => $e->getMessage()]);
//     }
// }
// add_action('wp_ajax_fmrseo_undo_last_rename', 'fmrseo_undo_last_rename_ajax');
