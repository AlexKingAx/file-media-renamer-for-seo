<?php
/**
 * Test script for plugin activation/deactivation hooks
 */

// Simulate WordPress environment
define('ABSPATH', dirname(__FILE__) . '/');
define('WP_DEBUG', true);

// Mock WordPress functions for testing
function add_action($hook, $callback) {
    echo "Hook registered: $hook\n";
}

function add_filter($hook, $callback) {
    echo "Filter registered: $hook\n";
}

function register_activation_hook($file, $callback) {
    echo "Activation hook registered for: " . basename($file) . "\n";
}

function register_deactivation_hook($file, $callback) {
    echo "Deactivation hook registered for: " . basename($file) . "\n";
}

function plugin_dir_path($file) {
    return dirname($file) . '/';
}

function plugin_dir_url($file) {
    return 'http://example.com/wp-content/plugins/' . basename(dirname($file)) . '/';
}

function plugin_basename($file) {
    return basename(dirname($file)) . '/' . basename($file);
}

function load_plugin_textdomain($domain, $deprecated, $path) {
    echo "Text domain loaded: $domain from $path\n";
}

function get_option($option, $default = false) {
    return $default;
}

function update_option($option, $value) {
    echo "Option updated: $option\n";
    return true;
}

function wp_next_scheduled($hook) {
    return false;
}

function wp_schedule_event($timestamp, $recurrence, $hook) {
    echo "Event scheduled: $hook\n";
}

function is_admin() {
    return true;
}

function get_current_user_id() {
    return 1;
}

if (!function_exists('spl_autoload_register')) {
    function spl_autoload_register($callback) {
        echo "Autoloader registered\n";
    }
}

// Test plugin loading
echo "=== Testing Plugin Activation/Deactivation Hooks ===\n\n";

try {
    include 'fmrseo.php';
    echo "\n✅ Plugin loaded successfully without errors\n";
    
    // Test activation function
    echo "\n=== Testing Activation Function ===\n";
    if (function_exists('fmrseo_activate_ai')) {
        fmrseo_activate_ai();
        echo "✅ Activation function executed successfully\n";
    } else {
        echo "❌ Activation function not found\n";
    }
    
    // Test table creation function
    echo "\n=== Testing Table Creation ===\n";
    if (function_exists('fmrseo_create_redirects_table')) {
        // Mock WordPress database functions
        global $wpdb;
        $wpdb = new stdClass();
        $wpdb->prefix = 'wp_';
        
        function dbDelta($sql) {
            echo "Database table created/updated\n";
        }
        
        fmrseo_create_redirects_table();
        echo "✅ Table creation function executed successfully\n";
    } else {
        echo "❌ Table creation function not found\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error loading plugin: " . $e->getMessage() . "\n";
} catch (Error $e) {
    echo "❌ Fatal error loading plugin: " . $e->getMessage() . "\n";
}

echo "\n=== Test Complete ===\n";