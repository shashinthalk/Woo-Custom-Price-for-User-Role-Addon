<?php

namespace HexWp\WooCommerce;

if (!defined('ABSPATH')) {
    exit;
}

class Customer {
    /**
     * A user counts as B2B if the feature is enabled and either their role
     * or their user meta flag matches what's configured in Settings.
     */
    public static function is_b2b(?\WP_User $user = null) {
        $settings = Settings::get();

        // Feature switched off, or nobody logged in: never treat as B2B.
        if (empty($settings['enabled']) || !\is_user_logged_in()) {
            return false;
        }

        // Default to whoever is browsing right now if no specific user was passed in.
        $user = $user ?: \wp_get_current_user();

        // Check 1: does the user have one of the configured B2B roles?
        if (!empty($settings['roles']) && array_intersect($settings['roles'], (array) $user->roles)) {
            return true;
        }

        // Check 2: does the user have the configured "is B2B" user-meta flag set to 'yes'?
        if (!empty($settings['meta_key'])) {
            $flag = \get_user_meta($user->ID, $settings['meta_key'], true);
            if ($flag === 'yes') {
                return true;
            }
        }

        return false;
    }
}
