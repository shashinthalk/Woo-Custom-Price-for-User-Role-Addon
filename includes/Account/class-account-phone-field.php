<?php

namespace HexWp\Account;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Owns the phone field on My Account > Account details — what it renders
 * and how it saves — backed by the same 'billing_phone' user meta key
 * WooCommerce itself uses at checkout.
 *
 * Deliberately does NOT hook its own render position: 'woocommerce_edit_account_form'
 * actually fires AFTER WooCommerce's password fields (not right after email
 * as an earlier version of this comment claimed), so Account_Extra_Fields
 * now calls render_field() directly from inside its own buffered-and-spliced
 * "right after Email" section instead. Saving isn't position-sensitive, so
 * save_field() still self-hooks normally.
 */
class Account_Phone_Field {
    public function register_hooks() {
        \add_action('woocommerce_save_account_details', [$this, 'save_field']);
    }

    public function render_field() {
        $settings = Settings::get();

        if (empty($settings['account_field_visibility']['phone'])) {
            return;
        }

        $user_id = \get_current_user_id();
        $phone = \get_user_meta($user_id, 'billing_phone', true);
        ?>
        <p class="form-row form-row-wide" id="account_phone_field">
            <label for="account_phone"><?php echo \esc_html($settings['phone_label']); ?></label>
            <input type="tel" class="woocommerce-Input woocommerce-Input--text input-text" name="account_phone" id="account_phone" value="<?php echo \esc_attr($phone); ?>" />
        </p>
        <?php
    }

    public function save_field($user_id) {
        $settings = Settings::get();

        if (empty($settings['account_field_visibility']['phone'])) {
            return;
        }

        if (isset($_POST['account_phone'])) {
            \update_user_meta($user_id, 'billing_phone', \sanitize_text_field(\wp_unslash($_POST['account_phone'])));
        }
    }
}
