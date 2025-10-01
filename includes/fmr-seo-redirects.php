<?php
/**========================================================
 * Redirects management for File Media Renamer for SEO
 **========================================================*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}


/**
 * Initializes the fmrseo_redirects option on plugin activation.
 *
 * @return true|WP_Error
 */
function fmrseo_initialize_redirects_option()
{
    if (false === get_option('fmrseo_redirects', false)) {
        $added = add_option('fmrseo_redirects', []);
        if (!$added) {
            return new WP_Error(
                'fmrseo_option_init_failed',
                esc_html__('FMRSEO: Failed to initialize redirects option.', 'file-media-renamer-for-seo')
            );
        }
    }
    return true;
}

/**
 * Adds or updates a redirect in the custom database table.
 *
 * @param string $old_url The original media URL.
 * @param string $new_url The new media URL.
 * @return true|WP_Error
 */
function fmrseo_add_redirect($old_url, $new_url)
{
    global $wpdb;
    $table_name = $wpdb->prefix . "fmrseo_redirects";
    // Check if the redirect already exists
    $existing_redirect = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM `" . $wpdb->prefix . "fmrseo_redirects` WHERE old_url = %s",
            $old_url
        )
    );

    if ($existing_redirect) {
        // Update the existing redirect
        $updated = $wpdb->update(
            $table_name,
            ['new_url' => $new_url],
            ['old_url' => $old_url],
            ['%s'],
            ['%s']
        );
        if ($updated === false) {
            return new WP_Error(
                'fmrseo_update_failed',
                esc_html__('FMRSEO: Failed to update redirect.', 'file-media-renamer-for-seo')
            );
        }
    } else {
        // Insert a new redirect
        $inserted = $wpdb->insert(
            $table_name,
            ['old_url' => $old_url, 'new_url' => $new_url],
            ['%s', '%s']
        );
        if ($inserted === false) {
            return new WP_Error(
                'fmrseo_insert_failed',
                esc_html__('FMRSEO: Failed to insert redirect.', 'file-media-renamer-for-seo')
            );
        }
    }
    return true;
}

/**
 * Checks if the requested URL has a redirect and performs it if necessary.
 *
 * @return void
 */
function fmrseo_check_image_redirect()
{
    // Avoid running redirects in the admin area.
    if (is_admin()) {
        return;
    }

    global $wpdb;
    // Build the current full URL being requested.
    if (isset($_SERVER['REQUEST_URI'])) $request_uri = ltrim(esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])), '/');

    $current_url = rtrim(home_url(), '/') . '/' . $request_uri;

    // Check if there's a redirect for the requested URL;
    $redirect = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM `". $wpdb->prefix . "fmrseo_redirects` WHERE old_url = %s",
            $current_url
        )
    );

    // If a redirect is found, perform a 301 redirect
    if ($redirect) {
        wp_redirect($redirect->new_url, 301);
        exit;
    }
}

// Hook into template_redirect to catch frontend requests before template rendering.
add_action('template_redirect', 'fmrseo_check_image_redirect');
