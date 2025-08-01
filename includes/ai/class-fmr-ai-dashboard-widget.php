<?php

/**
 * AI Dashboard Widget for File Media Renamer for SEO
 * 
 * Displays credit balance and AI usage statistics on WordPress dashboard
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class FMR_AI_Dashboard_Widget {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widget'));
        add_action('wp_ajax_fmrseo_refresh_dashboard_stats', array($this, 'ajax_refresh_dashboard_stats'));
        add_action('wp_ajax_fmr_get_security_status', array($this, 'handle_security_status_ajax'));
        add_action('wp_ajax_fmr_get_performance_metrics', array($this, 'handle_performance_metrics_ajax'));
    }

    /**
     * Add dashboard widget
     */
    public function add_dashboard_widget() {
        // Only show to users who can manage options
        if (!current_user_can('manage_options')) {
            return;
        }

        // Only show if AI is enabled
        $options = get_option('fmrseo_options', array());
        if (empty($options['ai_enabled'])) {
            return;
        }

        wp_add_dashboard_widget(
            'fmrseo_ai_dashboard_widget',
            __('AI Media Renamer Statistics', 'fmrseo'),
            array($this, 'render_dashboard_widget'),
            array($this, 'render_dashboard_widget_config')
        );
    }

    /**
     * Render the dashboard widget content
     */
    public function render_dashboard_widget() {
        // Get current user's credit data
        $credit_manager = class_exists('FMR_Credit_Manager') ? new FMR_Credit_Manager() : null;
        $security_manager = class_exists('FMR_Security_Manager') ? new FMR_Security_Manager() : null;
        $performance_optimizer = class_exists('FMR_Performance_Optimizer') ? new FMR_Performance_Optimizer() : null;
        
        echo '<div class="fmr-ai-dashboard-widget">';
        
        // Credit Balance Section
        if ($credit_manager) {
            $balance = $credit_manager->get_credit_balance();
            echo '<div class="fmr-widget-section">';
            echo '<h4>' . __('Credit Balance', 'fmrseo') . '</h4>';
            echo '<div class="fmr-credit-display">';
            echo '<span class="fmr-credit-amount">' . number_format($balance) . '</span> ';
            echo '<span class="fmr-credit-label">' . __('credits remaining', 'fmrseo') . '</span>';
            echo '</div>';
            echo '</div>';
        }
        
        // Security Status Section
        echo '<div class="fmr-widget-section">';
        echo '<h4>' . __('Security Status', 'fmrseo') . '</h4>';
        echo '<div id="fmr-security-status">';
        echo '<span class="spinner is-active"></span> ' . __('Loading security status...', 'fmrseo');
        echo '</div>';
        echo '</div>';
        
        // Performance Metrics Section
        echo '<div class="fmr-widget-section">';
        echo '<h4>' . __('Performance Metrics', 'fmrseo') . '</h4>';
        echo '<div id="fmr-performance-metrics">';
        echo '<span class="spinner is-active"></span> ' . __('Loading performance data...', 'fmrseo');
        echo '</div>';
        echo '</div>';
        
        // Cache Statistics Section
        if ($performance_optimizer) {
            $cache_stats = $performance_optimizer->get_cache_stats();
            echo '<div class="fmr-widget-section">';
            echo '<h4>' . __('Cache Statistics', 'fmrseo') . '</h4>';
            echo '<div class="fmr-cache-stats">';
            echo '<div class="fmr-stat-item">';
            echo '<span class="fmr-stat-label">' . __('Cache Entries:', 'fmrseo') . '</span> ';
            echo '<span class="fmr-stat-value">' . number_format($cache_stats['total_entries']) . '</span>';
            echo '</div>';
            echo '<div class="fmr-stat-item">';
            echo '<span class="fmr-stat-label">' . __('Cache Size:', 'fmrseo') . '</span> ';
            echo '<span class="fmr-stat-value">' . $cache_stats['total_size_mb'] . ' MB</span>';
            echo '</div>';
            echo '<div class="fmr-stat-item">';
            echo '<span class="fmr-stat-label">' . __('Status:', 'fmrseo') . '</span> ';
            echo '<span class="fmr-stat-value ' . ($cache_stats['cache_enabled'] ? 'enabled' : 'disabled') . '">';
            echo $cache_stats['cache_enabled'] ? __('Enabled', 'fmrseo') : __('Disabled', 'fmrseo');
            echo '</span>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
        }
        
        // Quick Actions Section
        echo '<div class="fmr-widget-section">';
        echo '<h4>' . __('Quick Actions', 'fmrseo') . '</h4>';
        echo '<div class="fmr-quick-actions">';
        echo '<button type="button" id="fmr-refresh-stats" class="button button-small">' . __('Refresh Stats', 'fmrseo') . '</button> ';
        if ($performance_optimizer) {
            echo '<button type="button" id="fmr-clear-cache" class="button button-small">' . __('Clear Cache', 'fmrseo') . '</button>';
        }
        echo '</div>';
        echo '</div>';
        
        echo '</div>';
        
        // Add widget styles and scripts
        $this->add_widget_assets();
        $user_id = get_current_user_id();
        $credit_data = get_user_meta($user_id, '_fmrseo_ai_credits', true);
        
        if (!is_array($credit_data)) {
            $credit_data = array(
                'balance' => 0,
                'used_total' => 0,
                'transactions' => array()
            );
        }

        // Get global statistics
        $stats = get_option('fmrseo_ai_statistics', array());
        if (!is_array($stats)) {
            $stats = array(
                'total_ai_operations' => 0,
                'successful_ai_operations' => 0,
                'total_credits_used' => 0,
                'average_processing_time' => 0
            );
        }

        // Calculate success rate
        $success_rate = 0;
        if ($stats['total_ai_operations'] > 0) {
            $success_rate = round(($stats['successful_ai_operations'] / $stats['total_ai_operations']) * 100, 1);
        }

        // Get recent activity (last 5 transactions)
        $recent_activity = array();
        if (isset($credit_data['transactions']) && is_array($credit_data['transactions'])) {
            $recent_activity = array_slice($credit_data['transactions'], 0, 5);
        }

        ?>
        <div class="fmrseo-dashboard-widget">
            <!-- Credit Balance Section -->
            <div class="fmrseo-widget-section">
                <h4 class="fmrseo-widget-title">
                    <span class="dashicons dashicons-star-filled"></span>
                    <?php esc_html_e('Credit Balance', 'fmrseo'); ?>
                </h4>
                <div class="fmrseo-credit-display">
                    <div class="fmrseo-credit-balance <?php echo $this->get_balance_class($credit_data['balance']); ?>">
                        <?php echo esc_html($credit_data['balance']); ?>
                    </div>
                    <div class="fmrseo-credit-label">
                        <?php esc_html_e('Available Credits', 'fmrseo'); ?>
                    </div>
                </div>
                
                <?php if ($credit_data['balance'] <= 5): ?>
                <div class="fmrseo-low-credits-warning">
                    <span class="dashicons dashicons-warning"></span>
                    <?php esc_html_e('Low credit balance! Consider purchasing more credits.', 'fmrseo'); ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Usage Statistics Section -->
            <div class="fmrseo-widget-section">
                <h4 class="fmrseo-widget-title">
                    <span class="dashicons dashicons-chart-area"></span>
                    <?php esc_html_e('Usage Statistics', 'fmrseo'); ?>
                </h4>
                <div class="fmrseo-stats-grid">
                    <div class="fmrseo-stat-item">
                        <div class="fmrseo-stat-number"><?php echo esc_html($credit_data['used_total']); ?></div>
                        <div class="fmrseo-stat-label"><?php esc_html_e('Credits Used', 'fmrseo'); ?></div>
                    </div>
                    <div class="fmrseo-stat-item">
                        <div class="fmrseo-stat-number"><?php echo esc_html($stats['total_ai_operations']); ?></div>
                        <div class="fmrseo-stat-label"><?php esc_html_e('AI Operations', 'fmrseo'); ?></div>
                    </div>
                    <div class="fmrseo-stat-item">
                        <div class="fmrseo-stat-number"><?php echo esc_html($success_rate); ?>%</div>
                        <div class="fmrseo-stat-label"><?php esc_html_e('Success Rate', 'fmrseo'); ?></div>
                    </div>
                    <div class="fmrseo-stat-item">
                        <div class="fmrseo-stat-number"><?php echo esc_html(round($stats['average_processing_time'], 1)); ?>s</div>
                        <div class="fmrseo-stat-label"><?php esc_html_e('Avg Time', 'fmrseo'); ?></div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity Section -->
            <?php if (!empty($recent_activity)): ?>
            <div class="fmrseo-widget-section">
                <h4 class="fmrseo-widget-title">
                    <span class="dashicons dashicons-clock"></span>
                    <?php esc_html_e('Recent Activity', 'fmrseo'); ?>
                </h4>
                <div class="fmrseo-recent-activity">
                    <?php foreach ($recent_activity as $transaction): ?>
                    <div class="fmrseo-activity-item">
                        <div class="fmrseo-activity-info">
                            <div class="fmrseo-activity-file">
                                <?php if (isset($transaction['filename']) && !empty($transaction['filename'])): ?>
                                    <?php echo esc_html($transaction['filename']); ?>
                                <?php else: ?>
                                    <?php esc_html_e('Unknown file', 'fmrseo'); ?>
                                <?php endif; ?>
                            </div>
                            <div class="fmrseo-activity-meta">
                                <span class="fmrseo-activity-type">
                                    <?php echo esc_html(ucfirst($transaction['operation'])); ?>
                                </span>
                                <span class="fmrseo-activity-date">
                                    <?php echo esc_html(human_time_diff($transaction['timestamp'], current_time('timestamp'))); ?> <?php esc_html_e('ago', 'fmrseo'); ?>
                                </span>
                            </div>
                        </div>
                        <div class="fmrseo-activity-credits">
                            -<?php echo esc_html($transaction['amount']); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Action Links -->
            <div class="fmrseo-widget-actions">
                <a href="<?php echo esc_url(admin_url('upload.php?page=fmrseo-ai-statistics')); ?>" class="button">
                    <?php esc_html_e('View Full Statistics', 'fmrseo'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('upload.php?page=fmrseo')); ?>" class="button">
                    <?php esc_html_e('Settings', 'fmrseo'); ?>
                </a>
                <button type="button" class="button" id="fmrseo-refresh-stats">
                    <?php esc_html_e('Refresh', 'fmrseo'); ?>
                </button>
            </div>
        </div>

        <style>
        .fmrseo-dashboard-widget {
            font-size: 13px;
        }
        
        .fmrseo-widget-section {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .fmrseo-widget-section:last-of-type {
            border-bottom: none;
            margin-bottom: 10px;
        }
        
        .fmrseo-widget-title {
            margin: 0 0 10px 0;
            font-size: 14px;
            font-weight: 600;
            color: #23282d;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .fmrseo-credit-display {
            text-align: center;
            margin-bottom: 10px;
        }
        
        .fmrseo-credit-balance {
            font-size: 36px;
            font-weight: bold;
            line-height: 1;
        }
        
        .fmrseo-credit-balance.high {
            color: #46b450;
        }
        
        .fmrseo-credit-balance.medium {
            color: #ffb900;
        }
        
        .fmrseo-credit-balance.low {
            color: #dc3232;
        }
        
        .fmrseo-credit-label {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        .fmrseo-low-credits-warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 3px;
            padding: 8px;
            font-size: 12px;
            color: #856404;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .fmrseo-stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        
        .fmrseo-stat-item {
            text-align: center;
            padding: 10px;
            background: #f9f9f9;
            border-radius: 3px;
        }
        
        .fmrseo-stat-number {
            font-size: 18px;
            font-weight: bold;
            color: #0073aa;
            line-height: 1;
        }
        
        .fmrseo-stat-label {
            font-size: 11px;
            color: #666;
            margin-top: 3px;
        }
        
        .fmrseo-recent-activity {
            max-height: 150px;
            overflow-y: auto;
        }
        
        .fmrseo-activity-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .fmrseo-activity-item:last-child {
            border-bottom: none;
        }
        
        .fmrseo-activity-info {
            flex: 1;
        }
        
        .fmrseo-activity-file {
            font-weight: 500;
            font-size: 12px;
            color: #23282d;
            margin-bottom: 2px;
        }
        
        .fmrseo-activity-meta {
            font-size: 11px;
            color: #666;
        }
        
        .fmrseo-activity-type {
            background: #e1f5fe;
            color: #0277bd;
            padding: 1px 4px;
            border-radius: 2px;
            margin-right: 5px;
        }
        
        .fmrseo-activity-credits {
            font-weight: bold;
            color: #dc3232;
            font-size: 12px;
        }
        
        .fmrseo-widget-actions {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        
        .fmrseo-widget-actions .button {
            font-size: 11px;
            height: auto;
            padding: 4px 8px;
        }
        </style>

        <script>
        jQuery(document).ready(function($) {
            $('#fmrseo-refresh-stats').on('click', function() {
                var button = $(this);
                button.prop('disabled', true).text('<?php esc_html_e('Refreshing...', 'fmrseo'); ?>');
                
                $.post(ajaxurl, {
                    action: 'fmrseo_refresh_dashboard_stats',
                    nonce: '<?php echo wp_create_nonce('fmrseo_dashboard_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('<?php esc_html_e('Failed to refresh statistics.', 'fmrseo'); ?>');
                    }
                }).always(function() {
                    button.prop('disabled', false).text('<?php esc_html_e('Refresh', 'fmrseo'); ?>');
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Render dashboard widget configuration
     */
    public function render_dashboard_widget_config() {
        // Widget configuration options can be added here if needed
        ?>
        <p>
            <label for="fmrseo_widget_show_recent">
                <input type="checkbox" id="fmrseo_widget_show_recent" name="fmrseo_widget_show_recent" value="1" checked>
                <?php esc_html_e('Show recent activity', 'fmrseo'); ?>
            </label>
        </p>
        <?php
    }

    /**
     * Get CSS class for credit balance display
     *
     * @param int $balance Credit balance
     * @return string CSS class
     */
    private function get_balance_class($balance) {
        if ($balance > 20) {
            return 'high';
        } elseif ($balance > 5) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    /**
     * AJAX handler for refreshing dashboard statistics
     */
    public function ajax_refresh_dashboard_stats() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'fmrseo'));
        }

        if (!wp_verify_nonce($_POST['nonce'], 'fmrseo_dashboard_nonce')) {
            wp_send_json_error(__('Security verification failed.', 'fmrseo'));
        }

        // Force refresh of credit balance from API
        $options = get_option('fmrseo_options', array());
        if (!empty($options['ai_api_key'])) {
            // This would typically make an API call to refresh the balance
            // For now, we'll just return success
            wp_send_json_success(__('Statistics refreshed.', 'fmrseo'));
        } else {
            wp_send_json_error(__('No API key configured.', 'fmrseo'));
        }
    }
} 
   }

    /**
     * Add widget assets (CSS and JavaScript)
     */
    private function add_widget_assets() {
        ?>
        <style type="text/css">
        .fmr-ai-dashboard-widget .fmr-widget-section {
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        .fmr-ai-dashboard-widget .fmr-widget-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        .fmr-ai-dashboard-widget h4 {
            margin: 0 0 8px 0;
            font-size: 13px;
            color: #23282d;
        }
        .fmr-credit-display {
            font-size: 18px;
            font-weight: bold;
        }
        .fmr-credit-amount {
            color: #0073aa;
        }
        .fmr-credit-label {
            font-size: 12px;
            color: #666;
            font-weight: normal;
        }
        .fmr-stat-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            font-size: 12px;
        }
        .fmr-stat-label {
            color: #666;
        }
        .fmr-stat-value {
            font-weight: bold;
        }
        .fmr-stat-value.enabled {
            color: #46b450;
        }
        .fmr-stat-value.disabled {
            color: #dc3232;
        }
        .fmr-quick-actions {
            text-align: center;
        }
        .fmr-security-status {
            font-size: 12px;
        }
        .fmr-security-good {
            color: #46b450;
        }
        .fmr-security-warning {
            color: #ffb900;
        }
        .fmr-security-error {
            color: #dc3232;
        }
        .fmr-performance-metric {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
            font-size: 11px;
        }
        </style>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Load initial security status
            loadSecurityStatus();
            
            // Load initial performance metrics
            loadPerformanceMetrics();
            
            // Refresh stats button
            $('#fmr-refresh-stats').on('click', function() {
                var button = $(this);
                button.prop('disabled', true).text('<?php echo esc_js(__('Refreshing...', 'fmrseo')); ?>');
                
                loadSecurityStatus();
                loadPerformanceMetrics();
                
                setTimeout(function() {
                    button.prop('disabled', false).text('<?php echo esc_js(__('Refresh Stats', 'fmrseo')); ?>');
                }, 2000);
            });
            
            // Clear cache button
            $('#fmr-clear-cache').on('click', function() {
                var button = $(this);
                button.prop('disabled', true).text('<?php echo esc_js(__('Clearing...', 'fmrseo')); ?>');
                
                $.post(ajaxurl, {
                    action: 'fmr_clear_ai_cache',
                    nonce: '<?php echo wp_create_nonce('fmr_management_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        // Refresh cache stats
                        location.reload();
                    }
                }).always(function() {
                    button.prop('disabled', false).text('<?php echo esc_js(__('Clear Cache', 'fmrseo')); ?>');
                });
            });
            
            function loadSecurityStatus() {
                $('#fmr-security-status').html('<span class="spinner is-active"></span> <?php echo esc_js(__('Loading...', 'fmrseo')); ?>');
                
                $.post(ajaxurl, {
                    action: 'fmr_get_security_status',
                    nonce: '<?php echo wp_create_nonce('fmr_dashboard_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        var html = '';
                        var data = response.data;
                        
                        html += '<div class="fmr-security-status fmr-security-' + data.overall_status + '">';
                        html += '<strong>' + data.status_text + '</strong>';
                        html += '</div>';
                        
                        if (data.recent_events > 0) {
                            html += '<div class="fmr-stat-item">';
                            html += '<span class="fmr-stat-label"><?php echo esc_js(__('Recent Events:', 'fmrseo')); ?></span>';
                            html += '<span class="fmr-stat-value">' + data.recent_events + '</span>';
                            html += '</div>';
                        }
                        
                        if (data.rate_limit_hits > 0) {
                            html += '<div class="fmr-stat-item">';
                            html += '<span class="fmr-stat-label"><?php echo esc_js(__('Rate Limit Hits:', 'fmrseo')); ?></span>';
                            html += '<span class="fmr-stat-value fmr-security-warning">' + data.rate_limit_hits + '</span>';
                            html += '</div>';
                        }
                        
                        $('#fmr-security-status').html(html);
                    } else {
                        $('#fmr-security-status').html('<span class="fmr-security-error"><?php echo esc_js(__('Failed to load security status', 'fmrseo')); ?></span>');
                    }
                });
            }
            
            function loadPerformanceMetrics() {
                $('#fmr-performance-metrics').html('<span class="spinner is-active"></span> <?php echo esc_js(__('Loading...', 'fmrseo')); ?>');
                
                $.post(ajaxurl, {
                    action: 'fmr_get_performance_metrics',
                    nonce: '<?php echo wp_create_nonce('fmr_dashboard_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        var html = '';
                        var metrics = response.data.metrics;
                        
                        if (metrics.length > 0) {
                            var avgTime = metrics.reduce(function(sum, m) { return sum + m.execution_time; }, 0) / metrics.length;
                            var avgMemory = metrics.reduce(function(sum, m) { return sum + m.memory_usage_mb; }, 0) / metrics.length;
                            
                            html += '<div class="fmr-performance-metric">';
                            html += '<span><?php echo esc_js(__('Avg Execution Time:', 'fmrseo')); ?></span>';
                            html += '<span>' + avgTime.toFixed(3) + 's</span>';
                            html += '</div>';
                            
                            html += '<div class="fmr-performance-metric">';
                            html += '<span><?php echo esc_js(__('Avg Memory Usage:', 'fmrseo')); ?></span>';
                            html += '<span>' + avgMemory.toFixed(1) + 'MB</span>';
                            html += '</div>';
                            
                            html += '<div class="fmr-performance-metric">';
                            html += '<span><?php echo esc_js(__('Recent Operations:', 'fmrseo')); ?></span>';
                            html += '<span>' + metrics.length + '</span>';
                            html += '</div>';
                        } else {
                            html = '<span class="fmr-stat-label"><?php echo esc_js(__('No recent performance data', 'fmrseo')); ?></span>';
                        }
                        
                        $('#fmr-performance-metrics').html(html);
                    } else {
                        $('#fmr-performance-metrics').html('<span class="fmr-security-error"><?php echo esc_js(__('Failed to load performance metrics', 'fmrseo')); ?></span>');
                    }
                });
            }
        });
        </script>
        <?php
    }

    /**
     * Handle AJAX request for security status
     */
    public function handle_security_status_ajax() {
        try {
            // Verify nonce
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'fmr_dashboard_nonce')) {
                throw new Exception(__('Security verification failed.', 'fmrseo'));
            }

            // Check permissions
            if (!current_user_can('upload_files')) {
                throw new Exception(__('Insufficient permissions.', 'fmrseo'));
            }

            $security_data = array(
                'overall_status' => 'good',
                'status_text' => __('Security Status: Good', 'fmrseo'),
                'recent_events' => 0,
                'rate_limit_hits' => 0
            );

            // Get security statistics if security manager is available
            if (class_exists('FMR_Security_Manager')) {
                global $wpdb;
                
                $table_name = $wpdb->prefix . 'fmrseo_security_log';
                
                // Check if security log table exists
                if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
                    // Get recent security events (last 24 hours)
                    $recent_events = $wpdb->get_var($wpdb->prepare("
                        SELECT COUNT(*) 
                        FROM $table_name 
                        WHERE timestamp > %d
                    ", time() - 86400));
                    
                    // Get rate limit hits (last 24 hours)
                    $rate_limit_hits = $wpdb->get_var($wpdb->prepare("
                        SELECT COUNT(*) 
                        FROM $table_name 
                        WHERE event_type = 'rate_limit_exceeded' 
                        AND timestamp > %d
                    ", time() - 86400));
                    
                    $security_data['recent_events'] = intval($recent_events);
                    $security_data['rate_limit_hits'] = intval($rate_limit_hits);
                    
                    // Determine overall status
                    if ($rate_limit_hits > 10) {
                        $security_data['overall_status'] = 'error';
                        $security_data['status_text'] = __('Security Status: High Alert', 'fmrseo');
                    } elseif ($rate_limit_hits > 5 || $recent_events > 20) {
                        $security_data['overall_status'] = 'warning';
                        $security_data['status_text'] = __('Security Status: Warning', 'fmrseo');
                    }
                }
            }

            wp_send_json_success($security_data);

        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * Handle AJAX request for performance metrics
     */
    public function handle_performance_metrics_ajax() {
        try {
            // Verify nonce
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'fmr_dashboard_nonce')) {
                throw new Exception(__('Security verification failed.', 'fmrseo'));
            }

            // Check permissions
            if (!current_user_can('upload_files')) {
                throw new Exception(__('Insufficient permissions.', 'fmrseo'));
            }

            $performance_data = array(
                'metrics' => array()
            );

            // Get performance metrics if performance optimizer is available
            if (class_exists('FMR_Performance_Optimizer')) {
                $performance_optimizer = new FMR_Performance_Optimizer();
                $metrics = $performance_optimizer->get_performance_metrics(10);
                $performance_data['metrics'] = $metrics;
            }

            wp_send_json_success($performance_data);

        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * Handle AJAX request to refresh dashboard stats
     */
    public function ajax_refresh_dashboard_stats() {
        try {
            // Verify nonce
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'fmr_dashboard_nonce')) {
                throw new Exception(__('Security verification failed.', 'fmrseo'));
            }

            // Check permissions
            if (!current_user_can('upload_files')) {
                throw new Exception(__('Insufficient permissions.', 'fmrseo'));
            }

            // Get fresh statistics
            $stats = array();

            // Credit balance
            if (class_exists('FMR_Credit_Manager')) {
                $credit_manager = new FMR_Credit_Manager();
                $stats['credit_balance'] = $credit_manager->get_credit_balance();
            }

            // Cache statistics
            if (class_exists('FMR_Performance_Optimizer')) {
                $performance_optimizer = new FMR_Performance_Optimizer();
                $stats['cache_stats'] = $performance_optimizer->get_cache_stats();
            }

            wp_send_json_success($stats);

        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * Render dashboard widget configuration
     */
    public function render_dashboard_widget_config() {
        echo '<p>' . __('Configure AI dashboard widget display options.', 'fmrseo') . '</p>';
    }
}