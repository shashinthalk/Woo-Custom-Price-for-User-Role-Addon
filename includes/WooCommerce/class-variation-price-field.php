<?php

namespace HexWp\WooCommerce;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Adds a per-variation "B2B price" field on the product edit screen and saves it.
 */
class Variation_Price_Field {
    // render_field draws the input box; save_field stores what was typed into it.
    public function register_hooks() {
        \add_action('woocommerce_variation_options_pricing', [$this, 'render_field'], 10, 3);
        \add_action('woocommerce_save_product_variation', [$this, 'save_field'], 10, 2);
    }

    // Draws a "B2B Price" text box inside each variation panel on the product edit screen.
    public function render_field($loop, $variation_data, $variation) {
        \woocommerce_wp_text_input([
            'id'            => Settings::PRICE_META_KEY . "[{$loop}]",
            'label'         => \sprintf(
                /* translators: %s: currency symbol */
                \__('B2B Price (%s)', HEX_WP_TEXT_DOMAIN),
                \get_woocommerce_currency_symbol()
            ),
            'data_type'     => 'price',
            'value'         => \get_post_meta($variation->ID, Settings::PRICE_META_KEY, true),
            'wrapper_class' => 'form-row form-row-full',
        ]);
    }

    // Runs when the admin clicks "Save changes" on the variations panel.
    public function save_field($variation_id, $i) {
        // Defense in depth: WooCommerce already gates this hook behind its own
        // product-edit nonce/capability check, but verify again before writing.
        if (!\current_user_can('edit_post', $variation_id)) {
            return;
        }

        $meta_key = Settings::PRICE_META_KEY;

        // Nothing submitted for this variation row: leave the stored value untouched.
        if (!isset($_POST[$meta_key][$i])) {
            return;
        }

        // Clean up the typed value (e.g. "48,31" -> "48.31") before saving.
        $price = \wc_format_decimal(\sanitize_text_field(\wp_unslash($_POST[$meta_key][$i])));
        \update_post_meta($variation_id, $meta_key, $price);
    }
}
