<?php

namespace HexWp\Admin;

use HexWp\WooCommerce\Settings;

if (!defined('ABSPATH')) {
    exit;
}

// Admin page under wp-admin: "B2B Pricing", the first item under the
// plugin's common top-level menu (see register_menu()). Shows the settings
// form and saves it via AJAX (no full page reload) using admin-ajax.php.
class B2B_Settings_Page {
    // The plugin's single top-level wp-admin menu slug. Every other
    // HexWp admin page (Account_Settings_Page, ...) is registered as a
    // submenu of this same slug rather than getting its own top-level entry.
    const MENU_SLUG = 'hex-wp-b2b-pricing';

    // The "action" name the browser sends to admin-ajax.php, and the nonce
    // action name used to prove the request came from our own settings form.
    const AJAX_ACTION = 'hex_wp_save_b2b_settings';
    const NONCE_ACTION = 'hex_wp_b2b_settings_ajax';

    // admin_enqueue_scripts hook suffix for this page, captured from
    // add_submenu_page()'s own return value in register_menu() rather than
    // guessed as a string — WordPress derives this suffix from the sanitized
    // *title* of the top-level menu (not its slug), so hardcoding it here
    // silently breaks the moment that title text changes (including per
    // locale, since it's translated). Only add_menu_page()/add_submenu_page()
    // know the real value.
    private $screen_hook;

    public function register_hooks() {
        \add_action('admin_menu', [$this, 'register_menu']);
        \add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        // wp_ajax_{action} is how WordPress routes an admin-ajax.php request
        // (for logged-in users) to a specific PHP method.
        \add_action('wp_ajax_' . self::AJAX_ACTION, [$this, 'handle_ajax_save']);
    }

    // Creates the plugin's one common top-level wp-admin menu, with this
    // page ("B2B Pricing") as its first item. Every other settings page
    // (Account_Settings_Page, etc.) attaches to self::MENU_SLUG as a
    // submenu instead of calling add_menu_page() itself.
    public function register_menu() {
        // Uses manage_options (not manage_woocommerce) so admins can still
        // reach this menu even if WooCommerce is deactivated.
        \add_menu_page(
            \__('B2B Customer Addon', HEX_WP_TEXT_DOMAIN),
            \__('B2B Customer Addon', HEX_WP_TEXT_DOMAIN),
            'manage_options',
            self::MENU_SLUG,
            [$this, 'render_page'],
            'dashicons-store',
            56
        );

        // add_menu_page() above also creates a first submenu item labeled
        // the same as the top-level entry ("B2B Customer Addon"). Re-adding
        // a submenu with the exact same slug relabels that one entry to
        // "B2B Pricing" instead, without changing its slug/callback — the
        // standard WordPress pattern for a "landing page IS a real submenu
        // item" top-level menu. Its return value is the actual hook suffix
        // WordPress will pass to admin_enqueue_scripts for this screen.
        $this->screen_hook = \add_submenu_page(
            self::MENU_SLUG,
            \__('B2B Pricing Settings', HEX_WP_TEXT_DOMAIN),
            \__('B2B Pricing', HEX_WP_TEXT_DOMAIN),
            'manage_options',
            self::MENU_SLUG,
            [$this, 'render_page']
        );
    }

    // Loads our CSS/JS, but only when the current admin screen is our own
    // settings page (WordPress calls this on every admin page load).
    public function enqueue_assets($hook) {
        if ($hook !== $this->screen_hook) {
            return;
        }

        \wp_enqueue_style(
            'hex-wp-b2b-admin',
            HEX_WP_PLUGIN_URL . 'assets/css/b2b-admin.css',
            [],
            HEX_WP_VERSION
        );

        \wp_enqueue_script(
            'hex-wp-b2b-admin',
            HEX_WP_PLUGIN_URL . 'assets/js/b2b-settings.js',
            [],
            HEX_WP_VERSION,
            true // load in the footer
        );

        // Hands the JS file everything it needs to call admin-ajax.php:
        // the URL to POST to, the nonce to prove it's a legit request, and
        // the button/notice text (kept translatable via __()).
        \wp_localize_script('hex-wp-b2b-admin', 'hexWpB2bSettings', [
            'ajaxUrl' => \admin_url('admin-ajax.php'),
            'action'  => self::AJAX_ACTION,
            'nonce'   => \wp_create_nonce(self::NONCE_ACTION),
            'messages' => [
                'saving'        => \__('Saving…', HEX_WP_TEXT_DOMAIN),
                'saved'         => \__('Saved', HEX_WP_TEXT_DOMAIN),
                'requestFailed' => \__('Could not save settings. Please try again.', HEX_WP_TEXT_DOMAIN),
                'enabled'       => \__('Enabled', HEX_WP_TEXT_DOMAIN),
                'disabled'      => \__('Disabled', HEX_WP_TEXT_DOMAIN),
            ],
        ]);
    }

    // Renders the settings page itself (just the form; saving happens via AJAX below).
    public function render_page() {
        if (!\current_user_can('manage_options')) {
            \wp_die(\esc_html__('You do not have permission to access this page.', HEX_WP_TEXT_DOMAIN));
        }

        $settings = Settings::get();
        $all_roles = \wp_roles()->get_names();
        $woocommerce_active = class_exists('WooCommerce');

        require HEX_WP_PLUGIN_DIR . 'templates/b2b-settings-page.php';
    }

    /**
     * AJAX handler for admin-ajax.php?action=hex_wp_save_b2b_settings.
     * check_ajax_referer() dies with a JSON -1 response on a bad/missing nonce.
     */
    public function handle_ajax_save() {
        // Confirms the request carries a valid nonce for our AJAX action (CSRF protection).
        \check_ajax_referer(self::NONCE_ACTION, 'nonce');

        // Belt-and-braces: only someone who could already reach this page should be able to save it.
        if (!\current_user_can('manage_options')) {
            \wp_send_json_error([
                'message' => \__('You do not have permission to do this.', HEX_WP_TEXT_DOMAIN),
            ], 403);
        }

        // Raw values only — Settings::update() does the actual sanitization
        // so there is one place that decides what's valid.
        $raw = \wp_unslash($_POST);

        Settings::update([
            'enabled'              => isset($raw['enabled']),
            'roles'                => isset($raw['roles']) ? (array) $raw['roles'] : [],
            'meta_key'             => isset($raw['meta_key']) ? $raw['meta_key'] : '',
            'tax_exempt_enabled'   => isset($raw['tax_exempt_enabled']),
            'price_suffix_enabled' => isset($raw['price_suffix_enabled']),
            'price_suffix_b2b'     => isset($raw['price_suffix_b2b']) ? $raw['price_suffix_b2b'] : '',
            'price_suffix_regular' => isset($raw['price_suffix_regular']) ? $raw['price_suffix_regular'] : '',
            'variation_filter_enabled' => isset($raw['variation_filter_enabled']),
            'b2b_stock_message'        => isset($raw['b2b_stock_message']) ? $raw['b2b_stock_message'] : '',
        ]);

        // wp_send_json_success() encodes this as JSON and ends the request —
        // this is what the JS's fetch().then() on the other end reads.
        \wp_send_json_success([
            'message' => \__('B2B pricing settings saved.', HEX_WP_TEXT_DOMAIN),
        ]);
    }
}
