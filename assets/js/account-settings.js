// Powers the "Registration & Account" admin settings page: saves the form
// via admin-ajax.php (no page reload), and keeps each card's own
// Enabled/Disabled pill in sync with its toggle. Same pattern as
// b2b-settings.js, generalized to any number of [data-hexwp-toggle] switches
// instead of one hardcoded "enabled" checkbox.
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var form = document.getElementById('hexwp-account-settings-form');
        // hexWpAccountSettings is injected by wp_localize_script() in
        // class-account-settings-page.php. If it's missing, our script/data
        // wasn't enqueued correctly, so bail out quietly.
        if (!form || typeof hexWpAccountSettings === 'undefined') {
            return;
        }

        var noticeEl = document.getElementById('hexwp-account-notice');
        var saveButton = form.querySelector('.hexwp-save-button');
        var saveStatus = form.querySelector('[data-hexwp-save-status]');

        function showNotice(message, type) {
            noticeEl.textContent = message;
            noticeEl.className = 'hexwp-notice hexwp-notice-' + type;
            noticeEl.hidden = false;
        }

        // Each [data-hexwp-toggle="x"] checkbox has a matching
        // [data-hexwp-status-pill="x"] element; keep the pill's text/color
        // in sync with the checkbox without waiting for a save.
        form.querySelectorAll('[data-hexwp-toggle]').forEach(function (toggle) {
            var key = toggle.getAttribute('data-hexwp-toggle');
            var pill = form.querySelector('[data-hexwp-status-pill="' + key + '"]');
            if (!pill) {
                return;
            }
            toggle.addEventListener('change', function () {
                pill.classList.toggle('is-on', toggle.checked);
                pill.textContent = toggle.checked
                    ? hexWpAccountSettings.messages.enabled
                    : hexWpAccountSettings.messages.disabled;
            });
        });

        // "Custom Fields" repeater (Registration Form Fields card). Add
        // clones the <template> row's HTML with a fresh, never-reused index
        // swapped into the __INDEX__ placeholder used by every name="..."
        // attribute (see templates/account-settings-page.php); Remove just
        // deletes the row it's inside of. Both are wired via one delegated
        // listener on the list container so newly-added rows work without
        // any extra binding.
        var repeaterList = document.getElementById('hexwp-custom-fields-list');
        var repeaterTemplate = document.getElementById('hexwp-custom-field-template');
        var repeaterAddButton = form.querySelector('[data-hexwp-repeater-add]');
        var repeaterIndex = repeaterList ? repeaterList.querySelectorAll('[data-hexwp-repeater-row]').length : 0;

        if (repeaterList && repeaterTemplate && repeaterAddButton) {
            repeaterAddButton.addEventListener('click', function () {
                var html = repeaterTemplate.innerHTML.split('__INDEX__').join('new-' + repeaterIndex);
                repeaterIndex++;
                repeaterList.insertAdjacentHTML('beforeend', html);
            });

            repeaterList.addEventListener('click', function (event) {
                var removeButton = event.target.closest('[data-hexwp-repeater-remove]');
                if (!removeButton) {
                    return;
                }
                var row = removeButton.closest('[data-hexwp-repeater-row]');
                if (row) {
                    row.remove();
                }
            });
        }

        // "Include"/"Exclude" role pickers (Customer Type section): a
        // search box that filters a checkbox list, replacing the native
        // <select multiple> — that control needs a ctrl/cmd-click (or
        // shift-click) to select more than one option, which most people
        // don't know and can't do at all on a touch device. Checkboxes are
        // proper multi-select on their own; the search box is here purely
        // to help find one role quickly in a long list. Filtering is
        // display-only — every checkbox stays in the DOM (just hidden),
        // so nothing here affects what actually gets submitted.
        form.querySelectorAll('[data-hexwp-role-picker]').forEach(function (picker) {
            var search = picker.querySelector('[data-hexwp-role-picker-search]');
            var items = picker.querySelectorAll('[data-hexwp-role-picker-item]');
            var emptyState = picker.querySelector('[data-hexwp-role-picker-empty]');

            if (!search || !items.length) {
                return;
            }

            search.addEventListener('input', function () {
                var term = search.value.trim().toLowerCase();
                var visibleCount = 0;

                items.forEach(function (item) {
                    var labelEl = item.querySelector('[data-hexwp-role-picker-label]');
                    var label = labelEl ? labelEl.textContent.toLowerCase() : '';
                    var matches = term === '' || label.indexOf(term) !== -1;
                    item.hidden = !matches;
                    if (matches) {
                        visibleCount++;
                    }
                });

                if (emptyState) {
                    emptyState.hidden = visibleCount !== 0;
                }
            });
        });

        // Intercept the normal form submit and send it to admin-ajax.php
        // instead, so saving doesn't reload the page.
        form.addEventListener('submit', function (event) {
            event.preventDefault();

            if (saveButton.disabled) {
                return; // already saving, ignore extra clicks/enter presses
            }

            saveButton.disabled = true;
            saveButton.classList.add('is-saving');
            saveStatus.textContent = hexWpAccountSettings.messages.saving;

            var formData = new FormData(form);
            formData.set('action', hexWpAccountSettings.action);
            formData.set('nonce', hexWpAccountSettings.nonce);

            fetch(hexWpAccountSettings.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            })
                .then(function (response) {
                    return response.json();
                })
                .then(function (response) {
                    if (response && response.success) {
                        showNotice(response.data.message, 'success');
                        saveStatus.textContent = hexWpAccountSettings.messages.saved;
                    } else {
                        var message = response && response.data && response.data.message
                            ? response.data.message
                            : hexWpAccountSettings.messages.requestFailed;
                        showNotice(message, 'error');
                        saveStatus.textContent = '';
                    }
                })
                .catch(function () {
                    showNotice(hexWpAccountSettings.messages.requestFailed, 'error');
                    saveStatus.textContent = '';
                })
                .finally(function () {
                    saveButton.disabled = false;
                    saveButton.classList.remove('is-saving');
                });
        });
    });
})();
