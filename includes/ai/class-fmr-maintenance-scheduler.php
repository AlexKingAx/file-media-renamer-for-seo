<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Maintenance Scheduler Class
 * 
 * Handles scheduled maintenance tasks for security and performance optimization.
 */
class FMR_Maintenance_Scheduler {

    /**
     * Constructor
     */
    public function __construct() {
        // Schedule maintenance tasks
        add_action('init', array($this, 'schedule_maintenance_tasks'));
        
        // Register maintenance hooks
        add_action('fmr_daily_maintenance', array($this, 'run_daily_maintenance'));
        add_action('fmr_weekly_maintenance', array($this, 'run_weekly_maintenance'));
        
        // Add cleanup on plugin deactivation
        register_deactivation_hook(FMRSEO_PLUGIN_FILE, array($this, 'clear_scheduled_tasks'));
    }

    /**
     * Schedule maintenance tasks
     */
    public function schedule_maintenance_tasks() {
        // Schedule daily maintenance if not already scheduled
        if (!wp_next_scheduled('fmr_daily_maintenance')) {
            wp_schedule_event(time(), 'daily', 'fmr_daily_maintenance');
        }
        
        // Schedule weekly maintenance if not already scheduled
        if (!wp_next_scheduled('fmr_weekly_maintenance')) {
            wp_schedule_event(time(), 'weekly', 'fmr_weekly_maintenance');
        }
    }

    /**
     * Run daily maintenance tasks
     */
    public function run_daily_maintenance() {
        try {
            $maintenance_log = array(
                'timestamp' => time(),
                'tasks_completed' => array(),
                'errors' => array()
            );

            // Clean up expired cache entries
            if (class_exists('FMR_Performance_Optimizer')) {
                $performance_optimizer = new FMR_Performance_Optimizer();
                $cleanup_results = $performance_optimizer->cleanup_and_optimize();
                
                $maintenance_log['tasks_completed'][] = array(
                    'task' => 'cache_cleanup',
                    'expired_cache_cleared' => $cleanup_results['expired_cache_cleared'],
                    'orphaned_timeouts_cleared' => $cleanup_results['orphaned_timeouts_cleared']
                );
            }

            // Clean up old security logs (keep based on retention setting)
            if (class_exists('FMR_Security_Manager')) {
                $security_manager = new FMR_Security_Manager();
                $options = get_option('fmrseo_options', array());
                $retention_days = isset($options['ai_security_log_retention']) ? intval($options['ai_security_log_retention']) : 30;
                
                $deleted_logs = $security_manager->cleanup_security_logs($retention_days);
                
                $maintenance_log['tasks_completed'][] = array(
                    'task' => 'security_log_cleanup',
                    'deleted_entries' => $deleted_logs,
                    'retention_days' => $retention_days
                );
            }

            // Clean up old performance metrics
            $this->cleanup_old_performance_metrics();
            $maintenance_log['tasks_completed'][] = array(
                'task' => 'performance_metrics_cleanup'
            );

            // Log maintenance completion
            error_log('FMR Daily Maintenance Completed: ' . wp_json_encode($maintenance_log));

        } catch (Exception $e) {
            error_log('FMR Daily Maintenance Error: ' . $e->getMessage());
        }
    }

    /**
     * Run weekly maintenance tasks
     */
    public function run_weekly_maintenance() {
        try {
            $maintenance_log = array(
                'timestamp' => time(),
                'tasks_completed' => array(),
                'errors' => array()
            );

            // Optimize database tables
            $this->optimize_database_tables();
            $maintenance_log['tasks_completed'][] = array(
                'task' => 'database_optimization'
            );

            // Generate performance report
            if (class_exists('FMR_Performance_Optimizer')) {
                $performance_report = $this->generate_performance_report();
                $maintenance_log['tasks_completed'][] = array(
                    'task' => 'performance_report_generation',
                    'report_data' => $performance_report
                );
            }

            // Security audit
            if (class_exists('FMR_Security_Manager')) {
                $security_audit = $this->run_security_audit();
                $maintenance_log['tasks_completed'][] = array(
                    'task' => 'security_audit',
                    'audit_results' => $security_audit
                );
            }

            // Log maintenance completion
            error_log('FMR Weekly Maintenance Completed: ' . wp_json_encode($maintenance_log));

        } catch (Exception $e) {
            error_log('FMR Weekly Maintenance Error: ' . $e->getMessage());
        }
    }

    /**
     * Clean up old performance metrics
     */
    private function cleanup_old_performance_metrics() {
        // Clean up performance logs older than 7 days
        $cache_key = 'fmr_ai_cache_performance_log';
        $perf_log = get_transient($cache_key);
        
        if (is_array($perf_log)) {
            $cutoff_time = time() - (7 * 24 * 60 * 60); // 7 days ago
            
            $filtered_log = array_filter($perf_log, function($entry) use ($cutoff_time) {
                return isset($entry['timestamp']) && $entry['timestamp'] > $cutoff_time;
            });
            
            // Keep only the most recent 100 entries
            if (count($filtered_log) > 100) {
                usort($filtered_log, function($a, $b) {
                    return $b['timestamp'] - $a['timestamp'];
                });
                $filtered_log = array_slice($filtered_log, 0, 100);
            }
            
            set_transient($cache_key, $filtered_log, 3600);
        }
    }

    /**
     * Optimize database tables
     */
    private function optimize_database_tables() {
        global $wpdb;
        
        // Optimize security log table if it exists
        $security_table = $wpdb->prefix . 'fmrseo_security_log';
        if ($wpdb->get_var("SHOW TABLES LIKE '$security_table'") == $security_table) {
            $wpdb->query("OPTIMIZE TABLE $security_table");
        }
        
        // Clean up orphaned transients
        $wpdb->query("
            DELETE a, b FROM {$wpdb->options} a, {$wpdb->options} b
            WHERE a.option_name LIKE '_transient_%'
            AND a.option_name NOT LIKE '_transient_timeout_%'
            AND b.option_name = CONCAT('_transient_timeout_', SUBSTRING(a.option_name, 12))
            AND b.option_value < UNIX_TIMESTAMP()
        ");
    }

    /**
     * Generate performance report
     *
     * @return array Performance report data
     */
    private function generate_performance_report() {
        if (!class_exists('FMR_Performance_Optimizer')) {
            return array();
        }

        $performance_optimizer = new FMR_Performance_Optimizer();
        $metrics = $performance_optimizer->get_performance_metrics(50);
        $cache_stats = $performance_optimizer->get_cache_stats();
        
        $report = array(
            'period' => 'weekly',
            'generated_at' => time(),
            'total_operations' => count($metrics),
            'cache_stats' => $cache_stats
        );
        
        if (!empty($metrics)) {
            // Calculate averages
            $total_time = array_sum(array_column($metrics, 'execution_time'));
            $total_memory = array_sum(array_column($metrics, 'memory_usage_mb'));
            
            $report['avg_execution_time'] = $total_time / count($metrics);
            $report['avg_memory_usage'] = $total_memory / count($metrics);
            
            // Find slowest operations
            usort($metrics, function($a, $b) {
                return $b['execution_time'] <=> $a['execution_time'];
            });
            
            $report['slowest_operations'] = array_slice($metrics, 0, 5);
            
            // Operation type breakdown
            $operation_counts = array();
            foreach ($metrics as $metric) {
                $operation = $metric['operation'];
                $operation_counts[$operation] = isset($operation_counts[$operation]) ? $operation_counts[$operation] + 1 : 1;
            }
            
            $report['operation_breakdown'] = $operation_counts;
        }
        
        return $report;
    }

    /**
     * Run security audit
     *
     * @return array Security audit results
     */
    private function run_security_audit() {
        if (!class_exists('FMR_Security_Manager')) {
            return array();
        }

        global $wpdb;
        $security_table = $wpdb->prefix . 'fmrseo_security_log';
        
        $audit_results = array(
            'audit_date' => time(),
            'total_events' => 0,
            'event_breakdown' => array(),
            'top_ips' => array(),
            'recommendations' => array()
        );
        
        // Check if security log table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$security_table'") != $security_table) {
            return $audit_results;
        }
        
        // Get events from last 7 days
        $week_ago = time() - (7 * 24 * 60 * 60);
        
        // Total events
        $total_events = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM $security_table WHERE timestamp > %d
        ", $week_ago));
        
        $audit_results['total_events'] = intval($total_events);
        
        // Event type breakdown
        $event_types = $wpdb->get_results($wpdb->prepare("
            SELECT event_type, COUNT(*) as count 
            FROM $security_table 
            WHERE timestamp > %d 
            GROUP BY event_type 
            ORDER BY count DESC
        ", $week_ago));
        
        foreach ($event_types as $event) {
            $audit_results['event_breakdown'][$event->event_type] = intval($event->count);
        }
        
        // Top IP addresses
        $top_ips = $wpdb->get_results($wpdb->prepare("
            SELECT ip_address, COUNT(*) as count 
            FROM $security_table 
            WHERE timestamp > %d 
            GROUP BY ip_address 
            ORDER BY count DESC 
            LIMIT 10
        ", $week_ago));
        
        foreach ($top_ips as $ip) {
            $audit_results['top_ips'][$ip->ip_address] = intval($ip->count);
        }
        
        // Generate recommendations
        if (isset($audit_results['event_breakdown']['rate_limit_exceeded']) && 
            $audit_results['event_breakdown']['rate_limit_exceeded'] > 50) {
            $audit_results['recommendations'][] = __('Consider tightening rate limits due to high number of rate limit violations.', 'fmrseo');
        }
        
        if (isset($audit_results['event_breakdown']['invalid_nonce']) && 
            $audit_results['event_breakdown']['invalid_nonce'] > 10) {
            $audit_results['recommendations'][] = __('High number of nonce validation failures detected. Monitor for potential attacks.', 'fmrseo');
        }
        
        if (count($audit_results['top_ips']) > 0) {
            $top_ip_count = reset($audit_results['top_ips']);
            if ($top_ip_count > 100) {
                $audit_results['recommendations'][] = __('Single IP address generating high number of security events. Consider blocking if malicious.', 'fmrseo');
            }
        }
        
        return $audit_results;
    }

    /**
     * Clear scheduled tasks on plugin deactivation
     */
    public function clear_scheduled_tasks() {
        wp_clear_scheduled_hook('fmr_daily_maintenance');
        wp_clear_scheduled_hook('fmr_weekly_maintenance');
    }

    /**
     * Get maintenance status
     *
     * @return array Maintenance status information
     */
    public function get_maintenance_status() {
        return array(
            'daily_maintenance' => array(
                'next_run' => wp_next_scheduled('fmr_daily_maintenance'),
                'last_run' => get_option('fmr_last_daily_maintenance', 0)
            ),
            'weekly_maintenance' => array(
                'next_run' => wp_next_scheduled('fmr_weekly_maintenance'),
                'last_run' => get_option('fmr_last_weekly_maintenance', 0)
            )
        );
    }

    /**
     * Force run maintenance tasks (for testing/manual execution)
     *
     * @param string $type Type of maintenance ('daily' or 'weekly')
     * @return array Results of maintenance tasks
     */
    public function force_run_maintenance($type = 'daily') {
        if ($type === 'weekly') {
            $this->run_weekly_maintenance();
            update_option('fmr_last_weekly_maintenance', time());
        } else {
            $this->run_daily_maintenance();
            update_option('fmr_last_daily_maintenance', time());
        }
        
        return array(
            'success' => true,
            'message' => sprintf(__('%s maintenance completed successfully.', 'fmrseo'), ucfirst($type)),
            'timestamp' => time()
        );
    }
}