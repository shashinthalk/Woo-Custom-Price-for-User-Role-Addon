<?php

namespace HexWp\WooCommerce;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Front-end behavior for the B2B quantity limit: on a product where the
 * admin set a "B2B quantity limit" (see Stock_Field), a B2B customer
 * requesting more than that limit sees a customizable message below the
 * price. This is purely informational — it doesn't touch WooCommerce's own
 * stock management or cart validation, which keep working normally.
 *
 * Works for both simple and variable products — the limit is one
 * product-level setting shared across all of a variable product's variations.
 *
 * Also registers [hexwp_b2b_stock_message] so the message can be placed
 * manually (e.g. in a theme area this plugin's automatic hook doesn't reach),
 * in addition to the automatic placement right after the price.
 */
class Stock_Engine {
    const SHORTCODE_TAG = 'hexwp_b2b_stock_message';

    public function register_hooks() {
        \add_action('woocommerce_single_product_summary', [$this, 'render_message_container'], 15); // 15 = right after the price (10), before the excerpt (20)
        \add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        \add_shortcode(self::SHORTCODE_TAG, [$this, 'render_shortcode']);
    }

    // Falls back to the current product (global $product, then the queried
    // post) when no product_id is given — covers both the automatic hook and
    // a bare [hexwp_b2b_stock_message] on a product page.
    private function resolve_product($product_id = 0) {
        if ($product_id) {
            return \wc_get_product($product_id);
        }

        global $product;
        if ($product instanceof \WC_Product) {
            return $product;
        }

        return \wc_get_product(\get_the_ID());
    }

    // Returns the configured quantity limit, or null if this product/feature isn't set up.
    private function get_limit($product) {
        if (!($product instanceof \WC_Product)) {
            return null;
        }

        if ('yes' !== \get_post_meta($product->get_id(), Settings::STOCK_META_KEY, true)) {
            return null;
        }

        $limit = \get_post_meta($product->get_id(), Settings::STOCK_QTY_META_KEY, true);

        return \is_numeric($limit) ? (float) $limit : null;
    }

    // True if this product (or, for a variable product, at least one of its
    // variations) has a B2B price set — same condition Price_Engine uses
    // before it overrides price/suffix.
    private function has_b2b_price($product) {
        if (!($product instanceof \WC_Product)) {
            return false;
        }

        if ($product->is_type('variable')) {
            foreach ($product->get_children() as $variation_id) {
                $price = \get_post_meta($variation_id, Settings::PRICE_META_KEY, true);
                if ($price !== '' && \is_numeric($price)) {
                    return true;
                }
            }

            return false;
        }

        $price = \get_post_meta($product->get_id(), Settings::PRICE_META_KEY, true);

        return $price !== '' && \is_numeric($price);
    }

    // Visible only when: a B2B quantity limit is set, this product has a B2B
    // price, and the current visitor is a B2B customer.
    private function applies_to($product) {
        return null !== $this->get_limit($product)
            && $this->has_b2b_price($product)
            && Customer::is_b2b();
    }

    // Builds the (initially hidden) message markup. Shared by both the
    // automatic hook and the shortcode so they always render identically.
    private function build_message_html($product) {
        $settings = Settings::get();
        ob_start();
        ?>
        <p class="hexwp-b2b-stock-message" data-quantity-limit="<?php echo \esc_attr($this->get_limit($product)); ?>" hidden>
            <?php echo \esc_html($settings['b2b_stock_message']); ?>
        </p>
        <?php
        return ob_get_clean();
    }

    // Automatic placement: right after the price on the single product page.
    public function render_message_container() {
        global $product;

        if (!$this->applies_to($product)) {
            return;
        }

        $this->enqueue_message_assets();
        echo $this->build_message_html($product);
    }

    // Manual placement: [hexwp_b2b_stock_message] or
    // [hexwp_b2b_stock_message product_id="123"] anywhere shortcodes render.
    public function render_shortcode($atts) {
        $atts = \shortcode_atts(['product_id' => 0], $atts, self::SHORTCODE_TAG);
        $product = $this->resolve_product((int) $atts['product_id']);

        if (!$this->applies_to($product)) {
            return '';
        }

        // wp_enqueue_scripts may already have run by the time a shortcode
        // renders (e.g. used outside is_product()), so enqueue here too —
        // our script is loaded in the footer, so this is still in time.
        $this->enqueue_message_assets();

        return $this->build_message_html($product);
    }

    // Covers the common case (product page, automatic placement) so assets
    // are enqueued as early as possible; the shortcode enqueues defensively too.
    public function enqueue_assets() {
        if (!\is_product()) {
            return;
        }

        if (!$this->applies_to($this->resolve_product())) {
            return;
        }

        $this->enqueue_message_assets();
    }

    private function enqueue_message_assets() {
        \wp_enqueue_style(
            'hex-wp-b2b-stock-message',
            HEX_WP_PLUGIN_URL . 'assets/css/stock-message.css',
            [],
            HEX_WP_VERSION
        );

        \wp_enqueue_script(
            'hex-wp-b2b-stock-message',
            HEX_WP_PLUGIN_URL . 'assets/js/stock-message.js',
            ['jquery'],
            HEX_WP_VERSION,
            true
        );
    }
}
