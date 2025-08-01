<?php
/**
 * Redirects management for File Media Renamer for SEO
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Initializes the fmrseo_redirects option on plugin activation.
 *
 * @return void
 */
function fmrseo_initialize_redirects_option() {
    if (false === get_option('fmrseo_redirects', false)) {
        add_option('fmrseo_redirects', []);
        error_log('[FMRSEO Init] Redirects option initialized.');
    } else {
        error_log('[FMRSEO Init] Redirects option already exists.');
    }
}

/**
 * Adds or updates a redirect in the custom database table.
 *
 * @param string $old_url The original media URL.
 * @param string $new_url The new media URL.
 * @return void
 */
function fmrseo_add_redirect($old_url, $new_url) {
    global $wpdb;

    $table_name = $wpdb->prefix . 'fmrseo_redirects';

    // Check if the redirect already exists
    $existing_redirect = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE old_url = %s",
            $old_url
        )
    );

    if ($existing_redirect) {
        // Update the existing redirect
        $wpdb->update(
            $table_name,
            ['new_url' => $new_url],
            ['old_url' => $old_url],
            ['%s'],
            ['%s']
        );
        error_log('[FMRSEO Add Redirect] Redirect updated: ' . $old_url . ' → ' . $new_url);
    } else {
        // Insert a new redirect
        $wpdb->insert(
            $table_name,
            ['old_url' => $old_url, 'new_url' => $new_url],
            ['%s', '%s']
        );
        error_log('[FMRSEO Add Redirect] New redirect added: ' . $old_url . ' → ' . $new_url);
    }
}

/**
 * Checks if the requested URL has a redirect and performs it if necessary.
 *
 * @return void
 */
function fmrseo_check_image_redirect() {
    // Avoid running redirects in the admin area.
    if (is_admin()) {
        error_log('[FMRSEO Redirect] Skipped: Admin area');
        return;
    }

    global $wpdb;

    // Build the current full URL being requested.
    $request_uri = ltrim(esc_url_raw($_SERVER['REQUEST_URI']), '/');
    $current_url = rtrim(home_url(), '/') . '/' . $request_uri;

    // Check if there's a redirect for the requested URL
    $table_name = $wpdb->prefix . 'fmrseo_redirects';
    $redirect = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM $table_name WHERE old_url = %s",
            $current_url
        )
    );

    // If a redirect is found, perform a 301 redirect
    if ($redirect) {
        error_log('[FMRSEO Redirect] Match found: ' . $current_url . ' → ' . $redirect->new_url);
        wp_redirect($redirect->new_url, 301);
        exit;
    }

    error_log('[FMRSEO Redirect] No match found for: ' . $current_url);
}

// Hook into template_redirect to catch frontend requests before template rendering.
add_action('template_redirect', 'fmrseo_check_image_redirect');
