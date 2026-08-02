<?php

namespace HexWp\Admin;

use HexWp\Account\Settings;

if (!defined('ABSPATH')) {
    exit;
}

// Admin page under wp-admin: "Registration & Account", a submenu of the
// plugin's common top-level menu (see B2B_Settings_Page::MENU_SLUG). Shows
// the settings form and saves it via AJAX (no full page reload), same
// pattern as HexWp\Admin\B2B_Settings_Page.
class Account_Settings_Page {
    const MENU_SLUG = 'hex-wp-account-settings';

    const AJAX_ACTION = 'hex_wp_save_account_settings';
    const NONCE_ACTION = 'hex_wp_account_settings_ajax';

    // admin_enqueue_scripts hook suffix for this page, captured from
    // add_submenu_page()'s own return value in register_menu() rather than
    // guessed as a string. WordPress derives a submenu's hook suffix from
    // the sanitized *title* of its parent top-level menu (not the parent's
    // slug), so a hardcoded '{parent_slug}_page_{this_slug}' constant here
    // was wrong the moment B2B_Settings_Page's top-level title stopped
    // being 'B2B Pricing' — this was the actual bug behind "CSS doesn't
    // load and Add Field doesn't work": enqueue_assets() always compared
    // against the wrong string and silently bailed out every time.
    private $screen_hook;

    public function register_hooks() {
        \add_action('admin_menu', [$this, 'register_menu']);
        \add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        \add_action('wp_ajax_' . self::AJAX_ACTION, [$this, 'handle_ajax_save']);
    }

    // Adds the "Registration & Account" item under the plugin's common
    // top-level menu (see B2B_Settings_Page::register_menu()).
    public function register_menu() {
        // manage_options (not manage_woocommerce) so admins can still reach
        // this page even if WooCommerce is deactivated.
        $this->screen_hook = \add_submenu_page(
            B2B_Settings_Page::MENU_SLUG,
            \__('Registration & Account Settings', HEX_WP_TEXT_DOMAIN),
            \__('Registration & Account', HEX_WP_TEXT_DOMAIN),
            'manage_options',
            self::MENU_SLUG,
            [$this, 'render_page']
        );
    }

    // Loads our CSS/JS, but only on this one admin screen.
    public function enqueue_assets($hook) {
        if ($hook !== $this->screen_hook) {
            return;
        }

        // Shared card/switch/notice/save-bar styles used by every HexWp
        // admin settings page (see class-b2b-settings-page.php).
        \wp_enqueue_style(
            'hex-wp-b2b-admin',
            HEX_WP_PLUGIN_URL . 'assets/css/b2b-admin.css',
            [],
            HEX_WP_VERSION
        );

        // Page-specific layout (wrapper/header/grid) only.
        \wp_enqueue_style(
            'hex-wp-account-admin',
            HEX_WP_PLUGIN_URL . 'assets/css/account-admin.css',
            ['hex-wp-b2b-admin'],
            HEX_WP_VERSION
        );

        \wp_enqueue_script(
            'hex-wp-account-admin',
            HEX_WP_PLUGIN_URL . 'assets/js/account-settings.js',
            [],
            HEX_WP_VERSION,
            true // load in the footer
        );

        \wp_localize_script('hex-wp-account-admin', 'hexWpAccountSettings', [
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

    public function render_page() {
        if (!\current_user_can('manage_options')) {
            \wp_die(\esc_html__('You do not have permission to access this page.', HEX_WP_TEXT_DOMAIN));
        }

        $settings = Settings::get();
        $woocommerce_active = class_exists('WooCommerce');

        require HEX_WP_PLUGIN_DIR . 'templates/account-settings-page.php';
    }

    /**
     * AJAX handler for admin-ajax.php?action=hex_wp_save_account_settings.
     */
    public function handle_ajax_save() {
        \check_ajax_referer(self::NONCE_ACTION, 'nonce');

        if (!\current_user_can('manage_options')) {
            \wp_send_json_error([
                'message' => \__('You do not have permission to do this.', HEX_WP_TEXT_DOMAIN),
            ], 403);
        }

        // Raw values only — Settings::update() does the actual sanitization
        // (including the custom_fields repeater rows and the
        // account_field_visibility list, both of which arrive as nested
        // arrays that wp_unslash() already unslashes recursively).
        $raw = \wp_unslash($_POST);

        Settings::update([
            'registration_fields_enabled' => isset($raw['registration_fields_enabled']),
            'first_name_enabled'  => isset($raw['first_name_enabled']),
            'first_name_required' => isset($raw['first_name_required']),
            'first_name_label'    => isset($raw['first_name_label']) ? $raw['first_name_label'] : '',
            'last_name_enabled'   => isset($raw['last_name_enabled']),
            'last_name_required'  => isset($raw['last_name_required']),
            'last_name_label'     => isset($raw['last_name_label']) ? $raw['last_name_label'] : '',
            'phone_enabled'       => isset($raw['phone_enabled']),
            'phone_required'      => isset($raw['phone_required']),
            'phone_label'         => isset($raw['phone_label']) ? $raw['phone_label'] : '',

            'custom_fields' => isset($raw['custom_fields']) && is_array($raw['custom_fields']) ? $raw['custom_fields'] : [],

            'customer_type_enabled'        => isset($raw['customer_type_enabled']),
            'customer_type_required'       => isset($raw['customer_type_required']),
            'customer_type_include_roles'  => isset($raw['customer_type_include_roles']) && is_array($raw['customer_type_include_roles']) ? $raw['customer_type_include_roles'] : [],
            'customer_type_exclude_roles'  => isset($raw['customer_type_exclude_roles']) && is_array($raw['customer_type_exclude_roles']) ? $raw['customer_type_exclude_roles'] : [],
            'customer_type_field_label'    => isset($raw['customer_type_field_label']) ? $raw['customer_type_field_label'] : '',
            'customer_type_placeholder'    => isset($raw['customer_type_placeholder']) ? $raw['customer_type_placeholder'] : '',
            'customer_type_required_error' => isset($raw['customer_type_required_error']) ? $raw['customer_type_required_error'] : '',

            'account_field_visibility' => isset($raw['account_field_visibility']) && is_array($raw['account_field_visibility']) ? $raw['account_field_visibility'] : [],

            'login_register_tabs_enabled' => isset($raw['login_register_tabs_enabled']),
            'login_register_css_enabled'  => isset($raw['login_register_css_enabled']),
            'login_register_js_enabled'   => isset($raw['login_register_js_enabled']),
        ]);

        \wp_send_json_success([
            'message' => \__('Registration & account settings saved.', HEX_WP_TEXT_DOMAIN),
        ]);
    }
}
