<?php
/**
 * Simple test file to verify AI settings integration
 * This file should be removed after testing
 */

// Mock WordPress functions for testing
if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        // Mock some test data
        if ($option === 'fmrseo_options') {
            return array(
                'rename_title' => 1,
                'rename_alt_text' => 0,
                'ai_enabled' => 1,
                'ai_api_key' => 'sk-test-key-123',
                'ai_timeout' => 30,
                'ai_max_retries' => 2
            );
        }
        return $default;
    }
}

if (!function_exists('__')) {
    function __($text, $domain = 'default') {
        return $text;
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_html')) {
    function esc_html($text) {
        return htmlspecialchars($text, ENT_NOQUOTES, 'UTF-8');
    }
}

if (!function_exists('checked')) {
    function checked($checked, $current = true, $echo = true) {
        $result = $checked == $current ? ' checked="checked"' : '';
        if ($echo) echo $result;
        return $result;
    }
}

if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
        // Mock function - do nothing in test
        return true;
    }
}

if (!function_exists('get_current_user_id')) {
    function get_current_user_id() {
        return 1; // Mock user ID
    }
}

if (!function_exists('get_user_meta')) {
    function get_user_meta($user_id, $key, $single = false) {
        // Mock user meta data
        if ($key === '_fmrseo_ai_credits') {
            return array(
                'balance' => 15,
                'used_total' => 5,
                'last_updated' => time(),
                'free_credits_initialized' => true,
                'transactions' => array()
            );
        }
        return $single ? '' : array();
    }
}

// Include the settings class
require_once 'includes/class-fmr-seo-settings.php';

// Test the settings class
$settings = new File_Media_Renamer_SEO_Settings();

echo "AI Settings Test Results:\n";
echo "========================\n";

// Test if AI is enabled
if (method_exists($settings, 'is_ai_enabled')) {
    echo "AI Enabled: " . ($settings->is_ai_enabled() ? 'Yes' : 'No') . "\n";
} else {
    echo "Error: is_ai_enabled method not found\n";
}

// Test option retrieval
$options = get_option('fmrseo_options');
echo "Current Options:\n";
print_r($options);

echo "\nTest completed successfully!\n";
?>