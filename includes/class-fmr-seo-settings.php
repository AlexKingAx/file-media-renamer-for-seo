<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class File_Media_Renamer_SEO_Settings {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));
    }

    /**
     * Adds a settings page under the Media menu.
     */
    public function add_settings_page() {
        add_submenu_page(
            'upload.php', // Parent slug
            __('File Media Renamer Settings', 'fmrseo'), // Page title
            __('FMR Settings', 'fmrseo'), // Menu title
            'manage_options', // Capability
            'fmrseo', // Menu slug
            array($this, 'settings_page_content') // Callback function
        );
    }

    /**
     * Registers plugin settings and adds settings fields.
     */
    public function register_settings() {
        register_setting('fmrseo', 'fmrseo_options', array($this, 'sanitize_options'));

        add_settings_section(
            'fmrseo_section_developers', // ID
            '', // Title
            array($this, 'settings_section_callback'), // Callback
            'fmrseo' // Page
        );

        // Checkbox: Rename Title
        add_settings_field(
            'rename_title', // ID
            __('Rename Title', 'fmrseo'), // Title
            array($this, 'checkbox_callback'), // Callback
            'fmrseo', // Page
            'fmrseo_section_developers', // Section
            array(
                'label_for' => 'rename_title',
                'description' => __('If enabled, the media title will also be renamed.', 'fmrseo'),
            )
        );

        // Checkbox: Rename Alt Text
        add_settings_field(
            'rename_alt_text', // ID
            __('Rename Alt Text', 'fmrseo'), // Title
            array($this, 'checkbox_callback'), // Callback
            'fmrseo', // Page
            'fmrseo_section_developers', // Section
            array(
                'label_for' => 'rename_alt_text',
                'description' => __('If enabled, the media alt text will also be renamed.', 'fmrseo'),
            )
        );

        // AI Settings Section
        add_settings_section(
            'fmrseo_ai_section', // ID
            __('AI Configuration', 'fmrseo'), // Title
            array($this, 'ai_settings_section_callback'), // Callback
            'fmrseo' // Page
        );

        // AI Enable/Disable Toggle
        add_settings_field(
            'ai_enabled', // ID
            __('Enable AI Renaming', 'fmrseo'), // Title
            array($this, 'checkbox_callback'), // Callback
            'fmrseo', // Page
            'fmrseo_ai_section', // Section
            array(
                'label_for' => 'ai_enabled',
                'description' => __('Enable AI-powered automatic media renaming functionality.', 'fmrseo'),
            )
        );

        // API Key Field
        add_settings_field(
            'ai_api_key', // ID
            __('API Key', 'fmrseo'), // Title
            array($this, 'text_callback'), // Callback
            'fmrseo', // Page
            'fmrseo_ai_section', // Section
            array(
                'label_for' => 'ai_api_key',
                'description' => __('Enter your AI service API key for content analysis and name generation.', 'fmrseo'),
                'type' => 'password',
            )
        );

        // Credit Balance Display
        add_settings_field(
            'ai_credit_balance', // ID
            __('Credit Balance', 'fmrseo'), // Title
            array($this, 'credit_balance_callback'), // Callback
            'fmrseo', // Page
            'fmrseo_ai_section', // Section
            array(
                'label_for' => 'ai_credit_balance',
                'description' => __('Current available credits for AI operations.', 'fmrseo'),
            )
        );

        // API Timeout Setting
        add_settings_field(
            'ai_timeout', // ID
            __('API Timeout (seconds)', 'fmrseo'), // Title
            array($this, 'number_callback'), // Callback
            'fmrseo', // Page
            'fmrseo_ai_section', // Section
            array(
                'label_for' => 'ai_timeout',
                'description' => __('Timeout for AI API requests in seconds (default: 30).', 'fmrseo'),
                'min' => 10,
                'max' => 120,
            )
        );

        // Max Retries Setting
        add_settings_field(
            'ai_max_retries', // ID
            __('Max Retries', 'fmrseo'), // Title
            array($this, 'number_callback'), // Callback
            'fmrseo', // Page
            'fmrseo_ai_section', // Section
            array(
                'label_for' => 'ai_max_retries',
                'description' => __('Maximum number of retry attempts for failed AI requests (default: 2).', 'fmrseo'),
                'min' => 0,
                'max' => 5,
            )
        );

        // AI Usage Statistics Section
        add_settings_section(
            'fmrseo_ai_stats_section', // ID
            __('AI Usage Statistics', 'fmrseo'), // Title
            array($this, 'ai_stats_section_callback'), // Callback
            'fmrseo' // Page
        );

        // AI Statistics Display
        add_settings_field(
            'ai_usage_statistics', // ID
            __('Usage Overview', 'fmrseo'), // Title
            array($this, 'ai_statistics_callback'), // Callback
            'fmrseo', // Page
            'fmrseo_ai_stats_section', // Section
            array(
                'label_for' => 'ai_usage_statistics',
                'description' => __('Overview of your AI usage and performance metrics.', 'fmrseo'),
            )
        );
    }

    /**
     * Callback for the settings section description.
     *
     * @param array $args Arguments passed by WordPress.
     */
    public function settings_section_callback($args) {
        ?>
        <p id="<?php echo esc_attr($args['id']); ?>"><?php esc_html_e('Configure the settings for File Media Renamer for SEO.', 'fmrseo'); ?></p>
        <?php
    }

    /**
     * Callback for the AI settings section description.
     *
     * @param array $args Arguments passed by WordPress.
     */
    public function ai_settings_section_callback($args) {
        ?>
        <p id="<?php echo esc_attr($args['id']); ?>"><?php esc_html_e('Configure AI-powered automatic media renaming settings. AI functionality requires a valid API key and sufficient credits.', 'fmrseo'); ?></p>
        <?php
    }

    /**
     * Callback for the AI statistics section description.
     *
     * @param array $args Arguments passed by WordPress.
     */
    public function ai_stats_section_callback($args) {
        ?>
        <p id="<?php echo esc_attr($args['id']); ?>"><?php esc_html_e('Monitor your AI usage patterns and performance metrics to optimize your media renaming workflow.', 'fmrseo'); ?></p>
        <?php
    }

    /**
     * Callback for rendering checkbox fields.
     *
     * @param array $args Arguments passed by WordPress.
     */
    public function checkbox_callback($args) {
        $options = get_option('fmrseo_options');
        $checked = isset($options[$args['label_for']]) ? $options[$args['label_for']] : false;
        ?>
        <input type="checkbox"
               id="<?php echo esc_attr($args['label_for']); ?>"
               name="fmrseo_options[<?php echo esc_attr($args['label_for']); ?>]"
               value="1"
               <?php checked($checked, 1); ?>>
        <label for="<?php echo esc_attr($args['label_for']); ?>">
            <?php echo esc_html($args['description']); ?>
        </label>
        <?php
    }

    /**
     * Callback for rendering text fields.
     *
     * @param array $args Arguments passed by WordPress.
     */
    public function text_callback($args) {
        $options = get_option('fmrseo_options');
        $value = isset($options[$args['label_for']]) ? $options[$args['label_for']] : '';
        $type = isset($args['type']) ? $args['type'] : 'text';
        ?>
        <input type="<?php echo esc_attr($type); ?>"
               id="<?php echo esc_attr($args['label_for']); ?>"
               name="fmrseo_options[<?php echo esc_attr($args['label_for']); ?>]"
               value="<?php echo esc_attr($value); ?>"
               class="regular-text">
        <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php
        // Show API key validation status
        if ($args['label_for'] === 'ai_api_key' && !empty($value)) {
            $is_valid = $this->validate_api_key($value);
            if ($is_valid) {
                echo '<p class="description" style="color: green;">' . esc_html__('✓ API Key is valid', 'fmrseo') . '</p>';
            } else {
                echo '<p class="description" style="color: red;">' . esc_html__('✗ API Key validation failed', 'fmrseo') . '</p>';
            }
        }
    }

    /**
     * Callback for rendering number fields.
     *
     * @param array $args Arguments passed by WordPress.
     */
    public function number_callback($args) {
        $options = get_option('fmrseo_options');
        $value = isset($options[$args['label_for']]) ? $options[$args['label_for']] : '';
        $min = isset($args['min']) ? $args['min'] : '';
        $max = isset($args['max']) ? $args['max'] : '';
        ?>
        <input type="number"
               id="<?php echo esc_attr($args['label_for']); ?>"
               name="fmrseo_options[<?php echo esc_attr($args['label_for']); ?>]"
               value="<?php echo esc_attr($value); ?>"
               <?php if ($min !== '') echo 'min="' . esc_attr($min) . '"'; ?>
               <?php if ($max !== '') echo 'max="' . esc_attr($max) . '"'; ?>
               class="small-text">
        <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php
    }

    /**
     * Callback for displaying credit balance.
     *
     * @param array $args Arguments passed by WordPress.
     */
    public function credit_balance_callback($args) {
        $options = get_option('fmrseo_options');
        $api_key = isset($options['ai_api_key']) ? $options['ai_api_key'] : '';
        
        if (empty($api_key)) {
            ?>
            <div class="fmrseo-credit-balance error">
                <p class="description">
                    <?php esc_html_e('Enter a valid API key to view credit balance.', 'fmrseo'); ?>
                </p>
            </div>
            <?php
            return;
        }

        // Try to get credit balance
        $credit_balance = $this->get_credit_balance($api_key);
        
        if ($credit_balance !== false) {
            $balance_class = 'positive';
            if ($credit_balance <= 5) {
                $balance_class = 'warning';
            }
            if ($credit_balance <= 0) {
                $balance_class = 'error';
            }
            ?>
            <div class="fmrseo-credit-balance <?php echo esc_attr($balance_class); ?>">
                <p style="font-size: 14px; font-weight: bold; margin: 0;">
                    <?php printf(esc_html__('Available Credits: %d', 'fmrseo'), $credit_balance); ?>
                </p>
                <?php
                // Show usage statistics if available
                $credit_data = get_user_meta(get_current_user_id(), '_fmrseo_ai_credits', true);
                if (is_array($credit_data) && isset($credit_data['used_total'])) {
                    ?>
                    <p class="description" style="margin: 5px 0 0 0;">
                        <?php printf(esc_html__('Total Credits Used: %d', 'fmrseo'), $credit_data['used_total']); ?>
                    </p>
                    <?php
                }
                ?>
            </div>
            <?php
        } else {
            ?>
            <div class="fmrseo-credit-balance error">
                <p class="description" style="margin: 0;">
                    <?php esc_html_e('Unable to retrieve credit balance. Please check your API key.', 'fmrseo'); ?>
                </p>
            </div>
            <?php
        }
    }

    /**
     * Renders the settings page content.
     */
    public function settings_page_content() {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (isset($_GET['settings-updated'])) {
            add_settings_error('fmrseo_messages', 'fmrseo_message', __('Settings Saved', 'fmrseo'), 'updated');
        }

        settings_errors('fmrseo_messages');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('fmrseo');
                do_settings_sections('fmrseo');
                submit_button(__('Save', 'fmrseo'));
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Sanitize and validate options before saving.
     *
     * @param array $input Raw input from the form.
     * @return array Sanitized options.
     */
    public function sanitize_options($input) {
        $sanitized = array();
        $current_options = get_option('fmrseo_options', array());

        // Sanitize checkbox fields
        $checkbox_fields = array('rename_title', 'rename_alt_text', 'ai_enabled');
        foreach ($checkbox_fields as $field) {
            $sanitized[$field] = isset($input[$field]) ? 1 : 0;
        }

        // Sanitize API key
        if (isset($input['ai_api_key'])) {
            $api_key = sanitize_text_field($input['ai_api_key']);
            
            // Only validate if key is not empty and has changed
            if (!empty($api_key) && $api_key !== $current_options['ai_api_key']) {
                if ($this->validate_api_key($api_key)) {
                    $sanitized['ai_api_key'] = $api_key;
                    // Initialize free credits for new API key
                    $this->initialize_free_credits();
                } else {
                    // Keep old key if validation fails
                    $sanitized['ai_api_key'] = isset($current_options['ai_api_key']) ? $current_options['ai_api_key'] : '';
                    add_settings_error(
                        'fmrseo_messages',
                        'invalid_api_key',
                        __('Invalid API key. Please check your key and try again.', 'fmrseo'),
                        'error'
                    );
                }
            } else {
                $sanitized['ai_api_key'] = $api_key;
            }
        }

        // Sanitize numeric fields
        $sanitized['ai_timeout'] = isset($input['ai_timeout']) ? 
            max(10, min(120, intval($input['ai_timeout']))) : 30;
        
        $sanitized['ai_max_retries'] = isset($input['ai_max_retries']) ? 
            max(0, min(5, intval($input['ai_max_retries']))) : 2;

        // Merge with existing options to preserve other settings
        return array_merge($current_options, $sanitized);
    }

    /**
     * Validate API key by making a test request.
     *
     * @param string $api_key The API key to validate.
     * @return bool True if valid, false otherwise.
     */
    private function validate_api_key($api_key) {
        if (empty($api_key)) {
            return false;
        }

        // Make a test request to validate the API key
        $response = wp_remote_get('https://api.example.com/v1/validate', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ),
            'timeout' => 10,
        ));

        if (is_wp_error($response)) {
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        return $response_code === 200;
    }

    /**
     * Get credit balance for the current API key.
     *
     * @param string $api_key The API key.
     * @return int|false Credit balance or false on error.
     */
    private function get_credit_balance($api_key) {
        if (empty($api_key)) {
            return false;
        }

        $response = wp_remote_get('https://api.example.com/v1/credits/balance', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ),
            'timeout' => 10,
        ));

        if (is_wp_error($response)) {
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        return isset($data['balance']) ? intval($data['balance']) : false;
    }

    /**
     * Initialize free credits for new users.
     */
    private function initialize_free_credits() {
        $user_id = get_current_user_id();
        $credit_data = get_user_meta($user_id, '_fmrseo_ai_credits', true);

        // Only initialize if not already done
        if (!is_array($credit_data) || !isset($credit_data['free_credits_initialized'])) {
            $credit_data = array(
                'balance' => 5, // 5 free credits
                'used_total' => 0,
                'last_updated' => time(),
                'free_credits_initialized' => true,
                'transactions' => array()
            );

            update_user_meta($user_id, '_fmrseo_ai_credits', $credit_data);
        }
    }

    /**
     * Callback for displaying AI usage statistics.
     *
     * @param array $args Arguments passed by WordPress.
     */
    public function ai_statistics_callback($args) {
        $options = get_option('fmrseo_options');
        $ai_enabled = !empty($options['ai_enabled']);
        
        if (!$ai_enabled) {
            ?>
            <div class="fmrseo-stats-disabled">
                <p class="description">
                    <?php esc_html_e('Enable AI functionality to view usage statistics.', 'fmrseo'); ?>
                </p>
            </div>
            <?php
            return;
        }

        // Get user credit data
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
                'failed_ai_operations' => 0,
                'total_credits_used' => 0,
                'average_processing_time' => 0,
                'fallback_operations' => 0
            );
        }

        // Calculate rates
        $success_rate = 0;
        $fallback_rate = 0;
        if ($stats['total_ai_operations'] > 0) {
            $success_rate = round(($stats['successful_ai_operations'] / $stats['total_ai_operations']) * 100, 1);
            $fallback_rate = round(($stats['fallback_operations'] / $stats['total_ai_operations']) * 100, 1);
        }

        ?>
        <div class="fmrseo-ai-statistics">
            <!-- Personal Statistics -->
            <div class="fmrseo-stats-section">
                <h4><?php esc_html_e('Your Usage', 'fmrseo'); ?></h4>
                <div class="fmrseo-stats-grid">
                    <div class="fmrseo-stat-box">
                        <div class="fmrseo-stat-number"><?php echo esc_html($credit_data['balance']); ?></div>
                        <div class="fmrseo-stat-label"><?php esc_html_e('Credits Remaining', 'fmrseo'); ?></div>
                    </div>
                    <div class="fmrseo-stat-box">
                        <div class="fmrseo-stat-number"><?php echo esc_html($credit_data['used_total']); ?></div>
                        <div class="fmrseo-stat-label"><?php esc_html_e('Credits Used', 'fmrseo'); ?></div>
                    </div>
                    <div class="fmrseo-stat-box">
                        <div class="fmrseo-stat-number"><?php echo esc_html(count($credit_data['transactions'])); ?></div>
                        <div class="fmrseo-stat-label"><?php esc_html_e('Total Operations', 'fmrseo'); ?></div>
                    </div>
                </div>
            </div>

            <!-- Global Performance Statistics -->
            <div class="fmrseo-stats-section">
                <h4><?php esc_html_e('System Performance', 'fmrseo'); ?></h4>
                <div class="fmrseo-stats-grid">
                    <div class="fmrseo-stat-box">
                        <div class="fmrseo-stat-number"><?php echo esc_html($success_rate); ?>%</div>
                        <div class="fmrseo-stat-label"><?php esc_html_e('Success Rate', 'fmrseo'); ?></div>
                    </div>
                    <div class="fmrseo-stat-box">
                        <div class="fmrseo-stat-number"><?php echo esc_html(round($stats['average_processing_time'], 1)); ?>s</div>
                        <div class="fmrseo-stat-label"><?php esc_html_e('Avg Processing Time', 'fmrseo'); ?></div>
                    </div>
                    <div class="fmrseo-stat-box">
                        <div class="fmrseo-stat-number"><?php echo esc_html($fallback_rate); ?>%</div>
                        <div class="fmrseo-stat-label"><?php esc_html_e('Fallback Rate', 'fmrseo'); ?></div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <?php if (!empty($credit_data['transactions'])): ?>
            <div class="fmrseo-stats-section">
                <h4><?php esc_html_e('Recent Activity', 'fmrseo'); ?></h4>
                <div class="fmrseo-recent-transactions">
                    <?php foreach (array_slice($credit_data['transactions'], 0, 5) as $transaction): ?>
                    <div class="fmrseo-transaction-item">
                        <div class="fmrseo-transaction-info">
                            <span class="fmrseo-transaction-file">
                                <?php echo esc_html(isset($transaction['filename']) ? $transaction['filename'] : __('Unknown file', 'fmrseo')); ?>
                            </span>
                            <span class="fmrseo-transaction-date">
                                <?php echo esc_html(date('M j, Y H:i', $transaction['timestamp'])); ?>
                            </span>
                        </div>
                        <div class="fmrseo-transaction-credits">
                            -<?php echo esc_html($transaction['amount']); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Action Links -->
            <div class="fmrseo-stats-actions">
                <a href="<?php echo esc_url(admin_url('upload.php?page=fmrseo-ai-statistics')); ?>" class="button">
                    <?php esc_html_e('View Detailed Statistics', 'fmrseo'); ?>
                </a>
            </div>
        </div>

        <style>
        .fmrseo-ai-statistics {
            max-width: 800px;
        }
        
        .fmrseo-stats-section {
            margin-bottom: 25px;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 5px;
        }
        
        .fmrseo-stats-section h4 {
            margin: 0 0 15px 0;
            font-size: 14px;
            font-weight: 600;
            color: #23282d;
        }
        
        .fmrseo-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
        }
        
        .fmrseo-stat-box {
            background: #fff;
            padding: 15px;
            border-radius: 4px;
            text-align: center;
            border: 1px solid #ddd;
        }
        
        .fmrseo-stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #0073aa;
            line-height: 1;
        }
        
        .fmrseo-stat-label {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        .fmrseo-recent-transactions {
            background: #fff;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        
        .fmrseo-transaction-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 15px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .fmrseo-transaction-item:last-child {
            border-bottom: none;
        }
        
        .fmrseo-transaction-info {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }
        
        .fmrseo-transaction-file {
            font-weight: 500;
            font-size: 13px;
        }
        
        .fmrseo-transaction-date {
            font-size: 11px;
            color: #666;
        }
        
        .fmrseo-transaction-credits {
            font-weight: bold;
            color: #dc3232;
        }
        
        .fmrseo-stats-actions {
            margin-top: 15px;
        }
        
        .fmrseo-stats-disabled {
            padding: 15px;
            background: #f0f0f0;
            border-radius: 4px;
            text-align: center;
        }
        </style>
        <?php
    }

    /**
     * Check if AI functionality is enabled and properly configured.
     *
     * @return bool True if AI is enabled and configured.
     */
    public function is_ai_enabled() {
        $options = get_option('fmrseo_options');
        return !empty($options['ai_enabled']) && !empty($options['ai_api_key']);
    }

    /**
     * Enqueue admin styles and scripts for settings page.
     */
    public function enqueue_admin_styles($hook) {
        // Only load on our settings page
        if ($hook !== 'media_page_fmrseo') {
            return;
        }

        wp_enqueue_style(
            'fmrseo-admin-settings',
            plugin_dir_url(dirname(__FILE__)) . 'assets/css/admin-settings.css',
            array(),
            '1.0.0'
        );

        wp_enqueue_script('jquery');
        wp_enqueue_script(
            'fmrseo-admin-settings',
            plugin_dir_url(dirname(__FILE__)) . 'assets/js/admin-settings.js',
            array('jquery'),
            '1.0.0',
            true
        );
    }
}
