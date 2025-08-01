<?php
/**
 * WordPress Compliance Verification Script
 * 
 * This script verifies WordPress coding standards, security, and internationalization
 * compliance for the FMR SEO plugin with AI functionality.
 */

if (!defined('ABSPATH')) {
    // Allow running from command line for testing
    define('ABSPATH', dirname(__FILE__) . '/');
}

class FMR_Compliance_Checker {
    
    private $errors = array();
    private $warnings = array();
    private $passed = array();
    
    /**
     * Run all compliance checks
     */
    public function run_all_checks() {
        echo "=== WordPress Compliance Verification ===\n\n";
        
        $this->check_plugin_header();
        $this->check_security_practices();
        $this->check_internationalization();
        $this->check_wordpress_hooks();
        $this->check_coding_standards();
        $this->check_file_structure();
        $this->check_activation_deactivation();
        
        $this->display_results();
    }
    
    /**
     * Check plugin header compliance
     */
    private function check_plugin_header() {
        echo "Checking plugin header...\n";
        
        $main_file = 'fmrseo.php';
        if (!file_exists($main_file)) {
            $this->errors[] = "Main plugin file not found: $main_file";
            return;
        }
        
        $content = file_get_contents($main_file);
        
        // Check required headers
        $required_headers = array(
            'Plugin Name' => '/Plugin Name:\s*(.+)/i',
            'Description' => '/Description:\s*(.+)/i',
            'Version' => '/Version:\s*(.+)/i',
            'Author' => '/Author:\s*(.+)/i',
            'Text Domain' => '/Text Domain:\s*(.+)/i',
            'Domain Path' => '/Domain Path:\s*(.+)/i'
        );
        
        foreach ($required_headers as $header => $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                $this->passed[] = "Plugin header '$header' found: " . trim($matches[1]);
            } else {
                $this->errors[] = "Missing required plugin header: $header";
            }
        }
        
        // Check ABSPATH security
        if (strpos($content, "if (!defined('ABSPATH'))") !== false || 
            strpos($content, "if (! defined('ABSPATH'))") !== false) {
            $this->passed[] = "ABSPATH security check present";
        } else {
            $this->errors[] = "Missing ABSPATH security check at top of main file";
        }
    }
    
    /**
     * Check security practices
     */
    private function check_security_practices() {
        echo "Checking security practices...\n";
        
        $php_files = $this->get_php_files();
        
        foreach ($php_files as $file) {
            $content = file_get_contents($file);
            
            // Check ABSPATH in each file
            if (strpos($content, "if (!defined('ABSPATH'))") !== false || 
                strpos($content, "if (! defined('ABSPATH'))") !== false) {
                $this->passed[] = "ABSPATH check in $file";
            } else {
                $this->warnings[] = "Missing ABSPATH check in $file";
            }
            
            // Check for nonce verification in AJAX handlers
            if (strpos($content, 'wp_ajax_') !== false) {
                if (strpos($content, 'wp_verify_nonce') !== false || strpos($content, 'check_ajax_referer') !== false) {
                    $this->passed[] = "Nonce verification found in $file";
                } else {
                    $this->errors[] = "AJAX handler without nonce verification in $file";
                }
            }
            
            // Check for capability checks
            if (strpos($content, 'current_user_can') !== false) {
                $this->passed[] = "Capability check found in $file";
            }
            
            // Check for data sanitization
            $sanitization_functions = array('sanitize_text_field', 'sanitize_title', 'esc_attr', 'esc_html', 'wp_kses');
            foreach ($sanitization_functions as $func) {
                if (strpos($content, $func) !== false) {
                    $this->passed[] = "Data sanitization ($func) found in $file";
                    break;
                }
            }
        }
    }
    
    /**
     * Check internationalization compliance
     */
    private function check_internationalization() {
        echo "Checking internationalization...\n";
        
        // Check if textdomain loading is implemented
        $main_file_content = file_get_contents('fmrseo.php');
        if (strpos($main_file_content, 'load_plugin_textdomain') !== false) {
            $this->passed[] = "Text domain loading implemented";
        } else {
            $this->errors[] = "Text domain loading not found";
        }
        
        // Check for translation files
        $lang_dir = 'languages/';
        if (is_dir($lang_dir)) {
            $po_files = glob($lang_dir . '*.po');
            $mo_files = glob($lang_dir . '*.mo');
            
            if (!empty($po_files)) {
                $this->passed[] = "Translation source files (.po) found: " . count($po_files);
            } else {
                $this->warnings[] = "No .po translation files found";
            }
            
            if (!empty($mo_files)) {
                $this->passed[] = "Compiled translation files (.mo) found: " . count($mo_files);
            } else {
                $this->warnings[] = "No .mo compiled translation files found";
            }
        } else {
            $this->errors[] = "Languages directory not found";
        }
        
        // Check for translatable strings in PHP files
        $php_files = $this->get_php_files();
        $translatable_strings_found = false;
        
        foreach ($php_files as $file) {
            $content = file_get_contents($file);
            if (preg_match('/__\s*\(\s*[\'"].*?[\'"].*?[\'"]fmrseo[\'"]/', $content)) {
                $translatable_strings_found = true;
                break;
            }
        }
        
        if ($translatable_strings_found) {
            $this->passed[] = "Translatable strings with correct text domain found";
        } else {
            $this->warnings[] = "No translatable strings with 'fmrseo' text domain found";
        }
    }
    
    /**
     * Check WordPress hooks usage
     */
    private function check_wordpress_hooks() {
        echo "Checking WordPress hooks...\n";
        
        $main_file_content = file_get_contents('fmrseo.php');
        
        // Check for proper hook usage
        $hooks_to_check = array(
            'plugins_loaded' => 'Plugin initialization hook',
            'admin_init' => 'Admin initialization hook',
            'wp_ajax_' => 'AJAX handlers',
            'admin_enqueue_scripts' => 'Script enqueuing',
            'init' => 'WordPress init hook'
        );
        
        foreach ($hooks_to_check as $hook => $description) {
            if (strpos($main_file_content, $hook) !== false) {
                $this->passed[] = "$description found";
            }
        }
        
        // Check activation/deactivation hooks
        if (strpos($main_file_content, 'register_activation_hook') !== false) {
            $this->passed[] = "Activation hook registered";
        } else {
            $this->warnings[] = "No activation hook found";
        }
        
        if (strpos($main_file_content, 'register_deactivation_hook') !== false) {
            $this->passed[] = "Deactivation hook registered";
        } else {
            $this->warnings[] = "No deactivation hook found";
        }
    }
    
    /**
     * Check basic coding standards
     */
    private function check_coding_standards() {
        echo "Checking coding standards...\n";
        
        $php_files = $this->get_php_files();
        
        foreach ($php_files as $file) {
            $content = file_get_contents($file);
            
            // Check for PHP opening tags
            if (strpos($content, '<?php') === 0) {
                $this->passed[] = "Proper PHP opening tag in $file";
            } else {
                $this->errors[] = "Missing or incorrect PHP opening tag in $file";
            }
            
            // Check for proper class naming (if contains class)
            if (preg_match('/class\s+([A-Za-z_][A-Za-z0-9_]*)/i', $content, $matches)) {
                $class_name = $matches[1];
                if (preg_match('/^[A-Z][A-Za-z0-9_]*$/', $class_name)) {
                    $this->passed[] = "Proper class naming in $file: $class_name";
                } else {
                    $this->warnings[] = "Class naming may not follow WordPress standards in $file: $class_name";
                }
            }
            
            // Check for proper function naming
            if (preg_match_all('/function\s+([a-zA-Z_][a-zA-Z0-9_]*)/i', $content, $matches)) {
                foreach ($matches[1] as $function_name) {
                    if (strpos($function_name, 'fmr') === 0 || strpos($function_name, '__') === 0) {
                        $this->passed[] = "Proper function prefixing in $file: $function_name";
                    } else {
                        $this->warnings[] = "Function may need prefixing in $file: $function_name";
                    }
                }
            }
        }
    }
    
    /**
     * Check file structure
     */
    private function check_file_structure() {
        echo "Checking file structure...\n";
        
        $required_dirs = array(
            'includes' => 'Core includes directory',
            'includes/ai' => 'AI functionality directory',
            'assets' => 'Assets directory',
            'assets/js' => 'JavaScript directory',
            'assets/css' => 'CSS directory',
            'languages' => 'Languages directory'
        );
        
        foreach ($required_dirs as $dir => $description) {
            if (is_dir($dir)) {
                $this->passed[] = "$description exists";
            } else {
                $this->warnings[] = "$description missing: $dir";
            }
        }
        
        // Check for readme file
        if (file_exists('README.md') || file_exists('readme.txt')) {
            $this->passed[] = "README file found";
        } else {
            $this->warnings[] = "No README file found";
        }
    }
    
    /**
     * Check activation/deactivation functionality
     */
    private function check_activation_deactivation() {
        echo "Checking activation/deactivation hooks...\n";
        
        $main_file_content = file_get_contents('fmrseo.php');
        
        // Check for database table creation
        if (strpos($main_file_content, 'dbDelta') !== false) {
            $this->passed[] = "Database table creation using dbDelta";
        }
        
        // Check for cleanup on deactivation
        if (strpos($main_file_content, 'DROP TABLE') !== false) {
            $this->passed[] = "Database cleanup on deactivation";
        }
        
        // Check for option cleanup
        if (strpos($main_file_content, 'delete_option') !== false) {
            $this->passed[] = "Option cleanup functionality present";
        }
    }
    
    /**
     * Get all PHP files in the plugin
     */
    private function get_php_files() {
        $files = array();
        
        // Main file
        if (file_exists('fmrseo.php')) {
            $files[] = 'fmrseo.php';
        }
        
        // Include files
        $include_dirs = array('includes', 'includes/ai', 'admin');
        foreach ($include_dirs as $dir) {
            if (is_dir($dir)) {
                $dir_files = glob($dir . '/*.php');
                $files = array_merge($files, $dir_files);
            }
        }
        
        return $files;
    }
    
    /**
     * Display results
     */
    private function display_results() {
        echo "\n=== COMPLIANCE CHECK RESULTS ===\n\n";
        
        if (!empty($this->passed)) {
            echo "✅ PASSED (" . count($this->passed) . "):\n";
            foreach ($this->passed as $item) {
                echo "  ✓ $item\n";
            }
            echo "\n";
        }
        
        if (!empty($this->warnings)) {
            echo "⚠️  WARNINGS (" . count($this->warnings) . "):\n";
            foreach ($this->warnings as $item) {
                echo "  ⚠ $item\n";
            }
            echo "\n";
        }
        
        if (!empty($this->errors)) {
            echo "❌ ERRORS (" . count($this->errors) . "):\n";
            foreach ($this->errors as $item) {
                echo "  ✗ $item\n";
            }
            echo "\n";
        }
        
        $total_issues = count($this->errors) + count($this->warnings);
        $total_passed = count($this->passed);
        
        echo "=== SUMMARY ===\n";
        echo "Passed: $total_passed\n";
        echo "Warnings: " . count($this->warnings) . "\n";
        echo "Errors: " . count($this->errors) . "\n";
        echo "Total Issues: $total_issues\n";
        
        if (count($this->errors) === 0) {
            echo "\n🎉 No critical errors found! Plugin meets basic WordPress compliance standards.\n";
        } else {
            echo "\n⚠️  Critical errors found. Please address before deployment.\n";
        }
    }
}

// Run the compliance check
$checker = new FMR_Compliance_Checker();
$checker->run_all_checks();