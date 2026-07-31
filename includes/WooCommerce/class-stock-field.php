<?php

namespace HexWp\WooCommerce;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Adds two fields right after WooCommerce's own "Manage stock?" checkbox in
 * the product's Inventory tab: an on/off checkbox and a quantity limit.
 *
 * This is independent of WooCommerce's own stock management — it doesn't
 * read or affect the real stock quantity. It's just a B2B order-quantity
 * threshold: when checked, a B2B customer requesting more than the limit
 * sees the message configured on the B2B Pricing settings page (see
 * Stock_Engine). Actual stock enforcement, if any, still works normally.
 */
class Stock_Field {
    public function register_hooks() {
        // woocommerce_product_options_stock fires immediately after WooCommerce
        // prints its own "Manage stock?" checkbox in the Inventory tab —
        // exactly where this needs to appear.
        \add_action('woocommerce_product_options_stock', [$this, 'render_fields']);
        \add_action('woocommerce_process_product_meta', [$this, 'save_fields']);
    }

    public function render_fields() {
        global $post;

        \woocommerce_wp_checkbox([
            'id'          => Settings::STOCK_META_KEY,
            'label'       => \__('Manage B2B stock?', HEX_WP_TEXT_DOMAIN),
            'description' => \__('Show a message above a set quantity.', HEX_WP_TEXT_DOMAIN),
            'value'       => \get_post_meta($post->ID, Settings::STOCK_META_KEY, true),
        ]);

        \woocommerce_wp_text_input([
            'id'          => Settings::STOCK_QTY_META_KEY,
            'label'       => \__('B2B quantity limit', HEX_WP_TEXT_DOMAIN),
            'data_type'   => 'stock',
            'value'       => \get_post_meta($post->ID, Settings::STOCK_QTY_META_KEY, true),
        ]);
    }

    // Runs when the admin clicks "Update"/"Publish" on the product edit screen.
    public function save_fields($post_id) {
        // Defense in depth: WooCommerce already gates this hook behind its own
        // product-edit nonce/capability check, but verify again before writing.
        if (!\current_user_can('edit_post', $post_id)) {
            return;
        }

        // Checkboxes only appear in $_POST when checked — same 'yes'/'no'
        // convention WooCommerce itself uses for this kind of meta.
        $enabled = isset($_POST[Settings::STOCK_META_KEY]) ? 'yes' : 'no';
        \update_post_meta($post_id, Settings::STOCK_META_KEY, $enabled);

        if (isset($_POST[Settings::STOCK_QTY_META_KEY])) {
            $qty = \wc_stock_amount(\sanitize_text_field(\wp_unslash($_POST[Settings::STOCK_QTY_META_KEY])));
            \update_post_meta($post_id, Settings::STOCK_QTY_META_KEY, $qty);
        }
    }
}
