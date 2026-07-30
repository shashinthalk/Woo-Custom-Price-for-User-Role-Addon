// Powers the "B2B Pricing" admin settings page: saves the form via
// admin-ajax.php (no page reload), plus two small UI niceties (the
// Enabled/Disabled pill and the live price-suffix preview).
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var form = document.getElementById('hexwp-b2b-settings-form');
        // hexWpB2bSettings is injected by wp_localize_script() in class-b2b-settings-page.php.
        // If it's missing, our script/data wasn't enqueued correctly, so bail out quietly.
        if (!form || typeof hexWpB2bSettings === 'undefined') {
            return;
        }

        var noticeEl = document.getElementById('hexwp-b2b-notice');
        var saveButton = form.querySelector('.hexwp-save-button');
        var saveStatus = form.querySelector('[data-hexwp-save-status]');
        var statusPill = document.querySelector('[data-hexwp-status-pill]');
        var enabledToggle = form.querySelector('[data-hexwp-enabled-toggle]');

        // Shows a success/error banner near the top of the page after a save attempt.
        function showNotice(message, type) {
            noticeEl.textContent = message;
            noticeEl.className = 'hexwp-notice hexwp-notice-' + type;
            noticeEl.hidden = false;
        }

        // Keep the "Enabled/Disabled" pill in sync with the toggle without waiting for a save.
        if (enabledToggle && statusPill) {
            enabledToggle.addEventListener('change', function () {
                statusPill.classList.toggle('is-on', enabledToggle.checked);
                statusPill.textContent = enabledToggle.checked
                    ? hexWpB2bSettings.messages.enabled
                    : hexWpB2bSettings.messages.disabled;
            });
        }

        // Live preview so the price-suffix spacing is obvious before saving.
        form.querySelectorAll('[data-hexwp-suffix-input]').forEach(function (input) {
            var previewId = input.getAttribute('data-hexwp-preview-target');
            var previewEl = previewId ? document.getElementById(previewId) : null;
            if (!previewEl) {
                return;
            }
            input.addEventListener('input', function () {
                previewEl.textContent = input.value ? ' ' + input.value : '';
            });
        });

        // Intercept the normal form submit and send it to admin-ajax.php instead,
        // so saving doesn't reload the page.
        form.addEventListener('submit', function (event) {
            event.preventDefault();

            if (saveButton.disabled) {
                return; // already saving, ignore extra clicks/enter presses
            }

            saveButton.disabled = true;
            saveButton.classList.add('is-saving');
            saveStatus.textContent = hexWpB2bSettings.messages.saving;

            // FormData(form) collects every field's current value automatically
            // (text inputs, checked checkboxes, etc.) — we just add the two
            // extra fields WordPress needs to route and verify the request.
            var formData = new FormData(form);
            formData.set('action', hexWpB2bSettings.action);
            formData.set('nonce', hexWpB2bSettings.nonce);

            fetch(hexWpB2bSettings.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            })
                .then(function (response) {
                    return response.json();
                })
                .then(function (response) {
                    // wp_send_json_success()/wp_send_json_error() on the PHP side both
                    // produce { success: true|false, data: {...} } — read that here.
                    if (response && response.success) {
                        showNotice(response.data.message, 'success');
                        saveStatus.textContent = hexWpB2bSettings.messages.saved;
                    } else {
                        var message = response && response.data && response.data.message
                            ? response.data.message
                            : hexWpB2bSettings.messages.requestFailed;
                        showNotice(message, 'error');
                        saveStatus.textContent = '';
                    }
                })
                .catch(function () {
                    // Network error, or the response wasn't valid JSON at all.
                    showNotice(hexWpB2bSettings.messages.requestFailed, 'error');
                    saveStatus.textContent = '';
                })
                .finally(function () {
                    // Always re-enable the button, whether the save succeeded or failed.
                    saveButton.disabled = false;
                    saveButton.classList.remove('is-saving');
                });
        });
    });
})();
