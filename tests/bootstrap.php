<?php
/**
 * Bootstrap file for FMR AI Media Renaming Tests
 * 
 * Sets up the testing environment and loads necessary files.
 */

// Define test constants
define('FMR_PLUGIN_DIR', dirname(__DIR__) . '/');
define('FMR_TESTS_DIR', __DIR__ . '/');
define('WP_CONTENT_DIR', '/tmp/wordpress/wp-content');
define('WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins');

// Set up error reporting
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
ini_set('display_errors', 1);

// Load Composer autoloader if available
if (file_exists(FMR_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once FMR_PLUGIN_DIR . 'vendor/autoload.php';
}

// Mock WordPress functions that are commonly used
if (!function_exists('__')) {
    function __($text, $domain = 'default') {
        return $text;
    }
}

if (!function_exists('_e')) {
    function _e($text, $domain = 'default') {
        echo $text;
    }
}

if (!function_exists('esc_html')) {
    function esc_html($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('wp_die')) {
    function wp_die($message, $title = '', $args = array()) {
        throw new Exception($message);
    }
}

if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce($nonce, $action = -1) {
        return true; // Always return true for tests
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can($capability) {
        return true; // Always return true for tests
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return trim(strip_tags($str));
    }
}

if (!function_exists('wp_unslash')) {
    function wp_unslash($value) {
        return is_string($value) ? stripslashes($value) : $value;
    }
}

// Mock WP_Error class
if (!class_exists('WP_Error')) {
    class WP_Error {
        public $errors = array();
        public $error_data = array();
        
        public function __construct($code = '', $message = '', $data = '') {
            if (!empty($code)) {
                $this->errors[$code][] = $message;
                if (!empty($data)) {
                    $this->error_data[$code] = $data;
                }
            }
        }
        
        public function get_error_code() {
            $codes = array_keys($this->errors);
            return empty($codes) ? '' : $codes[0];
        }
        
        public function get_error_message($code = '') {
            if (empty($code)) {
                $code = $this->get_error_code();
            }
            return isset($this->errors[$code]) ? $this->errors[$code][0] : '';
        }
    }
}

// Load plugin classes (these would normally be autoloaded)
$plugin_classes = [
    'FMR_Context_Extractor',
    'FMR_Image_Analyzer',
    'FMR_PDF_Analyzer',
    'FMR_Office_Analyzer',
    'FMR_Analyzer_Factory',
    'FMR_Credit_Manager',
    'FMR_AI_Service'
];

foreach ($plugin_classes as $class) {
    if (!class_exists($class)) {
        // Create mock classes for testing
        eval("
            class $class {
                public function __construct() {}
                public function __call(\$method, \$args) {
                    // Return appropriate mock responses based on method name
                    if (strpos(\$method, 'get_') === 0 || strpos(\$method, 'extract_') === 0) {
                        return [];
                    }
                    if (strpos(\$method, 'analyze') !== false) {
                        return ['content' => 'mock content', 'metadata' => []];
                    }
                    if (strpos(\$method, 'deduct_') === 0 || strpos(\$method, 'add_') === 0) {
                        return true;
                    }
                    if (strpos(\$method, 'generate_') === 0) {
                        return ['success' => true, 'filename' => 'mock-filename.jpg', 'tokens_used' => 100];
                    }
                    return true;
                }
            }
        ");
    }
}

// Create test fixtures directory if it doesn't exist
$fixtures_dir = FMR_TESTS_DIR . 'fixtures';
if (!is_dir($fixtures_dir)) {
    mkdir($fixtures_dir, 0755, true);
}

// Load test classes
require_once FMR_TESTS_DIR . 'ContextExtractorTest.php';
require_once FMR_TESTS_DIR . 'ContentAnalyzerTest.php';
require_once FMR_TESTS_DIR . 'CreditManagerTest.php';
require_once FMR_TESTS_DIR . 'AIServiceTest.php';
require_once FMR_TESTS_DIR . 'IntegrationTest.php';
require_once FMR_TESTS_DIR . 'TestSuite.php';

echo "FMR AI Media Renaming Test Environment Loaded\n";
echo "Test fixtures directory: " . $fixtures_dir . "\n";
echo "Plugin directory: " . FMR_PLUGIN_DIR . "\n";
echo str_repeat("-", 50) . "\n";