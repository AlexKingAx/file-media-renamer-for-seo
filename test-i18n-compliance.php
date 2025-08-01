<?php
/**
 * Test script for internationalization compliance
 */

echo "=== Internationalization Compliance Test ===\n\n";

// Check for translatable strings in PHP files
function check_translatable_strings($file) {
    $content = file_get_contents($file);
    $strings = array();
    
    // Find __() function calls
    if (preg_match_all('/__\s*\(\s*[\'"]([^\'"]+)[\'"].*?[\'"]([^\'"]+)[\'"]/', $content, $matches)) {
        foreach ($matches[1] as $index => $string) {
            $domain = $matches[2][$index];
            $strings[] = array(
                'string' => $string,
                'domain' => $domain,
                'function' => '__'
            );
        }
    }
    
    // Find _e() function calls
    if (preg_match_all('/_e\s*\(\s*[\'"]([^\'"]+)[\'"].*?[\'"]([^\'"]+)[\'"]/', $content, $matches)) {
        foreach ($matches[1] as $index => $string) {
            $domain = $matches[2][$index];
            $strings[] = array(
                'string' => $string,
                'domain' => $domain,
                'function' => '_e'
            );
        }
    }
    
    // Find esc_html__() function calls
    if (preg_match_all('/esc_html__\s*\(\s*[\'"]([^\'"]+)[\'"].*?[\'"]([^\'"]+)[\'"]/', $content, $matches)) {
        foreach ($matches[1] as $index => $string) {
            $domain = $matches[2][$index];
            $strings[] = array(
                'string' => $string,
                'domain' => $domain,
                'function' => 'esc_html__'
            );
        }
    }
    
    return $strings;
}

// Get all PHP files
function get_php_files($dir = '.') {
    $files = array();
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $path = $file->getPathname();
            // Skip test files and vendor directories
            if (strpos($path, 'test-') === false && strpos($path, 'vendor') === false) {
                $files[] = $path;
            }
        }
    }
    
    return $files;
}

// Check translation files
function check_translation_files() {
    $lang_dir = 'languages/';
    $results = array();
    
    if (!is_dir($lang_dir)) {
        $results['error'] = 'Languages directory not found';
        return $results;
    }
    
    $po_files = glob($lang_dir . '*.po');
    $mo_files = glob($lang_dir . '*.mo');
    
    $results['po_files'] = count($po_files);
    $results['mo_files'] = count($mo_files);
    $results['po_list'] = array_map('basename', $po_files);
    $results['mo_list'] = array_map('basename', $mo_files);
    
    // Check if .po files have corresponding .mo files
    $results['missing_mo'] = array();
    foreach ($po_files as $po_file) {
        $mo_file = str_replace('.po', '.mo', $po_file);
        if (!file_exists($mo_file)) {
            $results['missing_mo'][] = basename($mo_file);
        }
    }
    
    return $results;
}

// Check for text domain loading
function check_textdomain_loading() {
    $main_file = 'fmrseo.php';
    if (!file_exists($main_file)) {
        return false;
    }
    
    $content = file_get_contents($main_file);
    return strpos($content, 'load_plugin_textdomain') !== false;
}

// Run checks
echo "1. Checking text domain loading...\n";
if (check_textdomain_loading()) {
    echo "   ✅ Text domain loading found in main plugin file\n";
} else {
    echo "   ❌ Text domain loading not found\n";
}

echo "\n2. Checking translation files...\n";
$translation_results = check_translation_files();
if (isset($translation_results['error'])) {
    echo "   ❌ " . $translation_results['error'] . "\n";
} else {
    echo "   ✅ Found {$translation_results['po_files']} .po files\n";
    echo "   ✅ Found {$translation_results['mo_files']} .mo files\n";
    
    if (!empty($translation_results['po_list'])) {
        echo "   📄 PO files: " . implode(', ', $translation_results['po_list']) . "\n";
    }
    
    if (!empty($translation_results['missing_mo'])) {
        echo "   ⚠️  Missing MO files: " . implode(', ', $translation_results['missing_mo']) . "\n";
    }
}

echo "\n3. Checking translatable strings in PHP files...\n";
$php_files = get_php_files();
$total_strings = 0;
$correct_domain = 0;
$incorrect_domain = 0;

foreach ($php_files as $file) {
    $strings = check_translatable_strings($file);
    if (!empty($strings)) {
        $file_correct = 0;
        $file_incorrect = 0;
        
        foreach ($strings as $string_info) {
            $total_strings++;
            if ($string_info['domain'] === 'fmrseo') {
                $correct_domain++;
                $file_correct++;
            } else {
                $incorrect_domain++;
                $file_incorrect++;
            }
        }
        
        $relative_file = str_replace(getcwd() . DIRECTORY_SEPARATOR, '', $file);
        if ($file_correct > 0) {
            echo "   ✅ $relative_file: $file_correct strings with correct domain\n";
        }
        if ($file_incorrect > 0) {
            echo "   ⚠️  $relative_file: $file_incorrect strings with incorrect domain\n";
        }
    }
}

echo "\n=== Summary ===\n";
echo "Total translatable strings found: $total_strings\n";
echo "Strings with correct domain (fmrseo): $correct_domain\n";
echo "Strings with incorrect domain: $incorrect_domain\n";

if ($incorrect_domain === 0 && $total_strings > 0) {
    echo "\n🎉 All translatable strings use the correct text domain!\n";
} elseif ($total_strings === 0) {
    echo "\n⚠️  No translatable strings found. This might indicate missing internationalization.\n";
} else {
    echo "\n⚠️  Some strings use incorrect text domains. Please review and fix.\n";
}

echo "\n=== Test Complete ===\n";