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

    // Fallback values used until an admin saves the settings page,
    // and to fill in any field a saved option might be missing.
    public static function defaults() {
        return [
            'enabled'              => false, // feature is off until an admin turns it on
            'roles'                => ['administrator'],
            'meta_key'             => 'is_b2b',
            'tax_exempt_enabled'   => true,
            'price_suffix_enabled' => false,
            'price_suffix_b2b'     => \__('zzgl. MwSt.', HEX_WP_TEXT_DOMAIN),
            'price_suffix_regular' => \__('inkl. MwSt.', HEX_WP_TEXT_DOMAIN),
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
        ];

        return \update_option(self::OPTION_NAME, $sanitized);
    }
}
