<?php

/**
 * AI History Manager for File Media Renamer for SEO
 * 
 * Manages comprehensive history tracking for AI operations including:
 * - AI vs manual rename tracking
 * - AI-specific metadata storage
 * - Credit usage tracking
 * - Statistics generation
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class FMR_AI_History_Manager {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'init'));
    }

    /**
     * Initialize the history manager
     */
    public function init() {
        // Hook into existing rename process to track operations
        add_action('fmrseo_after_rename', array($this, 'track_rename_operation'), 10, 3);
        
        // Add admin hooks for statistics display
        add_action('admin_menu', array($this, 'add_statistics_page'));
        add_action('wp_ajax_fmrseo_get_ai_statistics', array($this, 'ajax_get_ai_statistics'));
        add_action('wp_ajax_fmrseo_export_ai_history', array($this, 'ajax_export_ai_history'));
        
        // Schedule cleanup tasks
        add_action('fmrseo_cleanup_old_history', array($this, 'scheduled_cleanup'));
        
        // Register cleanup schedule if not already scheduled
        if (!wp_next_scheduled('fmrseo_cleanup_old_history')) {
            wp_schedule_event(time(), 'weekly', 'fmrseo_cleanup_old_history');
        }
    }

    /**
     * Track rename operation with comprehensive metadata
     *
     * @param int $post_id The media post ID
     * @param array $rename_result Result from rename operation
     * @param array $operation_data Additional operation data
     */
    public function track_rename_operation($post_id, $rename_result, $operation_data = array()) {
        // Get current history
        $history = get_post_meta($post_id, '_fmrseo_rename_history', true);
        if (!is_array($history)) {
            $history = array();
        }

        // Determine operation method
        $method = isset($operation_data['method']) ? $operation_data['method'] : 'manual';
        $is_ai_operation = ($method === 'ai');

        // Prepare base history entry
        $history_entry = array(
            'file_path' => $rename_result['old_file_path'],
            'file_url' => $rename_result['old_file_url'],
            'seo_name' => basename($rename_result['old_file_path'], '.' . $rename_result['file_ext']),
            'new_seo_name' => isset($rename_result['seo_name']) ? $rename_result['seo_name'] : '',
            'timestamp' => time(),
            'method' => $method,
            'user_id' => get_current_user_id(),
            'operation_id' => $this->generate_operation_id()
        );

        // Add AI-specific metadata if this is an AI operation
        if ($is_ai_operation) {
            $history_entry = array_merge($history_entry, array(
                'ai_suggestions' => isset($operation_data['ai_suggestions']) ? $operation_data['ai_suggestions'] : array(),
                'selected_suggestion_index' => isset($operation_data['selected_suggestion_index']) ? $operation_data['selected_suggestion_index'] : 0,
                'credits_used' => isset($operation_data['credits_used']) ? $operation_data['credits_used'] : 1,
                'ai_processing_time' => isset($operation_data['processing_time']) ? $operation_data['processing_time'] : 0,
                'content_analysis_data' => isset($operation_data['content_analysis']) ? $operation_data['content_analysis'] : array(),
                'context_data' => isset($operation_data['context_data']) ? $operation_data['context_data'] : array(),
                'fallback_used' => isset($operation_data['fallback_used']) ? $operation_data['fallback_used'] : false,
                'error_occurred' => isset($operation_data['error_occurred']) ? $operation_data['error_occurred'] : false,
                'error_message' => isset($operation_data['error_message']) ? $operation_data['error_message'] : ''
            ));

            // Track credit usage
            $this->track_credit_usage($post_id, $history_entry);
        }

        // Add bulk operation metadata if applicable
        if (isset($operation_data['bulk_operation'])) {
            $history_entry['bulk_operation'] = true;
            $history_entry['bulk_batch_id'] = isset($operation_data['bulk_batch_id']) ? $operation_data['bulk_batch_id'] : '';
            $history_entry['bulk_total_files'] = isset($operation_data['bulk_total_files']) ? $operation_data['bulk_total_files'] : 0;
            $history_entry['bulk_file_index'] = isset($operation_data['bulk_file_index']) ? $operation_data['bulk_file_index'] : 0;
        }

        // Add to history (most recent first)
        array_unshift($history, $history_entry);

        // Keep only the last 5 versions (increased from 2 for better tracking)
        $history = array_slice($history, 0, 5);

        // Update post meta
        update_post_meta($post_id, '_fmrseo_rename_history', $history);

        // Update global statistics
        $this->update_global_statistics($history_entry);

        // Log operation for debugging if needed
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("FMR AI History: Tracked {$method} operation for post {$post_id}");
        }
    }

    /**
     * Track credit usage for AI operations
     *
     * @param int $post_id The media post ID
     * @param array $history_entry The history entry data
     */
    private function track_credit_usage($post_id, $history_entry) {
        $user_id = get_current_user_id();
        $credit_data = get_user_meta($user_id, '_fmrseo_ai_credits', true);
        
        if (!is_array($credit_data)) {
            $credit_data = array(
                'balance' => 0,
                'used_total' => 0,
                'last_updated' => time(),
                'free_credits_initialized' => false,
                'transactions' => array()
            );
        }

        // Add transaction record
        $transaction = array(
            'type' => 'deduct',
            'amount' => $history_entry['credits_used'],
            'timestamp' => $history_entry['timestamp'],
            'post_id' => $post_id,
            'operation' => isset($history_entry['bulk_operation']) ? 'bulk' : 'single',
            'operation_id' => $history_entry['operation_id'],
            'filename' => $history_entry['new_seo_name'],
            'processing_time' => isset($history_entry['ai_processing_time']) ? $history_entry['ai_processing_time'] : 0,
            'fallback_used' => isset($history_entry['fallback_used']) ? $history_entry['fallback_used'] : false
        );

        // Add to transactions array
        if (!isset($credit_data['transactions'])) {
            $credit_data['transactions'] = array();
        }
        array_unshift($credit_data['transactions'], $transaction);

        // Keep only last 100 transactions
        $credit_data['transactions'] = array_slice($credit_data['transactions'], 0, 100);

        // Update totals
        $credit_data['used_total'] += $history_entry['credits_used'];
        $credit_data['last_updated'] = time();

        // Update user meta
        update_user_meta($user_id, '_fmrseo_ai_credits', $credit_data);
    }

    /**
     * Update global AI usage statistics
     *
     * @param array $history_entry The history entry data
     */
    private function update_global_statistics($history_entry) {
        $stats = get_option('fmrseo_ai_statistics', array());
        
        if (!is_array($stats)) {
            $stats = array(
                'total_ai_operations' => 0,
                'total_manual_operations' => 0,
                'total_credits_used' => 0,
                'successful_ai_operations' => 0,
                'failed_ai_operations' => 0,
                'fallback_operations' => 0,
                'bulk_operations' => 0,
                'single_operations' => 0,
                'average_processing_time' => 0,
                'last_updated' => time(),
                'daily_stats' => array(),
                'monthly_stats' => array()
            );
        }

        $is_ai = ($history_entry['method'] === 'ai');
        $is_bulk = isset($history_entry['bulk_operation']) && $history_entry['bulk_operation'];
        $date_key = date('Y-m-d', $history_entry['timestamp']);
        $month_key = date('Y-m', $history_entry['timestamp']);

        // Update main counters
        if ($is_ai) {
            $stats['total_ai_operations']++;
            
            if (isset($history_entry['credits_used'])) {
                $stats['total_credits_used'] += $history_entry['credits_used'];
            }
            
            if (isset($history_entry['error_occurred']) && $history_entry['error_occurred']) {
                $stats['failed_ai_operations']++;
            } else {
                $stats['successful_ai_operations']++;
            }
            
            if (isset($history_entry['fallback_used']) && $history_entry['fallback_used']) {
                $stats['fallback_operations']++;
            }
            
            // Update average processing time
            if (isset($history_entry['ai_processing_time']) && $history_entry['ai_processing_time'] > 0) {
                $current_avg = $stats['average_processing_time'];
                $total_ops = $stats['total_ai_operations'];
                $stats['average_processing_time'] = (($current_avg * ($total_ops - 1)) + $history_entry['ai_processing_time']) / $total_ops;
            }
        } else {
            $stats['total_manual_operations']++;
        }

        // Update bulk/single counters
        if ($is_bulk) {
            $stats['bulk_operations']++;
        } else {
            $stats['single_operations']++;
        }

        // Update daily stats
        if (!isset($stats['daily_stats'][$date_key])) {
            $stats['daily_stats'][$date_key] = array(
                'ai_operations' => 0,
                'manual_operations' => 0,
                'credits_used' => 0,
                'successful_ai' => 0,
                'failed_ai' => 0
            );
        }

        $daily = &$stats['daily_stats'][$date_key];
        if ($is_ai) {
            $daily['ai_operations']++;
            $daily['credits_used'] += isset($history_entry['credits_used']) ? $history_entry['credits_used'] : 0;
            
            if (isset($history_entry['error_occurred']) && $history_entry['error_occurred']) {
                $daily['failed_ai']++;
            } else {
                $daily['successful_ai']++;
            }
        } else {
            $daily['manual_operations']++;
        }

        // Update monthly stats
        if (!isset($stats['monthly_stats'][$month_key])) {
            $stats['monthly_stats'][$month_key] = array(
                'ai_operations' => 0,
                'manual_operations' => 0,
                'credits_used' => 0,
                'successful_ai' => 0,
                'failed_ai' => 0
            );
        }

        $monthly = &$stats['monthly_stats'][$month_key];
        if ($is_ai) {
            $monthly['ai_operations']++;
            $monthly['credits_used'] += isset($history_entry['credits_used']) ? $history_entry['credits_used'] : 0;
            
            if (isset($history_entry['error_occurred']) && $history_entry['error_occurred']) {
                $monthly['failed_ai']++;
            } else {
                $monthly['successful_ai']++;
            }
        } else {
            $monthly['manual_operations']++;
        }

        // Clean up old daily stats (keep last 90 days)
        $cutoff_date = date('Y-m-d', strtotime('-90 days'));
        foreach ($stats['daily_stats'] as $date => $data) {
            if ($date < $cutoff_date) {
                unset($stats['daily_stats'][$date]);
            }
        }

        // Clean up old monthly stats (keep last 24 months)
        $cutoff_month = date('Y-m', strtotime('-24 months'));
        foreach ($stats['monthly_stats'] as $month => $data) {
            if ($month < $cutoff_month) {
                unset($stats['monthly_stats'][$month]);
            }
        }

        $stats['last_updated'] = time();
        update_option('fmrseo_ai_statistics', $stats);
    }

    /**
     * Generate unique operation ID
     *
     * @return string Unique operation ID
     */
    private function generate_operation_id() {
        return 'fmr_' . time() . '_' . wp_generate_password(8, false);
    }

    /**
     * Get AI usage statistics
     *
     * @param array $filters Optional filters for statistics
     * @return array Statistics data
     */
    public function get_ai_statistics($filters = array()) {
        $stats = get_option('fmrseo_ai_statistics', array());
        
        // Apply filters if provided
        if (!empty($filters)) {
            $stats = $this->apply_statistics_filters($stats, $filters);
        }

        // Add calculated metrics
        $stats['success_rate'] = 0;
        if ($stats['total_ai_operations'] > 0) {
            $stats['success_rate'] = round(($stats['successful_ai_operations'] / $stats['total_ai_operations']) * 100, 2);
        }

        $stats['fallback_rate'] = 0;
        if ($stats['total_ai_operations'] > 0) {
            $stats['fallback_rate'] = round(($stats['fallback_operations'] / $stats['total_ai_operations']) * 100, 2);
        }

        return $stats;
    }

    /**
     * Apply filters to statistics data
     *
     * @param array $stats Statistics data
     * @param array $filters Filters to apply
     * @return array Filtered statistics
     */
    private function apply_statistics_filters($stats, $filters) {
        // Date range filter
        if (isset($filters['date_from']) || isset($filters['date_to'])) {
            $date_from = isset($filters['date_from']) ? $filters['date_from'] : '1970-01-01';
            $date_to = isset($filters['date_to']) ? $filters['date_to'] : date('Y-m-d');
            
            // Filter daily stats
            $filtered_daily = array();
            foreach ($stats['daily_stats'] as $date => $data) {
                if ($date >= $date_from && $date <= $date_to) {
                    $filtered_daily[$date] = $data;
                }
            }
            $stats['daily_stats'] = $filtered_daily;
        }

        return $stats;
    }

    /**
     * Get user-specific AI history
     *
     * @param int $user_id User ID (default: current user)
     * @param int $limit Number of records to return
     * @return array User history data
     */
    public function get_user_ai_history($user_id = null, $limit = 50) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        $credit_data = get_user_meta($user_id, '_fmrseo_ai_credits', true);
        
        if (!is_array($credit_data) || !isset($credit_data['transactions'])) {
            return array();
        }

        $transactions = array_slice($credit_data['transactions'], 0, $limit);
        
        // Enhance transaction data with post information
        foreach ($transactions as &$transaction) {
            if (isset($transaction['post_id'])) {
                $post = get_post($transaction['post_id']);
                if ($post) {
                    $transaction['post_title'] = $post->post_title;
                    $transaction['post_url'] = wp_get_attachment_url($transaction['post_id']);
                }
            }
        }

        return $transactions;
    }

    /**
     * Add statistics page to admin menu
     */
    public function add_statistics_page() {
        add_submenu_page(
            'upload.php',
            __('AI Rename Statistics', 'fmrseo'),
            __('AI Statistics', 'fmrseo'),
            'manage_options',
            'fmrseo-ai-statistics',
            array($this, 'render_statistics_page')
        );
    }

    /**
     * Render the AI statistics admin page
     */
    public function render_statistics_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'fmrseo'));
        }

        $stats = $this->get_ai_statistics();
        $user_history = $this->get_user_ai_history();
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('AI Rename Statistics', 'fmrseo'); ?></h1>
            
            <!-- Statistics Overview -->
            <div class="fmrseo-stats-overview">
                <div class="fmrseo-stats-cards">
                    <div class="fmrseo-stat-card">
                        <h3><?php esc_html_e('Total AI Operations', 'fmrseo'); ?></h3>
                        <div class="stat-number"><?php echo esc_html($stats['total_ai_operations']); ?></div>
                    </div>
                    
                    <div class="fmrseo-stat-card">
                        <h3><?php esc_html_e('Success Rate', 'fmrseo'); ?></h3>
                        <div class="stat-number"><?php echo esc_html($stats['success_rate']); ?>%</div>
                    </div>
                    
                    <div class="fmrseo-stat-card">
                        <h3><?php esc_html_e('Credits Used', 'fmrseo'); ?></h3>
                        <div class="stat-number"><?php echo esc_html($stats['total_credits_used']); ?></div>
                    </div>
                    
                    <div class="fmrseo-stat-card">
                        <h3><?php esc_html_e('Avg Processing Time', 'fmrseo'); ?></h3>
                        <div class="stat-number"><?php echo esc_html(round($stats['average_processing_time'], 2)); ?>s</div>
                    </div>
                </div>
            </div>

            <!-- Detailed Statistics -->
            <div class="fmrseo-detailed-stats">
                <h2><?php esc_html_e('Detailed Statistics', 'fmrseo'); ?></h2>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Metric', 'fmrseo'); ?></th>
                            <th><?php esc_html_e('Value', 'fmrseo'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><?php esc_html_e('Total Manual Operations', 'fmrseo'); ?></td>
                            <td><?php echo esc_html($stats['total_manual_operations']); ?></td>
                        </tr>
                        <tr>
                            <td><?php esc_html_e('Successful AI Operations', 'fmrseo'); ?></td>
                            <td><?php echo esc_html($stats['successful_ai_operations']); ?></td>
                        </tr>
                        <tr>
                            <td><?php esc_html_e('Failed AI Operations', 'fmrseo'); ?></td>
                            <td><?php echo esc_html($stats['failed_ai_operations']); ?></td>
                        </tr>
                        <tr>
                            <td><?php esc_html_e('Fallback Operations', 'fmrseo'); ?></td>
                            <td><?php echo esc_html($stats['fallback_operations']); ?> (<?php echo esc_html($stats['fallback_rate']); ?>%)</td>
                        </tr>
                        <tr>
                            <td><?php esc_html_e('Bulk Operations', 'fmrseo'); ?></td>
                            <td><?php echo esc_html($stats['bulk_operations']); ?></td>
                        </tr>
                        <tr>
                            <td><?php esc_html_e('Single Operations', 'fmrseo'); ?></td>
                            <td><?php echo esc_html($stats['single_operations']); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Recent Activity -->
            <div class="fmrseo-recent-activity">
                <h2><?php esc_html_e('Recent AI Activity', 'fmrseo'); ?></h2>
                
                <?php if (!empty($user_history)): ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Date', 'fmrseo'); ?></th>
                            <th><?php esc_html_e('File', 'fmrseo'); ?></th>
                            <th><?php esc_html_e('Operation', 'fmrseo'); ?></th>
                            <th><?php esc_html_e('Credits', 'fmrseo'); ?></th>
                            <th><?php esc_html_e('Processing Time', 'fmrseo'); ?></th>
                            <th><?php esc_html_e('Status', 'fmrseo'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($user_history, 0, 20) as $transaction): ?>
                        <tr>
                            <td><?php echo esc_html(date('Y-m-d H:i:s', $transaction['timestamp'])); ?></td>
                            <td>
                                <?php if (isset($transaction['filename'])): ?>
                                    <?php echo esc_html($transaction['filename']); ?>
                                <?php else: ?>
                                    <?php esc_html_e('Unknown', 'fmrseo'); ?>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html(ucfirst($transaction['operation'])); ?></td>
                            <td><?php echo esc_html($transaction['amount']); ?></td>
                            <td>
                                <?php if (isset($transaction['processing_time']) && $transaction['processing_time'] > 0): ?>
                                    <?php echo esc_html(round($transaction['processing_time'], 2)); ?>s
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (isset($transaction['fallback_used']) && $transaction['fallback_used']): ?>
                                    <span class="fmrseo-status-warning"><?php esc_html_e('Fallback', 'fmrseo'); ?></span>
                                <?php else: ?>
                                    <span class="fmrseo-status-success"><?php esc_html_e('Success', 'fmrseo'); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p><?php esc_html_e('No AI activity found.', 'fmrseo'); ?></p>
                <?php endif; ?>
            </div>

            <!-- Export Options -->
            <div class="fmrseo-export-options">
                <h2><?php esc_html_e('Export Options', 'fmrseo'); ?></h2>
                <p>
                    <button type="button" class="button" id="fmrseo-export-statistics">
                        <?php esc_html_e('Export Statistics (CSV)', 'fmrseo'); ?>
                    </button>
                    <button type="button" class="button" id="fmrseo-export-history">
                        <?php esc_html_e('Export History (CSV)', 'fmrseo'); ?>
                    </button>
                </p>
            </div>
        </div>

        <style>
        .fmrseo-stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .fmrseo-stat-card {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
            text-align: center;
        }
        
        .fmrseo-stat-card h3 {
            margin: 0 0 10px 0;
            font-size: 14px;
            color: #666;
        }
        
        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #0073aa;
        }
        
        .fmrseo-detailed-stats,
        .fmrseo-recent-activity,
        .fmrseo-export-options {
            margin: 30px 0;
        }
        
        .fmrseo-status-success {
            color: #46b450;
            font-weight: bold;
        }
        
        .fmrseo-status-warning {
            color: #ffb900;
            font-weight: bold;
        }
        </style>

        <script>
        jQuery(document).ready(function($) {
            $('#fmrseo-export-statistics').on('click', function() {
                window.location.href = ajaxurl + '?action=fmrseo_export_ai_statistics&nonce=' + '<?php echo wp_create_nonce('fmrseo_export_nonce'); ?>';
            });
            
            $('#fmrseo-export-history').on('click', function() {
                window.location.href = ajaxurl + '?action=fmrseo_export_ai_history&nonce=' + '<?php echo wp_create_nonce('fmrseo_export_nonce'); ?>';
            });
        });
        </script>
        <?php
    }

    /**
     * AJAX handler for getting AI statistics
     */
    public function ajax_get_ai_statistics() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'fmrseo'));
        }

        $filters = array();
        if (isset($_POST['date_from'])) {
            $filters['date_from'] = sanitize_text_field($_POST['date_from']);
        }
        if (isset($_POST['date_to'])) {
            $filters['date_to'] = sanitize_text_field($_POST['date_to']);
        }

        $stats = $this->get_ai_statistics($filters);
        wp_send_json_success($stats);
    }

    /**
     * AJAX handler for exporting AI history
     */
    public function ajax_export_ai_history() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'fmrseo'));
        }

        if (!wp_verify_nonce($_GET['nonce'], 'fmrseo_export_nonce')) {
            wp_die(__('Security verification failed.', 'fmrseo'));
        }

        $history = $this->get_user_ai_history(null, 1000); // Export up to 1000 records

        // Set headers for CSV download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="fmrseo-ai-history-' . date('Y-m-d') . '.csv"');

        // Output CSV
        $output = fopen('php://output', 'w');
        
        // CSV headers
        fputcsv($output, array(
            'Date',
            'Operation ID',
            'File',
            'Operation Type',
            'Credits Used',
            'Processing Time',
            'Fallback Used',
            'Post ID'
        ));

        // CSV data
        foreach ($history as $transaction) {
            fputcsv($output, array(
                date('Y-m-d H:i:s', $transaction['timestamp']),
                isset($transaction['operation_id']) ? $transaction['operation_id'] : '',
                isset($transaction['filename']) ? $transaction['filename'] : '',
                ucfirst($transaction['operation']),
                $transaction['amount'],
                isset($transaction['processing_time']) ? round($transaction['processing_time'], 2) : '',
                isset($transaction['fallback_used']) ? ($transaction['fallback_used'] ? 'Yes' : 'No') : 'No',
                isset($transaction['post_id']) ? $transaction['post_id'] : ''
            ));
        }

        fclose($output);
        exit;
    }

    /**
     * Get comprehensive history for a specific media file
     *
     * @param int $post_id Media post ID
     * @return array Complete history with enhanced metadata
     */
    public function get_media_history($post_id) {
        $history = get_post_meta($post_id, '_fmrseo_rename_history', true);
        
        if (!is_array($history)) {
            return array();
        }

        // Enhance history entries with additional data
        foreach ($history as &$entry) {
            // Add user information
            if (isset($entry['user_id'])) {
                $user = get_user_by('id', $entry['user_id']);
                $entry['user_display_name'] = $user ? $user->display_name : __('Unknown User', 'fmrseo');
            }

            // Add formatted timestamp
            $entry['formatted_date'] = date('Y-m-d H:i:s', $entry['timestamp']);
            
            // Add method-specific formatting
            if ($entry['method'] === 'ai') {
                $entry['method_display'] = __('AI Rename', 'fmrseo');
                
                if (isset($entry['fallback_used']) && $entry['fallback_used']) {
                    $entry['method_display'] .= ' (' . __('Fallback', 'fmrseo') . ')';
                }
            } else {
                $entry['method_display'] = __('Manual Rename', 'fmrseo');
            }
        }

        return $history;
    }

    /**
     * Clean up old history data (maintenance function)
     *
     * @param int $days_to_keep Number of days to keep history
     */
    public function cleanup_old_history($days_to_keep = 90) {
        global $wpdb;
        
        $cutoff_timestamp = time() - ($days_to_keep * 24 * 60 * 60);
        
        // Get all posts with rename history
        $posts_with_history = $wpdb->get_results(
            "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_fmrseo_rename_history'"
        );

        $cleaned_count = 0;
        
        foreach ($posts_with_history as $post_meta) {
            $history = maybe_unserialize($post_meta->meta_value);
            
            if (!is_array($history)) {
                continue;
            }

            $original_count = count($history);
            
            // Filter out old entries
            $history = array_filter($history, function($entry) use ($cutoff_timestamp) {
                return isset($entry['timestamp']) && $entry['timestamp'] > $cutoff_timestamp;
            });

            // Re-index array
            $history = array_values($history);
            
            if (count($history) !== $original_count) {
                if (empty($history)) {
                    delete_post_meta($post_meta->post_id, '_fmrseo_rename_history');
                } else {
                    update_post_meta($post_meta->post_id, '_fmrseo_rename_history', $history);
                }
                $cleaned_count++;
            }
        }

        return $cleaned_count;
    }

    /**
     * Scheduled cleanup of old history data
     */
    public function scheduled_cleanup() {
        // Clean up old history entries (keep 90 days)
        $cleaned_posts = $this->cleanup_old_history(90);
        
        // Clean up old user credit transactions (keep 100 most recent per user)
        $this->cleanup_old_credit_transactions();
        
        // Log cleanup results
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("FMR AI History Cleanup: Cleaned {$cleaned_posts} posts");
        }
    }

    /**
     * Clean up old credit transactions for all users
     */
    private function cleanup_old_credit_transactions() {
        global $wpdb;
        
        // Get all users with credit data
        $users_with_credits = $wpdb->get_results(
            "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = '_fmrseo_ai_credits'"
        );

        $cleaned_users = 0;
        
        foreach ($users_with_credits as $user_meta) {
            $credit_data = maybe_unserialize($user_meta->meta_value);
            
            if (!is_array($credit_data) || !isset($credit_data['transactions'])) {
                continue;
            }

            $original_count = count($credit_data['transactions']);
            
            // Keep only the most recent 100 transactions
            if ($original_count > 100) {
                $credit_data['transactions'] = array_slice($credit_data['transactions'], 0, 100);
                update_user_meta($user_meta->user_id, '_fmrseo_ai_credits', $credit_data);
                $cleaned_users++;
            }
        }

        return $cleaned_users;
    }

    /**
     * Get history statistics for admin display
     *
     * @return array History statistics
     */
    public function get_history_statistics() {
        global $wpdb;
        
        // Count posts with history
        $posts_with_history = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_fmrseo_rename_history'"
        );

        // Get total history entries
        $history_entries = $wpdb->get_results(
            "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_fmrseo_rename_history'"
        );

        $total_entries = 0;
        $ai_entries = 0;
        $manual_entries = 0;
        
        foreach ($history_entries as $entry) {
            $history = maybe_unserialize($entry->meta_value);
            if (is_array($history)) {
                $total_entries += count($history);
                
                foreach ($history as $item) {
                    if (isset($item['method']) && $item['method'] === 'ai') {
                        $ai_entries++;
                    } else {
                        $manual_entries++;
                    }
                }
            }
        }

        return array(
            'posts_with_history' => intval($posts_with_history),
            'total_entries' => $total_entries,
            'ai_entries' => $ai_entries,
            'manual_entries' => $manual_entries,
            'storage_efficiency' => $posts_with_history > 0 ? round($total_entries / $posts_with_history, 1) : 0
        );
    }
}
