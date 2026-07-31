/**
 * Shows/hides the B2B quantity-limit message (printed by
 * Stock_Engine::render_message_container()) as the customer changes the
 * quantity field on a product's single product page — works for both
 * simple products and variable products (whose quantity field only
 * appears once a variation is selected).
 */
jQuery(function ($) {
    'use strict';

    var $message = $('.hexwp-b2b-stock-message');
    if (!$message.length) {
        return;
    }

    var limit = parseFloat($message.data('quantity-limit'));
    if (isNaN(limit)) {
        return;
    }

    function checkQuantity() {
        // form.cart covers simple products; variable products use the same
        // form (class "variations_form cart"), with the .qty field only
        // present once a variation is selected.
        var $qty = $('form.cart .qty');
        if (!$qty.length) {
            $message.prop('hidden', true);
            return;
        }

        var requested = parseFloat($qty.val());
        $message.prop('hidden', !(requested > limit));
    }

    $(document.body).on('change input', 'form.cart .qty', checkQuantity);

    // Variable products swap their quantity field in/out as the customer
    // picks a variation — re-check whenever WooCommerce fires these.
    $(document.body).on('found_variation show_variation hide_variation reset_data', checkQuantity);

    // Run once on load in case the quantity field already starts above
    // the limit (e.g. a minimum quantity set by another plugin).
    checkQuantity();
});
