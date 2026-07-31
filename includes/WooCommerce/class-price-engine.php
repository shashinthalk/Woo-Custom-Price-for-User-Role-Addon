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

        // Variable products send their price to the browser as JSON
        // (display_price/price_html per variation) built by WooCommerce
        // core, not through woocommerce_get_price_html — so the price shown
        // after picking a variation needs its own filter.
        \add_filter('woocommerce_available_variation', [$this, 'apply_b2b_price_to_variation_data'], 10, 3);

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
        $price = \wc_price($this->get_display_price($product, $b2b_price));

        return $price . $product->get_price_suffix();
    }

    /**
     * Applies the same B2B price to a variable product's per-variation JSON
     * data (display_price / price_html), which WooCommerce builds itself
     * and sends to the browser — this is what actually updates the price
     * shown after a variation is selected, so it needs its own override
     * rather than relying on the woocommerce_get_price_html filter above.
     */
    public function apply_b2b_price_to_variation_data($variation_data, $product, $variation) {
        if (!Customer::is_b2b()) {
            return $variation_data;
        }

        $b2b_price = \get_post_meta($variation->get_id(), Settings::PRICE_META_KEY, true);
        if ($b2b_price === '' || !\is_numeric($b2b_price)) {
            return $variation_data;
        }

        $display_price = $this->get_display_price($variation, $b2b_price);

        $variation_data['display_price'] = $display_price;
        $variation_data['display_regular_price'] = $display_price;
        // get_price_suffix() re-triggers the woocommerce_get_price_suffix
        // filter below, so it already reflects the same on/off settings.
        $variation_data['price_html'] = '<span class="price">' . \wc_price($display_price) . $variation->get_price_suffix() . '</span>';

        return $variation_data;
    }

    // Decide incl./excl. tax from our own tax_exempt_enabled setting rather
    // than wc_get_price_to_display(), which reads WooCommerce's site-wide
    // tax display option and doesn't reliably follow a manually-injected
    // price override like the B2B price.
    private function get_display_price($product, $price) {
        $settings = Settings::get();

        return !empty($settings['tax_exempt_enabled'])
            ? \wc_get_price_excluding_tax($product, ['price' => $price])
            : \wc_get_price_including_tax($product, ['price' => $price]);
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

        // The suffix text is specifically about tax status ("zzgl./inkl.
        // MwSt."), so it only makes sense while tax exemption is actually
        // in play. Tax exemption off: same suffix as everyone else gets.
        if (empty($settings['price_suffix_enabled']) || empty($settings['tax_exempt_enabled'])) {
            return $suffix;
        }

        // Only override the suffix for products that actually have a B2B
        // price set — otherwise leave the default suffix alone, same as price.
        $b2b_price = \get_post_meta($product->get_id(), Settings::PRICE_META_KEY, true);
        if ($b2b_price === '' || !\is_numeric($b2b_price)) {
            return $suffix;
        }

        $text = Customer::is_b2b() ? $settings['price_suffix_b2b'] : $settings['price_suffix_regular'];

        // Same wrapper WooCommerce's own default suffix uses (' <small
        // class="woocommerce-price-suffix">...</small>'), so theme styling
        // targeting that class still applies to ours.
        return ' <small class="woocommerce-price-suffix">' . \esc_html($text) . '</small>';
    }
}
