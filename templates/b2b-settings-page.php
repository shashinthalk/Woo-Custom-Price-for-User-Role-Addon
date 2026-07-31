<?php
/** @var array $settings */
/** @var array $all_roles */
/** @var bool $woocommerce_active */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap hexwp-b2b-page">
    <div class="hexwp-b2b-header">
        <div>
            <h1><?php echo \esc_html__('B2B Pricing', HEX_WP_TEXT_DOMAIN); ?></h1>
            <p class="hexwp-b2b-subtitle">
                <?php echo \esc_html__('Choose who counts as a B2B customer and how their pricing/tax works. Set the actual B2B price per product on the Pricing tab of each product (or per variation for variable products).', HEX_WP_TEXT_DOMAIN); ?>
            </p>
        </div>
        <span class="hexwp-status-pill<?php echo $settings['enabled'] ? ' is-on' : ''; ?>" data-hexwp-status-pill>
            <?php echo $settings['enabled'] ? \esc_html__('Enabled', HEX_WP_TEXT_DOMAIN) : \esc_html__('Disabled', HEX_WP_TEXT_DOMAIN); ?>
        </span>
    </div>

    <div id="hexwp-b2b-notice" class="hexwp-notice" hidden></div>

    <?php if (!$woocommerce_active) : ?>
        <div class="notice notice-warning">
            <p><?php echo \esc_html__('WooCommerce is not active. These settings are saved, but B2B pricing will not run until WooCommerce is installed and active.', HEX_WP_TEXT_DOMAIN); ?></p>
        </div>
    <?php endif; ?>

    <form id="hexwp-b2b-settings-form" class="hexwp-b2b-grid">

        <div class="hexwp-card">
            <h2><?php echo \esc_html__('General', HEX_WP_TEXT_DOMAIN); ?></h2>
            <label class="hexwp-switch-row">
                <span class="hexwp-switch">
                    <input type="checkbox" name="enabled" value="1" data-hexwp-enabled-toggle <?php \checked($settings['enabled']); ?> />
                    <span class="hexwp-switch-track"></span>
                </span>
                <span>
                    <strong><?php echo \esc_html__('Enable B2B pricing', HEX_WP_TEXT_DOMAIN); ?></strong>
                    <span class="hexwp-field-hint"><?php echo \esc_html__('Off by default so pricing/tax behavior never changes until you explicitly turn it on.', HEX_WP_TEXT_DOMAIN); ?></span>
                </span>
            </label>
        </div>

        <div class="hexwp-card">
            <h2><?php echo \esc_html__('Tax handling', HEX_WP_TEXT_DOMAIN); ?></h2>
            <label class="hexwp-switch-row">
                <span class="hexwp-switch">
                    <input type="checkbox" name="tax_exempt_enabled" value="1" <?php \checked($settings['tax_exempt_enabled']); ?> />
                    <span class="hexwp-switch-track"></span>
                </span>
                <span>
                    <strong><?php echo \esc_html__('Tax exemption', HEX_WP_TEXT_DOMAIN); ?></strong>
                    <span class="hexwp-field-hint"><?php echo \esc_html__('Show prices excluding tax and exempt B2B customers from tax calculation.', HEX_WP_TEXT_DOMAIN); ?></span>
                </span>
            </label>
        </div>

        <div class="hexwp-card">
            <h2><?php echo \esc_html__('Price suffix', HEX_WP_TEXT_DOMAIN); ?></h2>
            <label class="hexwp-switch-row">
                <span class="hexwp-switch">
                    <input type="checkbox" name="price_suffix_enabled" value="1" data-hexwp-suffix-toggle <?php \checked($settings['price_suffix_enabled']); ?> />
                    <span class="hexwp-switch-track"></span>
                </span>
                <span>
                    <strong><?php echo \esc_html__('Override the price suffix', HEX_WP_TEXT_DOMAIN); ?></strong>
                    <span class="hexwp-field-hint"><?php echo \esc_html__('Text shown after every price, e.g. "€ 48,31 zzgl. MwSt."', HEX_WP_TEXT_DOMAIN); ?></span>
                </span>
            </label>

            <div class="hexwp-field">
                <label for="price_suffix_b2b"><?php echo \esc_html__('Suffix for B2B customers', HEX_WP_TEXT_DOMAIN); ?></label>
                <input type="text" id="price_suffix_b2b" name="price_suffix_b2b" class="regular-text" value="<?php echo \esc_attr($settings['price_suffix_b2b']); ?>" data-hexwp-suffix-input data-hexwp-preview-target="hexwp-preview-b2b" />
                <span class="hexwp-preview">€ 48,31<span id="hexwp-preview-b2b"> <?php echo \esc_html($settings['price_suffix_b2b']); ?></span></span>
            </div>

            <div class="hexwp-field">
                <label for="price_suffix_regular"><?php echo \esc_html__('Suffix for regular customers', HEX_WP_TEXT_DOMAIN); ?></label>
                <input type="text" id="price_suffix_regular" name="price_suffix_regular" class="regular-text" value="<?php echo \esc_attr($settings['price_suffix_regular']); ?>" data-hexwp-suffix-input data-hexwp-preview-target="hexwp-preview-regular" />
                <span class="hexwp-preview">€ 48,31<span id="hexwp-preview-regular"> <?php echo \esc_html($settings['price_suffix_regular']); ?></span></span>
            </div>
        </div>

        <div class="hexwp-card">
            <h2><?php echo \esc_html__('Who counts as B2B', HEX_WP_TEXT_DOMAIN); ?></h2>
            <p class="hexwp-field-hint"><?php echo \esc_html__('Logged-in users with any of these roles are treated as B2B customers.', HEX_WP_TEXT_DOMAIN); ?></p>
            <div class="hexwp-chip-group">
                <?php foreach ($all_roles as $role_slug => $role_label) : ?>
                    <label class="hexwp-chip">
                        <input type="checkbox" name="roles[]" value="<?php echo \esc_attr($role_slug); ?>" <?php \checked(in_array($role_slug, $settings['roles'], true)); ?> />
                        <span><?php echo \esc_html($role_label); ?></span>
                    </label>
                <?php endforeach; ?>
            </div>

            <div class="hexwp-field">
                <label for="meta_key"><?php echo \esc_html__('B2B user meta key', HEX_WP_TEXT_DOMAIN); ?></label>
                <input type="text" id="meta_key" name="meta_key" class="regular-text" value="<?php echo \esc_attr($settings['meta_key']); ?>" />
                <span class="hexwp-field-hint"><?php echo \esc_html__("A user is also treated as B2B if this user meta key is set to 'yes' (e.g. via a signup form or another plugin). Leave blank to disable this check.", HEX_WP_TEXT_DOMAIN); ?></span>
            </div>
        </div>

        <div class="hexwp-card">
            <h2><?php echo \esc_html__('Variation filter toolbar', HEX_WP_TEXT_DOMAIN); ?></h2>
            <label class="hexwp-switch-row">
                <span class="hexwp-switch">
                    <input type="checkbox" name="variation_filter_enabled" value="1" <?php \checked($settings['variation_filter_enabled']); ?> />
                    <span class="hexwp-switch-track"></span>
                </span>
                <span>
                    <strong><?php echo \esc_html__('Show the "Filter variations by..." toolbar', HEX_WP_TEXT_DOMAIN); ?></strong>
                    <span class="hexwp-field-hint"><?php echo \esc_html__('Adds dropdown filters above the Variations panel on the product edit screen, so products with many variations are easier to search through. Editing convenience only — does not affect pricing or the storefront.', HEX_WP_TEXT_DOMAIN); ?></span>
                </span>
            </label>
        </div>

        <div class="hexwp-card">
            <h2><?php echo \esc_html__('B2B stock message', HEX_WP_TEXT_DOMAIN); ?></h2>
            <p class="hexwp-field-hint">
                <?php echo \esc_html__('Set a quantity limit per product on the Inventory tab.', HEX_WP_TEXT_DOMAIN); ?>
            </p>

            <div class="hexwp-field">
                <label for="b2b_stock_message"><?php echo \esc_html__('Message', HEX_WP_TEXT_DOMAIN); ?></label>
                <input type="text" id="b2b_stock_message" name="b2b_stock_message" class="regular-text" value="<?php echo \esc_attr($settings['b2b_stock_message']); ?>" />
            </div>
        </div>

        <div class="hexwp-save-bar">
            <button type="submit" class="button button-primary hexwp-save-button">
                <?php echo \esc_html__('Save Settings', HEX_WP_TEXT_DOMAIN); ?>
            </button>
            <span class="hexwp-save-status" data-hexwp-save-status></span>
        </div>
    </form>
</div>
