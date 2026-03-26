<?php
/**
 * Plugin Name: Demo Ribbon
 * Description: Adds a configurable demo ribbon that can render below the header or as a fixed banner.
 * Version: 0.1.0
 * Author: Magalie Chetrit
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Demo_Ribbon {
    private const OPTION_NAME = 'demo_ribbon_settings';

    public static function init() {
	        add_action('plugins_loaded', array(__CLASS__, 'load_textdomain'));
        add_action('admin_init', array(__CLASS__, 'register_settings'));
        add_action('admin_menu', array(__CLASS__, 'register_settings_page'));
        add_action('wp', array(__CLASS__, 'register_frontend_hooks'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_assets'));
    }

	    public static function load_textdomain() {
	        load_plugin_textdomain('chetrit-demo-ribbon', false, dirname(plugin_basename(__FILE__)) . '/languages');
	    }

    public static function register_settings() {
        register_setting(
            'chetrit_demo_ribbon',
            self::OPTION_NAME,
            array(
                'type' => 'array',
                'sanitize_callback' => array(__CLASS__, 'sanitize_settings'),
                'default' => self::get_default_settings(),
            )
        );
    }

    public static function register_settings_page() {
        add_options_page(
            __('Demo Ribbon', 'chetrit-demo-ribbon'),
            __('Demo Ribbon', 'chetrit-demo-ribbon'),
            'manage_options',
            'chetrit-demo-ribbon',
            array(__CLASS__, 'render_settings_page')
        );
    }

    public static function sanitize_settings($input) {
        $defaults = self::get_default_settings();
	        $current_settings = self::get_settings();

	        if (!current_user_can('manage_options')) {
	            return $current_settings;
	        }

        $placement = isset($input['placement']) ? sanitize_key($input['placement']) : $defaults['placement'];
        $allowed_placements = array_keys(self::get_placement_options());

        if (!in_array($placement, $allowed_placements, true)) {
            $placement = $defaults['placement'];
        }

            $message = isset($input['message']) ? wp_kses($input['message'], self::get_allowed_message_html()) : $defaults['message'];

        return array(
            'enabled' => !empty($input['enabled']),
            'placement' => $placement,
            'badge_text' => isset($input['badge_text']) ? sanitize_text_field($input['badge_text']) : $defaults['badge_text'],
            'message' => !empty($message) ? $message : $defaults['message'],
            'dismissible' => !empty($input['dismissible']),
        );
    }

    public static function register_frontend_hooks() {
        $settings = self::get_settings();

        if (is_admin() || empty($settings['enabled'])) {
            return;
        }

        if ($settings['placement'] === 'fixed-bottom') {
            add_action('wp_footer', array(__CLASS__, 'render_ribbon'));
            return;
        }

        if ($settings['placement'] === 'fixed-top') {
            add_action('wp_body_open', array(__CLASS__, 'render_ribbon'));
            return;
        }

        add_action('farbest_after_header', array(__CLASS__, 'render_ribbon'));
    }

    public static function enqueue_assets() {
        $settings = self::get_settings();

        if (is_admin() || empty($settings['enabled'])) {
            return;
        }

            wp_register_style('chetrit-demo-ribbon', false, array(), '0.1.0');
        wp_enqueue_style('chetrit-demo-ribbon');
        wp_add_inline_style('chetrit-demo-ribbon', self::get_inline_styles());

        if (!empty($settings['dismissible'])) {
                wp_register_script('chetrit-demo-ribbon', false, array(), '0.1.0', true);
            wp_enqueue_script('chetrit-demo-ribbon');
            wp_add_inline_script('chetrit-demo-ribbon', self::get_inline_script());
        }
    }

    public static function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $settings = self::get_settings();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Demo Ribbon', 'chetrit-demo-ribbon'); ?></h1>
            <p><?php esc_html_e('This plugin reuses the existing theme ribbon styling and adds placement controls.', 'chetrit-demo-ribbon'); ?></p>

            <form action="options.php" method="post">
                <?php settings_fields('chetrit_demo_ribbon'); ?>

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><?php esc_html_e('Enable Ribbon', 'chetrit-demo-ribbon'); ?></th>
                            <td>
                                <label>
                                    <input
                                        type="checkbox"
                                        name="<?php echo esc_attr(self::OPTION_NAME); ?>[enabled]"
                                        value="1"
                                        <?php checked(!empty($settings['enabled'])); ?>
                                    />
                                    <?php esc_html_e('Show the demo ribbon on the front end.', 'chetrit-demo-ribbon'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Placement', 'chetrit-demo-ribbon'); ?></th>
                            <td>
                                <select name="<?php echo esc_attr(self::OPTION_NAME); ?>[placement]">
                                    <?php foreach (self::get_placement_options() as $value => $label) : ?>
                                        <option value="<?php echo esc_attr($value); ?>" <?php selected($settings['placement'], $value); ?>>
                                            <?php echo esc_html($label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Badge Text', 'chetrit-demo-ribbon'); ?></th>
                            <td>
                                <input
                                    type="text"
                                    class="regular-text"
                                    name="<?php echo esc_attr(self::OPTION_NAME); ?>[badge_text]"
                                    value="<?php echo esc_attr($settings['badge_text']); ?>"
                                />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Message', 'chetrit-demo-ribbon'); ?></th>
                            <td>
                                <textarea
                                    name="<?php echo esc_attr(self::OPTION_NAME); ?>[message]"
                                    rows="4"
                                    class="large-text"
                                ><?php echo esc_textarea($settings['message']); ?></textarea>
                                <p class="description"><?php esc_html_e('Basic inline HTML such as strong tags is allowed.', 'chetrit-demo-ribbon'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Dismiss Button', 'chetrit-demo-ribbon'); ?></th>
                            <td>
                                <label>
                                    <input
                                        type="checkbox"
                                        name="<?php echo esc_attr(self::OPTION_NAME); ?>[dismissible]"
                                        value="1"
                                        <?php checked(!empty($settings['dismissible'])); ?>
                                    />
                                    <?php esc_html_e('Allow visitors to dismiss the ribbon for the current browser.', 'chetrit-demo-ribbon'); ?>
                                </label>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public static function render_ribbon() {
        $settings = self::get_settings();

        if (empty($settings['enabled'])) {
            return;
        }

        $placement_class = 'demo-ribbon--' . $settings['placement'];
        ?>
        <div id="demo-ribbon" class="<?php echo esc_attr($placement_class); ?>" role="banner" aria-label="<?php esc_attr_e('Demo notice', 'chetrit-demo-ribbon'); ?>">
            <div class="demo-ribbon-inner">
                <span class="demo-ribbon-badge"><?php echo esc_html($settings['badge_text']); ?></span>
                <span class="demo-ribbon-message"><?php echo wp_kses($settings['message'], self::get_allowed_message_html()); ?></span>

                <?php if (!empty($settings['dismissible'])) : ?>
                    <button class="demo-ribbon-close" type="button" data-demo-ribbon-close="1" aria-label="<?php esc_attr_e('Dismiss notice', 'chetrit-demo-ribbon'); ?>">&times;</button>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    private static function get_settings() {
        $settings = get_option(self::OPTION_NAME, array());
        return wp_parse_args(is_array($settings) ? $settings : array(), self::get_default_settings());
    }

    private static function get_default_settings() {
        return array(
            'enabled' => true,
            'placement' => 'after-header',
            'badge_text' => 'DEMO',
            'message' => __('This is a <strong>client preview</strong> — features, data, and design are subject to change and do not represent the final product.', 'chetrit-demo-ribbon'),
            'dismissible' => true,
        );
    }

    private static function get_placement_options() {
        return array(
            'after-header' => __('Below Header', 'chetrit-demo-ribbon'),
            'fixed-top' => __('Fixed Top', 'chetrit-demo-ribbon'),
            'fixed-bottom' => __('Fixed Bottom', 'chetrit-demo-ribbon'),
        );
    }

        private static function get_allowed_message_html() {
            return array(
                'strong' => array(),
                'em' => array(),
                'br' => array(),
                'span' => array(),
            );
        }

    private static function get_inline_styles() {
        return <<<'CSS'
#demo-ribbon.demo-ribbon--after-header {
    position: sticky;
    top: 0;
}

#demo-ribbon.demo-ribbon--fixed-top,
#demo-ribbon.demo-ribbon--fixed-bottom {
    position: fixed;
    left: 0;
    right: 0;
    width: 100%;
}

#demo-ribbon.demo-ribbon--fixed-top {
    top: 0;
}

body.admin-bar #demo-ribbon.demo-ribbon--fixed-top {
    top: 32px;
}

@media (max-width: 782px) {
    body.admin-bar #demo-ribbon.demo-ribbon--fixed-top {
        top: 46px;
    }
}

#demo-ribbon.demo-ribbon--fixed-bottom {
    top: auto;
    bottom: 0;
    border-top: 3px solid #d97706;
    border-bottom: 0;
    box-shadow: 0 -2px 8px rgba(0, 0, 0, 0.2);
}
CSS;
    }

    private static function get_inline_script() {
        return <<<'JS'
document.addEventListener('DOMContentLoaded', function () {
    var storageKey = 'chetrit-demo-ribbon-dismissed';
    var ribbon = document.getElementById('demo-ribbon');

    if (!ribbon) {
        return;
    }

    try {
        if (window.localStorage && window.localStorage.getItem(storageKey) === '1') {
            ribbon.style.display = 'none';
            return;
        }
    } catch (error) {
    }

    var closeButton = ribbon.querySelector('[data-demo-ribbon-close="1"]');

    if (!closeButton) {
        return;
    }

    closeButton.addEventListener('click', function () {
        ribbon.style.display = 'none';

        try {
            if (window.localStorage) {
                window.localStorage.setItem(storageKey, '1');
            }
        } catch (error) {
        }
    });
});
JS;
    }
}

Demo_Ribbon::init();