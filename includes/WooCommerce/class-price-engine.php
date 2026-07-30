<?php

namespace HexWp\WooCommerce;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Applies the configured B2B price/tax/suffix behavior at render and checkout time.
 * Everything here reads Settings::get() fresh so admin changes take effect immediately.
 */
class Price_Engine {
    // Hook everything up to the WooCommerce actions/filters that control
    // price, tax, and the little text shown after a price (e.g. "inkl. MwSt.").
    public function register_hooks() {
        // Swap in the B2B price wherever WooCommerce reads a product's price.
        \add_filter('woocommerce_product_get_price', [$this, 'apply_b2b_price'], 10, 2);
        \add_filter('woocommerce_product_variation_get_price', [$this, 'apply_b2b_price'], 10, 2);
        \add_filter('woocommerce_get_price_html', [$this, 'apply_b2b_price_html'], 10, 2);

        // Show/charge tax excluded for B2B customers, if enabled.
        \add_filter('woocommerce_tax_display_shop', [$this, 'filter_tax_display']);
        \add_filter('woocommerce_tax_display_cart', [$this, 'filter_tax_display']);
        \add_action('woocommerce_before_calculate_totals', [$this, 'maybe_set_tax_exempt']);
        \add_action('template_redirect', [$this, 'maybe_set_tax_exempt']);

        // Replace the small suffix text after the price, if enabled.
        \add_filter('woocommerce_get_price_suffix', [$this, 'filter_price_suffix'], 10, 2);
    }

    // Used for the raw price value (e.g. in cart/checkout calculations).
    public function apply_b2b_price($price, $product) {
        if (!Customer::is_b2b()) {
            return $price; // not a B2B customer: leave the normal price alone
        }

        $b2b_price = \get_post_meta($product->get_id(), Settings::PRICE_META_KEY, true);
        if ($b2b_price !== '' && \is_numeric($b2b_price)) {
            return $b2b_price;
        }

        // No B2B price set on this product: fall back to the regular price.
        return $price;
    }

    // Used for the formatted price string shown on shop/product pages
    // (e.g. "€ 48,31"), so the displayed price matches apply_b2b_price() above.
    public function apply_b2b_price_html($price_html, $product) {
        if (!Customer::is_b2b()) {
            return $price_html;
        }

        $b2b_price = \get_post_meta($product->get_id(), Settings::PRICE_META_KEY, true);
        if ($b2b_price === '' || !\is_numeric($b2b_price)) {
            return $price_html;
        }

        // Rebuild the formatted price (currency symbol, decimals) from the B2B price.
        $price = \wc_price(\wc_get_price_to_display($product, ['price' => $b2b_price]));

        return $price . $product->get_price_suffix();
    }

    // Tells WooCommerce whether to display prices "incl." or "excl." tax.
    public function filter_tax_display($display) {
        $settings = Settings::get();

        if (!empty($settings['tax_exempt_enabled']) && Customer::is_b2b()) {
            return 'excl';
        }

        return $display; // leave WooCommerce's normal setting untouched
    }

    // Actually exempts the customer from tax during totals calculation
    // (filter_tax_display() above only changes what's shown, not what's charged).
    public function maybe_set_tax_exempt() {
        $settings = Settings::get();

        if (empty($settings['tax_exempt_enabled'])) {
            return;
        }

        if (Customer::is_b2b() && \WC()->customer) {
            \WC()->customer->set_is_vat_exempt(true);
        }
    }

    // The short text shown right after a price, e.g. "zzgl. MwSt." or "inkl. MwSt.".
    public function filter_price_suffix($suffix, $product) {
        $settings = Settings::get();

        if (empty($settings['price_suffix_enabled'])) {
            return $suffix; // feature off: keep WooCommerce's/theme's default suffix
        }

        // Leading space so the suffix doesn't run into the price (WooCommerce's
        // own default suffix markup includes this space; ours has to add it back).
        return ' ' . (Customer::is_b2b() ? $settings['price_suffix_b2b'] : $settings['price_suffix_regular']);
    }
}
