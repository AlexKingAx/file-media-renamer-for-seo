<?php
/**
 * Plugin Name: File Media Renamer for SEO
 * Plugin URI: https://filemediarenamerwp.com/
 * Description: A lightweight, fast plugin that improves SEO and streamlines your media workflow.
 * Version: 1.0.2
 * Author: alexwebitaly
 * Author URI: https://alex-web.it/
 * Developer: alexwebitaly
 * Developer URI: https://alex-web.it/
 * Text Domain: file-media-renamer-for-seo
 * Domain Path: /languages
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

if (! defined('ABSPATH')) {
    exit;
}

// Include the settings file
require_once plugin_dir_path(__FILE__) . 'includes/class-fmr-seo-settings.php';
require_once plugin_dir_path(__FILE__) . 'includes/fmr-seo-redirects.php';
require_once plugin_dir_path(__FILE__) . 'includes/fmr-seo-bulk-rename.php';


define('FMRSEO_VERSION', '1.0.0');

/**
 * Ensure the WordPress filesystem API is available.
 *
 * @return WP_Filesystem_Base|WP_Error
 */
function fmrseo_get_filesystem()
{
    global $wp_filesystem;

    if (!function_exists('WP_Filesystem')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }

    if (!is_object($wp_filesystem)) {
        WP_Filesystem();
    }

    if (is_object($wp_filesystem)) {
        return $wp_filesystem;
    }

    return new WP_Error('fmrseo_filesystem_unavailable', esc_html__('Unable to initialize the WordPress filesystem API.', 'file-media-renamer-for-seo'));
}

/**
 * Move a file using the WordPress filesystem API.
 *
 * @param string $source Source path.
 * @param string $destination Destination path.
 * @param bool $overwrite Whether to overwrite the destination if it exists.
 * @return true|WP_Error
 */
function fmrseo_move_file($source, $destination, $overwrite = false)
{
    $filesystem = fmrseo_get_filesystem();

    if (is_wp_error($filesystem)) {
        return $filesystem;
    }

    if (!$filesystem->move($source, $destination, $overwrite)) {
        return new WP_Error('fmrseo_move_failed', esc_html__('Failed to move the file via WP_Filesystem.', 'file-media-renamer-for-seo'));
    }

    return true;
}

// Upload the text domain
function fmrseo_load_textdomain()
{
    load_plugin_textdomain('file-media-renamer-for-seo', false, dirname(plugin_basename(__FILE__)) . '/languages/');
}
add_action('plugins_loaded', 'fmrseo_load_textdomain');


// Initialize the settings
function fmrseo_init_settings()
{
    new File_Media_Renamer_SEO_Settings();
}
add_action('init', 'fmrseo_init_settings');

/**
 * Add a settings link to the plugin action links.
 */
function fmrseo_setting_link($links)
{
    // Add a settings link to the plugin action links
    $settings_link = '<a href="upload.php?page=fmrseo">' . esc_html__('Settings', 'file-media-renamer-for-seo') . '</a>';
    // Add the settings link to the beginning of the links array
    array_unshift($links, $settings_link);

    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'fmrseo_setting_link');


/**
 * Main function to handle plugin hooks and filters.
 */
function fmrseo_activation_hooks()
{
    // Register the filter to add the custom field
    add_filter('attachment_fields_to_edit', 'fmrseo_add_seo_name_field_to_attachment', 10, 2);

    // Load the custom JS file
    add_action('admin_enqueue_scripts', 'fmrseo_enqueue_custom_admin_script');

    // Register the AJAX action to save the SEO name
    add_action('wp_ajax_fmrseo_save_seo_name', 'fmrseo_save_seo_name_ajax');

    // Hook to execute the update function when the event is scheduled
    add_action('fmrseo_update_content_image_references_event', 'fmrseo_update_content_image_references_background', 10, 4);

    // Make sure WP-Cron is active
    if (!wp_next_scheduled('fmrseo_wp_cron_hook')) {
        wp_schedule_event(time(), 'hourly', 'fmrseo_wp_cron_hook');
    }

    // Clean up scheduled events when they are no longer needed
    add_action('fmrseo_clear_scheduled_update_content_image_references', 'fmrseo_clear_scheduled_update_content_image_references');
}

// Hook to activate the main function
add_action('plugins_loaded', 'fmrseo_activation_hooks');

/**
 * Add a custom text field to the media attachment.
 *
 * @param array $form_fields The form fields.
 * @param object $post The post object.
 * @return array The modified form fields.
 */
function fmrseo_add_seo_name_field_to_attachment($form_fields, $post)
{
    // Get the full name of the file
    $file_name_with_extension = basename(get_attached_file($post->ID));

    // Remove the extension from the file name
    $file_name = pathinfo($file_name_with_extension, PATHINFO_FILENAME);

    $fmrseo_image_seo_name = get_post_meta($post->ID, 'fmrseo_image_seo_name', true);

    // If the custom field is still empty, use the file name as the default
    if (empty($fmrseo_image_seo_name)) {
        $fmrseo_image_seo_name = $file_name;
    }

    $form_fields['fmrseo_image_seo_name'] = array(
        'label' => esc_html__('SEO Name', 'file-media-renamer-for-seo'),
        'input' => 'text',
        'value' => esc_attr($fmrseo_image_seo_name),
        'helps' => esc_html__('Change image file name and path for better SEO', 'file-media-renamer-for-seo')
    );

    $form_fields['save_button'] = array(
        'input' => 'html',
        'html' => '<button id="save-seo-name" class="button" media-id="' . esc_attr($post->ID) . '">' . esc_html__('Save SEO Name', 'file-media-renamer-for-seo') . '</button>',
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
            'label' => esc_html__('SEO name history', 'file-media-renamer-for-seo'),
            'input' => 'html',
            'html'  => $ul,
        );
    }

    return $form_fields;
}

/**
 * Enqueue the plug-in script.
 */
function fmrseo_enqueue_custom_admin_script()
{
    wp_enqueue_script('jquery');
    wp_enqueue_script('rename-media', plugin_dir_url(__FILE__) . 'assets/js/rename-media.js', array('jquery'), FMRSEO_VERSION, true);
    wp_localize_script('rename-media', 'renameMedia', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('fmrseo_save_seo_name_nonce') // pass nonce
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
    $file_path = get_attached_file($post_id);

    if (!$file_path) {
        return new WP_Error('fmrseo_file_path_not_found', esc_html__('File path not found.', 'file-media-renamer-for-seo'));
    }

    $file_dir = pathinfo($file_path, PATHINFO_DIRNAME);
    $file_ext = pathinfo($file_path, PATHINFO_EXTENSION);
    $new_file_path = trailingslashit($file_dir) . $seo_name . '.' . $file_ext;

    if (!file_exists($file_path)) {
        return new WP_Error('fmrseo_original_file_missing', esc_html__('Original file does not exist.', 'file-media-renamer-for-seo'));
    }

    $unique = null;
    // Check if the new file path is the same as the current file path
    if (file_exists($new_file_path) && $file_path !== $new_file_path) {
        // If the new file already exists and if not restoring, return an exception
        if (!$is_restore) return new WP_Error('fmrseo_file_exists', esc_html__('A file with the new name and the same file extension already exists. Change it or add a number!', 'file-media-renamer-for-seo'));
        else {
            $unique = wp_unique_filename($file_dir, $seo_name . '.' . $file_ext);
            $new_file_path = trailingslashit($file_dir) . $unique;

            // Update the SEO name based on the unique filename generated
            $seo_name = pathinfo($unique, PATHINFO_FILENAME);
        }
    }

    // Rename file if needed
    if ($file_path !== $new_file_path) {
        $move_result = fmrseo_move_file($file_path, $new_file_path);
        if (is_wp_error($move_result)) {
            return $move_result;
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
                $thumbnail_move = fmrseo_move_file($old_thumbnail_path, $new_thumbnail_path);
                if (is_wp_error($thumbnail_move)) {
                    continue;
                }

                $old_thumbnail_url = str_replace($wp_upload_dir['basedir'], $wp_upload_dir['baseurl'], $old_thumbnail_path);
                $new_thumbnail_url = str_replace($wp_upload_dir['basedir'], $wp_upload_dir['baseurl'], $new_thumbnail_path);

                fmrseo_add_redirect($old_thumbnail_url, $new_thumbnail_url);

                $metadata['sizes'][$size]['file'] = $thumbnail_name;
                fmrseo_schedule_update_content_image_references($old_thumbnail_url, $new_thumbnail_url, $seo_name, null);
            }
        }
    }

    // Update the attachment metadata
    wp_update_attachment_metadata($post_id, $metadata);

    // Schedule background refresh for full-size image
    fmrseo_schedule_update_content_image_references($old_file_url, $new_file_url, $seo_name, $post_id);

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

    // save fmrseo_image_seo_name Custom Field of the plugin
    update_post_meta($post_id, 'fmrseo_image_seo_name', $seo_name);

    // --- Begin: Save rename history ---
    // Add current file info to history before renaming
    array_unshift($history, [
        'file_path' => $result['old_file_path'],
        'file_url'  => $result['old_file_url'],
        'seo_name'  => basename($result['old_file_path'], '.' . $result['file_ext']),
        'timestamp' => time(),
    ]);

    // Keep only the last 2 versions
    $history = array_slice($history, 0, 2);
    update_post_meta($post_id, '_fmrseo_rename_history', $history);
    // --- End: Save rename history ---

    return $result;
}

/**
 * Save the SEO name via AJAX.
 */
function fmrseo_save_seo_name_ajax()
{
    try {
        if (!check_ajax_referer('fmrseo_save_seo_name_nonce', '_ajax_nonce', false)) {
            throw new Exception(esc_html__('Nonce verification failed.', 'file-media-renamer-for-seo'));
        }

        $post_id = isset($_POST['post_id']) ? intval(wp_unslash($_POST['post_id'])) : 0;
        $seo_name = '';
        if (isset($_POST['seo_name'])) {
            $seo_name = sanitize_file_name(wp_unslash($_POST['seo_name']));
        }

        if (!$post_id || !$seo_name) {
            throw new Exception(esc_html__('File path not found.', 'file-media-renamer-for-seo'));
        }

        // Use the wrapper function
        $result = fmrseo_complete_rename_process($post_id, $seo_name);

        // Get final seo_name in case it was modified
        $final_seo_name = isset($result['seo_name']) ? $result['seo_name'] : pathinfo($result['new_file_path'], PATHINFO_FILENAME);

        wp_send_json_success([
            'message'  => esc_html__('File and thumbnails renamed successfully.', 'file-media-renamer-for-seo'),
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
function fmrseo_update_content_image_references_background($old_url, $new_url, $seo_name, $post_id)
{
    global $wpdb;

    // Update post_name, and metadata if post_id is provided
    if ($post_id) {

        $post_data = get_post($post_id);

        // Aggiorna il post_name
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

        update_post_meta($post_id, '_wp_attachment_metadata', $wp_attachment_metadata);

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

    $old_url_escaped = '%' . $wpdb->esc_like($old_url) . '%';

    // Search for the old URL in post_content of wp_posts
    $posts = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT ID, post_content 
         FROM {$wpdb->posts} 
         WHERE post_content LIKE %s",
            $old_url_escaped
        )
    );

    foreach ($posts as $post) {
        $updated_content = fmrseo_update_serialized_data($post->post_content, $old_url, $new_url);

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

    // Search for the old URL in meta_value of wp_postsmeta
    $postmeta = $wpdb->get_results($wpdb->prepare("SELECT meta_id, post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE meta_value LIKE %s", $old_url_escaped));

    foreach ($postmeta as $meta) {
        $updated_meta_value = fmrseo_update_serialized_data($meta->meta_value, $old_url, $new_url);

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
function fmrseo_schedule_update_content_image_references($old_url, $new_url, $seo_name, $post_id)
{
    if (!wp_next_scheduled('fmrseo_update_content_image_references_event')) {
        wp_schedule_single_event(time(), 'fmrseo_update_content_image_references_event', [$old_url, $new_url, $seo_name, $post_id]);
    }
}

/**
 * Clear scheduled update content image references.
 */
function fmrseo_clear_scheduled_update_content_image_references()
{
    $timestamp = wp_next_scheduled('fmrseo_update_content_image_references_event');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'fmrseo_update_content_image_references_event');
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
function fmrseo_update_serialized_data($data, $old_value, $new_value)
{
    if (is_serialized($data)) {
        $unserialized_data = unserialize($data);

        $unserialized_data = fmrseo_update_value_in_array($unserialized_data, $old_value, $new_value);

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
function fmrseo_update_value_in_array($array, $old_value, $new_value)
{
    foreach ($array as $key => $value) {
        if (is_array($value)) {
            $array[$key] = fmrseo_update_value_in_array($value, $old_value, $new_value);
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

    $wpdb->query('DROP TABLE IF EXISTS `' . $wpdb->prefix . 'fmrseo_redirects`');
}
register_deactivation_hook(__FILE__, 'fmrseo_drop_redirects_table');
