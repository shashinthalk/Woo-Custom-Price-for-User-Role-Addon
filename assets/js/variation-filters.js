/**
 * Live-filters the WooCommerce admin variations list against the dropdowns
 * printed by Variation_Filter_Toolbar::render_toolbar(). Runs entirely in
 * the browser — WooCommerce still only loads one "page" of variations at a
 * time, so this filters within whatever is currently loaded (which is why
 * the PHP side raises the per-page count to 200).
 */
jQuery(function ($) {
    'use strict';

    function applyVariationFilters() {
        // Collect only the dropdowns that actually have a value selected.
        var filters = {};
        $('.hexwp-variation-filter').each(function () {
            var value = $(this).val();
            if (value) {
                filters[$(this).data('field')] = value;
            }
        });

        var $rows = $('.woocommerce_variations .woocommerce_variation');
        var visible = 0;

        $rows.each(function () {
            var $row = $(this);
            var matches = true;

            // A row must match every active filter (AND, not OR) to stay visible.
            $.each(filters, function (field, value) {
                // Match on "starts with" since WooCommerce may suffix the
                // control's name attribute per-row.
                var $control = $row.find('select[name^="' + field + '"], input[name^="' + field + '"]');
                if ($control.length && $control.val() !== value) {
                    matches = false;
                }
            });

            $row.toggle(matches);
            if (matches) {
                visible++;
            }
        });

        $('.hexwp-variation-filter-count').text(visible + ' / ' + $rows.length + ' shown');
    }

    // Re-run whenever a dropdown changes.
    $(document.body).on('change', '.hexwp-variation-filter', applyVariationFilters);

    // "Reset" clears every dropdown and shows all rows again.
    $(document.body).on('click', '.hexwp-variation-filter-reset', function () {
        $('.hexwp-variation-filter').val('');
        applyVariationFilters();
    });

    // Re-apply filters whenever WooCommerce reloads/re-renders the variations
    // list (e.g. after "Generate variations" or switching pages).
    $(document.body).on('woocommerce_variations_loaded woocommerce_variations_added', applyVariationFilters);
});
