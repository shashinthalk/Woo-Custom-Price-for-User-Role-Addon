<?php

namespace HexWp\WooCommerce;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Adds a "B2B price" field on the Pricing tab for simple products and saves it.
 * (Variable products use per-variation pricing instead — see Variation_Price_Field.)
 */
class Product_Price_Field {
    // render_field draws the input box; save_field stores what was typed into it.
    public function register_hooks() {
        \add_action('woocommerce_product_options_pricing', [$this, 'render_field']);
        \add_action('woocommerce_process_product_meta', [$this, 'save_field']);
    }

    // Draws a "B2B Price" text box on the product's Pricing tab.
    public function render_field() {
        global $post;

        \woocommerce_wp_text_input([
            'id'        => Settings::PRICE_META_KEY,
            'label'     => \sprintf(
                /* translators: %s: currency symbol */
                \__('B2B Price (%s)', HEX_WP_TEXT_DOMAIN),
                \get_woocommerce_currency_symbol()
            ),
            'data_type' => 'price',
            'value'     => \get_post_meta($post->ID, Settings::PRICE_META_KEY, true),
        ]);
    }

    // Runs when the admin clicks "Update"/"Publish" on the product edit screen.
    public function save_field($post_id) {
        // Defense in depth: WooCommerce already gates this hook behind its own
        // product-edit nonce/capability check, but verify again before writing.
        if (!\current_user_can('edit_post', $post_id)) {
            return;
        }

        $meta_key = Settings::PRICE_META_KEY;

        // Field wasn't submitted (e.g. quick-edit screen): leave the stored value untouched.
        if (!isset($_POST[$meta_key])) {
            return;
        }

        // Clean up the typed value (e.g. "48,31" -> "48.31") before saving.
        $price = \wc_format_decimal(\sanitize_text_field(\wp_unslash($_POST[$meta_key])));
        \update_post_meta($post_id, $meta_key, $price);
    }
}
