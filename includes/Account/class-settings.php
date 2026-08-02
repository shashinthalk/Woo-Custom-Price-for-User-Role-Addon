<?php

namespace HexWp\Account;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Single source of truth for the registration-fields / customer-type-roles /
 * account-field-visibility / login-register-tabs settings. Stored as one
 * option array, same pattern as HexWp\WooCommerce\Settings.
 */
class Settings {
    const OPTION_NAME = 'hex_wp_account_settings';

    // Field types an admin can pick for a dynamic registration field.
    const CUSTOM_FIELD_TYPES = ['text', 'email', 'tel', 'number', 'textarea'];

    // The fixed set of fields WooCommerce itself renders on My Account >
    // Account details (plus our own 'phone'), each independently hideable.
    const ACCOUNT_VISIBILITY_FIELDS = ['first_name', 'last_name', 'display_name', 'email', 'phone', 'password'];

    /**
     * The complete set of WordPress roles a "customer type" can ever be —
     * i.e. every role currently registered on this site, EXCEPT
     * 'administrator', which is removed here unconditionally and cannot be
     * added back by any admin setting. This is the single, hard-coded place
     * that guarantee lives: since 'administrator' never even appears in the
     * pool an admin can choose from (for either the include or the exclude
     * list — see sanitize_role_list()), there is no configuration, typo, or
     * tampered request that can result in a self-registered admin account.
     *
     * Reads live from wp_roles(), not a fixed list we invented, so it
     * reflects whatever roles actually exist on this install (including
     * custom roles added by other plugins) — this is what "real user
     * types" means for this feature, replacing the earlier fixed catalog.
     * Labels go through translate_user_role(), matching how WordPress core
     * displays role names everywhere else (e.g. the Users list table).
     *
     * Safe to call from any of this class's actual call sites (registration
     * hooks, the admin settings page) since none of them fire before
     * 'init', by which point wp_roles() is safe to use.
     */
    public static function available_roles() {
        $roles = [];

        foreach (\wp_roles()->get_names() as $slug => $name) {
            if ($slug === 'administrator') {
                continue;
            }
            $roles[$slug] = \translate_user_role($name);
        }

        return $roles;
    }

    // Deliberately no __()/translation calls in here — defaults() is read
    // from Settings::get(), which feature classes call from inside hook
    // callbacks that can fire as early as 'plugins_loaded' (before 'init'),
    // and translating that early triggers a WordPress "translation loaded
    // too early" notice. See includes/WooCommerce/class-variation-filter-toolbar.php
    // for the same rule applied to an existing feature.
    public static function defaults() {
        return [
            // Extra fields on the WooCommerce registration form. Labels are
            // admin-editable free text (not __()) for the same reason as the
            // customer-type text below — no .po/.mo translation catalog
            // ships with this plugin, so an admin needing e.g. German just
            // types it in directly.
            'registration_fields_enabled' => false,
            'first_name_enabled'  => true,
            'first_name_required' => true,
            'first_name_label'    => 'First name',
            'last_name_enabled'   => true,
            'last_name_required'  => true,
            'last_name_label'     => 'Last name',
            'phone_enabled'       => true,
            'phone_required'      => true,
            'phone_label'         => 'Phone',

            // Admin-defined extra registration fields, on top of the 3 above.
            // Each row: ['id' => string, 'label' => string, 'type' => string, 'required' => bool].
            'custom_fields' => [],

            // Lets a registrant pick their customer type, which is applied
            // to their new account as an actual WordPress role (see
            // available_roles() above and Registration_Fields::apply_customer_type_role()).
            // 'customer_type_include_roles' is offered on the registration
            // dropdown AND applied if selected; 'customer_type_exclude_roles'
            // is checked FIRST and forces the account to 'subscriber'
            // instead, no matter what — so a role that ends up in both
            // lists (e.g. an admin mistake) is always treated as excluded.
            // Anything not in either list (blank, unrecognized, or a role
            // the admin never configured either way) also falls back to
            // 'subscriber'. Both empty by default, so the field renders
            // nothing (and is therefore inert) until an admin explicitly
            // picks at least one include role.
            //
            // The text below is admin-editable free text rather than
            // translated via __() — this plugin ships no .po/.mo translation
            // catalog, so an admin who needs this in German (or anything
            // other than English) can just type it in directly instead of
            // depending on translation infrastructure that isn't there.
            'customer_type_enabled'        => false,
            'customer_type_required'       => false,
            'customer_type_include_roles'  => [],
            'customer_type_exclude_roles'  => [],
            'customer_type_field_label'    => 'Customer type',
            'customer_type_placeholder'    => '-- Select --',
            'customer_type_required_error' => 'Please select a customer type.',

            // Which fields show on My Account > Account details. Everything
            // WooCommerce itself already places there (first/last/display
            // name, email, password) can only be hidden via CSS, since core
            // doesn't expose a per-field filter for that template — see
            // class-account-field-visibility.php. 'phone' is entirely ours,
            // so it's a true hide (Account_Phone_Field just won't render it).
            'account_field_visibility' => [
                'first_name'   => true,
                'last_name'    => true,
                'display_name' => true,
                'email'        => true,
                'phone'        => true,
                'password'     => true,
            ],

            // Tabbed Login/Register layout on the My Account page.
            'login_register_tabs_enabled' => false,
            'login_register_css_enabled'  => true,
            'login_register_js_enabled'   => true,
        ];
    }

    // Reads the saved settings and fills in any missing keys with defaults()
    // so callers never have to check isset() themselves.
    public static function get() {
        $saved = \get_option(self::OPTION_NAME, []);

        if (!is_array($saved)) {
            $saved = [];
        }

        $settings = \wp_parse_args($saved, self::defaults());

        // wp_parse_args() only merges top-level keys, so a saved
        // 'account_field_visibility' missing a key that got added to
        // defaults() later (e.g. after an update) would silently drop it.
        // Merge this one sub-array explicitly so every field always has a
        // value.
        $settings['account_field_visibility'] = \wp_parse_args(
            is_array($settings['account_field_visibility']) ? $settings['account_field_visibility'] : [],
            self::defaults()['account_field_visibility']
        );

        if (!is_array($settings['custom_fields'])) {
            $settings['custom_fields'] = [];
        }

        // Re-filter both role lists against the CURRENT set of real roles on
        // every read, not just on save — a role could be removed (by this
        // site or another plugin) after being saved here, and 'administrator'
        // is stripped unconditionally regardless of what was ever saved, on
        // every read, not just at sanitize time.
        $current_roles = array_keys(self::available_roles());
        $settings['customer_type_include_roles'] = is_array($settings['customer_type_include_roles'])
            ? array_values(array_intersect($settings['customer_type_include_roles'], $current_roles))
            : [];
        $settings['customer_type_exclude_roles'] = is_array($settings['customer_type_exclude_roles'])
            ? array_values(array_intersect($settings['customer_type_exclude_roles'], $current_roles))
            : [];

        return $settings;
    }

    /**
     * Sanitizes and persists settings. All validation lives here so every
     * caller (currently just the admin settings page) gets the same rules.
     */
    public static function update(array $raw) {
        $defaults = self::defaults();

        $sanitized = [
            'registration_fields_enabled' => !empty($raw['registration_fields_enabled']),
            'first_name_enabled'  => !empty($raw['first_name_enabled']),
            'first_name_required' => !empty($raw['first_name_required']),
            'first_name_label'    => isset($raw['first_name_label']) ? \sanitize_text_field($raw['first_name_label']) : $defaults['first_name_label'],
            'last_name_enabled'   => !empty($raw['last_name_enabled']),
            'last_name_required'  => !empty($raw['last_name_required']),
            'last_name_label'     => isset($raw['last_name_label']) ? \sanitize_text_field($raw['last_name_label']) : $defaults['last_name_label'],
            'phone_enabled'       => !empty($raw['phone_enabled']),
            'phone_required'      => !empty($raw['phone_required']),
            'phone_label'         => isset($raw['phone_label']) ? \sanitize_text_field($raw['phone_label']) : $defaults['phone_label'],

            'custom_fields' => self::sanitize_custom_fields(!empty($raw['custom_fields']) && is_array($raw['custom_fields']) ? $raw['custom_fields'] : []),

            'customer_type_enabled'        => !empty($raw['customer_type_enabled']),
            'customer_type_required'       => !empty($raw['customer_type_required']),
            'customer_type_include_roles'  => self::sanitize_role_list(!empty($raw['customer_type_include_roles']) && is_array($raw['customer_type_include_roles']) ? $raw['customer_type_include_roles'] : []),
            'customer_type_exclude_roles'  => self::sanitize_role_list(!empty($raw['customer_type_exclude_roles']) && is_array($raw['customer_type_exclude_roles']) ? $raw['customer_type_exclude_roles'] : []),
            'customer_type_field_label'    => isset($raw['customer_type_field_label']) ? \sanitize_text_field($raw['customer_type_field_label']) : $defaults['customer_type_field_label'],
            'customer_type_placeholder'    => isset($raw['customer_type_placeholder']) ? \sanitize_text_field($raw['customer_type_placeholder']) : $defaults['customer_type_placeholder'],
            'customer_type_required_error' => isset($raw['customer_type_required_error']) ? \sanitize_text_field($raw['customer_type_required_error']) : $defaults['customer_type_required_error'],

            'account_field_visibility' => self::sanitize_account_field_visibility(!empty($raw['account_field_visibility']) && is_array($raw['account_field_visibility']) ? $raw['account_field_visibility'] : []),

            'login_register_tabs_enabled' => !empty($raw['login_register_tabs_enabled']),
            'login_register_css_enabled'  => !empty($raw['login_register_css_enabled']),
            'login_register_js_enabled'   => !empty($raw['login_register_js_enabled']),
        ];

        return \update_option(self::OPTION_NAME, $sanitized);
    }

    /**
     * Whitelist filter shared by both customer_type_include_roles and
     * customer_type_exclude_roles. Every submitted value is sanitize_key()'d
     * and then must exactly match a role slug already present in
     * available_roles() — which is itself always missing 'administrator' —
     * so this method cannot store 'administrator' (or any role that
     * doesn't currently exist) into either list under any circumstances,
     * admin typo or tampered admin-ajax request alike.
     */
    private static function sanitize_role_list(array $raw) {
        $valid_roles = array_keys(self::available_roles());
        $sanitized = [];

        foreach ($raw as $role) {
            $role = \sanitize_key($role);
            if (in_array($role, $valid_roles, true) && !in_array($role, $sanitized, true)) {
                $sanitized[] = $role;
            }
        }

        return $sanitized;
    }

    // Only keeps the 6 known keys, each cast to a plain bool.
    private static function sanitize_account_field_visibility(array $raw) {
        $sanitized = [];

        foreach (self::ACCOUNT_VISIBILITY_FIELDS as $field) {
            $sanitized[$field] = !empty($raw[$field]);
        }

        return $sanitized;
    }

    /**
     * Sanitizes the admin-submitted repeater rows for custom registration
     * fields. Each raw row is expected to look like
     * ['id' => string, 'label' => string, 'type' => string, 'required' => bool|string].
     *
     * A row with no usable label is dropped entirely (an empty repeater row
     * left over from the admin UI, not a real field). Every kept row gets a
     * stable, unique 'id' — reused from the row if it already had one
     * (an existing field being edited), otherwise derived from the label
     * so meta keys stay readable instead of random.
     */
    private static function sanitize_custom_fields(array $raw_rows) {
        $sanitized = [];
        $used_ids = [];

        foreach ($raw_rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $label = isset($row['label']) ? \sanitize_text_field($row['label']) : '';
            if ($label === '') {
                continue; // blank row, nothing to save
            }

            $type = isset($row['type']) && in_array($row['type'], self::CUSTOM_FIELD_TYPES, true)
                ? $row['type']
                : 'text';

            $id = isset($row['id']) ? \sanitize_key($row['id']) : '';
            if ($id === '') {
                $id = \sanitize_title($label);
            }
            if ($id === '') {
                $id = 'field'; // label was made entirely of characters sanitize_title() strips
            }

            // Keep ids unique within this save even if two labels collide
            // (e.g. two fields both named "Notes").
            $unique_id = $id;
            $suffix = 2;
            while (in_array($unique_id, $used_ids, true)) {
                $unique_id = $id . '-' . $suffix;
                $suffix++;
            }
            $used_ids[] = $unique_id;

            $sanitized[] = [
                'id'       => $unique_id,
                'label'    => $label,
                'type'     => $type,
                'required' => !empty($row['required']),
            ];
        }

        return $sanitized;
    }
}
