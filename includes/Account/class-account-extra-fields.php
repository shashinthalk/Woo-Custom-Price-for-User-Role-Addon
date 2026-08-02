<?php

namespace HexWp\Account;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Positions the Phone field, a read-only Customer Type display, and any
 * admin-defined Custom Fields together on My Account > Account details,
 * directly after the Email field and before the Password section.
 *
 * WooCommerce's classic edit-account template has no hook positioned
 * exactly between Email and the password fields — only
 * 'woocommerce_edit_account_form_start' (before First name) and
 * 'woocommerce_edit_account_form' (after the password fields, right before
 * the submit button). Rather than guess at template internals this plugin
 * doesn't own, everything WooCommerce renders in between is captured in an
 * output buffer and our own markup is spliced in right after the Email
 * field's own closing </p>, found by searching for the stable, well-known
 * #account_email field id. This works regardless of what else renders in
 * between (theme output, other plugins, a future WooCommerce template
 * change), and is the same technique already used for Custom Fields on the
 * registration form's positioning problem — see the commit history for
 * that precedent.
 *
 * Note for future readers: any code that runs inside render_extra_fields()
 * (between start_buffer() and end_buffer_and_inject()) can safely call
 * Settings::get() as many times as needed — that used to be unsafe from
 * inside a gettext filter callback (see Account_Field_Visibility), but this
 * class only ever runs from plain WordPress actions, not filters, so there
 * is no risk of the same recursion there.
 */
class Account_Extra_Fields {
    private $phone_field;

    public function __construct(Account_Phone_Field $phone_field) {
        $this->phone_field = $phone_field;
    }

    public function register_hooks() {
        \add_action('woocommerce_edit_account_form_start', [$this, 'start_buffer']);
        // Priority 20 so this runs after any other default-priority (10)
        // callback still hooked to 'woocommerce_edit_account_form', keeping
        // the buffer window as wide as possible.
        \add_action('woocommerce_edit_account_form', [$this, 'end_buffer_and_inject'], 20);
        \add_action('woocommerce_save_account_details', [$this, 'save_custom_fields']);
    }

    public function start_buffer() {
        \ob_start();
    }

    public function end_buffer_and_inject() {
        $buffered = \ob_get_clean();
        $extra_html = $this->render_extra_fields();

        if ($extra_html === '') {
            echo $buffered;
            return;
        }

        $marker_pos = strpos($buffered, 'account_email');

        // Anchor not found (e.g. a theme/plugin fully overrides this
        // template) — fail safe by appending at the end rather than losing
        // the fields entirely.
        if ($marker_pos === false) {
            echo $buffered . $extra_html;
            return;
        }

        $closing_tag_pos = strpos($buffered, '</p>', $marker_pos);

        if ($closing_tag_pos === false) {
            echo $buffered . $extra_html;
            return;
        }

        $insert_at = $closing_tag_pos + strlen('</p>');
        echo substr($buffered, 0, $insert_at) . $extra_html . substr($buffered, $insert_at);
    }

    private function render_extra_fields() {
        $settings = Settings::get();

        \ob_start();

        // Account_Phone_Field::render_field() already checks
        // account_field_visibility.phone itself and no-ops if it's off.
        $this->phone_field->render_field();

        if (!empty($settings['customer_type_enabled'])) {
            $this->render_customer_type_display($settings);
        }

        foreach ($settings['custom_fields'] as $field) {
            $this->render_custom_field($field);
        }

        return \ob_get_clean();
    }

    // Read-only — this page is for an already-registered customer, not a
    // place to re-pick or change a role after the fact (that's what
    // Registration_Fields::apply_customer_type_role() is for, once, at
    // signup). Shows nothing if the account's current role isn't one
    // available_roles() recognizes (e.g. Administrator, or a role removed
    // since registration) rather than showing a raw, confusing role slug.
    private function render_customer_type_display(array $settings) {
        $user = \wp_get_current_user();
        $roles = Settings::available_roles();
        $current_role_label = '';

        foreach ($user->roles as $role_slug) {
            if (isset($roles[$role_slug])) {
                $current_role_label = $roles[$role_slug];
                break;
            }
        }

        if ($current_role_label === '') {
            return;
        }
        ?>
        <p class="form-row form-row-wide">
            <label><?php echo \esc_html($settings['customer_type_field_label']); ?></label>
            <strong><?php echo \esc_html($current_role_label); ?></strong>
        </p>
        <?php
    }

    private function render_custom_field(array $field) {
        $user_id = \get_current_user_id();
        $name = Registration_Fields::CUSTOM_FIELD_PREFIX . '[' . $field['id'] . ']';
        $id = 'account_' . Registration_Fields::CUSTOM_FIELD_PREFIX . '_' . $field['id'];
        $saved_value = \get_user_meta($user_id, Registration_Fields::CUSTOM_FIELD_META_PREFIX . $field['id'], true);
        ?>
        <p class="form-row form-row-wide">
            <label for="<?php echo \esc_attr($id); ?>">
                <?php echo \esc_html($field['label']); ?>
                <?php if ($field['required']) : ?><span class="required">*</span><?php endif; ?>
            </label>
            <?php if ($field['type'] === 'textarea') : ?>
                <textarea class="woocommerce-Input woocommerce-Input--text input-text" name="<?php echo \esc_attr($name); ?>" id="<?php echo \esc_attr($id); ?>"><?php echo \esc_textarea($saved_value); ?></textarea>
            <?php else : ?>
                <input type="<?php echo \esc_attr($field['type']); ?>" class="woocommerce-Input woocommerce-Input--text input-text" name="<?php echo \esc_attr($name); ?>" id="<?php echo \esc_attr($id); ?>" value="<?php echo \esc_attr($saved_value); ?>" />
            <?php endif; ?>
        </p>
        <?php
    }

    // Not position-sensitive, so this saves independently of the
    // buffer/splice rendering above — same field names (custom_field[{id}])
    // and meta keys (Registration_Fields::CUSTOM_FIELD_META_PREFIX.{id}) as
    // the registration form, so editing here updates the exact same value.
    public function save_custom_fields($user_id) {
        $settings = Settings::get();
        $posted = $_POST[Registration_Fields::CUSTOM_FIELD_PREFIX] ?? [];

        foreach ($settings['custom_fields'] as $field) {
            if (!isset($posted[$field['id']])) {
                continue;
            }

            $raw_value = \wp_unslash($posted[$field['id']]);
            $value = $field['type'] === 'textarea'
                ? \sanitize_textarea_field($raw_value)
                : \sanitize_text_field($raw_value);

            \update_user_meta($user_id, Registration_Fields::CUSTOM_FIELD_META_PREFIX . $field['id'], $value);
        }
    }
}
