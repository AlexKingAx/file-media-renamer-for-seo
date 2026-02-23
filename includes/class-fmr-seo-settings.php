<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**========================================================
 * File Media Renamer class for Plugin Settings
 **========================================================*/
class File_Media_Renamer_SEO_Settings
{

    public function __construct()
    {
        add_action('admin_menu', array($this, 'fmrseo_add_settings_page'));
        add_action('admin_init', array($this, 'fmrseo_register_settings'));
    }

    /**
     * Adds a settings page under the Media menu.
     */
    public function fmrseo_add_settings_page()
    {
        add_submenu_page(
            'upload.php', // Parent slug
            esc_html__('File Media Renamer Settings', 'file-media-renamer-for-seo'), // Page title
            esc_html__('FMR Settings', 'file-media-renamer-for-seo'), // Menu title
            'manage_options', // Capability
            'fmrseo', // Menu slug
            array($this, 'fmrseo_settings_page_content') // Callback function
        );
    }

    /**
     * Registers plugin settings and adds settings fields.
     */
    public function fmrseo_register_settings()
    {
        register_setting(
            'fmrseo',
            'fmrseo_options',
            array(
                'sanitize_callback' => array($this, 'fmrseo_sanitize_options')
            )
        );

        add_settings_section(
            'fmrseo_section_general', // ID
            '', // Title
            array($this, 'fmrseo_settings_section_callback'), // Callback
            'fmrseo_general' // Page
        );

        // Checkbox: Rename Title
        add_settings_field(
            'rename_title', // ID
            esc_html__('Rename Title', 'file-media-renamer-for-seo'), // Title
            array($this, 'fmrseo_checkbox_callback'), // Callback
            'fmrseo_general', // Page
            'fmrseo_section_general', // Section
            array(
                'label_for' => 'rename_title',
                'description' => esc_html__('If enabled, the media title will also be renamed.', 'file-media-renamer-for-seo'),
            )
        );

        // Checkbox: Rename Alt Text
        add_settings_field(
            'rename_alt_text', // ID
            esc_html__('Rename Alt Text', 'file-media-renamer-for-seo'), // Title
            array($this, 'fmrseo_checkbox_callback'), // Callback
            'fmrseo_general', // Page
            'fmrseo_section_general', // Section
            array(
                'label_for' => 'rename_alt_text',
                'description' => esc_html__('If enabled, the media alt text will also be renamed.', 'file-media-renamer-for-seo'),
            )
        );

        add_settings_section(
            'fmrseo_section_ai', // ID
            '', // Title
            array($this, 'fmrseo_ai_settings_section_callback'), // Callback
            'fmrseo_ai_rename' // Page
        );

        add_settings_field(
            'ai_enable',
            esc_html__('Enable AI rename', 'file-media-renamer-for-seo'),
            array($this, 'fmrseo_checkbox_callback'),
            'fmrseo_ai_rename',
            'fmrseo_section_ai',
            array(
                'label_for' => 'ai_enable',
                'description' => esc_html__('Enable automatic AI filename generation.', 'file-media-renamer-for-seo'),
            )
        );

        add_settings_field(
            'ai_provider',
            esc_html__('AI Provider', 'file-media-renamer-for-seo'),
            array($this, 'fmrseo_select_callback'),
            'fmrseo_ai_rename',
            'fmrseo_section_ai',
            array(
                'label_for' => 'ai_provider',
                'options' => array(
                    'openai' => esc_html__('OpenAI', 'file-media-renamer-for-seo'),
                ),
                'description' => esc_html__('Select the AI provider to use for renaming.', 'file-media-renamer-for-seo'),
            )
        );

        add_settings_field(
            'ai_api_key',
            esc_html__('API Key', 'file-media-renamer-for-seo'),
            array($this, 'fmrseo_password_callback'),
            'fmrseo_ai_rename',
            'fmrseo_section_ai',
            array(
                'label_for' => 'ai_api_key',
                'description' => esc_html__('Enter your OpenAI API key. Leave empty to keep the current key.', 'file-media-renamer-for-seo'),
            )
        );

        add_settings_field(
            'ai_model',
            esc_html__('Model', 'file-media-renamer-for-seo'),
            array($this, 'fmrseo_text_callback'),
            'fmrseo_ai_rename',
            'fmrseo_section_ai',
            array(
                'label_for' => 'ai_model',
                'placeholder' => 'gpt-4.1-mini',
                'description' => esc_html__('OpenAI model name used for image-based filename generation.', 'file-media-renamer-for-seo'),
            )
        );

        add_settings_field(
            'ai_website_info',
            esc_html__('Website Info', 'file-media-renamer-for-seo'),
            array($this, 'fmrseo_textarea_callback'),
            'fmrseo_ai_rename',
            'fmrseo_section_ai',
            array(
                'label_for' => 'ai_website_info',
                'description' => esc_html__('Optional context about your website used in the AI prompt.', 'file-media-renamer-for-seo'),
            )
        );

        add_settings_field(
            'ai_brand',
            esc_html__('Brand', 'file-media-renamer-for-seo'),
            array($this, 'fmrseo_text_callback'),
            'fmrseo_ai_rename',
            'fmrseo_section_ai',
            array(
                'label_for' => 'ai_brand',
                'description' => esc_html__('Brand keyword to include in generated filenames.', 'file-media-renamer-for-seo'),
            )
        );

        add_settings_field(
            'ai_delay',
            esc_html__('Delay between requests (seconds)', 'file-media-renamer-for-seo'),
            array($this, 'fmrseo_number_callback'),
            'fmrseo_ai_rename',
            'fmrseo_section_ai',
            array(
                'label_for' => 'ai_delay',
                'min' => '0',
                'max' => '60',
                'step' => '1',
                'description' => esc_html__('Delay used during Batch AI Rename to reduce rate-limit errors.', 'file-media-renamer-for-seo'),
            )
        );

        add_settings_field(
            'ai_max_files',
            esc_html__('Max files per batch', 'file-media-renamer-for-seo'),
            array($this, 'fmrseo_number_callback'),
            'fmrseo_ai_rename',
            'fmrseo_section_ai',
            array(
                'label_for' => 'ai_max_files',
                'min' => '0',
                'max' => '10000',
                'step' => '1',
                'description' => esc_html__('Maximum files allowed for Batch AI Rename. Set 0 for unlimited.', 'file-media-renamer-for-seo'),
            )
        );
    }

    /**
     * Default plugin settings.
     *
     * @return array
     */
    public static function fmrseo_get_default_options()
    {
        return array(
            'rename_title' => false,
            'rename_alt_text' => false,
            'ai_enable' => false,
            'ai_provider' => 'openai',
            'ai_api_key' => '',
            'ai_model' => 'gpt-4.1-mini',
            'ai_website_info' => '',
            'ai_brand' => '',
            'ai_delay' => 2,
            'ai_max_files' => 500,
        );
    }

    /**
     * Returns plugin options merged with defaults.
     *
     * @return array
     */
    private function fmrseo_get_options()
    {
        $options = get_option('fmrseo_options', array());
        if (!is_array($options)) {
            $options = array();
        }

        return wp_parse_args($options, self::fmrseo_get_default_options());
    }

    /**
     * Returns the settings tabs.
     *
     * @return array
     */
    private function fmrseo_get_tabs()
    {
        return array(
            'general' => esc_html__('General', 'file-media-renamer-for-seo'),
            'ai-rename' => esc_html__('AI Rename', 'file-media-renamer-for-seo'),
        );
    }

    /**
     * Returns current active tab.
     *
     * @return string
     */
    private function fmrseo_get_active_tab()
    {
        $tabs = $this->fmrseo_get_tabs();

        if (!isset($_GET['tab'])) {
            return 'general';
        }

        $tab = sanitize_key(wp_unslash($_GET['tab']));

        if (!array_key_exists($tab, $tabs)) {
            return 'general';
        }

        return $tab;
    }

    /**
     * Returns current submitted tab from the settings form.
     *
     * @return string
     */
    private function fmrseo_get_submitted_tab()
    {
        $tabs = $this->fmrseo_get_tabs();
        $tab = 'general';

        if (!$this->fmrseo_has_valid_settings_nonce()) {
            return $tab;
        }

        if (isset($_POST['_wp_http_referer'])) {
            $referer = esc_url_raw(wp_unslash($_POST['_wp_http_referer']));
            $query = wp_parse_url($referer, PHP_URL_QUERY);

            if (is_string($query)) {
                parse_str($query, $query_args);
                if (isset($query_args['tab'])) {
                    $tab = sanitize_key($query_args['tab']);
                }
            }
        }

        if (!array_key_exists($tab, $tabs)) {
            $tab = 'general';
        }

        return $tab;
    }

    /**
     * Verifies settings nonce from the options form submission.
     *
     * @return bool
     */
    private function fmrseo_has_valid_settings_nonce()
    {
        if (!isset($_POST['_wpnonce'])) {
            return false;
        }

        $nonce = sanitize_text_field(wp_unslash($_POST['_wpnonce']));

        return (bool) wp_verify_nonce($nonce, 'fmrseo-options');
    }

    /**
     * Callback for the settings section description.
     *
     * @param array $args Arguments passed by WordPress.
     */
    public function fmrseo_settings_section_callback($args)
    {
?>
        <p id="<?php echo esc_attr($args['id']); ?>"><?php esc_html_e('Configure the settings for File Media Renamer for SEO.', 'file-media-renamer-for-seo'); ?></p>
    <?php
    }

    /**
     * Callback for AI settings section description.
     *
     * @param array $args Arguments passed by WordPress.
     */
    public function fmrseo_ai_settings_section_callback($args)
    {
    ?>
        <p id="<?php echo esc_attr($args['id']); ?>"><?php esc_html_e('Configure AI rename settings for automatic SEO filenames.', 'file-media-renamer-for-seo'); ?></p>
    <?php
    }

    /**
     * Callback for rendering checkbox fields.
     *
     * @param array $args Arguments passed by WordPress.
     */
    public function fmrseo_checkbox_callback($args)
    {
        $options = $this->fmrseo_get_options();
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
     * Callback for rendering select fields.
     *
     * @param array $args Arguments passed by WordPress.
     */
    public function fmrseo_select_callback($args)
    {
        $options = $this->fmrseo_get_options();
        $value = isset($options[$args['label_for']]) ? (string) $options[$args['label_for']] : '';
        $field_options = isset($args['options']) && is_array($args['options']) ? $args['options'] : array();
    ?>
        <select
            id="<?php echo esc_attr($args['label_for']); ?>"
            name="fmrseo_options[<?php echo esc_attr($args['label_for']); ?>]">
            <?php foreach ($field_options as $option_value => $option_label) : ?>
                <option value="<?php echo esc_attr($option_value); ?>" <?php selected($value, $option_value); ?>>
                    <?php echo esc_html($option_label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if (!empty($args['description'])) : ?>
            <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php endif; ?>
    <?php
    }

    /**
     * Callback for rendering text fields.
     *
     * @param array $args Arguments passed by WordPress.
     */
    public function fmrseo_text_callback($args)
    {
        $options = $this->fmrseo_get_options();
        $value = isset($options[$args['label_for']]) ? (string) $options[$args['label_for']] : '';
        $placeholder = isset($args['placeholder']) ? (string) $args['placeholder'] : '';
    ?>
        <input
            type="text"
            id="<?php echo esc_attr($args['label_for']); ?>"
            name="fmrseo_options[<?php echo esc_attr($args['label_for']); ?>]"
            class="regular-text"
            value="<?php echo esc_attr($value); ?>"
            placeholder="<?php echo esc_attr($placeholder); ?>" />
        <?php if (!empty($args['description'])) : ?>
            <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php endif; ?>
    <?php
    }

    /**
     * Callback for rendering password fields.
     *
     * @param array $args Arguments passed by WordPress.
     */
    public function fmrseo_password_callback($args)
    {
        $options = $this->fmrseo_get_options();
        $has_saved_value = !empty($options[$args['label_for']]);
    ?>
        <input
            type="password"
            id="<?php echo esc_attr($args['label_for']); ?>"
            name="fmrseo_options[<?php echo esc_attr($args['label_for']); ?>]"
            class="regular-text"
            value=""
            placeholder="********"
            autocomplete="new-password" />
        <?php if ($has_saved_value) : ?>
            <p class="description"><?php esc_html_e('A key is already saved. Leave this field empty to keep it.', 'file-media-renamer-for-seo'); ?></p>
        <?php endif; ?>
        <?php if (!empty($args['description'])) : ?>
            <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php endif; ?>
    <?php
    }

    /**
     * Callback for rendering textarea fields.
     *
     * @param array $args Arguments passed by WordPress.
     */
    public function fmrseo_textarea_callback($args)
    {
        $options = $this->fmrseo_get_options();
        $value = isset($options[$args['label_for']]) ? (string) $options[$args['label_for']] : '';
    ?>
        <textarea
            id="<?php echo esc_attr($args['label_for']); ?>"
            name="fmrseo_options[<?php echo esc_attr($args['label_for']); ?>]"
            class="large-text"
            rows="4"><?php echo esc_textarea($value); ?></textarea>
        <?php if (!empty($args['description'])) : ?>
            <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php endif; ?>
    <?php
    }

    /**
     * Callback for rendering number fields.
     *
     * @param array $args Arguments passed by WordPress.
     */
    public function fmrseo_number_callback($args)
    {
        $options = $this->fmrseo_get_options();
        $value = isset($options[$args['label_for']]) ? (float) $options[$args['label_for']] : 0;
        $min = isset($args['min']) ? (string) $args['min'] : '0';
        $max = isset($args['max']) ? (string) $args['max'] : '60';
        $step = isset($args['step']) ? (string) $args['step'] : '1';
    ?>
        <input
            type="number"
            id="<?php echo esc_attr($args['label_for']); ?>"
            name="fmrseo_options[<?php echo esc_attr($args['label_for']); ?>]"
            min="<?php echo esc_attr($min); ?>"
            max="<?php echo esc_attr($max); ?>"
            step="<?php echo esc_attr($step); ?>"
            value="<?php echo esc_attr($value); ?>" />
        <?php if (!empty($args['description'])) : ?>
            <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php endif; ?>
    <?php
    }

    /**
     * Renders the settings page content.
     */
    public function fmrseo_settings_page_content()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        // check parameter without controlling nonce
        // because is already done by wordpress using settings_fields()
        if (isset($_GET['settings-updated'])) {
            add_settings_error('fmrseo_messages', 'fmrseo_message', esc_html__('Settings Saved', 'file-media-renamer-for-seo'), 'updated');
        }

        $tabs = $this->fmrseo_get_tabs();
        $active_tab = $this->fmrseo_get_active_tab();
        $settings_page = ('ai-rename' === $active_tab) ? 'fmrseo_ai_rename' : 'fmrseo_general';

        settings_errors('fmrseo_messages');
    ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <h2 class="nav-tab-wrapper">
                <?php foreach ($tabs as $tab_slug => $tab_label) :
                    $tab_url = add_query_arg(
                        array(
                            'page' => 'fmrseo',
                            'tab' => $tab_slug,
                        ),
                        admin_url('upload.php')
                    );
                    $tab_class = 'nav-tab' . (' ' . (($active_tab === $tab_slug) ? 'nav-tab-active' : ''));
                ?>
                    <a href="<?php echo esc_url($tab_url); ?>" class="<?php echo esc_attr(trim($tab_class)); ?>">
                        <?php echo esc_html($tab_label); ?>
                    </a>
                <?php endforeach; ?>
            </h2>
            <form action="options.php" method="post">
                <?php
                settings_fields('fmrseo');
                do_settings_sections($settings_page);
                submit_button(esc_html__('Save', 'file-media-renamer-for-seo'));
                ?>
            </form>
        </div>
<?php
    }

    /**
     * Sanitize options before saving.
     */
    public function fmrseo_sanitize_options($options)
    {
        $options = is_array($options) ? $options : array();
        $current_options = get_option('fmrseo_options', array());
        if (!is_array($current_options)) {
            $current_options = array();
        }

        $defaults = self::fmrseo_get_default_options();
        $sanitized = wp_parse_args($current_options, $defaults);
        $submitted_tab = $this->fmrseo_get_submitted_tab();

        if ('general' === $submitted_tab) {
            $sanitized['rename_title'] = !empty($options['rename_title']);
            $sanitized['rename_alt_text'] = !empty($options['rename_alt_text']);
        }

        if ('ai-rename' === $submitted_tab) {
            $sanitized['ai_enable'] = !empty($options['ai_enable']);

            $provider = isset($options['ai_provider']) ? sanitize_key($options['ai_provider']) : $defaults['ai_provider'];
            $sanitized['ai_provider'] = ('openai' === $provider) ? 'openai' : 'openai';

            $model = isset($options['ai_model']) ? sanitize_text_field($options['ai_model']) : '';
            $sanitized['ai_model'] = !empty($model) ? $model : $defaults['ai_model'];

            $sanitized['ai_website_info'] = isset($options['ai_website_info']) ? sanitize_textarea_field($options['ai_website_info']) : '';
            $sanitized['ai_brand'] = isset($options['ai_brand']) ? sanitize_text_field($options['ai_brand']) : '';

            $delay = isset($options['ai_delay']) ? (float) $options['ai_delay'] : (float) $defaults['ai_delay'];
            if ($delay < 0) {
                $delay = 0;
            }
            if ($delay > 60) {
                $delay = 60;
            }
            $sanitized['ai_delay'] = $delay;

            $sanitized['ai_max_files'] = isset($options['ai_max_files']) ? absint($options['ai_max_files']) : absint($defaults['ai_max_files']);

            $api_key = '';
            if (isset($options['ai_api_key'])) {
                $api_key = trim(sanitize_text_field($options['ai_api_key']));
            }

            if (!empty($api_key)) {
                $sanitized['ai_api_key'] = $api_key;
            } elseif (isset($current_options['ai_api_key']) && is_string($current_options['ai_api_key'])) {
                $sanitized['ai_api_key'] = $current_options['ai_api_key'];
            }
        }

        return $sanitized;
    }
}
