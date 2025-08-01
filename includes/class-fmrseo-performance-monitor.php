<?php
/**
 * Performance Monitor for FMRSEO Plugin
 * Tracks resource usage, API calls, and system performance
 */

if (!defined('ABSPATH')) {
    exit;
}

class FMRSEO_Performance_Monitor {
    
    private $start_time;
    private $start_memory;
    private $metrics = [];
    private $settings;
    
    public function __construct() {
        $this->settings = new FMRSEO_Settings_Extension();
        $this->start_time = microtime(true);
        $this->start_memory = memory_get_usage(true);
        
        add_action('wp_ajax_fmrseo_get_performance_data', [$this, 'get_performance_data']);
        add_action('wp_ajax_fmrseo_clear_performance_data', [$this, 'clear_performance_data']);
        add_action('fmrseo_after_rename', [$this, 'track_rename_operation']);
        add_action('fmrseo_api_call_made', [$this, 'track_api_call']);
        
        // Schedule cleanup of old metrics
        if (!wp_next_scheduled('fmrseo_cleanup_metrics')) {
            wp_schedule_event(time(), 'daily', 'fmrseo_cleanup_metrics');
        }
        add_action('fmrseo_cleanup_metrics', [$this, 'cleanup_old_metrics']);
    }
    
    public function start_operation($operation_name) {
        $this->metrics[$operation_name] = [
            'start_time' => microtime(true),
            'start_memory' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true)
        ];
    }
    
    public function end_operation($operation_name, $additional_data = []) {
        if (!isset($this->metrics[$operation_name])) {
            return false;
        }
        
        $end_time = microtime(true);
        $end_memory = memory_get_usage(true);
        $peak_memory = memory_get_peak_usage(true);
        
        $operation_data = [
            'operation' => $operation_name,
            'duration' => $end_time - $this->metrics[$operation_name]['start_time'],
            'memory_used' => $end_memory - $this->metrics[$operation_name]['start_memory'],
            'peak_memory' => $peak_memory,
            'timestamp' => current_time('mysql'),
            'additional_data' => $additional_data
        ];
        
        $this->save_metric($operation_data);
        unset($this->metrics[$operation_name]);
        
        return $operation_data;
    }
    
    public function track_api_call($provider, $endpoint, $response_time, $success = true) {
        $metric_data = [
            'operation' => 'api_call',
            'provider' => $provider,
            'endpoint' => $endpoint,
            'response_time' => $response_time,
            'success' => $success,
            'timestamp' => current_time('mysql'),
            'memory_usage' => memory_get_usage(true)
        ];
        
        $this->save_metric($metric_data);
        
        // Update rate limiting data
        $this->update_rate_limit_data($provider);
    }
    
    public function track_rename_operation($file_id, $old_name, $new_name, $success, $method = 'ai') {
        $metric_data = [
            'operation' => 'file_rename',
            'file_id' => $file_id,
            'old_name' => $old_name,
            'new_name' => $new_name,
            'success' => $success,
            'method' => $method,
            'timestamp' => current_time('mysql')
        ];
        
        $this->save_metric($metric_data);
    }
    
    private function save_metric($data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fmrseo_performance_metrics';
        
        // Create table if it doesn't exist
        $this->create_metrics_table();
        
        $wpdb->insert(
            $table_name,
            [
                'operation_type' => $data['operation'],
                'data' => json_encode($data),
                'timestamp' => $data['timestamp'],
                'memory_usage' => isset($data['memory_usage']) ? $data['memory_usage'] : memory_get_usage(true)
            ],
            ['%s', '%s', '%s', '%d']
        );
    }
    
    private function create_metrics_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fmrseo_performance_metrics';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            operation_type varchar(50) NOT NULL,
            data longtext NOT NULL,
            timestamp datetime NOT NULL,
            memory_usage bigint(20) DEFAULT 0,
            PRIMARY KEY (id),
            KEY operation_type (operation_type),
            KEY timestamp (timestamp)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    public function get_performance_stats($days = 7) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fmrseo_performance_metrics';
        $date_limit = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE timestamp >= %s ORDER BY timestamp DESC",
            $date_limit
        ));
        
        $stats = [
            'total_operations' => 0,
            'successful_operations' => 0,
            'failed_operations' => 0,
            'avg_response_time' => 0,
            'total_memory_used' => 0,
            'peak_memory' => 0,
            'api_calls' => [],
            'rename_operations' => [],
            'daily_stats' => []
        ];
        
        $response_times = [];
        $daily_data = [];
        
        foreach ($results as $result) {
            $data = json_decode($result->data, true);
            $date = date('Y-m-d', strtotime($result->timestamp));
            
            if (!isset($daily_data[$date])) {
                $daily_data[$date] = [
                    'operations' => 0,
                    'successful' => 0,
                    'failed' => 0,
                    'memory_used' => 0
                ];
            }
            
            $stats['total_operations']++;
            $daily_data[$date]['operations']++;
            
            if (isset($data['success']) && $data['success']) {
                $stats['successful_operations']++;
                $daily_data[$date]['successful']++;
            } else {
                $stats['failed_operations']++;
                $daily_data[$date]['failed']++;
            }
            
            if (isset($data['response_time'])) {
                $response_times[] = $data['response_time'];
            }
            
            if (isset($data['duration'])) {
                $response_times[] = $data['duration'];
            }
            
            $stats['total_memory_used'] += $result->memory_usage;
            $daily_data[$date]['memory_used'] += $result->memory_usage;
            
            if ($result->memory_usage > $stats['peak_memory']) {
                $stats['peak_memory'] = $result->memory_usage;
            }
            
            // Categorize by operation type
            if ($data['operation'] === 'api_call') {
                $provider = $data['provider'] ?? 'unknown';
                if (!isset($stats['api_calls'][$provider])) {
                    $stats['api_calls'][$provider] = [
                        'total' => 0,
                        'successful' => 0,
                        'failed' => 0,
                        'avg_response_time' => 0
                    ];
                }
                $stats['api_calls'][$provider]['total']++;
                if ($data['success']) {
                    $stats['api_calls'][$provider]['successful']++;
                } else {
                    $stats['api_calls'][$provider]['failed']++;
                }
            }
            
            if ($data['operation'] === 'file_rename') {
                $method = $data['method'] ?? 'unknown';
                if (!isset($stats['rename_operations'][$method])) {
                    $stats['rename_operations'][$method] = [
                        'total' => 0,
                        'successful' => 0,
                        'failed' => 0
                    ];
                }
                $stats['rename_operations'][$method]['total']++;
                if ($data['success']) {
                    $stats['rename_operations'][$method]['successful']++;
                } else {
                    $stats['rename_operations'][$method]['failed']++;
                }
            }
        }
        
        if (!empty($response_times)) {
            $stats['avg_response_time'] = array_sum($response_times) / count($response_times);
        }
        
        $stats['daily_stats'] = $daily_data;
        
        return $stats;
    } 
   
    public function get_system_info() {
        return [
            'php_version' => PHP_VERSION,
            'wordpress_version' => get_bloginfo('version'),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'current_memory_usage' => size_format(memory_get_usage(true)),
            'peak_memory_usage' => size_format(memory_get_peak_usage(true)),
            'server_load' => $this->get_server_load(),
            'disk_space' => $this->get_disk_space_info()
        ];
    }
    
    private function get_server_load() {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            return [
                '1min' => $load[0],
                '5min' => $load[1],
                '15min' => $load[2]
            ];
        }
        return null;
    }
    
    private function get_disk_space_info() {
        $upload_dir = wp_upload_dir();
        $path = $upload_dir['basedir'];
        
        if (function_exists('disk_free_space') && function_exists('disk_total_space')) {
            $free = disk_free_space($path);
            $total = disk_total_space($path);
            
            return [
                'free' => size_format($free),
                'total' => size_format($total),
                'used_percentage' => round((($total - $free) / $total) * 100, 2)
            ];
        }
        
        return null;
    }
    
    public function check_performance_issues() {
        $issues = [];
        $stats = $this->get_performance_stats(1); // Last 24 hours
        $system_info = $this->get_system_info();
        
        // Check memory usage
        $memory_limit_bytes = $this->convert_to_bytes(ini_get('memory_limit'));
        $current_memory = memory_get_usage(true);
        
        if ($current_memory > ($memory_limit_bytes * 0.8)) {
            $issues[] = [
                'type' => 'memory',
                'severity' => 'high',
                'message' => 'Memory usage is above 80% of the limit',
                'current' => size_format($current_memory),
                'limit' => size_format($memory_limit_bytes)
            ];
        }
        
        // Check API response times
        if ($stats['avg_response_time'] > 5) {
            $issues[] = [
                'type' => 'api_performance',
                'severity' => 'medium',
                'message' => 'Average API response time is high',
                'avg_time' => round($stats['avg_response_time'], 2) . 's'
            ];
        }
        
        // Check failure rate
        if ($stats['total_operations'] > 0) {
            $failure_rate = ($stats['failed_operations'] / $stats['total_operations']) * 100;
            if ($failure_rate > 10) {
                $issues[] = [
                    'type' => 'failure_rate',
                    'severity' => 'high',
                    'message' => 'High failure rate detected',
                    'rate' => round($failure_rate, 2) . '%'
                ];
            }
        }
        
        // Check rate limiting
        if ($this->is_rate_limited()) {
            $issues[] = [
                'type' => 'rate_limit',
                'severity' => 'medium',
                'message' => 'Rate limit threshold reached',
                'action' => 'Consider upgrading API plan or reducing request frequency'
            ];
        }
        
        return $issues;
    }
    
    private function convert_to_bytes($size_str) {
        $size_str = trim($size_str);
        $last = strtolower($size_str[strlen($size_str) - 1]);
        $size = (int) $size_str;
        
        switch ($last) {
            case 'g':
                $size *= 1024;
            case 'm':
                $size *= 1024;
            case 'k':
                $size *= 1024;
        }
        
        return $size;
    }
    
    private function update_rate_limit_data($provider) {
        $rate_limit_key = 'fmrseo_rate_limit_' . $provider;
        $current_data = get_transient($rate_limit_key);
        
        if (!$current_data) {
            $current_data = [
                'count' => 0,
                'window_start' => time()
            ];
        }
        
        $window_duration = $this->settings->get_setting('security.rate_limit_window');
        
        // Reset if window expired
        if (time() - $current_data['window_start'] > $window_duration) {
            $current_data = [
                'count' => 1,
                'window_start' => time()
            ];
        } else {
            $current_data['count']++;
        }
        
        set_transient($rate_limit_key, $current_data, $window_duration);
    }
    
    private function is_rate_limited($provider = null) {
        if (!$this->settings->get_setting('security.rate_limit_enabled')) {
            return false;
        }
        
        $providers = $provider ? [$provider] : ['openai', 'anthropic', 'google'];
        $limit = $this->settings->get_setting('security.rate_limit_requests');
        
        foreach ($providers as $prov) {
            $rate_limit_key = 'fmrseo_rate_limit_' . $prov;
            $current_data = get_transient($rate_limit_key);
            
            if ($current_data && $current_data['count'] >= $limit) {
                return true;
            }
        }
        
        return false;
    }
    
    public function cleanup_old_metrics() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fmrseo_performance_metrics';
        $retention_days = $this->settings->get_setting('logging.retention_days');
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$retention_days} days"));
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name WHERE timestamp < %s",
            $cutoff_date
        ));
    }
    
    // AJAX Handlers
    public function get_performance_data() {
        check_ajax_referer('fmrseo_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'fmrseo'));
        }
        
        $days = isset($_POST['days']) ? absint($_POST['days']) : 7;
        $stats = $this->get_performance_stats($days);
        $system_info = $this->get_system_info();
        $issues = $this->check_performance_issues();
        
        wp_send_json_success([
            'stats' => $stats,
            'system_info' => $system_info,
            'issues' => $issues
        ]);
    }
    
    public function clear_performance_data() {
        check_ajax_referer('fmrseo_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'fmrseo'));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'fmrseo_performance_metrics';
        $wpdb->query("TRUNCATE TABLE $table_name");
        
        wp_send_json_success();
    }
    
    public function generate_performance_report($days = 30) {
        $stats = $this->get_performance_stats($days);
        $system_info = $this->get_system_info();
        $issues = $this->check_performance_issues();
        
        $report = [
            'generated_at' => current_time('mysql'),
            'period_days' => $days,
            'summary' => [
                'total_operations' => $stats['total_operations'],
                'success_rate' => $stats['total_operations'] > 0 ? 
                    round(($stats['successful_operations'] / $stats['total_operations']) * 100, 2) : 0,
                'avg_response_time' => round($stats['avg_response_time'], 3),
                'total_memory_used' => size_format($stats['total_memory_used']),
                'peak_memory' => size_format($stats['peak_memory'])
            ],
            'api_performance' => $stats['api_calls'],
            'rename_performance' => $stats['rename_operations'],
            'daily_breakdown' => $stats['daily_stats'],
            'system_info' => $system_info,
            'issues_detected' => $issues,
            'recommendations' => $this->generate_recommendations($stats, $issues)
        ];
        
        return $report;
    }
    
    private function generate_recommendations($stats, $issues) {
        $recommendations = [];
        
        // Memory recommendations
        foreach ($issues as $issue) {
            switch ($issue['type']) {
                case 'memory':
                    $recommendations[] = 'Consider increasing PHP memory limit or optimizing memory usage';
                    break;
                case 'api_performance':
                    $recommendations[] = 'Enable caching to reduce API response times';
                    $recommendations[] = 'Consider reducing batch sizes for better performance';
                    break;
                case 'failure_rate':
                    $recommendations[] = 'Review error logs to identify common failure patterns';
                    $recommendations[] = 'Implement retry mechanisms for failed operations';
                    break;
                case 'rate_limit':
                    $recommendations[] = 'Implement request queuing to stay within rate limits';
                    $recommendations[] = 'Consider upgrading to a higher API tier';
                    break;
            }
        }
        
        // General recommendations based on stats
        if ($stats['total_operations'] > 1000 && !$this->settings->get_setting('performance.cache_enabled')) {
            $recommendations[] = 'Enable caching to improve performance with high operation volumes';
        }
        
        if ($stats['avg_response_time'] > 2 && !$this->settings->get_setting('performance.batch_processing_enabled')) {
            $recommendations[] = 'Enable batch processing to improve overall throughput';
        }
        
        return array_unique($recommendations);
    }
}

// Initialize the performance monitor
new FMRSEO_Performance_Monitor();