<?php

namespace HexWp;

use HexWp\Admin\B2B_Settings_Page;
use HexWp\WooCommerce\Product_Price_Field;
use HexWp\WooCommerce\Variation_Price_Field;
use HexWp\WooCommerce\Price_Engine;

if (!defined('ABSPATH')) {
    exit;
}

class Plugin {
    private $repository;
    private $admin_page;
    private $b2b_settings_page;

    public function __construct() {
        // Create one repository instance and pass it to all layers.
        $this->repository = new \HexWp\Data_Repository();
        $this->b2b_settings_page = new B2B_Settings_Page();
    }

    public function run() {
        \add_action('init', [$this, 'load_text_domain']);

        $this->b2b_settings_page->register_hooks();

        // Deferred so WooCommerce (if present) has finished loading before we check for it.
        \add_action('plugins_loaded', [$this, 'maybe_register_b2b_pricing']);
    }

    public function maybe_register_b2b_pricing() {
        if (!class_exists('WooCommerce')) {
            return;
        }

        (new Variation_Price_Field())->register_hooks();
        (new Product_Price_Field())->register_hooks();
        (new Price_Engine())->register_hooks();
    }

    public function load_text_domain() {
        \load_plugin_textdomain(
            HEX_WP_TEXT_DOMAIN,
            false,
            \dirname(\plugin_basename(HEX_WP_PLUGIN_FILE)) . '/languages'
        );
    }
}
