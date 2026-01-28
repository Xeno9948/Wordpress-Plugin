<?php
/**
 * Plugin Name: Kiyoh Review Widget
 * Plugin URI: https://www.kiyoh.com
 * Description: Display Kiyoh review widgets on your WordPress site. Supports standard embedded widgets and sticky floating widgets with glass effect.
 * Version: 1.0.0
 * Author: Kiyoh
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: kiyoh-widget
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('KIYOH_WIDGET_VERSION', '1.0.0');
define('KIYOH_WIDGET_PATH', plugin_dir_path(__FILE__));
define('KIYOH_WIDGET_URL', plugin_dir_url(__FILE__));

/**
 * Main Kiyoh Widget Class
 */
class Kiyoh_Widget {

    /**
     * Instance of this class
     */
    private static $instance = null;

    /**
     * Default settings
     */
    private $defaults = array(
        'tenant_id' => '',
        'location_id' => '',
        'width' => 400,
        'height' => 222,
        'color' => 'white',
        'language' => 'en',
        'border' => 'on',
        'ssl' => 'on',
        'transparent' => 'off',
        'button' => 'on',
        'display_mode' => 'standard',
        'sticky_position' => 'right',
        'sticky_vertical' => 'middle',
        'sticky_glass' => 'on',
        'sticky_enabled' => 'off'
    );

    /**
     * Get instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Admin hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));

        // Frontend hooks
        add_action('wp_enqueue_scripts', array($this, 'frontend_enqueue_scripts'));
        add_action('wp_footer', array($this, 'render_sticky_widget'));

        // Shortcode
        add_shortcode('kiyoh_widget', array($this, 'shortcode_handler'));

        // Add settings link on plugins page
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_settings_link'));
    }

    /**
     * Add settings link to plugins page
     */
    public function add_settings_link($links) {
        $settings_link = '<a href="admin.php?page=kiyoh-widget">' . __('Settings', 'kiyoh-widget') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Kiyoh Widget', 'kiyoh-widget'),
            __('Kiyoh Widget', 'kiyoh-widget'),
            'manage_options',
            'kiyoh-widget',
            array($this, 'render_admin_page'),
            'dashicons-star-filled',
            100
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('kiyoh_widget_settings', 'kiyoh_widget_options', array($this, 'sanitize_options'));
    }

    /**
     * Sanitize options
     */
    public function sanitize_options($input) {
        $sanitized = array();

        $sanitized['tenant_id'] = sanitize_text_field($input['tenant_id'] ?? '');
        $sanitized['location_id'] = sanitize_text_field($input['location_id'] ?? '');
        $sanitized['width'] = absint($input['width'] ?? 400);
        $sanitized['height'] = absint($input['height'] ?? 222);
        $sanitized['color'] = sanitize_text_field($input['color'] ?? 'white');
        $sanitized['language'] = sanitize_text_field($input['language'] ?? 'en');
        $sanitized['border'] = in_array($input['border'] ?? 'on', array('on', 'off')) ? $input['border'] : 'on';
        $sanitized['ssl'] = in_array($input['ssl'] ?? 'on', array('on', 'off')) ? $input['ssl'] : 'on';
        $sanitized['transparent'] = in_array($input['transparent'] ?? 'off', array('on', 'off')) ? $input['transparent'] : 'off';
        $sanitized['button'] = in_array($input['button'] ?? 'on', array('on', 'off')) ? $input['button'] : 'on';
        $sanitized['display_mode'] = in_array($input['display_mode'] ?? 'standard', array('standard', 'sticky', 'both')) ? $input['display_mode'] : 'standard';
        $sanitized['sticky_position'] = in_array($input['sticky_position'] ?? 'right', array('left', 'right')) ? $input['sticky_position'] : 'right';
        $sanitized['sticky_vertical'] = in_array($input['sticky_vertical'] ?? 'middle', array('top', 'middle', 'bottom')) ? $input['sticky_vertical'] : 'middle';
        $sanitized['sticky_glass'] = in_array($input['sticky_glass'] ?? 'on', array('on', 'off')) ? $input['sticky_glass'] : 'on';
        $sanitized['sticky_enabled'] = in_array($input['sticky_enabled'] ?? 'off', array('on', 'off')) ? $input['sticky_enabled'] : 'off';

        return $sanitized;
    }

    /**
     * Get options
     */
    public function get_options() {
        $options = get_option('kiyoh_widget_options', array());
        return wp_parse_args($options, $this->defaults);
    }

    /**
     * Admin enqueue scripts
     */
    public function admin_enqueue_scripts($hook) {
        if ('toplevel_page_kiyoh-widget' !== $hook) {
            return;
        }

        wp_enqueue_style('kiyoh-widget-admin', KIYOH_WIDGET_URL . 'assets/css/admin.css', array(), KIYOH_WIDGET_VERSION);
        wp_enqueue_script('kiyoh-widget-admin', KIYOH_WIDGET_URL . 'assets/js/admin.js', array('jquery'), KIYOH_WIDGET_VERSION, true);
    }

    /**
     * Frontend enqueue scripts
     */
    public function frontend_enqueue_scripts() {
        $options = $this->get_options();

        wp_enqueue_style('kiyoh-widget-frontend', KIYOH_WIDGET_URL . 'assets/css/frontend.css', array(), KIYOH_WIDGET_VERSION);

        if ($options['sticky_enabled'] === 'on') {
            wp_enqueue_script('kiyoh-widget-frontend', KIYOH_WIDGET_URL . 'assets/js/frontend.js', array('jquery'), KIYOH_WIDGET_VERSION, true);
        }
    }

    /**
     * Build iframe URL
     */
    public function build_iframe_url($options = array()) {
        $settings = wp_parse_args($options, $this->get_options());

        $params = array(
            'color' => $settings['color'],
            'allowTransparency' => $settings['transparent'] === 'on' ? 'true' : 'false',
            'button' => $settings['button'] === 'on' ? 'true' : 'false',
            'lang' => $settings['language'],
            'tenantId' => $settings['tenant_id'],
            'locationId' => $settings['location_id']
        );

        $base_url = 'https://www.kiyoh.com/retrieve-widget.html';
        return $base_url . '?' . http_build_query($params);
    }

    /**
     * Shortcode handler
     */
    public function shortcode_handler($atts) {
        $options = $this->get_options();

        $atts = shortcode_atts(array(
            'tenant_id' => $options['tenant_id'],
            'location_id' => $options['location_id'],
            'width' => $options['width'],
            'height' => $options['height'],
            'color' => $options['color'],
            'language' => $options['language'],
            'border' => $options['border'],
            'transparent' => $options['transparent'],
            'button' => $options['button']
        ), $atts, 'kiyoh_widget');

        if (empty($atts['tenant_id']) || empty($atts['location_id'])) {
            return '<!-- Kiyoh Widget: Missing tenant_id or location_id -->';
        }

        $iframe_url = $this->build_iframe_url($atts);
        $frameborder = $atts['border'] === 'on' ? '1' : '0';
        $allow_transparency = $atts['transparent'] === 'on' ? 'true' : 'false';

        $output = '<div class="kiyoh-widget-container">';
        $output .= sprintf(
            '<iframe frameborder="%s" allowtransparency="%s" src="%s" width="%d" height="%d" title="%s"></iframe>',
            esc_attr($frameborder),
            esc_attr($allow_transparency),
            esc_url($iframe_url),
            absint($atts['width']),
            absint($atts['height']),
            esc_attr__('Kiyoh Reviews', 'kiyoh-widget')
        );
        $output .= '</div>';

        return $output;
    }

    /**
     * Render sticky widget
     */
    public function render_sticky_widget() {
        $options = $this->get_options();

        if ($options['sticky_enabled'] !== 'on') {
            return;
        }

        if (empty($options['tenant_id']) || empty($options['location_id'])) {
            return;
        }

        $iframe_url = $this->build_iframe_url($options);
        $frameborder = $options['border'] === 'on' ? '1' : '0';
        $allow_transparency = $options['transparent'] === 'on' ? 'true' : 'false';

        $classes = array('kiyoh-sticky-widget');
        $classes[] = 'kiyoh-sticky-' . $options['sticky_position'];
        $classes[] = 'kiyoh-sticky-v-' . $options['sticky_vertical'];

        if ($options['sticky_glass'] === 'on') {
            $classes[] = 'kiyoh-sticky-glass';
        }

        ?>
        <div id="kiyoh-sticky-widget" class="<?php echo esc_attr(implode(' ', $classes)); ?>">
            <div class="kiyoh-sticky-toggle">
                <span class="kiyoh-sticky-icon">★</span>
                <span class="kiyoh-sticky-label"><?php esc_html_e('Reviews', 'kiyoh-widget'); ?></span>
            </div>
            <div class="kiyoh-sticky-content">
                <button class="kiyoh-sticky-close" aria-label="<?php esc_attr_e('Close', 'kiyoh-widget'); ?>">&times;</button>
                <iframe
                    frameborder="<?php echo esc_attr($frameborder); ?>"
                    allowtransparency="<?php echo esc_attr($allow_transparency); ?>"
                    src="<?php echo esc_url($iframe_url); ?>"
                    width="<?php echo absint($options['width']); ?>"
                    height="<?php echo absint($options['height']); ?>"
                    title="<?php esc_attr_e('Kiyoh Reviews', 'kiyoh-widget'); ?>">
                </iframe>
            </div>
        </div>
        <?php
    }

    /**
     * Render admin page
     */
    public function render_admin_page() {
        $options = $this->get_options();
        ?>
        <div class="wrap kiyoh-admin-wrap">
            <h1>
                <span class="dashicons dashicons-star-filled" style="color: #ff9800;"></span>
                <?php esc_html_e('Kiyoh Widget Settings', 'kiyoh-widget'); ?>
            </h1>

            <form method="post" action="options.php">
                <?php settings_fields('kiyoh_widget_settings'); ?>

                <div class="kiyoh-admin-container">
                    <div class="kiyoh-admin-main">
                        <!-- API Settings -->
                        <div class="kiyoh-admin-section">
                            <h2><?php esc_html_e('Kiyoh Account Settings', 'kiyoh-widget'); ?></h2>
                            <p class="description"><?php esc_html_e('Enter your Kiyoh tenant and location IDs. You can find these in your Kiyoh dashboard.', 'kiyoh-widget'); ?></p>

                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="tenant_id"><?php esc_html_e('Tenant ID', 'kiyoh-widget'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="tenant_id" name="kiyoh_widget_options[tenant_id]" value="<?php echo esc_attr($options['tenant_id']); ?>" class="regular-text" placeholder="98">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="location_id"><?php esc_html_e('Location ID', 'kiyoh-widget'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="location_id" name="kiyoh_widget_options[location_id]" value="<?php echo esc_attr($options['location_id']); ?>" class="regular-text" placeholder="1077528">
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <!-- Widget Appearance -->
                        <div class="kiyoh-admin-section">
                            <h2><?php esc_html_e('Widget Appearance', 'kiyoh-widget'); ?></h2>

                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="width"><?php esc_html_e('Width (pixels)', 'kiyoh-widget'); ?></label>
                                    </th>
                                    <td>
                                        <input type="number" id="width" name="kiyoh_widget_options[width]" value="<?php echo absint($options['width']); ?>" min="200" max="800" class="small-text">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="height"><?php esc_html_e('Height (pixels)', 'kiyoh-widget'); ?></label>
                                    </th>
                                    <td>
                                        <input type="number" id="height" name="kiyoh_widget_options[height]" value="<?php echo absint($options['height']); ?>" min="100" max="600" class="small-text">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="color"><?php esc_html_e('Color', 'kiyoh-widget'); ?></label>
                                    </th>
                                    <td>
                                        <select id="color" name="kiyoh_widget_options[color]">
                                            <option value="white" <?php selected($options['color'], 'white'); ?>><?php esc_html_e('White', 'kiyoh-widget'); ?></option>
                                            <option value="black" <?php selected($options['color'], 'black'); ?>><?php esc_html_e('Black', 'kiyoh-widget'); ?></option>
                                            <option value="transparent" <?php selected($options['color'], 'transparent'); ?>><?php esc_html_e('Transparent', 'kiyoh-widget'); ?></option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="language"><?php esc_html_e('Language', 'kiyoh-widget'); ?></label>
                                    </th>
                                    <td>
                                        <select id="language" name="kiyoh_widget_options[language]">
                                            <option value="en" <?php selected($options['language'], 'en'); ?>><?php esc_html_e('English', 'kiyoh-widget'); ?></option>
                                            <option value="nl" <?php selected($options['language'], 'nl'); ?>><?php esc_html_e('Dutch', 'kiyoh-widget'); ?></option>
                                            <option value="de" <?php selected($options['language'], 'de'); ?>><?php esc_html_e('German', 'kiyoh-widget'); ?></option>
                                            <option value="fr" <?php selected($options['language'], 'fr'); ?>><?php esc_html_e('French', 'kiyoh-widget'); ?></option>
                                            <option value="es" <?php selected($options['language'], 'es'); ?>><?php esc_html_e('Spanish', 'kiyoh-widget'); ?></option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Border', 'kiyoh-widget'); ?></th>
                                    <td>
                                        <label>
                                            <input type="radio" name="kiyoh_widget_options[border]" value="on" <?php checked($options['border'], 'on'); ?>>
                                            <?php esc_html_e('On', 'kiyoh-widget'); ?>
                                        </label>
                                        <label>
                                            <input type="radio" name="kiyoh_widget_options[border]" value="off" <?php checked($options['border'], 'off'); ?>>
                                            <?php esc_html_e('Off', 'kiyoh-widget'); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Transparent', 'kiyoh-widget'); ?></th>
                                    <td>
                                        <label>
                                            <input type="radio" name="kiyoh_widget_options[transparent]" value="on" <?php checked($options['transparent'], 'on'); ?>>
                                            <?php esc_html_e('On', 'kiyoh-widget'); ?>
                                        </label>
                                        <label>
                                            <input type="radio" name="kiyoh_widget_options[transparent]" value="off" <?php checked($options['transparent'], 'off'); ?>>
                                            <?php esc_html_e('Off', 'kiyoh-widget'); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Review Button', 'kiyoh-widget'); ?></th>
                                    <td>
                                        <label>
                                            <input type="radio" name="kiyoh_widget_options[button]" value="on" <?php checked($options['button'], 'on'); ?>>
                                            <?php esc_html_e('On', 'kiyoh-widget'); ?>
                                        </label>
                                        <label>
                                            <input type="radio" name="kiyoh_widget_options[button]" value="off" <?php checked($options['button'], 'off'); ?>>
                                            <?php esc_html_e('Off', 'kiyoh-widget'); ?>
                                        </label>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <!-- Sticky Widget Settings -->
                        <div class="kiyoh-admin-section">
                            <h2><?php esc_html_e('Sticky Widget Settings', 'kiyoh-widget'); ?></h2>
                            <p class="description"><?php esc_html_e('Configure the floating sticky widget that appears on all pages.', 'kiyoh-widget'); ?></p>

                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php esc_html_e('Enable Sticky Widget', 'kiyoh-widget'); ?></th>
                                    <td>
                                        <label>
                                            <input type="radio" name="kiyoh_widget_options[sticky_enabled]" value="on" <?php checked($options['sticky_enabled'], 'on'); ?>>
                                            <?php esc_html_e('On', 'kiyoh-widget'); ?>
                                        </label>
                                        <label>
                                            <input type="radio" name="kiyoh_widget_options[sticky_enabled]" value="off" <?php checked($options['sticky_enabled'], 'off'); ?>>
                                            <?php esc_html_e('Off', 'kiyoh-widget'); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="sticky_position"><?php esc_html_e('Horizontal Position', 'kiyoh-widget'); ?></label>
                                    </th>
                                    <td>
                                        <select id="sticky_position" name="kiyoh_widget_options[sticky_position]">
                                            <option value="left" <?php selected($options['sticky_position'], 'left'); ?>><?php esc_html_e('Left', 'kiyoh-widget'); ?></option>
                                            <option value="right" <?php selected($options['sticky_position'], 'right'); ?>><?php esc_html_e('Right', 'kiyoh-widget'); ?></option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="sticky_vertical"><?php esc_html_e('Vertical Position', 'kiyoh-widget'); ?></label>
                                    </th>
                                    <td>
                                        <select id="sticky_vertical" name="kiyoh_widget_options[sticky_vertical]">
                                            <option value="top" <?php selected($options['sticky_vertical'], 'top'); ?>><?php esc_html_e('Top', 'kiyoh-widget'); ?></option>
                                            <option value="middle" <?php selected($options['sticky_vertical'], 'middle'); ?>><?php esc_html_e('Middle', 'kiyoh-widget'); ?></option>
                                            <option value="bottom" <?php selected($options['sticky_vertical'], 'bottom'); ?>><?php esc_html_e('Bottom', 'kiyoh-widget'); ?></option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Glass Effect', 'kiyoh-widget'); ?></th>
                                    <td>
                                        <label>
                                            <input type="radio" name="kiyoh_widget_options[sticky_glass]" value="on" <?php checked($options['sticky_glass'], 'on'); ?>>
                                            <?php esc_html_e('On', 'kiyoh-widget'); ?>
                                        </label>
                                        <label>
                                            <input type="radio" name="kiyoh_widget_options[sticky_glass]" value="off" <?php checked($options['sticky_glass'], 'off'); ?>>
                                            <?php esc_html_e('Off', 'kiyoh-widget'); ?>
                                        </label>
                                        <p class="description"><?php esc_html_e('Enable glassmorphism effect for a modern look.', 'kiyoh-widget'); ?></p>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <?php submit_button(__('Save Settings', 'kiyoh-widget')); ?>
                    </div>

                    <!-- Sidebar -->
                    <div class="kiyoh-admin-sidebar">
                        <div class="kiyoh-admin-box">
                            <h3><?php esc_html_e('Shortcode Usage', 'kiyoh-widget'); ?></h3>
                            <p><?php esc_html_e('Use this shortcode to display the widget anywhere:', 'kiyoh-widget'); ?></p>
                            <code>[kiyoh_widget]</code>

                            <h4><?php esc_html_e('With Custom Parameters:', 'kiyoh-widget'); ?></h4>
                            <code>[kiyoh_widget width="300" height="200"]</code>
                        </div>

                        <div class="kiyoh-admin-box">
                            <h3><?php esc_html_e('Preview', 'kiyoh-widget'); ?></h3>
                            <div id="kiyoh-preview-container">
                                <?php if (!empty($options['tenant_id']) && !empty($options['location_id'])): ?>
                                    <iframe
                                        frameborder="<?php echo $options['border'] === 'on' ? '1' : '0'; ?>"
                                        allowtransparency="<?php echo $options['transparent'] === 'on' ? 'true' : 'false'; ?>"
                                        src="<?php echo esc_url($this->build_iframe_url($options)); ?>"
                                        width="100%"
                                        height="<?php echo absint($options['height']); ?>"
                                        title="<?php esc_attr_e('Kiyoh Reviews Preview', 'kiyoh-widget'); ?>">
                                    </iframe>
                                <?php else: ?>
                                    <p class="kiyoh-preview-placeholder"><?php esc_html_e('Enter your Tenant ID and Location ID to see a preview.', 'kiyoh-widget'); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="kiyoh-admin-box">
                            <h3><?php esc_html_e('Need Help?', 'kiyoh-widget'); ?></h3>
                            <p><?php esc_html_e('Find your Tenant ID and Location ID in your Kiyoh dashboard under Widget settings.', 'kiyoh-widget'); ?></p>
                            <a href="https://www.kiyoh.com" target="_blank" class="button"><?php esc_html_e('Visit Kiyoh', 'kiyoh-widget'); ?></a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <?php
    }
}

// Initialize the plugin
function kiyoh_widget_init() {
    return Kiyoh_Widget::get_instance();
}
add_action('plugins_loaded', 'kiyoh_widget_init');

// Activation hook
register_activation_hook(__FILE__, 'kiyoh_widget_activate');
function kiyoh_widget_activate() {
    // Set default options on activation
    if (false === get_option('kiyoh_widget_options')) {
        add_option('kiyoh_widget_options', array(
            'tenant_id' => '',
            'location_id' => '',
            'width' => 400,
            'height' => 222,
            'color' => 'white',
            'language' => 'en',
            'border' => 'on',
            'ssl' => 'on',
            'transparent' => 'off',
            'button' => 'on',
            'display_mode' => 'standard',
            'sticky_position' => 'right',
            'sticky_vertical' => 'middle',
            'sticky_glass' => 'on',
            'sticky_enabled' => 'off'
        ));
    }
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'kiyoh_widget_deactivate');
function kiyoh_widget_deactivate() {
    // Clean up if needed
}
