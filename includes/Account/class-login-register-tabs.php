<?php

namespace HexWp\Account;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Turns the My Account page's combined Login/Register form into a tabbed
 * layout. The tab buttons themselves are just markup — CSS (which form is
 * hidden vs shown) and JS (clicking a tab) are each their own admin switch,
 * so e.g. a theme can supply its own tab styling while still using our JS,
 * or vice versa.
 */
class Login_Register_Tabs {
    public function register_hooks() {
        \add_action('woocommerce_before_customer_login_form', [$this, 'render_tab_buttons']);
        \add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function render_tab_buttons() {
        $settings = Settings::get();

        if (!$settings['login_register_tabs_enabled']) {
            return;
        }

        $active_tab = $this->get_active_tab();
        ?>
        <div class="hexwp-account-tabs" data-active-tab="<?php echo \esc_attr($active_tab); ?>">
            <button type="button" class="hexwp-account-tab-btn<?php echo $active_tab === 'login' ? ' active' : ''; ?>" data-tab="login"><?php echo \esc_html__('Login', HEX_WP_TEXT_DOMAIN); ?></button>
            <button type="button" class="hexwp-account-tab-btn<?php echo $active_tab === 'register' ? ' active' : ''; ?>" data-tab="register"><?php echo \esc_html__('Register', HEX_WP_TEXT_DOMAIN); ?></button>
        </div>
        <?php
    }

    /**
     * Which tab should be active when the page is (re)rendered. Defaults to
     * 'login', except right after a Register form submission — including a
     * FAILED one redisplaying the page with validation errors — where it
     * stays 'register'. 'woocommerce-register-nonce' is the hidden nonce
     * field WooCommerce's own Register form always posts (wp_nonce_field(
     * 'woocommerce-register', 'woocommerce-register-nonce') in its
     * myaccount/form-login.php template); its mere presence in $_POST is a
     * reliable signal of which form was actually submitted, independent of
     * whether that submission succeeded. Not read as a security check
     * (nothing here needs to verify the nonce's validity, only that the
     * field exists), just as a "which tab was the user on" signal.
     */
    private function get_active_tab() {
        return isset($_POST['woocommerce-register-nonce']) ? 'register' : 'login';
    }

    public function enqueue_assets() {
        if (!\is_account_page()) {
            return;
        }

        $settings = Settings::get();

        if (!$settings['login_register_tabs_enabled']) {
            return;
        }

        if ($settings['login_register_css_enabled']) {
            \wp_enqueue_style(
                'hex-wp-account-tabs',
                HEX_WP_PLUGIN_URL . 'assets/css/account-tabs.css',
                [],
                HEX_WP_VERSION
            );
        }

        if ($settings['login_register_js_enabled']) {
            \wp_enqueue_script(
                'hex-wp-account-tabs',
                HEX_WP_PLUGIN_URL . 'assets/js/account-tabs.js',
                [],
                HEX_WP_VERSION,
                true // load in the footer
            );
        }
    }
}
