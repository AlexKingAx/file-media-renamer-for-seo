<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * File Media Renamer class for Plugin Settings
 */
class File_Media_Renamer_SEO_Settings
{

    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    /**
     * Adds a settings page under the Media menu.
     */
    public function add_settings_page()
    {
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
    public function register_settings()
    {
        register_setting(
            'fmrseo',
            'fmrseo_options',
            array(
                'sanitize_callback' => array($this, 'sanitize_options')
            )
        );

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
    }

    /**
     * Callback for the settings section description.
     *
     * @param array $args Arguments passed by WordPress.
     */
    public function settings_section_callback($args)
    {
?>
        <p id="<?php echo esc_attr($args['id']); ?>"><?php esc_html_e('Configure the settings for File Media Renamer for SEO.', 'fmrseo'); ?></p>
    <?php
    }

    /**
     * Callback for rendering checkbox fields.
     *
     * @param array $args Arguments passed by WordPress.
     */
    public function checkbox_callback($args)
    {
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
     * Renders the settings page content.
     */
    public function settings_page_content()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (isset($_GET['settings-updated']) && check_admin_referer('fmrseo-options')) {
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
     * Sanitize options before saving.
     */
    public function sanitize_options($options)
    {
        $sanitized = array();

        // Checkbox: Rename Title
        $sanitized['rename_title'] = isset($options['rename_title']) ? (bool) $options['rename_title'] : false;

        // Checkbox: Rename Alt Text
        $sanitized['rename_alt_text'] = isset($options['rename_alt_text']) ? (bool) $options['rename_alt_text'] : false;

        return $sanitized;
    }
}
