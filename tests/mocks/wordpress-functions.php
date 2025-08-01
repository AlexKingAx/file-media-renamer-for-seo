<?php
/**
 * WordPress Function Mocks for Testing
 * 
 * Provides mock implementations of WordPress functions needed for testing
 * AI components following WordPress testing standards.
 */

// Global variables for test data
global $wp_test_posts, $wp_test_users, $wp_test_meta, $wp_test_options, $wp_test_transients;
$wp_test_posts = array();
$wp_test_users = array();
$wp_test_meta = array();
$wp_test_options = array();
$wp_test_transients = array();

// Mock WordPress database global
global $wpdb;
$wpdb = new stdClass();
$wpdb->posts = 'wp_posts';
$wpdb->postmeta = 'wp_postmeta';
$wpdb->usermeta = 'wp_usermeta';

// Core WordPress functions
if (!function_exists('get_post')) {
    function get_post($post_id) {
        global $wp_test_posts;
        return isset($wp_test_posts[$post_id]) ? $wp_test_posts[$post_id] : null;
    }
}

if (!function_exists('get_attached_file')) {
    function get_attached_file($post_id) {
        global $wp_test_posts;
        if (isset($wp_test_posts[$post_id])) {
            return '/uploads/test-file-' . $post_id . '.jpg';
        }
        return false;
    }
}

if (!function_exists('wp_check_filetype')) {
    function wp_check_filetype($filename) {
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $types = array(
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'pdf' => 'application/pdf',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        );
        
        return array(
            'ext' => $ext,
            'type' => isset($types[$ext]) ? $types[$ext] : 'application/octet-stream'
        );
    }
}

if (!function_exists('get_post_meta')) {
    function get_post_meta($post_id, $key = '', $single = false) {
        global $wp_test_meta;
        $meta_key = 'post_' . $post_id . '_' . $key;
        
        if (empty($key)) {
            // Return all meta for post
            $all_meta = array();
            foreach ($wp_test_meta as $k => $v) {
                if (strpos($k, 'post_' . $post_id . '_') === 0) {
                    $meta_key = str_replace('post_' . $post_id . '_', '', $k);
                    $all_meta[$meta_key] = is_array($v) ? $v : array($v);
                }
            }
            return $all_meta;
        }
        
        if (isset($wp_test_meta[$meta_key])) {
            return $single ? $wp_test_meta[$meta_key] : array($wp_test_meta[$meta_key]);
        }
        
        return $single ? '' : array();
    }
}

if (!function_exists('update_post_meta')) {
    function update_post_meta($post_id, $key, $value) {
        global $wp_test_meta;
        $meta_key = 'post_' . $post_id . '_' . $key;
        $wp_test_meta[$meta_key] = $value;
        return true;
    }
}

if (!function_exists('get_user_meta')) {
    function get_user_meta($user_id, $key = '', $single = false) {
        global $wp_test_meta;
        $meta_key = 'user_' . $user_id . '_' . $key;
        
        if (isset($wp_test_meta[$meta_key])) {
            return $single ? $wp_test_meta[$meta_key] : array($wp_test_meta[$meta_key]);
        }
        
        return $single ? '' : array();
    }
}

if (!function_exists('update_user_meta')) {
    function update_user_meta($user_id, $key, $value) {
        global $wp_test_meta;
        $meta_key = 'user_' . $user_id . '_' . $key;
        $wp_test_meta[$meta_key] = $value;
        return true;
    }
}

if (!function_exists('get_userdata')) {
    function get_userdata($user_id) {
        global $wp_test_users;
        return isset($wp_test_users[$user_id]) ? $wp_test_users[$user_id] : false;
    }
}

if (!function_exists('get_current_user_id')) {
    function get_current_user_id() {
        return 1; // Default test user ID
    }
}

if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        global $wp_test_options;
        return isset($wp_test_options[$option]) ? $wp_test_options[$option] : $default;
    }
}

if (!function_exists('update_option')) {
    function update_option($option, $value) {
        global $wp_test_options;
        $wp_test_options[$option] = $value;
        return true;
    }
}

if (!function_exists('get_transient')) {
    function get_transient($transient) {
        global $wp_test_transients;
        if (isset($wp_test_transients[$transient])) {
            $data = $wp_test_transients[$transient];
            if ($data['expiry'] > time()) {
                return $data['value'];
            } else {
                unset($wp_test_transients[$transient]);
            }
        }
        return false;
    }
}

if (!function_exists('set_transient')) {
    function set_transient($transient, $value, $expiration = 0) {
        global $wp_test_transients;
        $wp_test_transients[$transient] = array(
            'value' => $value,
            'expiry' => time() + $expiration
        );
        return true;
    }
}

if (!function_exists('delete_transient')) {
    function delete_transient($transient) {
        global $wp_test_transients;
        unset($wp_test_transients[$transient]);
        return true;
    }
}

// WordPress sanitization functions
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return trim(strip_tags($str));
    }
}

if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($str) {
        return trim(strip_tags($str));
    }
}

if (!function_exists('sanitize_title')) {
    function sanitize_title($title) {
        return strtolower(str_replace(' ', '-', trim(strip_tags($title))));
    }
}

// WordPress utility functions
if (!function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags($string) {
        return strip_tags($string);
    }
}

if (!function_exists('wp_get_attachment_metadata')) {
    function wp_get_attachment_metadata($post_id) {
        return array(
            'width' => 1920,
            'height' => 1080,
            'filesize' => 150000,
            'image_meta' => array(
                'camera' => 'Test Camera',
                'created_timestamp' => '1234567890',
                'copyright' => 'Test Copyright',
                'credit' => 'Test Credit',
                'title' => 'Test Image Title',
                'keywords' => array('test', 'image', 'keyword')
            )
        );
    }
}

if (!function_exists('get_post_mime_type')) {
    function get_post_mime_type($post_id) {
        return 'image/jpeg';
    }
}

if (!function_exists('wp_get_attachment_url')) {
    function wp_get_attachment_url($post_id) {
        return 'https://example.com/wp-content/uploads/test-file-' . $post_id . '.jpg';
    }
}

if (!function_exists('wp_get_attachment_image_src')) {
    function wp_get_attachment_image_src($post_id, $size = 'thumbnail') {
        return array(
            'https://example.com/wp-content/uploads/test-file-' . $post_id . '-' . $size . '.jpg',
            150,
            150
        );
    }
}

if (!function_exists('get_intermediate_image_sizes')) {
    function get_intermediate_image_sizes() {
        return array('thumbnail', 'medium', 'large');
    }
}

if (!function_exists('wp_upload_dir')) {
    function wp_upload_dir() {
        return array(
            'path' => '/var/www/html/wp-content/uploads',
            'url' => 'https://example.com/wp-content/uploads',
            'subdir' => '',
            'basedir' => '/var/www/html/wp-content/uploads',
            'baseurl' => 'https://example.com/wp-content/uploads',
            'error' => false
        );
    }
}

// WordPress localization functions
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

// WordPress HTTP API functions
if (!function_exists('wp_remote_request')) {
    function wp_remote_request($url, $args = array()) {
        // Mock successful response for testing
        return array(
            'response' => array('code' => 200),
            'body' => json_encode(array('success' => true, 'message' => 'Test response'))
        );
    }
}

if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($response) {
        return isset($response['response']['code']) ? $response['response']['code'] : 200;
    }
}

if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response) {
        return isset($response['body']) ? $response['body'] : '';
    }
}

if (!function_exists('wp_remote_retrieve_headers')) {
    function wp_remote_retrieve_headers($response) {
        return isset($response['headers']) ? $response['headers'] : array();
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return $thing instanceof WP_Error;
    }
}

// WordPress JSON functions
if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data) {
        return json_encode($data);
    }
}

if (!function_exists('wp_generate_uuid4')) {
    function wp_generate_uuid4() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}

// WordPress filesystem functions
if (!function_exists('WP_Filesystem')) {
    function WP_Filesystem() {
        return true;
    }
}

// WordPress error class
if (!class_exists('WP_Error')) {
    class WP_Error {
        private $errors = array();
        private $error_data = array();
        
        public function __construct($code = '', $message = '', $data = '') {
            if (!empty($code)) {
                $this->errors[$code][] = $message;
                if (!empty($data)) {
                    $this->error_data[$code] = $data;
                }
            }
        }
        
        public function get_error_message($code = '') {
            if (empty($code)) {
                $code = $this->get_error_code();
            }
            if (isset($this->errors[$code])) {
                return $this->errors[$code][0];
            }
            return '';
        }
        
        public function get_error_code() {
            $codes = array_keys($this->errors);
            return empty($codes) ? '' : $codes[0];
        }
    }
}

// Test helper functions
function fmr_test_create_post($post_id, $post_type = 'attachment', $post_title = 'Test Post') {
    global $wp_test_posts;
    $wp_test_posts[$post_id] = (object) array(
        'ID' => $post_id,
        'post_type' => $post_type,
        'post_title' => $post_title,
        'post_content' => 'Test content',
        'post_excerpt' => 'Test excerpt',
        'post_name' => sanitize_title($post_title),
        'post_date' => '2023-01-01 12:00:00',
        'post_modified' => '2023-01-01 12:00:00',
        'post_status' => 'publish'
    );
}

function fmr_test_create_user($user_id, $user_login = 'testuser') {
    global $wp_test_users;
    $wp_test_users[$user_id] = (object) array(
        'ID' => $user_id,
        'user_login' => $user_login,
        'user_email' => $user_login . '@example.com',
        'display_name' => 'Test User'
    );
}

function fmr_test_reset_data() {
    global $wp_test_posts, $wp_test_users, $wp_test_meta, $wp_test_options, $wp_test_transients;
    $wp_test_posts = array();
    $wp_test_users = array();
    $wp_test_meta = array();
    $wp_test_options = array();
    $wp_test_transients = array();
}