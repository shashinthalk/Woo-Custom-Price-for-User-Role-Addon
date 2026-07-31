<?php

namespace HexWp\WooCommerce;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Single source of truth for B2B pricing configuration.
 * Stored as one option array so the settings page has one thing to read/write.
 */
class Settings {
    // The single wp_options row everything below is stored in.
    const OPTION_NAME = 'hex_wp_b2b_pricing_settings';

    // Shared post/variation meta key that stores the B2B price.
    const PRICE_META_KEY = '_b2b_price';

    // Per-product meta keys set in the Inventory tab (see Stock_Field).
    // These are independent of WooCommerce's own stock management —
    // just a B2B order-quantity threshold and an on/off switch for it.
    const STOCK_META_KEY = '_hexwp_b2b_manage_stock'; // 'yes'/'no'
    const STOCK_QTY_META_KEY = '_hexwp_b2b_stock_qty'; // number

    // Fallback values used until an admin saves the settings page,
    // and to fill in any field a saved option might be missing.
    //
    // Deliberately no __()/translation calls in here: defaults() is called
    // from Settings::get(), which can run very early (e.g. during
    // 'plugins_loaded', before WordPress's 'init' hook). Calling a
    // translation function that early triggers a WordPress "translation
    // loaded too early" notice. These suffix values are just an editable
    // starting point for the admin anyway, not fixed UI text, so plain
    // strings are the right call here regardless.
    public static function defaults() {
        return [
            'enabled'              => false, // feature is off until an admin turns it on
            'roles'                => ['administrator'],
            'meta_key'             => 'is_b2b',
            'tax_exempt_enabled'   => true,
            'price_suffix_enabled' => false,
            'price_suffix_b2b'     => 'zzgl. MwSt.',
            'price_suffix_regular' => 'inkl. MwSt.',
            // Just an editing convenience on the product screen, doesn't affect
            // pricing/tax, so it's safe to default this one to on.
            'variation_filter_enabled' => true,
            // Shown below the price when a B2B customer requests more than
            // a product's B2B quantity limit (Inventory tab).
            'b2b_stock_message' => 'This quantity exceeds our standard limit.',
        ];
    }

    // Reads the saved settings from the database and fills in any missing
    // keys with defaults() so callers never have to check isset() themselves.
    public static function get() {
        $saved = \get_option(self::OPTION_NAME, []);

        if (!is_array($saved)) {
            $saved = [];
        }

        return \wp_parse_args($saved, self::defaults());
    }

    /**
     * Sanitizes and persists settings. All validation lives here so every
     * caller (currently just the admin settings page) gets the same rules.
     */
    public static function update(array $raw) {
        $defaults = self::defaults();

        // Only keep role slugs that actually exist as WordPress roles,
        // so a tampered/odd request can't store junk values here.
        $roles = [];
        if (!empty($raw['roles']) && is_array($raw['roles'])) {
            $valid_roles = array_keys(\wp_roles()->roles);
            foreach ($raw['roles'] as $role) {
                $role = \sanitize_key($role);
                if (in_array($role, $valid_roles, true)) {
                    $roles[] = $role;
                }
            }
        }

        // Checkboxes just need to exist to count as "checked"; everything
        // else gets WordPress's standard sanitization for that data type.
        $sanitized = [
            'enabled'              => !empty($raw['enabled']),
            'roles'                => $roles,
            'meta_key'             => isset($raw['meta_key']) ? \sanitize_key($raw['meta_key']) : $defaults['meta_key'],
            'tax_exempt_enabled'   => !empty($raw['tax_exempt_enabled']),
            'price_suffix_enabled' => !empty($raw['price_suffix_enabled']),
            'price_suffix_b2b'     => isset($raw['price_suffix_b2b']) ? \sanitize_text_field($raw['price_suffix_b2b']) : $defaults['price_suffix_b2b'],
            'price_suffix_regular' => isset($raw['price_suffix_regular']) ? \sanitize_text_field($raw['price_suffix_regular']) : $defaults['price_suffix_regular'],
            'variation_filter_enabled' => !empty($raw['variation_filter_enabled']),
            'b2b_stock_message'        => isset($raw['b2b_stock_message']) ? \sanitize_text_field($raw['b2b_stock_message']) : $defaults['b2b_stock_message'],
        ];

        return \update_option(self::OPTION_NAME, $sanitized);
    }
}
