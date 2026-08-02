<?php

namespace HexWp\Account;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Adds First name / Last name / Phone / Customer type fields to the
 * WooCommerce registration form, plus any admin-defined custom fields
 * (Settings::get()['custom_fields']). Each of the 3 built-in text fields has
 * its own "shown at all" and "required" switch; custom fields carry the
 * same two settings per row, configured on the admin settings page instead
 * of hardcoded here.
 *
 * Customer type is a dropdown of real WordPress roles (Settings::available_roles(),
 * which always excludes 'administrator') — whichever role the registrant
 * picks is APPLIED to their new account, following the admin's
 * include/exclude configuration. See apply_customer_type_role() for the
 * exact rule and the security reasoning behind it.
 */
class Registration_Fields {
    // Custom field POST values arrive as custom_field[{id}] so they can
    // never collide with the 3 built-in field names (first_name/last_name/phone)
    // or with WooCommerce's own registration form fields.
    const CUSTOM_FIELD_PREFIX = 'custom_field';

    // User meta key prefix for custom fields, so they never collide with a
    // core/plugin meta key that happens to share the same id.
    const CUSTOM_FIELD_META_PREFIX = 'hexwp_regfield_';

    // POST field name for the customer-type dropdown. The role actually
    // applied to the account is stored as informational user meta under
    // this key too, purely for the admin's own record-keeping (e.g. what
    // was originally selected before an exclude-list downgrade) — it is
    // never read back for any security decision.
    const CUSTOMER_TYPE_FIELD = 'customer_type';
    const CUSTOMER_TYPE_META_KEY = 'hexwp_customer_type';

    // Applied whenever the submitted role is excluded, unrecognized, or
    // simply wasn't offered — WordPress's own lowest-privilege built-in
    // role, guaranteed to exist on every install.
    const FALLBACK_ROLE = 'subscriber';

    public function register_hooks() {
        \add_action('woocommerce_register_form', [$this, 'render_fields']);
        \add_filter('woocommerce_registration_errors', [$this, 'validate_fields'], 10, 3);
        \add_action('woocommerce_created_customer', [$this, 'save_fields']);
    }

    public function render_fields() {
        $settings = Settings::get();

        if (!$settings['registration_fields_enabled']) {
            return;
        }

        if ($settings['first_name_enabled']) {
            $this->render_field('first_name', 'reg_first_name', 'text', $settings['first_name_label'], $settings['first_name_required']);
        }

        if ($settings['last_name_enabled']) {
            $this->render_field('last_name', 'reg_last_name', 'text', $settings['last_name_label'], $settings['last_name_required']);
        }

        if ($settings['phone_enabled']) {
            $this->render_field('phone', 'reg_phone', 'tel', $settings['phone_label'], $settings['phone_required']);
        }

        if ($settings['customer_type_enabled']) {
            $this->render_customer_type_field($settings);
        }

        foreach ($settings['custom_fields'] as $field) {
            $this->render_custom_field($field);
        }
    }

    /**
     * Returns [role slug => display label] for only the roles the admin has
     * put in 'customer_type_include_roles'. This is what the dropdown
     * offers — it is NOT the full security check for what gets applied
     * (that's apply_customer_type_role(), which also consults the exclude
     * list), it's just "what's shown". Settings::available_roles() already
     * guarantees 'administrator' can never appear here.
     */
    private function get_offered_roles(array $settings) {
        $all_roles = Settings::available_roles();
        $offered = [];

        foreach ($settings['customer_type_include_roles'] as $role) {
            if (isset($all_roles[$role])) {
                $offered[$role] = $all_roles[$role];
            }
        }

        return $offered;
    }

    private function render_customer_type_field(array $settings) {
        $offered_roles = $this->get_offered_roles($settings);

        if (empty($offered_roles)) {
            return; // admin turned the field on but hasn't included any roles yet — nothing to offer
        }

        // Re-fill the selection if the form is being redisplayed after a
        // validation error. This is just for the HTML value, not a security
        // boundary — apply_customer_type_role() re-derives the actual role
        // from $_POST independently at save time.
        $selected = !empty($_POST[self::CUSTOMER_TYPE_FIELD]) ? \sanitize_key(\wp_unslash($_POST[self::CUSTOMER_TYPE_FIELD])) : '';
        ?>
        <p class="form-row form-row-wide">
            <label for="reg_customer_type">
                <?php echo \esc_html($settings['customer_type_field_label']); ?>
                <?php if ($settings['customer_type_required']) : ?><span class="required">*</span><?php endif; ?>
            </label>
            <select class="input-text" name="<?php echo \esc_attr(self::CUSTOMER_TYPE_FIELD); ?>" id="reg_customer_type">
                <option value=""><?php echo \esc_html($settings['customer_type_placeholder']); ?></option>
                <?php foreach ($offered_roles as $role => $label) : ?>
                    <option value="<?php echo \esc_attr($role); ?>" <?php \selected($selected, $role); ?>><?php echo \esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
        </p>
        <?php
    }

    private function render_field($name, $id, $type, $label, $required) {
        // Re-fill the field with whatever was submitted if the form is
        // being redisplayed after a validation error.
        $value = !empty($_POST[$name]) ? \esc_attr(\wp_unslash($_POST[$name])) : '';
        ?>
        <p class="form-row form-row-wide">
            <label for="<?php echo \esc_attr($id); ?>">
                <?php echo \esc_html($label); ?>
                <?php if ($required) : ?><span class="required">*</span><?php endif; ?>
            </label>
            <input type="<?php echo \esc_attr($type); ?>" class="input-text" name="<?php echo \esc_attr($name); ?>" id="<?php echo \esc_attr($id); ?>" value="<?php echo $value; ?>" />
        </p>
        <?php
    }

    private function render_custom_field(array $field) {
        $name = self::CUSTOM_FIELD_PREFIX . '[' . $field['id'] . ']';
        $id = 'reg_' . self::CUSTOM_FIELD_PREFIX . '_' . $field['id'];
        $posted = $_POST[self::CUSTOM_FIELD_PREFIX] ?? [];
        $value = !empty($posted[$field['id']]) ? \esc_attr(\wp_unslash($posted[$field['id']])) : '';
        ?>
        <p class="form-row form-row-wide">
            <label for="<?php echo \esc_attr($id); ?>">
                <?php echo \esc_html($field['label']); ?>
                <?php if ($field['required']) : ?><span class="required">*</span><?php endif; ?>
            </label>
            <?php if ($field['type'] === 'textarea') : ?>
                <textarea class="input-text" name="<?php echo \esc_attr($name); ?>" id="<?php echo \esc_attr($id); ?>"><?php echo $value; ?></textarea>
            <?php else : ?>
                <input type="<?php echo \esc_attr($field['type']); ?>" class="input-text" name="<?php echo \esc_attr($name); ?>" id="<?php echo \esc_attr($id); ?>" value="<?php echo $value; ?>" />
            <?php endif; ?>
        </p>
        <?php
    }

    public function validate_fields($errors, $username, $email) {
        $settings = Settings::get();

        if (!$settings['registration_fields_enabled']) {
            return $errors;
        }

        if ($settings['first_name_enabled'] && $settings['first_name_required'] && empty($_POST['first_name'])) {
            $errors->add('first_name_error', \__('First name is required.', HEX_WP_TEXT_DOMAIN));
        }

        if ($settings['last_name_enabled'] && $settings['last_name_required'] && empty($_POST['last_name'])) {
            $errors->add('last_name_error', \__('Last name is required.', HEX_WP_TEXT_DOMAIN));
        }

        if ($settings['phone_enabled'] && $settings['phone_required'] && empty($_POST['phone'])) {
            $errors->add('phone_error', \__('Phone number is required.', HEX_WP_TEXT_DOMAIN));
        }

        // Only a presence check here — unlike the other fields, there's no
        // "invalid selection" rejection for customer_type: whatever gets
        // submitted, apply_customer_type_role() always resolves it to a
        // safe, real role (falling back to Subscriber for anything not on
        // the admin's include list), so there's nothing here that needs to
        // block registration except the registrant leaving it blank.
        if ($settings['customer_type_enabled'] && $settings['customer_type_required'] && empty($_POST[self::CUSTOMER_TYPE_FIELD])) {
            $errors->add('customer_type_error', $settings['customer_type_required_error']);
        }

        $posted = $_POST[self::CUSTOM_FIELD_PREFIX] ?? [];
        foreach ($settings['custom_fields'] as $field) {
            if ($field['required'] && empty($posted[$field['id']])) {
                /* translators: %s: the admin-configured field label, e.g. "Company name" */
                $errors->add(
                    'custom_field_' . $field['id'] . '_error',
                    sprintf(\__('%s is required.', HEX_WP_TEXT_DOMAIN), $field['label'])
                );
            }
        }

        return $errors;
    }

    public function save_fields($customer_id) {
        $settings = Settings::get();

        if (!$settings['registration_fields_enabled']) {
            return;
        }

        if ($settings['first_name_enabled'] && isset($_POST['first_name'])) {
            \update_user_meta($customer_id, 'first_name', \sanitize_text_field(\wp_unslash($_POST['first_name'])));
        }

        if ($settings['last_name_enabled'] && isset($_POST['last_name'])) {
            \update_user_meta($customer_id, 'last_name', \sanitize_text_field(\wp_unslash($_POST['last_name'])));
        }

        if ($settings['phone_enabled'] && isset($_POST['phone'])) {
            \update_user_meta($customer_id, 'billing_phone', \sanitize_text_field(\wp_unslash($_POST['phone'])));
        }

        if ($settings['customer_type_enabled']) {
            $this->apply_customer_type_role($customer_id, $settings);
        }

        $posted = $_POST[self::CUSTOM_FIELD_PREFIX] ?? [];
        foreach ($settings['custom_fields'] as $field) {
            if (!isset($posted[$field['id']])) {
                continue;
            }

            $raw_value = \wp_unslash($posted[$field['id']]);
            $value = $field['type'] === 'textarea'
                ? \sanitize_textarea_field($raw_value)
                : \sanitize_text_field($raw_value);

            \update_user_meta($customer_id, self::CUSTOM_FIELD_META_PREFIX . $field['id'], $value);
        }
    }

    /**
     * Resolves the submitted customer_type value to an actual WordPress
     * role and applies it to the new account, per the admin's
     * include/exclude configuration:
     *
     *   1. If the submission matches a role in 'customer_type_exclude_roles',
     *      the account becomes self::FALLBACK_ROLE ('subscriber') — no
     *      matter what, checked first.
     *   2. Else if it matches a role in 'customer_type_include_roles', that
     *      role is applied as submitted.
     *   3. Else (blank, unrecognized, tampered/injected, or simply a role
     *      the admin never put in either list) — self::FALLBACK_ROLE again.
     *
     * 'administrator' can never be the outcome of this method: it's already
     * stripped from both role lists as early as Settings::get() (so step 1
     * and step 2 can never match it), and the explicit check in step 2
     * below is one more layer of defense in depth on top of that — the
     * account being registered here always ends up regular Subscriber or
     * one of the admin's own explicitly-approved include roles, never more.
     *
     * The original submitted value (before any of the above resolution) is
     * also recorded as plain user meta (self::CUSTOMER_TYPE_META_KEY) —
     * informational only, e.g. so an admin can see someone tried to
     * register as a role that got excluded — never read back for any
     * security decision.
     *
     * A BLANK submission (the field is optional and left unset) does
     * nothing at all here — the account simply keeps whatever role
     * WooCommerce's own registration handler already gave it. Only an
     * actual, non-empty submission ever triggers a role change; leaving
     * the field empty isn't itself a signal to downgrade the account.
     */
    private function apply_customer_type_role($customer_id, array $settings) {
        $submitted = isset($_POST[self::CUSTOMER_TYPE_FIELD]) ? \sanitize_key(\wp_unslash($_POST[self::CUSTOMER_TYPE_FIELD])) : '';

        if ($submitted === '') {
            return;
        }

        \update_user_meta($customer_id, self::CUSTOMER_TYPE_META_KEY, $submitted);

        if (in_array($submitted, $settings['customer_type_exclude_roles'], true)) {
            $role = self::FALLBACK_ROLE;
        } elseif ($submitted !== 'administrator' && in_array($submitted, $settings['customer_type_include_roles'], true)) {
            $role = $submitted;
        } else {
            $role = self::FALLBACK_ROLE;
        }

        $user = new \WP_User($customer_id);
        $user->set_role($role);
    }
}
