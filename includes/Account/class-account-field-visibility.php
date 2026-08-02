<?php

namespace HexWp\Account;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Hides whichever My Account > Account details fields an admin has turned
 * off, and overrides WooCommerce's own First name / Last name field labels
 * with the admin's customized text (first_name_label/last_name_label —
 * the same settings used for the registration form's labels, so the two
 * pages stay in sync automatically).
 *
 * Our own 'phone' field is entirely ours (Account_Phone_Field simply
 * doesn't render it when off, and reads its label straight from
 * $settings['phone_label'] — no filter needed there), but WooCommerce's
 * own first name / last name / display name / email / password fields are
 * hardcoded into its form-edit-account.php template with no per-field
 * filter to remove or relabel them directly, so the only way to affect
 * them without overriding that whole template is CSS (for hiding) and a
 * scoped 'gettext' filter (for the label text). The field is still
 * technically present in the DOM and still posts its existing value on
 * save when hidden (a no-op), it just isn't shown or editable — same
 * trade-off documented on the settings page itself.
 */
class Account_Field_Visibility {
    // Maps a visibility setting key to the CSS selector(s) for the form
    // row(s) that field lives in. WooCommerce always gives the underlying
    // <input> a fixed id, so :has() lets us hide the wrapping <p> without
    // needing to know its exact class list.
    const FIELD_SELECTORS = [
        'first_name'   => ['#account_first_name'],
        'last_name'    => ['#account_last_name'],
        'display_name' => ['#account_display_name'],
        'email'        => ['#account_email'],
        'password'     => ['#password_current', '#password_1', '#password_2'],
    ];

    // [source text => admin's override] for the current request, resolved
    // ONCE in start_label_overrides() before the gettext filters are even
    // attached. The filter callbacks below read only from this — they must
    // NEVER call Settings::get() themselves: Settings::get() calls
    // available_roles(), which calls WordPress's own translate_user_role()
    // for every role, and THAT fires the gettext_with_context filter —
    // exactly the filter this class hooks. A filter callback that calls
    // Settings::get() while active would retrigger itself on every role, on
    // every retrigger, recursing until PHP's memory limit is exhausted.
    private $label_overrides = [];

    public function register_hooks() {
        // Fires right at the top of the edit-account form, before
        // WooCommerce prints its own first_name field — early enough that
        // both the CSS and the label filter apply regardless of field order.
        \add_action('woocommerce_edit_account_form_start', [$this, 'render_visibility_css']);
        \add_action('woocommerce_edit_account_form_start', [$this, 'start_label_overrides']);

        // 'woocommerce_edit_account_form' fires after all of WooCommerce's
        // own account-details fields have rendered, so the filter is only
        // ever active for the exact window it's needed — it can't leak
        // into some unrelated later __('First name', 'woocommerce') call
        // elsewhere on the same page load.
        \add_action('woocommerce_edit_account_form', [$this, 'stop_label_overrides']);
    }

    public function render_visibility_css() {
        $settings = Settings::get();
        $visibility = $settings['account_field_visibility'];

        $selectors = [];
        foreach (self::FIELD_SELECTORS as $key => $field_selectors) {
            if (empty($visibility[$key])) {
                foreach ($field_selectors as $selector) {
                    $selectors[] = 'p:has(' . $selector . ')';
                }
            }
        }

        if (empty($selectors)) {
            return; // everything visible, nothing to hide
        }

        echo '<style>' . implode(',', $selectors) . '{display:none}</style>';
    }

    public function start_label_overrides() {
        // Resolve everything the filter callbacks could possibly need right
        // now, while no gettext filter is active yet — see the property
        // docblock above for why the callbacks themselves must not call
        // Settings::get().
        $settings = Settings::get();
        $this->label_overrides = [
            'First name' => $settings['first_name_label'],
            'Last name'  => $settings['last_name_label'],
        ];

        // gettext covers __()/_e()/esc_html_e() etc.; gettext_with_context
        // covers the _x()/_ex() equivalents. Hooking both means the override
        // works regardless of which one WooCommerce's template actually
        // uses for these two strings, without needing to know that ahead of
        // time.
        \add_filter('gettext', [$this, 'filter_field_label'], 10, 3);
        \add_filter('gettext_with_context', [$this, 'filter_field_label_with_context'], 10, 4);
    }

    public function stop_label_overrides() {
        \remove_filter('gettext', [$this, 'filter_field_label'], 10);
        \remove_filter('gettext_with_context', [$this, 'filter_field_label_with_context'], 10);
        $this->label_overrides = [];
    }

    public function filter_field_label($translation, $text, $domain) {
        if ($domain !== 'woocommerce' || !isset($this->label_overrides[$text])) {
            return $translation;
        }

        return $this->label_overrides[$text];
    }

    public function filter_field_label_with_context($translation, $text, $context, $domain) {
        return $this->filter_field_label($translation, $text, $domain);
    }
}
