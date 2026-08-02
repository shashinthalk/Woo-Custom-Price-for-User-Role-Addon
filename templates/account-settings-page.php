<?php
/** @var array $settings */
/** @var bool $woocommerce_active */

if (!defined('ABSPATH')) {
    exit;
}

// Renders one row of the "Custom Fields" repeater (Registration Form Fields
// card). Used both for each already-saved field and, with a placeholder
// index, inside the <template> that account-settings.js clones for new
// rows — kept as one closure so both stay structurally identical.
$hexwp_render_custom_field_row = function ($index, array $field) {
    $field_types = \HexWp\Account\Settings::CUSTOM_FIELD_TYPES;
    $field_type_labels = [
        'text'     => \__('Text', HEX_WP_TEXT_DOMAIN),
        'email'    => \__('Email', HEX_WP_TEXT_DOMAIN),
        'tel'      => \__('Phone', HEX_WP_TEXT_DOMAIN),
        'number'   => \__('Number', HEX_WP_TEXT_DOMAIN),
        'textarea' => \__('Textarea (multi-line)', HEX_WP_TEXT_DOMAIN),
    ];
    ?>
    <div class="hexwp-repeater-row" data-hexwp-repeater-row>
        <input type="hidden" name="custom_fields[<?php echo \esc_attr($index); ?>][id]" value="<?php echo \esc_attr($field['id']); ?>" />
        <div class="hexwp-repeater-grid">
            <div class="hexwp-field hexwp-field-tight">
                <label><?php echo \esc_html__('Field label', HEX_WP_TEXT_DOMAIN); ?></label>
                <input type="text" name="custom_fields[<?php echo \esc_attr($index); ?>][label]" value="<?php echo \esc_attr($field['label']); ?>" class="regular-text" placeholder="<?php echo \esc_attr__('e.g. Company name', HEX_WP_TEXT_DOMAIN); ?>" />
            </div>
            <div class="hexwp-field hexwp-field-tight">
                <label><?php echo \esc_html__('Type', HEX_WP_TEXT_DOMAIN); ?></label>
                <select name="custom_fields[<?php echo \esc_attr($index); ?>][type]">
                    <?php foreach ($field_types as $type) : ?>
                        <option value="<?php echo \esc_attr($type); ?>" <?php \selected($field['type'], $type); ?>>
                            <?php echo \esc_html($field_type_labels[$type] ?? $type); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <label class="hexwp-subfield-check hexwp-repeater-required">
                <input type="checkbox" name="custom_fields[<?php echo \esc_attr($index); ?>][required]" value="1" <?php \checked(!empty($field['required'])); ?> />
                <?php echo \esc_html__('Required', HEX_WP_TEXT_DOMAIN); ?>
            </label>
            <button type="button" class="button-link-delete hexwp-repeater-remove" data-hexwp-repeater-remove>
                <?php echo \esc_html__('Remove', HEX_WP_TEXT_DOMAIN); ?>
            </button>
        </div>
    </div>
    <?php
};

// Renders a searchable checkbox list for choosing multiple WordPress roles
// (the Customer Type "Include"/"Exclude" pickers) — used instead of a
// native <select multiple>, which needs a ctrl/cmd-click (or shift-click)
// to select more than one option most people don't know about and can't do
// at all on a touch device. See account-settings.js for the search-filter
// behavior; every checkbox stays in the DOM either way, so JS being off
// just means no filtering, not broken submission.
$hexwp_render_role_picker = function ($field_name, array $roles, array $selected) {
    ?>
    <div class="hexwp-role-picker" data-hexwp-role-picker>
        <input type="text" class="hexwp-role-picker-search" data-hexwp-role-picker-search placeholder="<?php echo \esc_attr__('Search roles…', HEX_WP_TEXT_DOMAIN); ?>" />
        <div class="hexwp-role-picker-list">
            <?php foreach ($roles as $role_slug => $role_label) : ?>
                <label class="hexwp-role-picker-item" data-hexwp-role-picker-item>
                    <input type="checkbox" name="<?php echo \esc_attr($field_name); ?>[]" value="<?php echo \esc_attr($role_slug); ?>" <?php \checked(in_array($role_slug, $selected, true)); ?> />
                    <span data-hexwp-role-picker-label><?php echo \esc_html($role_label); ?></span>
                </label>
            <?php endforeach; ?>
            <p class="hexwp-role-picker-empty" data-hexwp-role-picker-empty hidden><?php echo \esc_html__('No roles match your search.', HEX_WP_TEXT_DOMAIN); ?></p>
        </div>
    </div>
    <?php
};

// The real WordPress roles a customer type can be — see
// HexWp\Account\Settings::available_roles() (always excludes 'administrator',
// no matter what).
$hexwp_available_roles = \HexWp\Account\Settings::available_roles();

// The fixed set of My Account > Account details fields an admin can
// individually show/hide — see HexWp\Account\Settings::ACCOUNT_VISIBILITY_FIELDS
// and class-account-field-visibility.php for how "hide" is actually applied.
$hexwp_account_visibility_fields = [
    'first_name'   => \__('First name', HEX_WP_TEXT_DOMAIN),
    'last_name'    => \__('Last name', HEX_WP_TEXT_DOMAIN),
    'display_name' => \__('Display name', HEX_WP_TEXT_DOMAIN),
    'email'        => \__('Email address', HEX_WP_TEXT_DOMAIN),
    'phone'        => \__('Phone', HEX_WP_TEXT_DOMAIN),
    'password'     => \__('Password change', HEX_WP_TEXT_DOMAIN),
];
?>
<div class="wrap hexwp-account-page">
    <div class="hexwp-account-header">
        <div>
            <h1><?php echo \esc_html__('Registration & Account', HEX_WP_TEXT_DOMAIN); ?></h1>
            <p class="hexwp-account-subtitle">
                <?php echo \esc_html__('Each feature below is off by default and only takes effect once you turn it on. Sub-options (which fields, whether CSS/JS load) only apply while the feature\'s own switch is on.', HEX_WP_TEXT_DOMAIN); ?>
            </p>
        </div>
    </div>

    <div id="hexwp-account-notice" class="hexwp-notice" hidden></div>

    <?php if (!$woocommerce_active) : ?>
        <div class="notice notice-warning">
            <p><?php echo \esc_html__('WooCommerce is not active. These settings are saved, but none of these features will run until WooCommerce is installed and active.', HEX_WP_TEXT_DOMAIN); ?></p>
        </div>
    <?php endif; ?>

    <form id="hexwp-account-settings-form" class="hexwp-account-grid">

        <div class="hexwp-card hexwp-card-wide">
            <div class="hexwp-card-header">
                <h2><?php echo \esc_html__('Registration Form Fields', HEX_WP_TEXT_DOMAIN); ?></h2>
                <span class="hexwp-status-pill<?php echo $settings['registration_fields_enabled'] ? ' is-on' : ''; ?>" data-hexwp-status-pill="registration">
                    <?php echo $settings['registration_fields_enabled'] ? \esc_html__('Enabled', HEX_WP_TEXT_DOMAIN) : \esc_html__('Disabled', HEX_WP_TEXT_DOMAIN); ?>
                </span>
            </div>

            <label class="hexwp-switch-row">
                <span class="hexwp-switch">
                    <input type="checkbox" name="registration_fields_enabled" value="1" data-hexwp-toggle="registration" <?php \checked($settings['registration_fields_enabled']); ?> />
                    <span class="hexwp-switch-track"></span>
                </span>
                <span>
                    <strong><?php echo \esc_html__('Add extra fields to the registration form', HEX_WP_TEXT_DOMAIN); ?></strong>
                    <span class="hexwp-field-hint"><?php echo \esc_html__('Adds First name, Last name, and Phone to the WooCommerce registration form (My Account and checkout registration).', HEX_WP_TEXT_DOMAIN); ?></span>
                </span>
            </label>

            <div class="hexwp-subfield-group">
                <div class="hexwp-subfield-row">
                    <span class="hexwp-subfield-name"><?php echo \esc_html__('First name', HEX_WP_TEXT_DOMAIN); ?></span>
                    <label class="hexwp-subfield-check">
                        <input type="checkbox" name="first_name_enabled" value="1" <?php \checked($settings['first_name_enabled']); ?> />
                        <?php echo \esc_html__('Shown', HEX_WP_TEXT_DOMAIN); ?>
                    </label>
                    <label class="hexwp-subfield-check">
                        <input type="checkbox" name="first_name_required" value="1" <?php \checked($settings['first_name_required']); ?> />
                        <?php echo \esc_html__('Required', HEX_WP_TEXT_DOMAIN); ?>
                    </label>
                </div>
                <div class="hexwp-field hexwp-field-tight">
                    <label for="first_name_label"><?php echo \esc_html__('Field label', HEX_WP_TEXT_DOMAIN); ?></label>
                    <input type="text" id="first_name_label" name="first_name_label" class="regular-text" value="<?php echo \esc_attr($settings['first_name_label']); ?>" required />
                </div>

                <div class="hexwp-subfield-row">
                    <span class="hexwp-subfield-name"><?php echo \esc_html__('Last name', HEX_WP_TEXT_DOMAIN); ?></span>
                    <label class="hexwp-subfield-check">
                        <input type="checkbox" name="last_name_enabled" value="1" <?php \checked($settings['last_name_enabled']); ?> />
                        <?php echo \esc_html__('Shown', HEX_WP_TEXT_DOMAIN); ?>
                    </label>
                    <label class="hexwp-subfield-check">
                        <input type="checkbox" name="last_name_required" value="1" <?php \checked($settings['last_name_required']); ?> />
                        <?php echo \esc_html__('Required', HEX_WP_TEXT_DOMAIN); ?>
                    </label>
                </div>
                <div class="hexwp-field hexwp-field-tight">
                    <label for="last_name_label"><?php echo \esc_html__('Field label', HEX_WP_TEXT_DOMAIN); ?></label>
                    <input type="text" id="last_name_label" name="last_name_label" class="regular-text" value="<?php echo \esc_attr($settings['last_name_label']); ?>" required />
                </div>

                <div class="hexwp-subfield-row">
                    <span class="hexwp-subfield-name"><?php echo \esc_html__('Phone', HEX_WP_TEXT_DOMAIN); ?></span>
                    <label class="hexwp-subfield-check">
                        <input type="checkbox" name="phone_enabled" value="1" <?php \checked($settings['phone_enabled']); ?> />
                        <?php echo \esc_html__('Shown', HEX_WP_TEXT_DOMAIN); ?>
                    </label>
                    <label class="hexwp-subfield-check">
                        <input type="checkbox" name="phone_required" value="1" <?php \checked($settings['phone_required']); ?> />
                        <?php echo \esc_html__('Required', HEX_WP_TEXT_DOMAIN); ?>
                    </label>
                </div>
                <div class="hexwp-field hexwp-field-tight">
                    <label for="phone_label"><?php echo \esc_html__('Field label', HEX_WP_TEXT_DOMAIN); ?></label>
                    <input type="text" id="phone_label" name="phone_label" class="regular-text" value="<?php echo \esc_attr($settings['phone_label']); ?>" required />
                </div>
            </div>

            <div class="hexwp-subfield-group">
                <h3 class="hexwp-repeater-heading"><?php echo \esc_html__('Customer Type', HEX_WP_TEXT_DOMAIN); ?></h3>
                <p class="hexwp-field-hint"><?php echo \esc_html__('Lets a new customer pick their type at registration (shown as a dropdown) — whichever role they pick is applied to their account. The dropdown items are your site\'s real WordPress roles. Administrator can never be offered or applied here, no matter what.', HEX_WP_TEXT_DOMAIN); ?></p>

                <div class="hexwp-subfield-row">
                    <span class="hexwp-subfield-name"><?php echo \esc_html__('Customer type field', HEX_WP_TEXT_DOMAIN); ?></span>
                    <label class="hexwp-subfield-check">
                        <input type="checkbox" name="customer_type_enabled" value="1" <?php \checked($settings['customer_type_enabled']); ?> />
                        <?php echo \esc_html__('Shown', HEX_WP_TEXT_DOMAIN); ?>
                    </label>
                    <label class="hexwp-subfield-check">
                        <input type="checkbox" name="customer_type_required" value="1" <?php \checked($settings['customer_type_required']); ?> />
                        <?php echo \esc_html__('Required', HEX_WP_TEXT_DOMAIN); ?>
                    </label>
                </div>

                <div class="hexwp-field">
                    <label><?php echo \esc_html__('Include (offered on the form, applied if selected)', HEX_WP_TEXT_DOMAIN); ?></label>
                    <?php $hexwp_render_role_picker('customer_type_include_roles', $hexwp_available_roles, $settings['customer_type_include_roles']); ?>
                    <span class="hexwp-field-hint"><?php echo \esc_html__('Type to search, then check each role to offer. None checked = the field doesn\'t appear on the form at all, even if "Shown" above is checked.', HEX_WP_TEXT_DOMAIN); ?></span>
                </div>

                <div class="hexwp-field">
                    <label><?php echo \esc_html__('Exclude (account is created as Subscriber instead)', HEX_WP_TEXT_DOMAIN); ?></label>
                    <?php $hexwp_render_role_picker('customer_type_exclude_roles', $hexwp_available_roles, $settings['customer_type_exclude_roles']); ?>
                    <span class="hexwp-field-hint"><?php echo \esc_html__('Checked BEFORE the include list above, so a role checked in both is always treated as excluded. Also applies if the registration form is tampered with to submit a role that was never actually offered.', HEX_WP_TEXT_DOMAIN); ?></span>
                </div>

                <div class="hexwp-field">
                    <label><?php echo \esc_html__('Customize the text customers see', HEX_WP_TEXT_DOMAIN); ?></label>
                    <span class="hexwp-field-hint"><?php echo \esc_html__('This plugin ships no translation file, so if your site isn\'t in English, put your own text here (e.g. German) instead of relying on WordPress to translate it automatically.', HEX_WP_TEXT_DOMAIN); ?></span>
                </div>

                <div class="hexwp-field hexwp-field-tight">
                    <label for="customer_type_field_label"><?php echo \esc_html__('Field label', HEX_WP_TEXT_DOMAIN); ?></label>
                    <input type="text" id="customer_type_field_label" name="customer_type_field_label" class="regular-text" value="<?php echo \esc_attr($settings['customer_type_field_label']); ?>" required />
                </div>

                <div class="hexwp-field hexwp-field-tight">
                    <label for="customer_type_placeholder"><?php echo \esc_html__('Placeholder option (e.g. "-- Select --")', HEX_WP_TEXT_DOMAIN); ?></label>
                    <input type="text" id="customer_type_placeholder" name="customer_type_placeholder" class="regular-text" value="<?php echo \esc_attr($settings['customer_type_placeholder']); ?>" required />
                </div>

                <div class="hexwp-field hexwp-field-tight">
                    <label for="customer_type_required_error"><?php echo \esc_html__('"Required" error message', HEX_WP_TEXT_DOMAIN); ?></label>
                    <input type="text" id="customer_type_required_error" name="customer_type_required_error" class="regular-text" value="<?php echo \esc_attr($settings['customer_type_required_error']); ?>" required />
                </div>
            </div>

            <div class="hexwp-subfield-group">
                <h3 class="hexwp-repeater-heading"><?php echo \esc_html__('Custom Fields', HEX_WP_TEXT_DOMAIN); ?></h3>
                <p class="hexwp-field-hint"><?php echo \esc_html__('Add your own fields — each is saved to its own value on the customer\'s account, separate from First name/Last name/Phone above.', HEX_WP_TEXT_DOMAIN); ?></p>

                <div id="hexwp-custom-fields-list" data-hexwp-repeater-list>
                    <?php foreach ($settings['custom_fields'] as $hexwp_index => $hexwp_field) : ?>
                        <?php $hexwp_render_custom_field_row($hexwp_index, $hexwp_field); ?>
                    <?php endforeach; ?>
                </div>

                <button type="button" class="button hexwp-repeater-add" data-hexwp-repeater-add>
                    <?php echo \esc_html__('+ Add Field', HEX_WP_TEXT_DOMAIN); ?>
                </button>

                <template id="hexwp-custom-field-template">
                    <?php $hexwp_render_custom_field_row('__INDEX__', ['id' => '', 'label' => '', 'type' => 'text', 'required' => false]); ?>
                </template>
            </div>
        </div>

        <div class="hexwp-card hexwp-card-wide">
            <div class="hexwp-card-header">
                <h2><?php echo \esc_html__('Account Details Page Fields', HEX_WP_TEXT_DOMAIN); ?></h2>
            </div>
            <p class="hexwp-field-hint">
                <?php echo \esc_html__('Show or hide each field on My Account > Account details. First name/Last name/Display name/Email/Password are WooCommerce\'s own fields — they can only be hidden, not removed outright, since WooCommerce doesn\'t offer a way to drop them from that form entirely. Phone is ours, so hiding it stops it from being added at all.', HEX_WP_TEXT_DOMAIN); ?>
            </p>

            <div class="hexwp-subfield-group">
                <?php foreach ($hexwp_account_visibility_fields as $hexwp_field_key => $hexwp_field_label) : ?>
                    <div class="hexwp-subfield-row">
                        <span class="hexwp-subfield-name"><?php echo \esc_html($hexwp_field_label); ?></span>
                        <label class="hexwp-subfield-check">
                            <input type="checkbox" name="account_field_visibility[<?php echo \esc_attr($hexwp_field_key); ?>]" value="1" <?php \checked(!empty($settings['account_field_visibility'][$hexwp_field_key])); ?> />
                            <?php echo \esc_html__('Visible', HEX_WP_TEXT_DOMAIN); ?>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="hexwp-card">
            <div class="hexwp-card-header">
                <h2><?php echo \esc_html__('Login / Register Tabs', HEX_WP_TEXT_DOMAIN); ?></h2>
                <span class="hexwp-status-pill<?php echo $settings['login_register_tabs_enabled'] ? ' is-on' : ''; ?>" data-hexwp-status-pill="tabs">
                    <?php echo $settings['login_register_tabs_enabled'] ? \esc_html__('Enabled', HEX_WP_TEXT_DOMAIN) : \esc_html__('Disabled', HEX_WP_TEXT_DOMAIN); ?>
                </span>
            </div>

            <label class="hexwp-switch-row">
                <span class="hexwp-switch">
                    <input type="checkbox" name="login_register_tabs_enabled" value="1" data-hexwp-toggle="tabs" <?php \checked($settings['login_register_tabs_enabled']); ?> />
                    <span class="hexwp-switch-track"></span>
                </span>
                <span>
                    <strong><?php echo \esc_html__('Turn the My Account login/register form into tabs', HEX_WP_TEXT_DOMAIN); ?></strong>
                    <span class="hexwp-field-hint"><?php echo \esc_html__('Adds "Login" / "Register" tab buttons above the My Account login form.', HEX_WP_TEXT_DOMAIN); ?></span>
                </span>
            </label>

            <div class="hexwp-subfield-group">
                <label class="hexwp-switch-row">
                    <span class="hexwp-switch">
                        <input type="checkbox" name="login_register_css_enabled" value="1" <?php \checked($settings['login_register_css_enabled']); ?> />
                        <span class="hexwp-switch-track"></span>
                    </span>
                    <span>
                        <strong><?php echo \esc_html__('Load tab styling (CSS)', HEX_WP_TEXT_DOMAIN); ?></strong>
                        <span class="hexwp-field-hint"><?php echo \esc_html__('Turn off if your theme provides its own styling for these tabs.', HEX_WP_TEXT_DOMAIN); ?></span>
                    </span>
                </label>

                <label class="hexwp-switch-row hexwp-switch-row-spaced">
                    <span class="hexwp-switch">
                        <input type="checkbox" name="login_register_js_enabled" value="1" <?php \checked($settings['login_register_js_enabled']); ?> />
                        <span class="hexwp-switch-track"></span>
                    </span>
                    <span>
                        <strong><?php echo \esc_html__('Load tab switching (JS)', HEX_WP_TEXT_DOMAIN); ?></strong>
                        <span class="hexwp-field-hint"><?php echo \esc_html__('Turn off if you provide your own tab-click handling. Without it, only the Login form is reachable.', HEX_WP_TEXT_DOMAIN); ?></span>
                    </span>
                </label>
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
