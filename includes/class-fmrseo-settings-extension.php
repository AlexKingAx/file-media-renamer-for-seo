<?php
/**
 * Settings Extension for FMRSEO Plugin
 * Manages advanced security and performance options
 */

if (!defined('ABSPATH')) {
    exit;
}

class FMRSEO_Settings_Extension {
    
    private $option_name = 'fmrseo_advanced_settings';
    private $default_settings;
    
    public function __construct() {
        $this->default_settings = [
            // Security Settings
            'security' => [
                'rate_limit_enabled' => true,
                'rate_limit_requests' => 100,
                'rate_limit_window' => 3600, // 1 hour
                'api_key_rotation_days' => 30,
                'log_api_calls' => true,
                'sanitize_filenames' => true,
                'allowed_file_types' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
                'max_file_size_mb' => 10
            ],
            
            // Performance Settings
            'performance' => [
                'batch_processing_enabled' => true,
                'batch_size' => 10,
                'processing_timeout' => 30,
                'cache_enabled' => true,
                'cache_duration' => 86400, // 24 hours
                'queue_processing' => true,
                'concurrent_requests' => 3,
                'memory_limit_mb' => 256
            ],
            
            // AI Settings
            'ai' => [
                'context_analysis_enabled' => true,
                'fallback_naming_enabled' => true,
                'confidence_threshold' => 0.7,
                'max_retries' => 3,
                'custom_prompts_enabled' => false,
                'language_detection' => true
            ],
            
            // Logging Settings
            'logging' => [
                'enabled' => true,
                'level' => 'info', // debug, info, warning, error
                'max_log_size_mb' => 50,
                'retention_days' => 30,
                'log_api_responses' => false
            ]
        ];
        
        add_action('admin_init', [$this, 'init_settings']);
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('wp_ajax_fmrseo_reset_settings', [$this, 'reset_settings']);
        add_action('wp_ajax_fmrseo_export_settings', [$this, 'export_settings']);
        add_action('wp_ajax_fmrseo_import_settings', [$this, 'import_settings']);
    }
    
    public function init_settings() {
        register_setting(
            'fmrseo_advanced_settings_group',
            $this->option_name,
            [$this, 'sanitize_settings']
        );
        
        // Security Section
        add_settings_section(
            'fmrseo_security_section',
            __('Security Settings', 'fmrseo'),
            [$this, 'security_section_callback'],
            'fmrseo_advanced_settings'
        );
        
        // Performance Section
        add_settings_section(
            'fmrseo_performance_section',
            __('Performance Settings', 'fmrseo'),
            [$this, 'performance_section_callback'],
            'fmrseo_advanced_settings'
        );
        
        // AI Section
        add_settings_section(
            'fmrseo_ai_section',
            __('AI Configuration', 'fmrseo'),
            [$this, 'ai_section_callback'],
            'fmrseo_advanced_settings'
        );
        
        // Logging Section
        add_settings_section(
            'fmrseo_logging_section',
            __('Logging Settings', 'fmrseo'),
            [$this, 'logging_section_callback'],
            'fmrseo_advanced_settings'
        );
        
        $this->add_settings_fields();
    }
    
    private function add_settings_fields() {
        // Security Fields
        add_settings_field(
            'rate_limit_enabled',
            __('Enable Rate Limiting', 'fmrseo'),
            [$this, 'checkbox_field'],
            'fmrseo_advanced_settings',
            'fmrseo_security_section',
            ['field' => 'security.rate_limit_enabled', 'description' => 'Limit API requests to prevent abuse']
        );
        
        add_settings_field(
            'rate_limit_requests',
            __('Rate Limit (requests/hour)', 'fmrseo'),
            [$this, 'number_field'],
            'fmrseo_advanced_settings',
            'fmrseo_security_section',
            ['field' => 'security.rate_limit_requests', 'min' => 1, 'max' => 1000]
        );
        
        add_settings_field(
            'max_file_size_mb',
            __('Max File Size (MB)', 'fmrseo'),
            [$this, 'number_field'],
            'fmrseo_advanced_settings',
            'fmrseo_security_section',
            ['field' => 'security.max_file_size_mb', 'min' => 1, 'max' => 100]
        );
        
        // Performance Fields
        add_settings_field(
            'batch_processing_enabled',
            __('Enable Batch Processing', 'fmrseo'),
            [$this, 'checkbox_field'],
            'fmrseo_advanced_settings',
            'fmrseo_performance_section',
            ['field' => 'performance.batch_processing_enabled', 'description' => 'Process multiple files simultaneously']
        );
        
        add_settings_field(
            'batch_size',
            __('Batch Size', 'fmrseo'),
            [$this, 'number_field'],
            'fmrseo_advanced_settings',
            'fmrseo_performance_section',
            ['field' => 'performance.batch_size', 'min' => 1, 'max' => 50]
        );
        
        add_settings_field(
            'cache_enabled',
            __('Enable Caching', 'fmrseo'),
            [$this, 'checkbox_field'],
            'fmrseo_advanced_settings',
            'fmrseo_performance_section',
            ['field' => 'performance.cache_enabled', 'description' => 'Cache AI responses to improve performance']
        );
        
        // AI Fields
        add_settings_field(
            'confidence_threshold',
            __('Confidence Threshold', 'fmrseo'),
            [$this, 'range_field'],
            'fmrseo_advanced_settings',
            'fmrseo_ai_section',
            ['field' => 'ai.confidence_threshold', 'min' => 0.1, 'max' => 1.0, 'step' => 0.1]
        );
        
        add_settings_field(
            'context_analysis_enabled',
            __('Enable Context Analysis', 'fmrseo'),
            [$this, 'checkbox_field'],
            'fmrseo_advanced_settings',
            'fmrseo_ai_section',
            ['field' => 'ai.context_analysis_enabled', 'description' => 'Analyze surrounding content for better naming']
        );
        
        // Logging Fields
        add_settings_field(
            'logging_enabled',
            __('Enable Logging', 'fmrseo'),
            [$this, 'checkbox_field'],
            'fmrseo_advanced_settings',
            'fmrseo_logging_section',
            ['field' => 'logging.enabled', 'description' => 'Log plugin activities for debugging']
        );
        
        add_settings_field(
            'logging_level',
            __('Log Level', 'fmrseo'),
            [$this, 'select_field'],
            'fmrseo_advanced_settings',
            'fmrseo_logging_section',
            [
                'field' => 'logging.level',
                'options' => [
                    'debug' => 'Debug',
                    'info' => 'Info',
                    'warning' => 'Warning',
                    'error' => 'Error'
                ]
            ]
        );
    }  
  
    public function add_settings_page() {
        add_submenu_page(
            'fmrseo-settings',
            __('Advanced Settings', 'fmrseo'),
            __('Advanced', 'fmrseo'),
            'manage_options',
            'fmrseo-advanced-settings',
            [$this, 'settings_page_callback']
        );
    }
    
    public function settings_page_callback() {
        if (isset($_GET['settings-updated'])) {
            add_settings_error(
                'fmrseo_messages',
                'fmrseo_message',
                __('Settings saved successfully!', 'fmrseo'),
                'updated'
            );
        }
        
        settings_errors('fmrseo_messages');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="fmrseo-settings-header">
                <p><?php _e('Configure advanced security, performance, and AI settings for the FMRSEO plugin.', 'fmrseo'); ?></p>
                
                <div class="fmrseo-settings-actions">
                    <button type="button" class="button" id="fmrseo-export-settings">
                        <?php _e('Export Settings', 'fmrseo'); ?>
                    </button>
                    <button type="button" class="button" id="fmrseo-import-settings">
                        <?php _e('Import Settings', 'fmrseo'); ?>
                    </button>
                    <button type="button" class="button button-secondary" id="fmrseo-reset-settings">
                        <?php _e('Reset to Defaults', 'fmrseo'); ?>
                    </button>
                </div>
            </div>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('fmrseo_advanced_settings_group');
                do_settings_sections('fmrseo_advanced_settings');
                submit_button();
                ?>
            </form>
            
            <div id="fmrseo-import-modal" class="fmrseo-modal" style="display: none;">
                <div class="fmrseo-modal-content">
                    <span class="fmrseo-modal-close">&times;</span>
                    <h3><?php _e('Import Settings', 'fmrseo'); ?></h3>
                    <textarea id="fmrseo-import-data" placeholder="<?php _e('Paste your settings JSON here...', 'fmrseo'); ?>"></textarea>
                    <div class="fmrseo-modal-actions">
                        <button type="button" class="button button-primary" id="fmrseo-confirm-import">
                            <?php _e('Import', 'fmrseo'); ?>
                        </button>
                        <button type="button" class="button" id="fmrseo-cancel-import">
                            <?php _e('Cancel', 'fmrseo'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .fmrseo-settings-header {
            background: #f9f9f9;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .fmrseo-settings-actions {
            margin-top: 10px;
        }
        
        .fmrseo-settings-actions .button {
            margin-right: 10px;
        }
        
        .fmrseo-modal {
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .fmrseo-modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 50%;
            border-radius: 4px;
        }
        
        .fmrseo-modal-close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .fmrseo-modal-close:hover {
            color: black;
        }
        
        #fmrseo-import-data {
            width: 100%;
            height: 200px;
            margin: 10px 0;
        }
        
        .fmrseo-modal-actions {
            text-align: right;
            margin-top: 15px;
        }
        
        .form-table th {
            width: 200px;
        }
        
        .fmrseo-field-description {
            font-style: italic;
            color: #666;
            font-size: 12px;
            margin-top: 5px;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Export settings
            $('#fmrseo-export-settings').on('click', function() {
                $.post(ajaxurl, {
                    action: 'fmrseo_export_settings',
                    nonce: '<?php echo wp_create_nonce('fmrseo_settings_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        const blob = new Blob([JSON.stringify(response.data, null, 2)], {
                            type: 'application/json'
                        });
                        const url = URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = 'fmrseo-settings-' + new Date().toISOString().split('T')[0] + '.json';
                        a.click();
                        URL.revokeObjectURL(url);
                    }
                });
            });
            
            // Import settings
            $('#fmrseo-import-settings').on('click', function() {
                $('#fmrseo-import-modal').show();
            });
            
            $('.fmrseo-modal-close, #fmrseo-cancel-import').on('click', function() {
                $('#fmrseo-import-modal').hide();
                $('#fmrseo-import-data').val('');
            });
            
            $('#fmrseo-confirm-import').on('click', function() {
                const data = $('#fmrseo-import-data').val();
                if (!data) return;
                
                $.post(ajaxurl, {
                    action: 'fmrseo_import_settings',
                    nonce: '<?php echo wp_create_nonce('fmrseo_settings_nonce'); ?>',
                    settings_data: data
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Import failed: ' + response.data);
                    }
                });
            });
            
            // Reset settings
            $('#fmrseo-reset-settings').on('click', function() {
                if (confirm('<?php _e('Are you sure you want to reset all settings to defaults?', 'fmrseo'); ?>')) {
                    $.post(ajaxurl, {
                        action: 'fmrseo_reset_settings',
                        nonce: '<?php echo wp_create_nonce('fmrseo_settings_nonce'); ?>'
                    }, function(response) {
                        if (response.success) {
                            location.reload();
                        }
                    });
                }
            });
        });
        </script>
        <?php
    }
    
    // Section Callbacks
    public function security_section_callback() {
        echo '<p>' . __('Configure security settings to protect your site and API usage.', 'fmrseo') . '</p>';
    }
    
    public function performance_section_callback() {
        echo '<p>' . __('Optimize performance settings for better speed and resource usage.', 'fmrseo') . '</p>';
    }
    
    public function ai_section_callback() {
        echo '<p>' . __('Configure AI behavior and processing parameters.', 'fmrseo') . '</p>';
    }
    
    public function logging_section_callback() {
        echo '<p>' . __('Control logging behavior for debugging and monitoring.', 'fmrseo') . '</p>';
    }    

    // Field Rendering Methods
    public function checkbox_field($args) {
        $settings = $this->get_settings();
        $field_path = explode('.', $args['field']);
        $value = $this->get_nested_value($settings, $field_path);
        $field_name = $this->option_name . '[' . implode('][', $field_path) . ']';
        
        echo '<input type="checkbox" id="' . esc_attr($args['field']) . '" name="' . esc_attr($field_name) . '" value="1" ' . checked(1, $value, false) . ' />';
        
        if (isset($args['description'])) {
            echo '<div class="fmrseo-field-description">' . esc_html($args['description']) . '</div>';
        }
    }
    
    public function number_field($args) {
        $settings = $this->get_settings();
        $field_path = explode('.', $args['field']);
        $value = $this->get_nested_value($settings, $field_path);
        $field_name = $this->option_name . '[' . implode('][', $field_path) . ']';
        
        $min = isset($args['min']) ? 'min="' . esc_attr($args['min']) . '"' : '';
        $max = isset($args['max']) ? 'max="' . esc_attr($args['max']) . '"' : '';
        
        echo '<input type="number" id="' . esc_attr($args['field']) . '" name="' . esc_attr($field_name) . '" value="' . esc_attr($value) . '" ' . $min . ' ' . $max . ' />';
        
        if (isset($args['description'])) {
            echo '<div class="fmrseo-field-description">' . esc_html($args['description']) . '</div>';
        }
    }
    
    public function range_field($args) {
        $settings = $this->get_settings();
        $field_path = explode('.', $args['field']);
        $value = $this->get_nested_value($settings, $field_path);
        $field_name = $this->option_name . '[' . implode('][', $field_path) . ']';
        
        $min = isset($args['min']) ? $args['min'] : 0;
        $max = isset($args['max']) ? $args['max'] : 1;
        $step = isset($args['step']) ? $args['step'] : 0.1;
        
        echo '<input type="range" id="' . esc_attr($args['field']) . '" name="' . esc_attr($field_name) . '" value="' . esc_attr($value) . '" min="' . esc_attr($min) . '" max="' . esc_attr($max) . '" step="' . esc_attr($step) . '" oninput="this.nextElementSibling.value = this.value" />';
        echo '<output>' . esc_html($value) . '</output>';
        
        if (isset($args['description'])) {
            echo '<div class="fmrseo-field-description">' . esc_html($args['description']) . '</div>';
        }
    }
    
    public function select_field($args) {
        $settings = $this->get_settings();
        $field_path = explode('.', $args['field']);
        $value = $this->get_nested_value($settings, $field_path);
        $field_name = $this->option_name . '[' . implode('][', $field_path) . ']';
        
        echo '<select id="' . esc_attr($args['field']) . '" name="' . esc_attr($field_name) . '">';
        foreach ($args['options'] as $option_value => $option_label) {
            echo '<option value="' . esc_attr($option_value) . '" ' . selected($value, $option_value, false) . '>' . esc_html($option_label) . '</option>';
        }
        echo '</select>';
        
        if (isset($args['description'])) {
            echo '<div class="fmrseo-field-description">' . esc_html($args['description']) . '</div>';
        }
    }
    
    // Settings Management
    public function get_settings() {
        $settings = get_option($this->option_name, []);
        return wp_parse_args($settings, $this->default_settings);
    }
    
    public function update_settings($new_settings) {
        return update_option($this->option_name, $new_settings);
    }
    
    public function sanitize_settings($input) {
        $sanitized = [];
        
        // Security settings
        if (isset($input['security'])) {
            $sanitized['security'] = [
                'rate_limit_enabled' => !empty($input['security']['rate_limit_enabled']),
                'rate_limit_requests' => absint($input['security']['rate_limit_requests']),
                'rate_limit_window' => absint($input['security']['rate_limit_window']),
                'api_key_rotation_days' => absint($input['security']['api_key_rotation_days']),
                'log_api_calls' => !empty($input['security']['log_api_calls']),
                'sanitize_filenames' => !empty($input['security']['sanitize_filenames']),
                'allowed_file_types' => isset($input['security']['allowed_file_types']) ? 
                    array_map('sanitize_text_field', $input['security']['allowed_file_types']) : 
                    $this->default_settings['security']['allowed_file_types'],
                'max_file_size_mb' => absint($input['security']['max_file_size_mb'])
            ];
        }
        
        // Performance settings
        if (isset($input['performance'])) {
            $sanitized['performance'] = [
                'batch_processing_enabled' => !empty($input['performance']['batch_processing_enabled']),
                'batch_size' => absint($input['performance']['batch_size']),
                'processing_timeout' => absint($input['performance']['processing_timeout']),
                'cache_enabled' => !empty($input['performance']['cache_enabled']),
                'cache_duration' => absint($input['performance']['cache_duration']),
                'queue_processing' => !empty($input['performance']['queue_processing']),
                'concurrent_requests' => absint($input['performance']['concurrent_requests']),
                'memory_limit_mb' => absint($input['performance']['memory_limit_mb'])
            ];
        }
        
        // AI settings
        if (isset($input['ai'])) {
            $sanitized['ai'] = [
                'context_analysis_enabled' => !empty($input['ai']['context_analysis_enabled']),
                'fallback_naming_enabled' => !empty($input['ai']['fallback_naming_enabled']),
                'confidence_threshold' => floatval($input['ai']['confidence_threshold']),
                'max_retries' => absint($input['ai']['max_retries']),
                'custom_prompts_enabled' => !empty($input['ai']['custom_prompts_enabled']),
                'language_detection' => !empty($input['ai']['language_detection'])
            ];
        }
        
        // Logging settings
        if (isset($input['logging'])) {
            $allowed_levels = ['debug', 'info', 'warning', 'error'];
            $sanitized['logging'] = [
                'enabled' => !empty($input['logging']['enabled']),
                'level' => in_array($input['logging']['level'], $allowed_levels) ? 
                    $input['logging']['level'] : 'info',
                'max_log_size_mb' => absint($input['logging']['max_log_size_mb']),
                'retention_days' => absint($input['logging']['retention_days']),
                'log_api_responses' => !empty($input['logging']['log_api_responses'])
            ];
        }
        
        return array_merge($this->get_settings(), $sanitized);
    }
    
    // AJAX Handlers
    public function reset_settings() {
        check_ajax_referer('fmrseo_settings_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'fmrseo'));
        }
        
        delete_option($this->option_name);
        wp_send_json_success();
    }
    
    public function export_settings() {
        check_ajax_referer('fmrseo_settings_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'fmrseo'));
        }
        
        $settings = $this->get_settings();
        wp_send_json_success($settings);
    }
    
    public function import_settings() {
        check_ajax_referer('fmrseo_settings_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'fmrseo'));
        }
        
        $settings_data = sanitize_textarea_field($_POST['settings_data']);
        $settings = json_decode($settings_data, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(__('Invalid JSON format', 'fmrseo'));
        }
        
        $sanitized_settings = $this->sanitize_settings($settings);
        $this->update_settings($sanitized_settings);
        
        wp_send_json_success();
    }
    
    // Helper Methods
    private function get_nested_value($array, $path) {
        $current = $array;
        foreach ($path as $key) {
            if (!isset($current[$key])) {
                return null;
            }
            $current = $current[$key];
        }
        return $current;
    }
    
    public function get_setting($path) {
        $settings = $this->get_settings();
        $keys = explode('.', $path);
        return $this->get_nested_value($settings, $keys);
    }
    
    public function is_feature_enabled($feature) {
        return $this->get_setting($feature) === true;
    }
}

// Initialize the settings extension
new FMRSEO_Settings_Extension();