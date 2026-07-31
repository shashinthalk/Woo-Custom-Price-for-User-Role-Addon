<?php

namespace HexWp\WooCommerce;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Adds a "Filter variations by..." toolbar above the Variations panel on the
 * product edit screen, so admins with many variations (e.g. Width, Height,
 * Load Index) can find the row they want instead of scrolling through all of
 * them. Filtering happens entirely in the browser (see variation-filters.js);
 * this class just prints the dropdowns and raises how many variations
 * WooCommerce loads per page so there's more to filter across.
 */
class Variation_Filter_Toolbar {
    // WooCommerce only loads 15 variations per "page" by default — too few
    // to make filtering useful. Raise it high enough to cover one page load
    // for most products; increase further if you have larger variation sets.
    const VARIATIONS_PER_PAGE = 200;

    public function register_hooks() {
        // Note: the on/off check itself is NOT done here. register_hooks() runs
        // during 'plugins_loaded' (before 'init'), and Settings::get() is not
        // safe to call that early (see is_enabled() below), so every callback
        // checks is_enabled() for itself once it actually runs.
        \add_filter('woocommerce_admin_meta_boxes_variations_per_page', [$this, 'increase_variations_per_page']);
        \add_action('woocommerce_variable_product_before_variations', [$this, 'render_toolbar']);
        \add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    /**
     * Reads the admin's on/off toggle from Settings.
     *
     * Only ever call this from inside a hook callback that fires at 'init' or
     * later (never from register_hooks() itself, which runs during
     * 'plugins_loaded'). Settings::get() can resolve translated strings, and
     * WordPress logs a "_load_textdomain_just_in_time" notice if a translation
     * function runs before 'init'.
     */
    private function is_enabled() {
        return !empty(Settings::get()['variation_filter_enabled']);
    }

    public function increase_variations_per_page($per_page) {
        return $this->is_enabled() ? self::VARIATIONS_PER_PAGE : $per_page;
    }

    // Prints the toolbar (one dropdown per attribute used for variations)
    // just above the variations list. WooCommerce calls this hook from
    // inside the product data metabox, where $post is already the product being edited.
    public function render_toolbar() {
        if (!$this->is_enabled()) {
            return;
        }

        global $post;

        $product = $post ? \wc_get_product($post->ID) : null;
        if (!$product) {
            return;
        }

        // get_attributes() returns WC_Product_Attribute objects (not the old
        // plain-array format), so we read them via their getter methods below.
        $attributes = $product->get_attributes();
        if (!$attributes) {
            return;
        }
        ?>
        <div class="hexwp-variation-filters">
            <strong class="hexwp-variation-filters-label"><?php echo \esc_html__('Filter variations by:', HEX_WP_TEXT_DOMAIN); ?></strong>
            <?php foreach ($attributes as $attribute) :
                // Only attributes actually used for variations have anything to filter.
                if (!$attribute->get_variation() || !$attribute->get_options()) {
                    continue;
                }

                $attr_name  = $attribute->get_name();
                $field_name = 'attribute_' . \sanitize_title($attr_name);
                ?>
                <select class="hexwp-variation-filter" data-field="<?php echo \esc_attr($field_name); ?>">
                    <option value=""><?php echo \esc_html(\sprintf(\__('Any %s', HEX_WP_TEXT_DOMAIN), \wc_attribute_label($attr_name))); ?></option>
                    <?php if ($attribute->is_taxonomy()) :
                        // Taxonomy attribute selects in the admin use the term SLUG as the
                        // option value (not the display name) — our filter values must
                        // match slugs too, or letter/text attributes (S, M, L...) won't line up.
                        $terms = \wc_get_product_terms($post->ID, $attr_name, ['fields' => 'all']);
                        foreach ($terms as $term) : ?>
                            <option value="<?php echo \esc_attr($term->slug); ?>"><?php echo \esc_html($term->name); ?></option>
                        <?php endforeach;
                    else :
                        // Custom (non-taxonomy) attributes: options are plain strings,
                        // used as-is for both value and label.
                        foreach ($attribute->get_options() as $option) : ?>
                            <option value="<?php echo \esc_attr($option); ?>"><?php echo \esc_html($option); ?></option>
                        <?php endforeach;
                    endif; ?>
                </select>
            <?php endforeach; ?>
            <button type="button" class="button hexwp-variation-filter-reset"><?php echo \esc_html__('Reset', HEX_WP_TEXT_DOMAIN); ?></button>
            <span class="hexwp-variation-filter-count"></span>
        </div>
        <?php
    }

    // Only loads the filter CSS/JS on the product edit screen — no point
    // shipping them to every other wp-admin page.
    public function enqueue_assets($hook) {
        if (!$this->is_enabled()) {
            return;
        }

        $screen = \get_current_screen();
        if (!$screen || 'product' !== $screen->id) {
            return;
        }

        \wp_enqueue_style(
            'hex-wp-variation-filters',
            HEX_WP_PLUGIN_URL . 'assets/css/variation-filters.css',
            [],
            HEX_WP_VERSION
        );

        // Depends on jquery: WooCommerce's own variation panel is jQuery-based
        // and triggers jQuery events (woocommerce_variations_loaded, etc.)
        // that our script listens for, so we use the same library it does.
        \wp_enqueue_script(
            'hex-wp-variation-filters',
            HEX_WP_PLUGIN_URL . 'assets/js/variation-filters.js',
            ['jquery'],
            HEX_WP_VERSION,
            true
        );
    }
}
