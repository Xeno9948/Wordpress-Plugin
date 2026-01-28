<?php
/**
 * Plugin Name: Kiyoh Review Widget
 * Plugin URI: https://www.kiyoh.com
 * Description: Display Kiyoh review widgets on your WordPress site. Supports standard embedded widgets and sticky floating widgets with glass effect.
 * Version: 1.1.0
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
define('KIYOH_WIDGET_VERSION', '1.1.0');
define('KIYOH_WIDGET_PATH', plugin_dir_path(__FILE__));
define('KIYOH_WIDGET_URL', plugin_dir_url(__FILE__));

/**
 * Main Kiyoh Widget Class
 */
class Kiyoh_Widget {

    private static $instance = null;

    private $defaults = array(
        'tenant_id' => '',
        'location_id' => '',
        'width' => 400,
        'height' => 222,
        'color' => 'white',
        'language' => 'en',
        'border' => 'off',
        'ssl' => 'on',
        'transparent' => 'off',
        'button' => 'on',
        // Standard widget display settings
        'standard_enabled' => 'off',
        'standard_location' => 'shortcode',
        'standard_pages' => 'all',
        'standard_alignment' => 'center',
        // Sticky widget settings
        'sticky_enabled' => 'off',
        'sticky_position' => 'right',
        'sticky_vertical' => 'middle',
        'sticky_style' => 'glass',
        'sticky_tab_color' => '#ff9800',
        'sticky_pages' => 'all'
    );

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));

        add_action('wp_enqueue_scripts', array($this, 'frontend_enqueue_scripts'));
        add_action('wp_head', array($this, 'render_header_widget'));
        add_action('wp_footer', array($this, 'render_footer_widget'));
        add_action('wp_footer', array($this, 'render_sticky_widget'), 99);

        add_shortcode('kiyoh_widget', array($this, 'shortcode_handler'));

        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_settings_link'));

        // Register widget for sidebar
        add_action('widgets_init', array($this, 'register_sidebar_widget'));
    }

    public function add_settings_link($links) {
        $settings_link = '<a href="admin.php?page=kiyoh-widget">' . __('Settings', 'kiyoh-widget') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

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

    public function register_settings() {
        register_setting('kiyoh_widget_settings', 'kiyoh_widget_options', array($this, 'sanitize_options'));
    }

    public function sanitize_options($input) {
        $sanitized = array();

        $sanitized['tenant_id'] = sanitize_text_field($input['tenant_id'] ?? '');
        $sanitized['location_id'] = sanitize_text_field($input['location_id'] ?? '');
        $sanitized['width'] = absint($input['width'] ?? 400);
        $sanitized['height'] = absint($input['height'] ?? 222);
        $sanitized['color'] = sanitize_text_field($input['color'] ?? 'white');
        $sanitized['language'] = sanitize_text_field($input['language'] ?? 'en');
        $sanitized['border'] = in_array($input['border'] ?? 'off', array('on', 'off')) ? $input['border'] : 'off';
        $sanitized['ssl'] = in_array($input['ssl'] ?? 'on', array('on', 'off')) ? $input['ssl'] : 'on';
        $sanitized['transparent'] = in_array($input['transparent'] ?? 'off', array('on', 'off')) ? $input['transparent'] : 'off';
        $sanitized['button'] = in_array($input['button'] ?? 'on', array('on', 'off')) ? $input['button'] : 'on';

        // Standard widget
        $sanitized['standard_enabled'] = in_array($input['standard_enabled'] ?? 'off', array('on', 'off')) ? $input['standard_enabled'] : 'off';
        $sanitized['standard_location'] = in_array($input['standard_location'] ?? 'shortcode', array('shortcode', 'header', 'footer', 'header_footer')) ? $input['standard_location'] : 'shortcode';
        $sanitized['standard_pages'] = in_array($input['standard_pages'] ?? 'all', array('all', 'home', 'posts', 'pages')) ? $input['standard_pages'] : 'all';
        $sanitized['standard_alignment'] = in_array($input['standard_alignment'] ?? 'center', array('left', 'center', 'right')) ? $input['standard_alignment'] : 'center';

        // Sticky widget
        $sanitized['sticky_enabled'] = in_array($input['sticky_enabled'] ?? 'off', array('on', 'off')) ? $input['sticky_enabled'] : 'off';
        $sanitized['sticky_position'] = in_array($input['sticky_position'] ?? 'right', array('left', 'right')) ? $input['sticky_position'] : 'right';
        $sanitized['sticky_vertical'] = in_array($input['sticky_vertical'] ?? 'middle', array('top', 'middle', 'bottom')) ? $input['sticky_vertical'] : 'middle';
        $sanitized['sticky_style'] = in_array($input['sticky_style'] ?? 'glass', array('glass', 'solid', 'shadow')) ? $input['sticky_style'] : 'glass';
        $sanitized['sticky_tab_color'] = sanitize_hex_color($input['sticky_tab_color'] ?? '#ff9800') ?: '#ff9800';
        $sanitized['sticky_pages'] = in_array($input['sticky_pages'] ?? 'all', array('all', 'home', 'posts', 'pages')) ? $input['sticky_pages'] : 'all';

        return $sanitized;
    }

    public function get_options() {
        $options = get_option('kiyoh_widget_options', array());
        return wp_parse_args($options, $this->defaults);
    }

    private function should_display_on_page($page_setting) {
        if ($page_setting === 'all') {
            return true;
        }
        if ($page_setting === 'home' && (is_front_page() || is_home())) {
            return true;
        }
        if ($page_setting === 'posts' && is_single()) {
            return true;
        }
        if ($page_setting === 'pages' && is_page()) {
            return true;
        }
        return false;
    }

    public function admin_enqueue_scripts($hook) {
        if ('toplevel_page_kiyoh-widget' !== $hook) {
            return;
        }

        wp_enqueue_style('wp-color-picker');
        wp_enqueue_style('kiyoh-widget-admin', KIYOH_WIDGET_URL . 'assets/css/admin.css', array(), KIYOH_WIDGET_VERSION);
        wp_enqueue_script('kiyoh-widget-admin', KIYOH_WIDGET_URL . 'assets/js/admin.js', array('jquery', 'wp-color-picker'), KIYOH_WIDGET_VERSION, true);
    }

    public function frontend_enqueue_scripts() {
        $options = $this->get_options();

        wp_enqueue_style('kiyoh-widget-frontend', KIYOH_WIDGET_URL . 'assets/css/frontend.css', array(), KIYOH_WIDGET_VERSION);

        if ($options['sticky_enabled'] === 'on') {
            wp_enqueue_script('kiyoh-widget-frontend', KIYOH_WIDGET_URL . 'assets/js/frontend.js', array(), KIYOH_WIDGET_VERSION, true);

            // Pass options to JS
            wp_localize_script('kiyoh-widget-frontend', 'kiyohWidgetOptions', array(
                'position' => $options['sticky_position']
            ));
        }

        // Add custom CSS for tab color
        if ($options['sticky_enabled'] === 'on') {
            $custom_css = "
                .kiyoh-sticky-tab {
                    background: linear-gradient(135deg, {$options['sticky_tab_color']} 0%, " . $this->adjust_brightness($options['sticky_tab_color'], -20) . " 100%);
                }
                .kiyoh-sticky-tab:hover {
                    background: linear-gradient(135deg, " . $this->adjust_brightness($options['sticky_tab_color'], 10) . " 0%, {$options['sticky_tab_color']} 100%);
                }
            ";
            wp_add_inline_style('kiyoh-widget-frontend', $custom_css);
        }
    }

    private function adjust_brightness($hex, $percent) {
        $hex = ltrim($hex, '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        $r = max(0, min(255, $r + ($r * $percent / 100)));
        $g = max(0, min(255, $g + ($g * $percent / 100)));
        $b = max(0, min(255, $b + ($b * $percent / 100)));

        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

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

        return 'https://www.kiyoh.com/retrieve-widget.html?' . http_build_query($params);
    }

    public function get_widget_html($options = null) {
        if ($options === null) {
            $options = $this->get_options();
        }

        if (empty($options['tenant_id']) || empty($options['location_id'])) {
            return '';
        }

        $iframe_url = $this->build_iframe_url($options);
        $frameborder = $options['border'] === 'on' ? '1' : '0';
        $allow_transparency = $options['transparent'] === 'on' ? 'true' : 'false';

        return sprintf(
            '<iframe frameborder="%s" allowtransparency="%s" src="%s" width="%d" height="%d" title="%s" style="border:none; border-radius: 12px;"></iframe>',
            esc_attr($frameborder),
            esc_attr($allow_transparency),
            esc_url($iframe_url),
            absint($options['width']),
            absint($options['height']),
            esc_attr__('Kiyoh Reviews', 'kiyoh-widget')
        );
    }

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
            'button' => $options['button'],
            'align' => $options['standard_alignment']
        ), $atts, 'kiyoh_widget');

        if (empty($atts['tenant_id']) || empty($atts['location_id'])) {
            return '<!-- Kiyoh Widget: Missing tenant_id or location_id -->';
        }

        $align_class = 'kiyoh-align-' . esc_attr($atts['align']);
        $widget_html = $this->get_widget_html($atts);

        return '<div class="kiyoh-widget-container ' . $align_class . '">' . $widget_html . '</div>';
    }

    public function render_header_widget() {
        $options = $this->get_options();

        if ($options['standard_enabled'] !== 'on') {
            return;
        }

        if (!in_array($options['standard_location'], array('header', 'header_footer'))) {
            return;
        }

        if (!$this->should_display_on_page($options['standard_pages'])) {
            return;
        }

        $align_class = 'kiyoh-align-' . esc_attr($options['standard_alignment']);
        $widget_html = $this->get_widget_html($options);

        if ($widget_html) {
            echo '<div class="kiyoh-widget-container kiyoh-widget-header ' . $align_class . '">' . $widget_html . '</div>';
        }
    }

    public function render_footer_widget() {
        $options = $this->get_options();

        if ($options['standard_enabled'] !== 'on') {
            return;
        }

        if (!in_array($options['standard_location'], array('footer', 'header_footer'))) {
            return;
        }

        if (!$this->should_display_on_page($options['standard_pages'])) {
            return;
        }

        $align_class = 'kiyoh-align-' . esc_attr($options['standard_alignment']);
        $widget_html = $this->get_widget_html($options);

        if ($widget_html) {
            echo '<div class="kiyoh-widget-container kiyoh-widget-footer ' . $align_class . '">' . $widget_html . '</div>';
        }
    }

    public function render_sticky_widget() {
        $options = $this->get_options();

        if ($options['sticky_enabled'] !== 'on') {
            return;
        }

        if (empty($options['tenant_id']) || empty($options['location_id'])) {
            return;
        }

        if (!$this->should_display_on_page($options['sticky_pages'])) {
            return;
        }

        $widget_html = $this->get_widget_html($options);

        $classes = array('kiyoh-sticky-widget');
        $classes[] = 'kiyoh-sticky-' . $options['sticky_position'];
        $classes[] = 'kiyoh-sticky-v-' . $options['sticky_vertical'];
        $classes[] = 'kiyoh-sticky-style-' . $options['sticky_style'];

        ?>
        <div id="kiyoh-sticky-widget" class="<?php echo esc_attr(implode(' ', $classes)); ?>">
            <!-- Collapsed Tab -->
            <div class="kiyoh-sticky-tab" role="button" tabindex="0" aria-label="<?php esc_attr_e('Open reviews', 'kiyoh-widget'); ?>">
                <div class="kiyoh-sticky-tab-inner">
                    <span class="kiyoh-sticky-tab-icon">&#9733;</span>
                    <span class="kiyoh-sticky-tab-text"><?php esc_html_e('Reviews', 'kiyoh-widget'); ?></span>
                </div>
            </div>

            <!-- Expanded Panel -->
            <div class="kiyoh-sticky-panel" aria-hidden="true">
                <button type="button" class="kiyoh-sticky-close" aria-label="<?php esc_attr_e('Close', 'kiyoh-widget'); ?>">&times;</button>
                <div class="kiyoh-sticky-panel-content">
                    <?php echo $widget_html; ?>
                </div>
            </div>
        </div>
        <?php
    }

    public function register_sidebar_widget() {
        register_widget('Kiyoh_Sidebar_Widget');
    }

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
                        <!-- Account Settings -->
                        <div class="kiyoh-admin-section">
                            <h2><?php esc_html_e('Kiyoh Account Settings', 'kiyoh-widget'); ?></h2>
                            <p class="description"><?php esc_html_e('Enter your Kiyoh tenant and location IDs from your Kiyoh dashboard.', 'kiyoh-widget'); ?></p>

                            <table class="form-table">
                                <tr>
                                    <th scope="row"><label for="tenant_id"><?php esc_html_e('Tenant ID', 'kiyoh-widget'); ?></label></th>
                                    <td><input type="text" id="tenant_id" name="kiyoh_widget_options[tenant_id]" value="<?php echo esc_attr($options['tenant_id']); ?>" class="regular-text" placeholder="98"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="location_id"><?php esc_html_e('Location ID', 'kiyoh-widget'); ?></label></th>
                                    <td><input type="text" id="location_id" name="kiyoh_widget_options[location_id]" value="<?php echo esc_attr($options['location_id']); ?>" class="regular-text" placeholder="1077528"></td>
                                </tr>
                            </table>
                        </div>

                        <!-- Widget Appearance -->
                        <div class="kiyoh-admin-section">
                            <h2><?php esc_html_e('Widget Appearance', 'kiyoh-widget'); ?></h2>

                            <table class="form-table">
                                <tr>
                                    <th scope="row"><label for="width"><?php esc_html_e('Width (px)', 'kiyoh-widget'); ?></label></th>
                                    <td><input type="number" id="width" name="kiyoh_widget_options[width]" value="<?php echo absint($options['width']); ?>" min="200" max="800" class="small-text"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="height"><?php esc_html_e('Height (px)', 'kiyoh-widget'); ?></label></th>
                                    <td><input type="number" id="height" name="kiyoh_widget_options[height]" value="<?php echo absint($options['height']); ?>" min="100" max="600" class="small-text"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="color"><?php esc_html_e('Background Color', 'kiyoh-widget'); ?></label></th>
                                    <td>
                                        <select id="color" name="kiyoh_widget_options[color]">
                                            <option value="white" <?php selected($options['color'], 'white'); ?>><?php esc_html_e('White', 'kiyoh-widget'); ?></option>
                                            <option value="black" <?php selected($options['color'], 'black'); ?>><?php esc_html_e('Black', 'kiyoh-widget'); ?></option>
                                            <option value="transparent" <?php selected($options['color'], 'transparent'); ?>><?php esc_html_e('Transparent', 'kiyoh-widget'); ?></option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="language"><?php esc_html_e('Language', 'kiyoh-widget'); ?></label></th>
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
                                    <th scope="row"><?php esc_html_e('Show Review Button', 'kiyoh-widget'); ?></th>
                                    <td>
                                        <label><input type="radio" name="kiyoh_widget_options[button]" value="on" <?php checked($options['button'], 'on'); ?>> <?php esc_html_e('Yes', 'kiyoh-widget'); ?></label>
                                        <label><input type="radio" name="kiyoh_widget_options[button]" value="off" <?php checked($options['button'], 'off'); ?>> <?php esc_html_e('No', 'kiyoh-widget'); ?></label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Transparent Background', 'kiyoh-widget'); ?></th>
                                    <td>
                                        <label><input type="radio" name="kiyoh_widget_options[transparent]" value="on" <?php checked($options['transparent'], 'on'); ?>> <?php esc_html_e('Yes', 'kiyoh-widget'); ?></label>
                                        <label><input type="radio" name="kiyoh_widget_options[transparent]" value="off" <?php checked($options['transparent'], 'off'); ?>> <?php esc_html_e('No', 'kiyoh-widget'); ?></label>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <!-- Standard Widget Display -->
                        <div class="kiyoh-admin-section">
                            <h2><?php esc_html_e('Standard Widget Display', 'kiyoh-widget'); ?></h2>
                            <p class="description"><?php esc_html_e('Configure where the standard embedded widget appears on your site.', 'kiyoh-widget'); ?></p>

                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php esc_html_e('Enable Auto Display', 'kiyoh-widget'); ?></th>
                                    <td>
                                        <label><input type="radio" name="kiyoh_widget_options[standard_enabled]" value="on" <?php checked($options['standard_enabled'], 'on'); ?>> <?php esc_html_e('Yes', 'kiyoh-widget'); ?></label>
                                        <label><input type="radio" name="kiyoh_widget_options[standard_enabled]" value="off" <?php checked($options['standard_enabled'], 'off'); ?>> <?php esc_html_e('No', 'kiyoh-widget'); ?></label>
                                        <p class="description"><?php esc_html_e('When disabled, use the shortcode [kiyoh_widget] to display manually.', 'kiyoh-widget'); ?></p>
                                    </td>
                                </tr>
                                <tr class="kiyoh-standard-options">
                                    <th scope="row"><label for="standard_location"><?php esc_html_e('Display Location', 'kiyoh-widget'); ?></label></th>
                                    <td>
                                        <select id="standard_location" name="kiyoh_widget_options[standard_location]">
                                            <option value="shortcode" <?php selected($options['standard_location'], 'shortcode'); ?>><?php esc_html_e('Shortcode Only', 'kiyoh-widget'); ?></option>
                                            <option value="header" <?php selected($options['standard_location'], 'header'); ?>><?php esc_html_e('Header (top of page)', 'kiyoh-widget'); ?></option>
                                            <option value="footer" <?php selected($options['standard_location'], 'footer'); ?>><?php esc_html_e('Footer (bottom of page)', 'kiyoh-widget'); ?></option>
                                            <option value="header_footer" <?php selected($options['standard_location'], 'header_footer'); ?>><?php esc_html_e('Both Header & Footer', 'kiyoh-widget'); ?></option>
                                        </select>
                                    </td>
                                </tr>
                                <tr class="kiyoh-standard-options">
                                    <th scope="row"><label for="standard_pages"><?php esc_html_e('Show On', 'kiyoh-widget'); ?></label></th>
                                    <td>
                                        <select id="standard_pages" name="kiyoh_widget_options[standard_pages]">
                                            <option value="all" <?php selected($options['standard_pages'], 'all'); ?>><?php esc_html_e('All Pages', 'kiyoh-widget'); ?></option>
                                            <option value="home" <?php selected($options['standard_pages'], 'home'); ?>><?php esc_html_e('Homepage Only', 'kiyoh-widget'); ?></option>
                                            <option value="posts" <?php selected($options['standard_pages'], 'posts'); ?>><?php esc_html_e('Blog Posts Only', 'kiyoh-widget'); ?></option>
                                            <option value="pages" <?php selected($options['standard_pages'], 'pages'); ?>><?php esc_html_e('Pages Only', 'kiyoh-widget'); ?></option>
                                        </select>
                                    </td>
                                </tr>
                                <tr class="kiyoh-standard-options">
                                    <th scope="row"><label for="standard_alignment"><?php esc_html_e('Alignment', 'kiyoh-widget'); ?></label></th>
                                    <td>
                                        <select id="standard_alignment" name="kiyoh_widget_options[standard_alignment]">
                                            <option value="left" <?php selected($options['standard_alignment'], 'left'); ?>><?php esc_html_e('Left', 'kiyoh-widget'); ?></option>
                                            <option value="center" <?php selected($options['standard_alignment'], 'center'); ?>><?php esc_html_e('Center', 'kiyoh-widget'); ?></option>
                                            <option value="right" <?php selected($options['standard_alignment'], 'right'); ?>><?php esc_html_e('Right', 'kiyoh-widget'); ?></option>
                                        </select>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <!-- Sticky Widget Settings -->
                        <div class="kiyoh-admin-section">
                            <h2><?php esc_html_e('Sticky Widget Settings', 'kiyoh-widget'); ?></h2>
                            <p class="description"><?php esc_html_e('A floating tab that expands to show the full review widget when clicked.', 'kiyoh-widget'); ?></p>

                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php esc_html_e('Enable Sticky Widget', 'kiyoh-widget'); ?></th>
                                    <td>
                                        <label><input type="radio" name="kiyoh_widget_options[sticky_enabled]" value="on" <?php checked($options['sticky_enabled'], 'on'); ?>> <?php esc_html_e('Yes', 'kiyoh-widget'); ?></label>
                                        <label><input type="radio" name="kiyoh_widget_options[sticky_enabled]" value="off" <?php checked($options['sticky_enabled'], 'off'); ?>> <?php esc_html_e('No', 'kiyoh-widget'); ?></label>
                                    </td>
                                </tr>
                                <tr class="kiyoh-sticky-options">
                                    <th scope="row"><label for="sticky_position"><?php esc_html_e('Position', 'kiyoh-widget'); ?></label></th>
                                    <td>
                                        <select id="sticky_position" name="kiyoh_widget_options[sticky_position]">
                                            <option value="left" <?php selected($options['sticky_position'], 'left'); ?>><?php esc_html_e('Left Side', 'kiyoh-widget'); ?></option>
                                            <option value="right" <?php selected($options['sticky_position'], 'right'); ?>><?php esc_html_e('Right Side', 'kiyoh-widget'); ?></option>
                                        </select>
                                    </td>
                                </tr>
                                <tr class="kiyoh-sticky-options">
                                    <th scope="row"><label for="sticky_vertical"><?php esc_html_e('Vertical Position', 'kiyoh-widget'); ?></label></th>
                                    <td>
                                        <select id="sticky_vertical" name="kiyoh_widget_options[sticky_vertical]">
                                            <option value="top" <?php selected($options['sticky_vertical'], 'top'); ?>><?php esc_html_e('Top', 'kiyoh-widget'); ?></option>
                                            <option value="middle" <?php selected($options['sticky_vertical'], 'middle'); ?>><?php esc_html_e('Middle', 'kiyoh-widget'); ?></option>
                                            <option value="bottom" <?php selected($options['sticky_vertical'], 'bottom'); ?>><?php esc_html_e('Bottom', 'kiyoh-widget'); ?></option>
                                        </select>
                                    </td>
                                </tr>
                                <tr class="kiyoh-sticky-options">
                                    <th scope="row"><label for="sticky_style"><?php esc_html_e('Panel Style', 'kiyoh-widget'); ?></label></th>
                                    <td>
                                        <select id="sticky_style" name="kiyoh_widget_options[sticky_style]">
                                            <option value="glass" <?php selected($options['sticky_style'], 'glass'); ?>><?php esc_html_e('Glass (Frosted)', 'kiyoh-widget'); ?></option>
                                            <option value="solid" <?php selected($options['sticky_style'], 'solid'); ?>><?php esc_html_e('Solid', 'kiyoh-widget'); ?></option>
                                            <option value="shadow" <?php selected($options['sticky_style'], 'shadow'); ?>><?php esc_html_e('Shadow', 'kiyoh-widget'); ?></option>
                                        </select>
                                    </td>
                                </tr>
                                <tr class="kiyoh-sticky-options">
                                    <th scope="row"><label for="sticky_tab_color"><?php esc_html_e('Tab Color', 'kiyoh-widget'); ?></label></th>
                                    <td>
                                        <input type="text" id="sticky_tab_color" name="kiyoh_widget_options[sticky_tab_color]" value="<?php echo esc_attr($options['sticky_tab_color']); ?>" class="kiyoh-color-picker">
                                    </td>
                                </tr>
                                <tr class="kiyoh-sticky-options">
                                    <th scope="row"><label for="sticky_pages"><?php esc_html_e('Show On', 'kiyoh-widget'); ?></label></th>
                                    <td>
                                        <select id="sticky_pages" name="kiyoh_widget_options[sticky_pages]">
                                            <option value="all" <?php selected($options['sticky_pages'], 'all'); ?>><?php esc_html_e('All Pages', 'kiyoh-widget'); ?></option>
                                            <option value="home" <?php selected($options['sticky_pages'], 'home'); ?>><?php esc_html_e('Homepage Only', 'kiyoh-widget'); ?></option>
                                            <option value="posts" <?php selected($options['sticky_pages'], 'posts'); ?>><?php esc_html_e('Blog Posts Only', 'kiyoh-widget'); ?></option>
                                            <option value="pages" <?php selected($options['sticky_pages'], 'pages'); ?>><?php esc_html_e('Pages Only', 'kiyoh-widget'); ?></option>
                                        </select>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <?php submit_button(__('Save Settings', 'kiyoh-widget')); ?>
                    </div>

                    <!-- Sidebar -->
                    <div class="kiyoh-admin-sidebar">
                        <div class="kiyoh-admin-box">
                            <h3><?php esc_html_e('Shortcode', 'kiyoh-widget'); ?></h3>
                            <p><?php esc_html_e('Use this shortcode anywhere:', 'kiyoh-widget'); ?></p>
                            <code>[kiyoh_widget]</code>

                            <h4><?php esc_html_e('With Options:', 'kiyoh-widget'); ?></h4>
                            <code>[kiyoh_widget width="300" height="200" align="left"]</code>
                        </div>

                        <div class="kiyoh-admin-box">
                            <h3><?php esc_html_e('Widget', 'kiyoh-widget'); ?></h3>
                            <p><?php esc_html_e('You can also add the Kiyoh widget to any sidebar via Appearance > Widgets.', 'kiyoh-widget'); ?></p>
                        </div>

                        <div class="kiyoh-admin-box">
                            <h3><?php esc_html_e('Preview', 'kiyoh-widget'); ?></h3>
                            <div id="kiyoh-preview-container">
                                <?php if (!empty($options['tenant_id']) && !empty($options['location_id'])): ?>
                                    <?php echo $this->get_widget_html($options); ?>
                                <?php else: ?>
                                    <p class="kiyoh-preview-placeholder"><?php esc_html_e('Enter your Tenant ID and Location ID to see a preview.', 'kiyoh-widget'); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <?php
    }
}

/**
 * Sidebar Widget Class
 */
class Kiyoh_Sidebar_Widget extends WP_Widget {

    public function __construct() {
        parent::__construct(
            'kiyoh_widget',
            __('Kiyoh Reviews', 'kiyoh-widget'),
            array('description' => __('Display your Kiyoh review widget', 'kiyoh-widget'))
        );
    }

    public function widget($args, $instance) {
        $kiyoh = Kiyoh_Widget::get_instance();
        $options = $kiyoh->get_options();

        if (empty($options['tenant_id']) || empty($options['location_id'])) {
            return;
        }

        echo $args['before_widget'];

        if (!empty($instance['title'])) {
            echo $args['before_title'] . apply_filters('widget_title', $instance['title']) . $args['after_title'];
        }

        echo '<div class="kiyoh-widget-container kiyoh-sidebar-widget">';
        echo $kiyoh->get_widget_html($options);
        echo '</div>';

        echo $args['after_widget'];
    }

    public function form($instance) {
        $title = !empty($instance['title']) ? $instance['title'] : '';
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>"><?php esc_html_e('Title:', 'kiyoh-widget'); ?></label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>" name="<?php echo esc_attr($this->get_field_name('title')); ?>" type="text" value="<?php echo esc_attr($title); ?>">
        </p>
        <p class="description"><?php esc_html_e('Configure widget settings in Kiyoh Widget menu.', 'kiyoh-widget'); ?></p>
        <?php
    }

    public function update($new_instance, $old_instance) {
        $instance = array();
        $instance['title'] = (!empty($new_instance['title'])) ? sanitize_text_field($new_instance['title']) : '';
        return $instance;
    }
}

// Initialize
function kiyoh_widget_init() {
    return Kiyoh_Widget::get_instance();
}
add_action('plugins_loaded', 'kiyoh_widget_init');

// Activation
register_activation_hook(__FILE__, 'kiyoh_widget_activate');
function kiyoh_widget_activate() {
    if (false === get_option('kiyoh_widget_options')) {
        add_option('kiyoh_widget_options', array());
    }
}

// Deactivation
register_deactivation_hook(__FILE__, 'kiyoh_widget_deactivate');
function kiyoh_widget_deactivate() {
    // Optional: Clean up
}
