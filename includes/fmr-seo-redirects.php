<?php
/**
 * Redirects management for File Media Renamer for SEO
 */

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
 * Adds a redirect rule from an old media URL to a new one.
 *
 * @param string $old_url The original media URL before renaming.
 * @param string $new_url The new media URL after renaming.
 * @return void
 */
function fmrseo_add_redirect($old_url, $new_url) {
    $redirects = get_option('fmrseo_redirects');

    if (!is_array($redirects)) {
        error_log('[FMRSEO Add Redirect] Invalid redirects option. Resetting.');
        $redirects = [];
    }

    $redirects[$old_url] = $new_url;
    update_option('fmrseo_redirects', $redirects);

    error_log('[FMRSEO Add Redirect] Redirect added: ' . $old_url . ' → ' . $new_url);
}

/**
 * Redirects old media URLs to their new locations based on stored mappings.
 *
 * This function hooks into the `template_redirect` action and checks if the current
 * request URL matches any previously stored old media URL. If a match is found,
 * it performs a 301 redirect to the new media URL, helping preserve SEO and avoid broken links.
 *
 * @return void
 */
function fmrseo_check_image_redirect() {
    // Avoid running redirects in the admin area.
    if (is_admin()) {
        error_log('[FMRSEO Redirect] Skipped: Admin area');
        return;
    }

    // Retrieve the stored redirect mappings from the options table.
    $redirects = get_option('fmrseo_redirects', []);

    // Build the current full URL being requested.
    $request_uri = esc_url_raw($_SERVER['REQUEST_URI']);
    $current_url = home_url($request_uri);

    error_log('[FMRSEO Redirect] Current request: ' . $current_url);
    error_log('[FMRSEO Redirect] Redirect map: ' . print_r($redirects, true));

    // Loop through stored old → new URL pairs to find a match.
    foreach ($redirects as $old => $new) {
        // Compare the requested URL with the stored old URL.
        if (urldecode($current_url) === urldecode($old)) {
            error_log('[FMRSEO Redirect] MATCH FOUND: ' . $old . ' → ' . $new);

            // Perform a 301 redirect to the new URL.
            wp_redirect($new, 301);
            exit;
        }
    }

    error_log('[FMRSEO Redirect] No match found for: ' . $current_url);
}

// Hook into template_redirect to catch frontend requests before template rendering.
add_action('template_redirect', 'fmrseo_check_image_redirect');
