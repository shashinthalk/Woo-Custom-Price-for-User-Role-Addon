<?php

namespace HexWp;

use HexWp\Admin\B2B_Settings_Page;
use HexWp\Admin\Account_Settings_Page;
use HexWp\WooCommerce\Product_Price_Field;
use HexWp\WooCommerce\Variation_Price_Field;
use HexWp\WooCommerce\Price_Engine;
use HexWp\WooCommerce\Variation_Filter_Toolbar;
use HexWp\WooCommerce\Stock_Field;
use HexWp\WooCommerce\Stock_Engine;
use HexWp\Account\Registration_Fields;
use HexWp\Account\Account_Phone_Field;
use HexWp\Account\Account_Extra_Fields;
use HexWp\Account\Account_Field_Visibility;
use HexWp\Account\Login_Register_Tabs;

if (!defined('ABSPATH')) {
    exit;
}

class Plugin {
    private $b2b_settings_page;
    private $account_settings_page;

    public function __construct() {
        $this->b2b_settings_page = new B2B_Settings_Page();
        $this->account_settings_page = new Account_Settings_Page();
    }

    public function run() {
        \add_action('init', [$this, 'load_text_domain']);

        $this->b2b_settings_page->register_hooks();
        $this->account_settings_page->register_hooks();

        // Deferred so WooCommerce (if present) has finished loading before we check for it.
        \add_action('plugins_loaded', [$this, 'maybe_register_woocommerce_features']);
    }

    // Everything here depends on WooCommerce classes/functions, so it's only
    // registered when WooCommerce is actually active.
    public function maybe_register_woocommerce_features() {
        if (!class_exists('WooCommerce')) {
            return;
        }

        (new Variation_Price_Field())->register_hooks();
        (new Product_Price_Field())->register_hooks();
        (new Price_Engine())->register_hooks();
        (new Variation_Filter_Toolbar())->register_hooks();
        (new Stock_Field())->register_hooks();
        (new Stock_Engine())->register_hooks();
        (new Registration_Fields())->register_hooks();

        // Account_Extra_Fields positions Account_Phone_Field's render_field()
        // (among other things) directly after Email on My Account, so the
        // phone field instance is constructed here and handed over rather
        // than each class self-registering independently — see
        // Account_Extra_Fields's docblock for why.
        $account_phone_field = new Account_Phone_Field();
        $account_phone_field->register_hooks();
        (new Account_Extra_Fields($account_phone_field))->register_hooks();

        (new Account_Field_Visibility())->register_hooks();
        (new Login_Register_Tabs())->register_hooks();
    }

    public function load_text_domain() {
        \load_plugin_textdomain(
            HEX_WP_TEXT_DOMAIN,
            false,
            \dirname(\plugin_basename(HEX_WP_PLUGIN_FILE)) . '/languages'
        );
    }
}
