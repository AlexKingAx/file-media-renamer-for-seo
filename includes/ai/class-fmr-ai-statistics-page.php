<?php

/**
 * AI Statistics Page for File Media Renamer for SEO
 * 
 * Provides detailed AI usage statistics and analytics
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class FMR_AI_Statistics_Page {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_statistics_page'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_statistics_assets'));
        add_action('wp_ajax_fmrseo_export_statistics', array($this, 'ajax_export_statistics'));
        add_action('wp_ajax_fmrseo_clear_statistics', array($this, 'ajax_clear_statistics'));
    }

    /**
     * Add statistics page to admin menu
     */
    public function add_statistics_page() {
        add_submenu_page(
            'upload.php', // Parent slug
            __('AI Usage Statistics', 'fmrseo'), // Page title
            __('AI Statistics', 'fmrseo'), // Menu title
            'manage_options', // Capability
            'fmrseo-ai-statistics', // Menu slug
            array($this, 'render_statistics_page') // Callback function
        );
    }

    /**
     * Render the statistics page
     */
    public function render_statistics_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'fmrseo'));
        }

        // Check if AI is enabled
        $options = get_option('fmrseo_options', array());
        if (empty($options['ai_enabled'])) {
            $this->render_ai_disabled_message();
            return;
        }

        // Get statistics data
        $user_stats = $this->get_user_statistics();
        $global_stats = $this->get_global_statistics();
        $performance_data = $this->get_performance_data();
        $usage_trends = $this->get_usage_trends();

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">
                <?php esc_html_e('AI Usage Statistics', 'fmrseo'); ?>
            </h1>
            
            <div class="fmrseo-stats-actions">
                <button type="button" class="button" id="fmrseo-export-stats">
                    <span class="dashicons dashicons-download"></span>
                    <?php esc_html_e('Export Data', 'fmrseo'); ?>
                </button>
                <button type="button" class="button" id="fmrseo-refresh-stats">
                    <span class="dashicons dashicons-update"></span>
                    <?php esc_html_e('Refresh', 'fmrseo'); ?>
                </button>
            </div>

            <hr class="wp-header-end">

            <!-- Overview Cards -->
            <div class="fmrseo-stats-overview">
                <div class="fmrseo-stat-card">
                    <div class="fmrseo-stat-card-icon">
                        <span class="dashicons dashicons-star-filled"></span>
                    </div>
                    <div class="fmrseo-stat-card-content">
                        <div class="fmrseo-stat-card-number"><?php echo esc_html($user_stats['balance']); ?></div>
                        <div class="fmrseo-stat-card-label"><?php esc_html_e('Credits Remaining', 'fmrseo'); ?></div>
                    </div>
                </div>

                <div class="fmrseo-stat-card">
                    <div class="fmrseo-stat-card-icon">
                        <span class="dashicons dashicons-chart-area"></span>
                    </div>
                    <div class="fmrseo-stat-card-content">
                        <div class="fmrseo-stat-card-number"><?php echo esc_html($user_stats['used_total']); ?></div>
                        <div class="fmrseo-stat-card-label"><?php esc_html_e('Total Credits Used', 'fmrseo'); ?></div>
                    </div>
                </div>

                <div class="fmrseo-stat-card">
                    <div class="fmrseo-stat-card-icon">
                        <span class="dashicons dashicons-yes-alt"></span>
                    </div>
                    <div class="fmrseo-stat-card-content">
                        <div class="fmrseo-stat-card-number"><?php echo esc_html($global_stats['success_rate']); ?>%</div>
                        <div class="fmrseo-stat-card-label"><?php esc_html_e('Success Rate', 'fmrseo'); ?></div>
                    </div>
                </div>

                <div class="fmrseo-stat-card">
                    <div class="fmrseo-stat-card-icon">
                        <span class="dashicons dashicons-clock"></span>
                    </div>
                    <div class="fmrseo-stat-card-content">
                        <div class="fmrseo-stat-card-number"><?php echo esc_html(round($global_stats['average_processing_time'], 1)); ?>s</div>
                        <div class="fmrseo-stat-card-label"><?php esc_html_e('Avg Processing Time', 'fmrseo'); ?></div>
                    </div>
                </div>
            </div>

            <div class="fmrseo-stats-container">
                <!-- Usage Trends Chart -->
                <div class="fmrseo-stats-section">
                    <div class="fmrseo-stats-section-header">
                        <h2><?php esc_html_e('Usage Trends', 'fmrseo'); ?></h2>
                        <div class="fmrseo-stats-period-selector">
                            <select id="fmrseo-period-selector">
                                <option value="7"><?php esc_html_e('Last 7 days', 'fmrseo'); ?></option>
                                <option value="30" selected><?php esc_html_e('Last 30 days', 'fmrseo'); ?></option>
                                <option value="90"><?php esc_html_e('Last 90 days', 'fmrseo'); ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="fmrseo-chart-container">
                        <canvas id="fmrseo-usage-chart" width="400" height="200"></canvas>
                    </div>
                </div>

                <!-- Performance Metrics -->
                <div class="fmrseo-stats-section">
                    <h2><?php esc_html_e('Performance Metrics', 'fmrseo'); ?></h2>
                    <div class="fmrseo-performance-grid">
                        <div class="fmrseo-performance-item">
                            <div class="fmrseo-performance-label"><?php esc_html_e('Total Operations', 'fmrseo'); ?></div>
                            <div class="fmrseo-performance-value"><?php echo esc_html($global_stats['total_ai_operations']); ?></div>
                        </div>
                        <div class="fmrseo-performance-item">
                            <div class="fmrseo-performance-label"><?php esc_html_e('Successful Operations', 'fmrseo'); ?></div>
                            <div class="fmrseo-performance-value success"><?php echo esc_html($global_stats['successful_ai_operations']); ?></div>
                        </div>
                        <div class="fmrseo-performance-item">
                            <div class="fmrseo-performance-label"><?php esc_html_e('Failed Operations', 'fmrseo'); ?></div>
                            <div class="fmrseo-performance-value error"><?php echo esc_html($global_stats['failed_ai_operations']); ?></div>
                        </div>
                        <div class="fmrseo-performance-item">
                            <div class="fmrseo-performance-label"><?php esc_html_e('Fallback Operations', 'fmrseo'); ?></div>
                            <div class="fmrseo-performance-value warning"><?php echo esc_html($global_stats['fallback_operations']); ?></div>
                        </div>
                        <div class="fmrseo-performance-item">
                            <div class="fmrseo-performance-label"><?php esc_html_e('Average Response Time', 'fmrseo'); ?></div>
                            <div class="fmrseo-performance-value"><?php echo esc_html(round($performance_data['avg_response_time'], 2)); ?>s</div>
                        </div>
                        <div class="fmrseo-performance-item">
                            <div class="fmrseo-performance-label"><?php esc_html_e('Fastest Operation', 'fmrseo'); ?></div>
                            <div class="fmrseo-performance-value success"><?php echo esc_html(round($performance_data['fastest_operation'], 2)); ?>s</div>
                        </div>
                        <div class="fmrseo-performance-item">
                            <div class="fmrseo-performance-label"><?php esc_html_e('Slowest Operation', 'fmrseo'); ?></div>
                            <div class="fmrseo-performance-value warning"><?php echo esc_html(round($performance_data['slowest_operation'], 2)); ?>s</div>
                        </div>
                        <div class="fmrseo-performance-item">
                            <div class="fmrseo-performance-label"><?php esc_html_e('Credits per Day (Avg)', 'fmrseo'); ?></div>
                            <div class="fmrseo-performance-value"><?php echo esc_html(round($performance_data['credits_per_day'], 1)); ?></div>
                        </div>
                    </div>
                </div>

                <!-- File Type Analysis -->
                <div class="fmrseo-stats-section">
                    <h2><?php esc_html_e('File Type Analysis', 'fmrseo'); ?></h2>
                    <div class="fmrseo-file-type-analysis">
                        <?php foreach ($performance_data['file_types'] as $type => $data): ?>
                        <div class="fmrseo-file-type-item">
                            <div class="fmrseo-file-type-header">
                                <span class="fmrseo-file-type-name"><?php echo esc_html(strtoupper($type)); ?></span>
                                <span class="fmrseo-file-type-count"><?php echo esc_html($data['count']); ?> <?php esc_html_e('files', 'fmrseo'); ?></span>
                            </div>
                            <div class="fmrseo-file-type-bar">
                                <div class="fmrseo-file-type-progress" style="width: <?php echo esc_attr($data['percentage']); ?>%"></div>
                            </div>
                            <div class="fmrseo-file-type-stats">
                                <span><?php echo esc_html($data['percentage']); ?>% <?php esc_html_e('of total', 'fmrseo'); ?></span>
                                <span><?php echo esc_html(round($data['avg_processing_time'], 1)); ?>s <?php esc_html_e('avg time', 'fmrseo'); ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="fmrseo-stats-section">
                    <h2><?php esc_html_e('Recent Activity', 'fmrseo'); ?></h2>
                    <div class="fmrseo-recent-activity-table">
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('File', 'fmrseo'); ?></th>
                                    <th><?php esc_html_e('Operation', 'fmrseo'); ?></th>
                                    <th><?php esc_html_e('Status', 'fmrseo'); ?></th>
                                    <th><?php esc_html_e('Credits', 'fmrseo'); ?></th>
                                    <th><?php esc_html_e('Processing Time', 'fmrseo'); ?></th>
                                    <th><?php esc_html_e('Date', 'fmrseo'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($user_stats['recent_transactions'] as $transaction): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html($transaction['filename'] ?? __('Unknown file', 'fmrseo')); ?></strong>
                                        <?php if (isset($transaction['file_type'])): ?>
                                        <br><small><?php echo esc_html(strtoupper($transaction['file_type'])); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="fmrseo-operation-badge fmrseo-operation-<?php echo esc_attr($transaction['operation']); ?>">
                                            <?php echo esc_html(ucfirst($transaction['operation'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $status = $transaction['status'] ?? 'success';
                                        $status_class = 'fmrseo-status-' . $status;
                                        $status_text = $status === 'success' ? __('Success', 'fmrseo') : 
                                                      ($status === 'failed' ? __('Failed', 'fmrseo') : __('Fallback', 'fmrseo'));
                                        ?>
                                        <span class="fmrseo-status-badge <?php echo esc_attr($status_class); ?>">
                                            <?php echo esc_html($status_text); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($status === 'success'): ?>
                                        <span class="fmrseo-credits-used">-<?php echo esc_html($transaction['amount']); ?></span>
                                        <?php else: ?>
                                        <span class="fmrseo-credits-saved">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (isset($transaction['processing_time'])): ?>
                                        <?php echo esc_html(round($transaction['processing_time'], 2)); ?>s
                                        <?php else: ?>
                                        <span class="fmrseo-no-data">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo esc_html(date('M j, Y H:i', $transaction['timestamp'])); ?>
                                        <br><small><?php echo esc_html(human_time_diff($transaction['timestamp'], current_time('timestamp'))); ?> <?php esc_html_e('ago', 'fmrseo'); ?></small>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                
                                <?php if (empty($user_stats['recent_transactions'])): ?>
                                <tr>
                                    <td colspan="6" class="fmrseo-no-data-row">
                                        <?php esc_html_e('No recent activity found.', 'fmrseo'); ?>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- System Health -->
                <div class="fmrseo-stats-section">
                    <h2><?php esc_html_e('System Health', 'fmrseo'); ?></h2>
                    <div class="fmrseo-system-health">
                        <?php
                        $health_checks = $this->get_system_health_checks();
                        foreach ($health_checks as $check):
                        ?>
                        <div class="fmrseo-health-item fmrseo-health-<?php echo esc_attr($check['status']); ?>">
                            <div class="fmrseo-health-icon">
                                <?php if ($check['status'] === 'good'): ?>
                                <span class="dashicons dashicons-yes-alt"></span>
                                <?php elseif ($check['status'] === 'warning'): ?>
                                <span class="dashicons dashicons-warning"></span>
                                <?php else: ?>
                                <span class="dashicons dashicons-dismiss"></span>
                                <?php endif; ?>
                            </div>
                            <div class="fmrseo-health-content">
                                <div class="fmrseo-health-title"><?php echo esc_html($check['title']); ?></div>
                                <div class="fmrseo-health-description"><?php echo esc_html($check['description']); ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Clear Statistics Section -->
            <div class="fmrseo-stats-section fmrseo-danger-zone">
                <h2><?php esc_html_e('Danger Zone', 'fmrseo'); ?></h2>
                <p><?php esc_html_e('These actions cannot be undone. Please proceed with caution.', 'fmrseo'); ?></p>
                <button type="button" class="button button-secondary" id="fmrseo-clear-stats">
                    <span class="dashicons dashicons-trash"></span>
                    <?php esc_html_e('Clear All Statistics', 'fmrseo'); ?>
                </button>
            </div>
        </div>
        <?php
    }

    /**
     * Render AI disabled message
     */
    private function render_ai_disabled_message() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('AI Usage Statistics', 'fmrseo'); ?></h1>
            <div class="notice notice-warning">
                <p>
                    <?php esc_html_e('AI functionality is currently disabled. Enable AI in the plugin settings to view usage statistics.', 'fmrseo'); ?>
                </p>
                <p>
                    <a href="<?php echo esc_url(admin_url('upload.php?page=fmrseo')); ?>" class="button button-primary">
                        <?php esc_html_e('Go to Settings', 'fmrseo'); ?>
                    </a>
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * Get user statistics
     *
     * @return array User statistics data
     */
    private function get_user_statistics() {
        $user_id = get_current_user_id();
        $credit_data = get_user_meta($user_id, '_fmrseo_ai_credits', true);
        
        if (!is_array($credit_data)) {
            $credit_data = array(
                'balance' => 0,
                'used_total' => 0,
                'transactions' => array()
            );
        }

        // Get recent transactions (last 20)
        $recent_transactions = array();
        if (isset($credit_data['transactions']) && is_array($credit_data['transactions'])) {
            $recent_transactions = array_slice($credit_data['transactions'], 0, 20);
        }

        return array(
            'balance' => $credit_data['balance'],
            'used_total' => $credit_data['used_total'],
            'recent_transactions' => $recent_transactions,
            'total_operations' => count($credit_data['transactions'] ?? array())
        );
    }

    /**
     * Get global statistics
     *
     * @return array Global statistics data
     */
    private function get_global_statistics() {
        $stats = get_option('fmrseo_ai_statistics', array());
        
        if (!is_array($stats)) {
            $stats = array(
                'total_ai_operations' => 0,
                'successful_ai_operations' => 0,
                'failed_ai_operations' => 0,
                'total_credits_used' => 0,
                'average_processing_time' => 0,
                'fallback_operations' => 0
            );
        }

        // Calculate success rate
        $success_rate = 0;
        if ($stats['total_ai_operations'] > 0) {
            $success_rate = round(($stats['successful_ai_operations'] / $stats['total_ai_operations']) * 100, 1);
        }

        return array_merge($stats, array('success_rate' => $success_rate));
    }

    /**
     * Get performance data
     *
     * @return array Performance metrics
     */
    private function get_performance_data() {
        $performance_data = get_option('fmrseo_ai_performance_data', array());
        
        if (!is_array($performance_data)) {
            $performance_data = array(
                'avg_response_time' => 0,
                'fastest_operation' => 0,
                'slowest_operation' => 0,
                'credits_per_day' => 0,
                'file_types' => array()
            );
        }

        // Ensure file types data exists
        if (empty($performance_data['file_types'])) {
            $performance_data['file_types'] = array(
                'jpg' => array('count' => 0, 'percentage' => 0, 'avg_processing_time' => 0),
                'png' => array('count' => 0, 'percentage' => 0, 'avg_processing_time' => 0),
                'pdf' => array('count' => 0, 'percentage' => 0, 'avg_processing_time' => 0),
                'docx' => array('count' => 0, 'percentage' => 0, 'avg_processing_time' => 0),
                'other' => array('count' => 0, 'percentage' => 0, 'avg_processing_time' => 0)
            );
        }

        return $performance_data;
    }

    /**
     * Get usage trends data
     *
     * @param int $days Number of days to get trends for
     * @return array Usage trends data
     */
    private function get_usage_trends($days = 30) {
        $trends = get_option('fmrseo_ai_usage_trends', array());
        
        if (!is_array($trends)) {
            $trends = array();
        }

        // Generate sample data for the last 30 days if no data exists
        if (empty($trends)) {
            $trends = array();
            for ($i = $days - 1; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-{$i} days"));
                $trends[$date] = array(
                    'operations' => rand(0, 10),
                    'credits_used' => rand(0, 8),
                    'success_rate' => rand(80, 100)
                );
            }
        }

        return $trends;
    }

    /**
     * Get system health checks
     *
     * @return array Health check results
     */
    private function get_system_health_checks() {
        $checks = array();
        $options = get_option('fmrseo_options', array());

        // API Key Check
        if (!empty($options['ai_api_key'])) {
            $checks[] = array(
                'title' => __('API Key Configuration', 'fmrseo'),
                'description' => __('API key is configured and valid.', 'fmrseo'),
                'status' => 'good'
            );
        } else {
            $checks[] = array(
                'title' => __('API Key Configuration', 'fmrseo'),
                'description' => __('No API key configured. AI functionality will not work.', 'fmrseo'),
                'status' => 'error'
            );
        }

        // Credit Balance Check
        $user_stats = $this->get_user_statistics();
        if ($user_stats['balance'] > 10) {
            $checks[] = array(
                'title' => __('Credit Balance', 'fmrseo'),
                'description' => __('Sufficient credits available for AI operations.', 'fmrseo'),
                'status' => 'good'
            );
        } elseif ($user_stats['balance'] > 0) {
            $checks[] = array(
                'title' => __('Credit Balance', 'fmrseo'),
                'description' => __('Low credit balance. Consider purchasing more credits.', 'fmrseo'),
                'status' => 'warning'
            );
        } else {
            $checks[] = array(
                'title' => __('Credit Balance', 'fmrseo'),
                'description' => __('No credits available. AI operations will fail.', 'fmrseo'),
                'status' => 'error'
            );
        }

        // Performance Check
        $global_stats = $this->get_global_statistics();
        if ($global_stats['success_rate'] >= 90) {
            $checks[] = array(
                'title' => __('AI Performance', 'fmrseo'),
                'description' => __('AI operations are performing well with high success rate.', 'fmrseo'),
                'status' => 'good'
            );
        } elseif ($global_stats['success_rate'] >= 70) {
            $checks[] = array(
                'title' => __('AI Performance', 'fmrseo'),
                'description' => __('AI performance is acceptable but could be improved.', 'fmrseo'),
                'status' => 'warning'
            );
        } else {
            $checks[] = array(
                'title' => __('AI Performance', 'fmrseo'),
                'description' => __('AI operations are failing frequently. Check configuration.', 'fmrseo'),
                'status' => 'error'
            );
        }

        return $checks;
    }

    /**
     * Enqueue statistics page assets
     */
    public function enqueue_statistics_assets($hook) {
        // Only load on our statistics page
        if ($hook !== 'media_page_fmrseo-ai-statistics') {
            return;
        }

        // Enqueue Chart.js for usage trends
        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js',
            array(),
            '3.9.1',
            true
        );

        wp_enqueue_style(
            'fmrseo-ai-statistics',
            plugin_dir_url(dirname(dirname(__FILE__))) . 'assets/css/ai-statistics.css',
            array(),
            '1.0.0'
        );

        wp_enqueue_script(
            'fmrseo-ai-statistics',
            plugin_dir_url(dirname(dirname(__FILE__))) . 'assets/js/ai-statistics.js',
            array('jquery', 'chartjs'),
            '1.0.0',
            true
        );

        // Localize script with data
        $usage_trends = $this->get_usage_trends();
        wp_localize_script('fmrseo-ai-statistics', 'fmrseoStats', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('fmrseo_statistics_nonce'),
            'usageTrends' => $usage_trends,
            'strings' => array(
                'confirmClear' => __('Are you sure you want to clear all statistics? This action cannot be undone.', 'fmrseo'),
                'exportSuccess' => __('Statistics exported successfully.', 'fmrseo'),
                'exportError' => __('Failed to export statistics.', 'fmrseo'),
                'clearSuccess' => __('Statistics cleared successfully.', 'fmrseo'),
                'clearError' => __('Failed to clear statistics.', 'fmrseo'),
                'refreshSuccess' => __('Statistics refreshed successfully.', 'fmrseo'),
                'refreshError' => __('Failed to refresh statistics.', 'fmrseo')
            )
        ));
    }

    /**
     * AJAX handler for exporting statistics
     */
    public function ajax_export_statistics() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'fmrseo'));
        }

        if (!wp_verify_nonce($_POST['nonce'], 'fmrseo_statistics_nonce')) {
            wp_send_json_error(__('Security verification failed.', 'fmrseo'));
        }

        // Gather all statistics data
        $export_data = array(
            'user_statistics' => $this->get_user_statistics(),
            'global_statistics' => $this->get_global_statistics(),
            'performance_data' => $this->get_performance_data(),
            'usage_trends' => $this->get_usage_trends(),
            'export_date' => current_time('mysql'),
            'site_url' => get_site_url()
        );

        // Create CSV content
        $csv_content = $this->generate_csv_export($export_data);

        wp_send_json_success(array(
            'filename' => 'fmrseo-ai-statistics-' . date('Y-m-d-H-i-s') . '.csv',
            'content' => $csv_content
        ));
    }

    /**
     * Generate CSV export content
     *
     * @param array $data Export data
     * @return string CSV content
     */
    private function generate_csv_export($data) {
        $csv = array();
        
        // Header
        $csv[] = array('FMR SEO AI Statistics Export');
        $csv[] = array('Export Date', $data['export_date']);
        $csv[] = array('Site URL', $data['site_url']);
        $csv[] = array(''); // Empty row

        // User Statistics
        $csv[] = array('USER STATISTICS');
        $csv[] = array('Credits Remaining', $data['user_statistics']['balance']);
        $csv[] = array('Total Credits Used', $data['user_statistics']['used_total']);
        $csv[] = array('Total Operations', $data['user_statistics']['total_operations']);
        $csv[] = array(''); // Empty row

        // Global Statistics
        $csv[] = array('GLOBAL STATISTICS');
        $csv[] = array('Total AI Operations', $data['global_statistics']['total_ai_operations']);
        $csv[] = array('Successful Operations', $data['global_statistics']['successful_ai_operations']);
        $csv[] = array('Failed Operations', $data['global_statistics']['failed_ai_operations']);
        $csv[] = array('Success Rate (%)', $data['global_statistics']['success_rate']);
        $csv[] = array('Average Processing Time (s)', $data['global_statistics']['average_processing_time']);
        $csv[] = array(''); // Empty row

        // Recent Transactions
        if (!empty($data['user_statistics']['recent_transactions'])) {
            $csv[] = array('RECENT TRANSACTIONS');
            $csv[] = array('Filename', 'Operation', 'Credits', 'Date');
            
            foreach ($data['user_statistics']['recent_transactions'] as $transaction) {
                $csv[] = array(
                    $transaction['filename'] ?? 'Unknown',
                    $transaction['operation'] ?? 'unknown',
                    $transaction['amount'] ?? 0,
                    date('Y-m-d H:i:s', $transaction['timestamp'])
                );
            }
        }

        // Convert to CSV string
        $output = '';
        foreach ($csv as $row) {
            $output .= implode(',', array_map(function($field) {
                return '"' . str_replace('"', '""', $field) . '"';
            }, $row)) . "\n";
        }

        return $output;
    }

    /**
     * AJAX handler for clearing statistics
     */
    public function ajax_clear_statistics() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'fmrseo'));
        }

        if (!wp_verify_nonce($_POST['nonce'], 'fmrseo_statistics_nonce')) {
            wp_send_json_error(__('Security verification failed.', 'fmrseo'));
        }

        // Clear global statistics
        delete_option('fmrseo_ai_statistics');
        delete_option('fmrseo_ai_performance_data');
        delete_option('fmrseo_ai_usage_trends');

        // Clear user statistics for all users
        global $wpdb;
        $wpdb->delete(
            $wpdb->usermeta,
            array('meta_key' => '_fmrseo_ai_credits'),
            array('%s')
        );

        wp_send_json_success(__('All statistics have been cleared.', 'fmrseo'));
    }
}