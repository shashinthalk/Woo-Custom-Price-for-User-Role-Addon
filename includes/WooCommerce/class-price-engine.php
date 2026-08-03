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

        // Charge no tax on a line item, but only when BOTH the shopper is
        // B2B AND this exact product/variation has a B2B price set — see
        // maybe_exempt_product_from_tax() for why this replaced a pair of
        // cart-wide/site-wide overrides that used to leak onto normal
        // (non-B2B-priced) products.
        \add_filter('woocommerce_product_is_taxable', [$this, 'maybe_exempt_product_from_tax'], 10, 2);

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

    // Whether tax applies to this specific product/variation. WooCommerce
    // checks this per line item during cart/checkout totals (WC_Cart_Totals
    // ::get_rates_for_item()), so exemption naturally follows each product
    // instead of the whole cart/customer — a mixed cart of B2B-priced and
    // normal products taxes only the B2B-priced ones.
    //
    // A previous version used WC()->customer->set_is_vat_exempt(true) and a
    // woocommerce_tax_display_shop/_cart override instead. Both are
    // cart-wide/site-wide flags with no concept of "this one product", so
    // they exempted (or showed as excl. tax) every product a B2B customer
    // looked at — including ones with no B2B price at all, where behavior
    // should have stayed completely default.
    public function maybe_exempt_product_from_tax($is_taxable, $product) {
        $settings = Settings::get();

        if (empty($settings['tax_exempt_enabled']) || !Customer::is_b2b()) {
            return $is_taxable; // default: leave WooCommerce's normal taxable status alone
        }

        $b2b_price = \get_post_meta($product->get_id(), Settings::PRICE_META_KEY, true);
        if ($b2b_price === '' || !\is_numeric($b2b_price)) {
            return $is_taxable; // no B2B price on this product: stays taxed normally
        }

        return false; // B2B customer + B2B price on this exact product: tax-exempt
    }

    // The short text shown right after a price, e.g. "zzgl. MwSt." or "inkl. MwSt.".
    public function filter_price_suffix($suffix, $product) {
        $settings = Settings::get();

        // Suffix override is its own on/off switch — independent of tax
        // exemption, which is a separate feature. Switched off: leave
        // whatever suffix WooCommerce/the theme would normally show.
        if (empty($settings['price_suffix_enabled'])) {
            return $suffix;
        }

        // The B2B suffix only applies when BOTH conditions hold: the
        // shopper is B2B (per the configured roles) AND this exact
        // product/variation has a B2B price set. Any other combination
        // (not B2B, or no B2B price here) falls back to the admin's
        // "regular" suffix text — never a mix of the two.
        $b2b_price = \get_post_meta($product->get_id(), Settings::PRICE_META_KEY, true);
        $has_b2b_price = ($b2b_price !== '' && \is_numeric($b2b_price));

        $text = (Customer::is_b2b() && $has_b2b_price)
            ? $settings['price_suffix_b2b']
            : $settings['price_suffix_regular'];

        // Same wrapper WooCommerce's own default suffix uses (' <small
        // class="woocommerce-price-suffix">...</small>'), so theme styling
        // targeting that class still applies to ours.
        return ' <small class="woocommerce-price-suffix">' . \esc_html($text) . '</small>';
    }
}
