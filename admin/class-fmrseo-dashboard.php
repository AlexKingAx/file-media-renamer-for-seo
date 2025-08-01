<?php
/**
 * Dashboard for FMRSEO Plugin
 * Displays performance metrics, statistics, and system status
 */

if (!defined('ABSPATH')) {
    exit;
}

class FMRSEO_Dashboard {
    
    private $performance_monitor;
    private $settings;
    
    public function __construct() {
        $this->performance_monitor = new FMRSEO_Performance_Monitor();
        $this->settings = new FMRSEO_Settings_Extension();
        
        add_action('admin_menu', [$this, 'add_dashboard_page']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_dashboard_assets']);
        add_action('wp_ajax_fmrseo_refresh_dashboard', [$this, 'refresh_dashboard_data']);
    }
    
    public function add_dashboard_page() {
        add_submenu_page(
            'fmrseo-settings',
            __('Dashboard', 'fmrseo'),
            __('Dashboard', 'fmrseo'),
            'manage_options',
            'fmrseo-dashboard',
            [$this, 'dashboard_page_callback']
        );
    }
    
    public function enqueue_dashboard_assets($hook) {
        if ($hook !== 'fmrseo_page_fmrseo-dashboard') {
            return;
        }
        
        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', [], '3.9.1', true);
        wp_enqueue_script(
            'fmrseo-dashboard',
            plugin_dir_url(__FILE__) . '../assets/js/dashboard.js',
            ['jquery', 'chart-js'],
            FMRSEO_VERSION,
            true
        );
        
        wp_localize_script('fmrseo-dashboard', 'fmrseoAjax', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('fmrseo_admin_nonce'),
            'strings' => [
                'loading' => __('Loading...', 'fmrseo'),
                'error' => __('Error loading data', 'fmrseo'),
                'refresh' => __('Refresh', 'fmrseo'),
                'export' => __('Export Report', 'fmrseo')
            ]
        ]);
        
        wp_enqueue_style(
            'fmrseo-dashboard',
            plugin_dir_url(__FILE__) . '../assets/css/dashboard.css',
            [],
            FMRSEO_VERSION
        );
    }
    
    public function dashboard_page_callback() {
        $stats = $this->performance_monitor->get_performance_stats(7);
        $system_info = $this->performance_monitor->get_system_info();
        $issues = $this->performance_monitor->check_performance_issues();
        ?>
        <div class="wrap fmrseo-dashboard">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="fmrseo-dashboard-header">
                <div class="fmrseo-dashboard-actions">
                    <button type="button" class="button" id="fmrseo-refresh-dashboard">
                        <span class="dashicons dashicons-update"></span>
                        <?php _e('Refresh Data', 'fmrseo'); ?>
                    </button>
                    <button type="button" class="button" id="fmrseo-export-report">
                        <span class="dashicons dashicons-download"></span>
                        <?php _e('Export Report', 'fmrseo'); ?>
                    </button>
                    <select id="fmrseo-dashboard-period">
                        <option value="1"><?php _e('Last 24 hours', 'fmrseo'); ?></option>
                        <option value="7" selected><?php _e('Last 7 days', 'fmrseo'); ?></option>
                        <option value="30"><?php _e('Last 30 days', 'fmrseo'); ?></option>
                    </select>
                </div>
            </div>
            
            <!-- Status Overview -->
            <div class="fmrseo-dashboard-row">
                <div class="fmrseo-dashboard-col-4">
                    <div class="fmrseo-stat-card">
                        <div class="fmrseo-stat-icon success">
                            <span class="dashicons dashicons-yes-alt"></span>
                        </div>
                        <div class="fmrseo-stat-content">
                            <h3><?php echo number_format($stats['successful_operations']); ?></h3>
                            <p><?php _e('Successful Operations', 'fmrseo'); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="fmrseo-dashboard-col-4">
                    <div class="fmrseo-stat-card">
                        <div class="fmrseo-stat-icon error">
                            <span class="dashicons dashicons-warning"></span>
                        </div>
                        <div class="fmrseo-stat-content">
                            <h3><?php echo number_format($stats['failed_operations']); ?></h3>
                            <p><?php _e('Failed Operations', 'fmrseo'); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="fmrseo-dashboard-col-4">
                    <div class="fmrseo-stat-card">
                        <div class="fmrseo-stat-icon info">
                            <span class="dashicons dashicons-clock"></span>
                        </div>
                        <div class="fmrseo-stat-content">
                            <h3><?php echo round($stats['avg_response_time'], 2); ?>s</h3>
                            <p><?php _e('Avg Response Time', 'fmrseo'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Performance Issues -->
            <?php if (!empty($issues)): ?>
            <div class="fmrseo-dashboard-row">
                <div class="fmrseo-dashboard-col-12">
                    <div class="fmrseo-dashboard-card">
                        <h2><?php _e('Performance Issues', 'fmrseo'); ?></h2>
                        <div class="fmrseo-issues-list">
                            <?php foreach ($issues as $issue): ?>
                            <div class="fmrseo-issue-item severity-<?php echo esc_attr($issue['severity']); ?>">
                                <div class="fmrseo-issue-icon">
                                    <span class="dashicons dashicons-warning"></span>
                                </div>
                                <div class="fmrseo-issue-content">
                                    <h4><?php echo esc_html($issue['message']); ?></h4>
                                    <p><?php echo esc_html($issue['type']); ?></p>
                                    <?php if (isset($issue['action'])): ?>
                                    <p class="fmrseo-issue-action"><?php echo esc_html($issue['action']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Charts Row -->
            <div class="fmrseo-dashboard-row">
                <div class="fmrseo-dashboard-col-6">
                    <div class="fmrseo-dashboard-card">
                        <h2><?php _e('Operations Over Time', 'fmrseo'); ?></h2>
                        <canvas id="fmrseo-operations-chart"></canvas>
                    </div>
                </div>
                
                <div class="fmrseo-dashboard-col-6">
                    <div class="fmrseo-dashboard-card">
                        <h2><?php _e('Success Rate', 'fmrseo'); ?></h2>
                        <canvas id="fmrseo-success-chart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- API Performance -->
            <div class="fmrseo-dashboard-row">
                <div class="fmrseo-dashboard-col-6">
                    <div class="fmrseo-dashboard-card">
                        <h2><?php _e('API Performance', 'fmrseo'); ?></h2>
                        <div class="fmrseo-api-stats">
                            <?php foreach ($stats['api_calls'] as $provider => $data): ?>
                            <div class="fmrseo-api-provider">
                                <h4><?php echo esc_html(ucfirst($provider)); ?></h4>
                                <div class="fmrseo-api-metrics">
                                    <span class="metric">
                                        <strong><?php echo number_format($data['total']); ?></strong>
                                        <?php _e('Total Calls', 'fmrseo'); ?>
                                    </span>
                                    <span class="metric">
                                        <strong><?php echo $data['total'] > 0 ? round(($data['successful'] / $data['total']) * 100, 1) : 0; ?>%</strong>
                                        <?php _e('Success Rate', 'fmrseo'); ?>
                                    </span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <div class="fmrseo-dashboard-col-6">
                    <div class="fmrseo-dashboard-card">
                        <h2><?php _e('Rename Methods', 'fmrseo'); ?></h2>
                        <canvas id="fmrseo-methods-chart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- System Information -->
            <div class="fmrseo-dashboard-row">
                <div class="fmrseo-dashboard-col-12">
                    <div class="fmrseo-dashboard-card">
                        <h2><?php _e('System Information', 'fmrseo'); ?></h2>
                        <div class="fmrseo-system-info">
                            <div class="fmrseo-system-row">
                                <div class="fmrseo-system-col">
                                    <h4><?php _e('Server Environment', 'fmrseo'); ?></h4>
                                    <ul>
                                        <li><strong>PHP:</strong> <?php echo esc_html($system_info['php_version']); ?></li>
                                        <li><strong>WordPress:</strong> <?php echo esc_html($system_info['wordpress_version']); ?></li>
                                        <li><strong>Memory Limit:</strong> <?php echo esc_html($system_info['memory_limit']); ?></li>
                                        <li><strong>Max Execution Time:</strong> <?php echo esc_html($system_info['max_execution_time']); ?>s</li>
                                    </ul>
                                </div>
                                
                                <div class="fmrseo-system-col">
                                    <h4><?php _e('Memory Usage', 'fmrseo'); ?></h4>
                                    <ul>
                                        <li><strong>Current:</strong> <?php echo esc_html($system_info['current_memory_usage']); ?></li>
                                        <li><strong>Peak:</strong> <?php echo esc_html($system_info['peak_memory_usage']); ?></li>
                                    </ul>
                                    
                                    <?php if ($system_info['disk_space']): ?>
                                    <h4><?php _e('Disk Space', 'fmrseo'); ?></h4>
                                    <ul>
                                        <li><strong>Free:</strong> <?php echo esc_html($system_info['disk_space']['free']); ?></li>
                                        <li><strong>Used:</strong> <?php echo esc_html($system_info['disk_space']['used_percentage']); ?>%</li>
                                    </ul>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($system_info['server_load']): ?>
                                <div class="fmrseo-system-col">
                                    <h4><?php _e('Server Load', 'fmrseo'); ?></h4>
                                    <ul>
                                        <li><strong>1 min:</strong> <?php echo esc_html(round($system_info['server_load']['1min'], 2)); ?></li>
                                        <li><strong>5 min:</strong> <?php echo esc_html(round($system_info['server_load']['5min'], 2)); ?></li>
                                        <li><strong>15 min:</strong> <?php echo esc_html(round($system_info['server_load']['15min'], 2)); ?></li>
                                    </ul>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <script type="text/javascript">
        // Pass data to JavaScript
        window.fmrseoData = {
            stats: <?php echo json_encode($stats); ?>,
            systemInfo: <?php echo json_encode($system_info); ?>,
            issues: <?php echo json_encode($issues); ?>
        };
        </script>
        <?php
    }    

    public function refresh_dashboard_data() {
        check_ajax_referer('fmrseo_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'fmrseo'));
        }
        
        $days = isset($_POST['days']) ? absint($_POST['days']) : 7;
        
        $stats = $this->performance_monitor->get_performance_stats($days);
        $system_info = $this->performance_monitor->get_system_info();
        $issues = $this->performance_monitor->check_performance_issues();
        
        wp_send_json_success([
            'stats' => $stats,
            'system_info' => $system_info,
            'issues' => $issues,
            'html' => $this->render_dashboard_content($stats, $system_info, $issues)
        ]);
    }
    
    private function render_dashboard_content($stats, $system_info, $issues) {
        ob_start();
        
        // Render updated content sections
        ?>
        <div class="fmrseo-dashboard-stats-update">
            <div class="fmrseo-stat-card">
                <div class="fmrseo-stat-icon success">
                    <span class="dashicons dashicons-yes-alt"></span>
                </div>
                <div class="fmrseo-stat-content">
                    <h3><?php echo number_format($stats['successful_operations']); ?></h3>
                    <p><?php _e('Successful Operations', 'fmrseo'); ?></p>
                </div>
            </div>
            
            <div class="fmrseo-stat-card">
                <div class="fmrseo-stat-icon error">
                    <span class="dashicons dashicons-warning"></span>
                </div>
                <div class="fmrseo-stat-content">
                    <h3><?php echo number_format($stats['failed_operations']); ?></h3>
                    <p><?php _e('Failed Operations', 'fmrseo'); ?></p>
                </div>
            </div>
            
            <div class="fmrseo-stat-card">
                <div class="fmrseo-stat-icon info">
                    <span class="dashicons dashicons-clock"></span>
                </div>
                <div class="fmrseo-stat-content">
                    <h3><?php echo round($stats['avg_response_time'], 2); ?>s</h3>
                    <p><?php _e('Avg Response Time', 'fmrseo'); ?></p>
                </div>
            </div>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    public function export_performance_report() {
        check_ajax_referer('fmrseo_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'fmrseo'));
        }
        
        $days = isset($_POST['days']) ? absint($_POST['days']) : 30;
        $report = $this->performance_monitor->generate_performance_report($days);
        
        // Generate CSV format
        $csv_data = $this->generate_csv_report($report);
        
        wp_send_json_success([
            'report' => $report,
            'csv' => $csv_data,
            'filename' => 'fmrseo-performance-report-' . date('Y-m-d') . '.csv'
        ]);
    }
    
    private function generate_csv_report($report) {
        $csv_lines = [];
        
        // Header
        $csv_lines[] = 'FMRSEO Performance Report';
        $csv_lines[] = 'Generated: ' . $report['generated_at'];
        $csv_lines[] = 'Period: ' . $report['period_days'] . ' days';
        $csv_lines[] = '';
        
        // Summary
        $csv_lines[] = 'SUMMARY';
        $csv_lines[] = 'Metric,Value';
        foreach ($report['summary'] as $key => $value) {
            $csv_lines[] = ucwords(str_replace('_', ' ', $key)) . ',' . $value;
        }
        $csv_lines[] = '';
        
        // API Performance
        $csv_lines[] = 'API PERFORMANCE';
        $csv_lines[] = 'Provider,Total Calls,Successful,Failed,Success Rate';
        foreach ($report['api_performance'] as $provider => $data) {
            $success_rate = $data['total'] > 0 ? round(($data['successful'] / $data['total']) * 100, 2) : 0;
            $csv_lines[] = ucfirst($provider) . ',' . $data['total'] . ',' . $data['successful'] . ',' . $data['failed'] . ',' . $success_rate . '%';
        }
        $csv_lines[] = '';
        
        // Daily Breakdown
        $csv_lines[] = 'DAILY BREAKDOWN';
        $csv_lines[] = 'Date,Operations,Successful,Failed,Memory Used';
        foreach ($report['daily_breakdown'] as $date => $data) {
            $csv_lines[] = $date . ',' . $data['operations'] . ',' . $data['successful'] . ',' . $data['failed'] . ',' . size_format($data['memory_used']);
        }
        $csv_lines[] = '';
        
        // Issues
        if (!empty($report['issues_detected'])) {
            $csv_lines[] = 'ISSUES DETECTED';
            $csv_lines[] = 'Type,Severity,Message';
            foreach ($report['issues_detected'] as $issue) {
                $csv_lines[] = $issue['type'] . ',' . $issue['severity'] . ',' . '"' . $issue['message'] . '"';
            }
            $csv_lines[] = '';
        }
        
        // Recommendations
        if (!empty($report['recommendations'])) {
            $csv_lines[] = 'RECOMMENDATIONS';
            foreach ($report['recommendations'] as $recommendation) {
                $csv_lines[] = '"' . $recommendation . '"';
            }
        }
        
        return implode("\n", $csv_lines);
    }
    
    public function get_dashboard_widget_data() {
        $stats = $this->performance_monitor->get_performance_stats(1); // Last 24 hours
        $issues = $this->performance_monitor->check_performance_issues();
        
        return [
            'operations_today' => $stats['total_operations'],
            'success_rate' => $stats['total_operations'] > 0 ? 
                round(($stats['successful_operations'] / $stats['total_operations']) * 100, 1) : 0,
            'avg_response_time' => round($stats['avg_response_time'], 2),
            'issues_count' => count($issues),
            'critical_issues' => count(array_filter($issues, function($issue) {
                return $issue['severity'] === 'high';
            }))
        ];
    }
    
    public function add_dashboard_widget() {
        wp_add_dashboard_widget(
            'fmrseo_dashboard_widget',
            __('FMRSEO Performance', 'fmrseo'),
            [$this, 'dashboard_widget_callback']
        );
    }
    
    public function dashboard_widget_callback() {
        $data = $this->get_dashboard_widget_data();
        ?>
        <div class="fmrseo-widget-content">
            <div class="fmrseo-widget-stats">
                <div class="fmrseo-widget-stat">
                    <span class="fmrseo-widget-number"><?php echo $data['operations_today']; ?></span>
                    <span class="fmrseo-widget-label"><?php _e('Operations Today', 'fmrseo'); ?></span>
                </div>
                
                <div class="fmrseo-widget-stat">
                    <span class="fmrseo-widget-number"><?php echo $data['success_rate']; ?>%</span>
                    <span class="fmrseo-widget-label"><?php _e('Success Rate', 'fmrseo'); ?></span>
                </div>
                
                <div class="fmrseo-widget-stat">
                    <span class="fmrseo-widget-number"><?php echo $data['avg_response_time']; ?>s</span>
                    <span class="fmrseo-widget-label"><?php _e('Avg Response', 'fmrseo'); ?></span>
                </div>
            </div>
            
            <?php if ($data['issues_count'] > 0): ?>
            <div class="fmrseo-widget-issues">
                <p class="fmrseo-widget-warning">
                    <span class="dashicons dashicons-warning"></span>
                    <?php printf(
                        _n('%d issue detected', '%d issues detected', $data['issues_count'], 'fmrseo'),
                        $data['issues_count']
                    ); ?>
                    <?php if ($data['critical_issues'] > 0): ?>
                    <strong>(<?php echo $data['critical_issues']; ?> critical)</strong>
                    <?php endif; ?>
                </p>
            </div>
            <?php endif; ?>
            
            <div class="fmrseo-widget-actions">
                <a href="<?php echo admin_url('admin.php?page=fmrseo-dashboard'); ?>" class="button button-primary">
                    <?php _e('View Dashboard', 'fmrseo'); ?>
                </a>
            </div>
        </div>
        
        <style>
        .fmrseo-widget-content {
            padding: 10px 0;
        }
        
        .fmrseo-widget-stats {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
        }
        
        .fmrseo-widget-stat {
            text-align: center;
            flex: 1;
        }
        
        .fmrseo-widget-number {
            display: block;
            font-size: 24px;
            font-weight: bold;
            color: #0073aa;
        }
        
        .fmrseo-widget-label {
            display: block;
            font-size: 12px;
            color: #666;
        }
        
        .fmrseo-widget-issues {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 4px;
            padding: 10px;
            margin-bottom: 15px;
        }
        
        .fmrseo-widget-warning {
            margin: 0;
            color: #856404;
        }
        
        .fmrseo-widget-warning .dashicons {
            color: #f39c12;
        }
        
        .fmrseo-widget-actions {
            text-align: center;
        }
        </style>
        <?php
    }
}

// Initialize the dashboard
add_action('init', function() {
    new FMRSEO_Dashboard();
});