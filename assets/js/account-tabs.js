// Powers tab clicks for the Login/Register tabbed My Account page.
// Only ever sets data-active-tab on .hexwp-account-tabs (our own wrapper,
// server-rendered right before #customer_login) — account-tabs.css reacts
// to that same attribute via an adjacent-sibling selector, whether it was
// set by PHP on page load (see class-login-register-tabs.php's
// get_active_tab()) or updated here on click. That's what keeps the page on
// whichever tab the user actually submitted after a validation error
// reload: PHP already set the right initial value before this script even
// runs, so a click isn't the only way the correct tab ends up active.
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var tabsEl = document.querySelector('.hexwp-account-tabs');
        var buttons = document.querySelectorAll('.hexwp-account-tab-btn');

        if (!tabsEl || !buttons.length) {
            return;
        }

        buttons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                buttons.forEach(function (b) {
                    b.classList.remove('active');
                });
                btn.classList.add('active');

                tabsEl.dataset.activeTab = btn.dataset.tab;
            });
        });
    });
})();
