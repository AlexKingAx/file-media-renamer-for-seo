<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * AI Settings Extension Class
 * 
 * Extends the existing settings system with AI security and performance options.
 */
class FMR_AI_Settings_Extension {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_init', array($this, 'register_security_performance_settings'));
        add_filter('fmrseo_settings_tabs', array($this, 'add_security_performance_tab'));
        add_action('fmrseo_settings_tab_content_security_performance', array($this, 'render_security_performance_settings'));
        add_action('wp_ajax_fmr_test_security_settings', array($this, 'handle_test_security_settings'));
        add_action('wp_ajax_fmr_clear_ai_cache', array($this, 'handle_clear_ai_cache'));
        add_action('wp_ajax_fmr_cleanup_security_logs', array($this, 'handle_cleanup_security_logs'));
    }

    /**
     * Register security and performance settings
     */
    public function register_security_performance_settings() {
        // Security and performance settings are integrated into main options
        $options = get_option('fmrseo_options', array());
        
        // Set default security settings if not present
        $security_defaults = array(
            'ai_rate_limits' => array(
                'ai_rename_single' => array('requests' => 10, 'window' => 300),
                'ai_suggestions' => array('requests' => 20, 'window' => 300),
                'ai_bulk_rename' => array('requests' => 3, 'window' => 600),
                'ai_test_connection' => array('requests' => 5, 'window' => 60)
            ),
            'ai_security_logging' => true,
            'ai_input_validation' => true,
            'ai_max_bulk_files' => 50,
            'ai_security_log_retention' => 30
        );
        
        // Set default performance settings if not present
        $performance_defaults = array(
            'ai_cache_enabled' => true,
            'ai_cache_expiration_content_analysis' => 7200,
            'ai_cache_expiration_context' => 3600,
            'ai_cache_expiration_suggestions' => 1800,
            'ai_cache_expiration_urls' => 3600,
            'ai_performance_logging' => true,
            'ai_bulk_batch_size' => 10,
            'ai_query_optimization' => true
        );
        
        // Merge defaults with existing options
        foreach ($security_defaults as $key => $value) {
            if (!isset($options[$key])) {
                $options[$key] = $value;
            }
        }
        
        foreach ($performance_defaults as $key => $value) {
            if (!isset($options[$key])) {
                $options[$key] = $value;
            }
        }
        
        update_option('fmrseo_options', $options);
    }

    /**
     * Add security and performance tab to settings
     */
    public function add_security_performance_tab($tabs) {
        $tabs['security_performance'] = __('Security & Performance', 'fmrseo');
        return $tabs;
    }

    /**
     * Render security and performance settings
     */
    public function render_security_performance_settings() {
        $options = get_option('fmrseo_options', array());
        
        echo '<div class="fmrseo-security-performance-settings">';
        echo '<h3>' . __('Security & Performance Settings', 'fmrseo') . '</h3>';
        echo '<p>' . __('Configure security and performance options for AI functionality.', 'fmrseo') . '</p>';
        
        // Security Settings Section
        echo '<div class="fmrseo-settings-section">';
        echo '<h4>' . __('Security Settings', 'fmrseo') . '</h4>';
        
        // Rate Limiting Settings
        echo '<table class="form-table">';
        echo '<tr>';
        echo '<th scope="row">' . __('Enable Security Logging', 'fmrseo') . '</th>';
        echo '<td>';
        echo '<label><input type="checkbox" name="fmrseo_options[ai_security_logging]" value="1" ' . 
             checked(isset($options['ai_security_logging']) ? $options['ai_security_logging'] : true, true, false) . '> ';
        echo __('Log security events and suspicious activities', 'fmrseo') . '</label>';
        echo '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th scope="row">' . __('Input Validation', 'fmrseo') . '</th>';
        echo '<td>';
        echo '<label><input type="checkbox" name="fmrseo_options[ai_input_validation]" value="1" ' . 
             checked(isset($options['ai_input_validation']) ? $options['ai_input_validation'] : true, true, false) . '> ';
        echo __('Enable strict input validation for AI operations', 'fmrseo') . '</label>';
        echo '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th scope="row">' . __('Max Bulk Files', 'fmrseo') . '</th>';
        echo '<td>';
        echo '<input type="number" name="fmrseo_options[ai_max_bulk_files]" value="' . 
             esc_attr(isset($options['ai_max_bulk_files']) ? $options['ai_max_bulk_files'] : 50) . '" min="1" max="100" class="small-text">';
        echo '<p class="description">' . __('Maximum number of files allowed in bulk operations (1-100)', 'fmrseo') . '</p>';
        echo '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th scope="row">' . __('Security Log Retention', 'fmrseo') . '</th>';
        echo '<td>';
        echo '<input type="number" name="fmrseo_options[ai_security_log_retention]" value="' . 
             esc_attr(isset($options['ai_security_log_retention']) ? $options['ai_security_log_retention'] : 30) . '" min="1" max="365" class="small-text"> ';
        echo __('days', 'fmrseo');
        echo '<p class="description">' . __('Number of days to keep security logs', 'fmrseo') . '</p>';
        echo '</td>';
        echo '</tr>';
        echo '</table>';
        
        // Rate Limiting Configuration
        echo '<h5>' . __('Rate Limiting Configuration', 'fmrseo') . '</h5>';
        echo '<table class="form-table">';
        
        $rate_limits = isset($options['ai_rate_limits']) ? $options['ai_rate_limits'] : array();
        $default_limits = array(
            'ai_rename_single' => array('requests' => 10, 'window' => 300, 'label' => __('Single Rename', 'fmrseo')),
            'ai_suggestions' => array('requests' => 20, 'window' => 300, 'label' => __('AI Suggestions', 'fmrseo')),
            'ai_bulk_rename' => array('requests' => 3, 'window' => 600, 'label' => __('Bulk Rename', 'fmrseo')),
            'ai_test_connection' => array('requests' => 5, 'window' => 60, 'label' => __('Connection Test', 'fmrseo'))
        );
        
        foreach ($default_limits as $operation => $defaults) {
            $current = isset($rate_limits[$operation]) ? $rate_limits[$operation] : $defaults;
            
            echo '<tr>';
            echo '<th scope="row">' . $defaults['label'] . '</th>';
            echo '<td>';
            echo '<input type="number" name="fmrseo_options[ai_rate_limits][' . $operation . '][requests]" value="' . 
                 esc_attr($current['requests']) . '" min="1" max="100" class="small-text"> ';
            echo __('requests per', 'fmrseo') . ' ';
            echo '<input type="number" name="fmrseo_options[ai_rate_limits][' . $operation . '][window]" value="' . 
                 esc_attr($current['window']) . '" min="60" max="3600" class="small-text"> ';
            echo __('seconds', 'fmrseo');
            echo '</td>';
            echo '</tr>';
        }
        echo '</table>';
        echo '</div>';
        
        // Performance Settings Section
        echo '<div class="fmrseo-settings-section">';
        echo '<h4>' . __('Performance Settings', 'fmrseo') . '</h4>';
        
        echo '<table class="form-table">';
        echo '<tr>';
        echo '<th scope="row">' . __('Enable Caching', 'fmrseo') . '</th>';
        echo '<td>';
        echo '<label><input type="checkbox" name="fmrseo_options[ai_cache_enabled]" value="1" ' . 
             checked(isset($options['ai_cache_enabled']) ? $options['ai_cache_enabled'] : true, true, false) . '> ';
        echo __('Enable caching for AI results and database queries', 'fmrseo') . '</label>';
        echo '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th scope="row">' . __('Query Optimization', 'fmrseo') . '</th>';
        echo '<td>';
        echo '<label><input type="checkbox" name="fmrseo_options[ai_query_optimization]" value="1" ' . 
             checked(isset($options['ai_query_optimization']) ? $options['ai_query_optimization'] : true, true, false) . '> ';
        echo __('Enable database query optimization for context extraction', 'fmrseo') . '</label>';
        echo '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th scope="row">' . __('Performance Logging', 'fmrseo') . '</th>';
        echo '<td>';
        echo '<label><input type="checkbox" name="fmrseo_options[ai_performance_logging]" value="1" ' . 
             checked(isset($options['ai_performance_logging']) ? $options['ai_performance_logging'] : true, true, false) . '> ';
        echo __('Log performance metrics for monitoring and optimization', 'fmrseo') . '</label>';
        echo '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th scope="row">' . __('Bulk Batch Size', 'fmrseo') . '</th>';
        echo '<td>';
        echo '<input type="number" name="fmrseo_options[ai_bulk_batch_size]" value="' . 
             esc_attr(isset($options['ai_bulk_batch_size']) ? $options['ai_bulk_batch_size'] : 10) . '" min="1" max="20" class="small-text">';
        echo '<p class="description">' . __('Number of files to process in each batch for bulk operations', 'fmrseo') . '</p>';
        echo '</td>';
        echo '</tr>';
        echo '</table>';
        
        // Cache Expiration Settings
        echo '<h5>' . __('Cache Expiration Settings', 'fmrseo') . '</h5>';
        echo '<table class="form-table">';
        
        $cache_settings = array(
            'ai_cache_expiration_content_analysis' => array('label' => __('Content Analysis', 'fmrseo'), 'default' => 7200),
            'ai_cache_expiration_context' => array('label' => __('Context Extraction', 'fmrseo'), 'default' => 3600),
            'ai_cache_expiration_suggestions' => array('label' => __('AI Suggestions', 'fmrseo'), 'default' => 1800),
            'ai_cache_expiration_urls' => array('label' => __('URL Cache', 'fmrseo'), 'default' => 3600)
        );
        
        foreach ($cache_settings as $setting => $config) {
            echo '<tr>';
            echo '<th scope="row">' . $config['label'] . '</th>';
            echo '<td>';
            echo '<input type="number" name="fmrseo_options[' . $setting . ']" value="' . 
                 esc_attr(isset($options[$setting]) ? $options[$setting] : $config['default']) . '" min="300" max="86400" class="small-text"> ';
            echo __('seconds', 'fmrseo');
            echo '<p class="description">' . sprintf(__('Cache expiration time for %s (300-86400 seconds)', 'fmrseo'), strtolower($config['label'])) . '</p>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</table>';
        echo '</div>';
        
        // Management Actions
        echo '<div class="fmrseo-settings-section">';
        echo '<h4>' . __('Management Actions', 'fmrseo') . '</h4>';
        echo '<table class="form-table">';
        echo '<tr>';
        echo '<th scope="row">' . __('Cache Management', 'fmrseo') . '</th>';
        echo '<td>';
        echo '<button type="button" id="fmr-clear-ai-cache" class="button button-secondary">' . __('Clear AI Cache', 'fmrseo') . '</button> ';
        echo '<span id="fmr-cache-status"></span>';
        echo '<p class="description">' . __('Clear all cached AI results and performance data', 'fmrseo') . '</p>';
        echo '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th scope="row">' . __('Security Logs', 'fmrseo') . '</th>';
        echo '<td>';
        echo '<button type="button" id="fmr-cleanup-security-logs" class="button button-secondary">' . __('Cleanup Old Logs', 'fmrseo') . '</button> ';
        echo '<span id="fmr-logs-status"></span>';
        echo '<p class="description">' . __('Remove security logs older than the retention period', 'fmrseo') . '</p>';
        echo '</td>';
        echo '</tr>';
        echo '</table>';
        echo '</div>';
        
        echo '</div>';
        
        // Add JavaScript for management actions
        $this->add_management_scripts();
    }

    /**
     * Add JavaScript for management actions
     */
    private function add_management_scripts() {
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#fmr-clear-ai-cache').on('click', function() {
                var button = $(this);
                var status = $('#fmr-cache-status');
                
                button.prop('disabled', true).text('<?php echo esc_js(__('Clearing...', 'fmrseo')); ?>');
                status.text('');
                
                $.post(ajaxurl, {
                    action: 'fmr_clear_ai_cache',
                    nonce: '<?php echo wp_create_nonce('fmr_management_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        status.html('<span style="color: green;">' + response.data.message + '</span>');
                    } else {
                        status.html('<span style="color: red;">' + response.data.message + '</span>');
                    }
                }).always(function() {
                    button.prop('disabled', false).text('<?php echo esc_js(__('Clear AI Cache', 'fmrseo')); ?>');
                });
            });
            
            $('#fmr-cleanup-security-logs').on('click', function() {
                var button = $(this);
                var status = $('#fmr-logs-status');
                
                button.prop('disabled', true).text('<?php echo esc_js(__('Cleaning...', 'fmrseo')); ?>');
                status.text('');
                
                $.post(ajaxurl, {
                    action: 'fmr_cleanup_security_logs',
                    nonce: '<?php echo wp_create_nonce('fmr_management_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        status.html('<span style="color: green;">' + response.data.message + '</span>');
                    } else {
                        status.html('<span style="color: red;">' + response.data.message + '</span>');
                    }
                }).always(function() {
                    button.prop('disabled', false).text('<?php echo esc_js(__('Cleanup Old Logs', 'fmrseo')); ?>');
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Handle AJAX request to clear AI cache
     */
    public function handle_clear_ai_cache() {
        try {
            // Verify nonce
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'fmr_management_nonce')) {
                throw new Exception(__('Security verification failed.', 'fmrseo'));
            }

            // Check permissions
            if (!current_user_can('manage_options')) {
                throw new Exception(__('Insufficient permissions.', 'fmrseo'));
            }

            if (class_exists('FMR_Performance_Optimizer')) {
                $performance_optimizer = new FMR_Performance_Optimizer();
                $cleared_count = $performance_optimizer->clear_all_ai_cache();
                
                wp_send_json_success(array(
                    'message' => sprintf(__('Cleared %d cache entries successfully.', 'fmrseo'), $cleared_count)
                ));
            } else {
                throw new Exception(__('Performance optimizer not available.', 'fmrseo'));
            }

        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * Handle AJAX request to cleanup security logs
     */
    public function handle_cleanup_security_logs() {
        try {
            // Verify nonce
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'fmr_management_nonce')) {
                throw new Exception(__('Security verification failed.', 'fmrseo'));
            }

            // Check permissions
            if (!current_user_can('manage_options')) {
                throw new Exception(__('Insufficient permissions.', 'fmrseo'));
            }

            if (class_exists('FMR_Security_Manager')) {
                $security_manager = new FMR_Security_Manager();
                $options = get_option('fmrseo_options', array());
                $retention_days = isset($options['ai_security_log_retention']) ? intval($options['ai_security_log_retention']) : 30;
                
                $deleted_count = $security_manager->cleanup_security_logs($retention_days);
                
                wp_send_json_success(array(
                    'message' => sprintf(__('Cleaned up %d old security log entries.', 'fmrseo'), $deleted_count)
                ));
            } else {
                throw new Exception(__('Security manager not available.', 'fmrseo'));
            }

        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * Security section callback
     */
    public function security_section_callback() {
        echo '<p>' . __('Configure security settings for AI operations.', 'fmrseo') . '</p>';
    }

    /**
     * Performance section callback
     */
    public function performance_section_callback() {
        echo '<p>' . __('Configure performance optimization settings.', 'fmrseo') . '</p>';
    }
}