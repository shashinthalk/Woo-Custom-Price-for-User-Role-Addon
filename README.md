# Hexnity WP Plugin Template

A minimal, secure WordPress plugin template that provides:
- A simple admin menu page with one text input.
- Safe text persistence to a custom database table.
- WooCommerce B2B pricing: a per-product/variation B2B price, configurable via an admin settings page.

This repository is intentionally small and easy to extend for production use.

## Features

- Clean OOP structure with autoloaded classes.
- Secure admin form processing (capability checks, nonce validation, sanitization).
- Database access isolated in a repository class.
- Activation hook creates required database table.
- Uninstall routine removes plugin data table.
- Optional WooCommerce B2B pricing (price/tax/suffix overrides), off by default and only active when WooCommerce is installed.

## Plugin Name

The plugin is registered in WordPress as:

**Hexnity WP Plugin Template**

## Requirements

- WordPress 6.x+
- PHP 7.4+ (PHP 8.x recommended)
- MySQL 5.7+ or MariaDB equivalent

## Installation

1. Copy this folder to your WordPress plugins directory:
   - `wp-content/plugins/wp-plugin-template`
2. In WordPress admin, go to **Plugins**.
3. Activate **Hexnity WP Plugin Template**.

## Usage

### 1) Save text from admin

1. Open WordPress admin menu: **Text Storage**.
2. Enter text in the single input field.
3. Click **Save Text**.

### 2) Configure WooCommerce B2B pricing

1. Open WordPress admin menu: **B2B Pricing**.
2. Check **Enable B2B pricing**, pick which roles count as B2B, and optionally set a B2B user-meta key, tax exemption, and custom price suffixes.
3. Save settings.
4. On any WooCommerce product (or each variation of a variable product), open the **Pricing** tab and set a **B2B Price**.
5. Logged-in B2B customers will see that price (and the configured tax/suffix behavior) instead of the regular price.

## Security Model

The plugin includes the following protections:

- Capability enforcement using `manage_options` for admin page access.
- CSRF protection using `wp_nonce_field()` and `wp_verify_nonce()`.
- Input sanitization using `wp_unslash()`, `sanitize_text_field()`, and `trim()`.
- Output escaping using `esc_html()` and `esc_attr()`.
- SQL safety via prepared queries and format arrays in `$wpdb` operations.

## Project Structure

```text
wp-plugin-template/
|- index.php
|- uninstall.php
|- README.md
|- LICENSE
|- docs/
|  \- documentation.html
|- includes/
|  |- class-activator.php
|  |- class-autoloader.php
|  |- class-data-repository.php
|  |- class-deactivator.php
|  |- class-plugin.php
|  |- class-uninstaller.php
|  |- Admin/
|  |  |- class-admin-page.php
|  |  \- class-b2b-settings-page.php
|  |- Frontend/
|  |  \- class-shortcode.php
|  \- WooCommerce/
|     |- class-settings.php
|     |- class-customer.php
|     |- class-variation-price-field.php
|     |- class-product-price-field.php
|     \- class-price-engine.php
\- templates/
   |- admin-page.php
   \- b2b-settings-page.php
```

## Development Notes

- Main bootstrap: `index.php`
- Runtime orchestrator: `includes/class-plugin.php`
- Data layer (demo text storage): `includes/class-data-repository.php`
- Admin UI handler (demo text storage): `includes/Admin/class-admin-page.php`
- Frontend shortcode renderer (demo text storage): `includes/Frontend/class-shortcode.php`
- B2B pricing settings + detection: `includes/WooCommerce/class-settings.php`, `includes/WooCommerce/class-customer.php`
- B2B pricing admin fields: `includes/WooCommerce/class-variation-price-field.php`, `includes/WooCommerce/class-product-price-field.php`
- B2B pricing filters (price/tax/suffix): `includes/WooCommerce/class-price-engine.php`
- B2B settings admin page: `includes/Admin/class-b2b-settings-page.php`

## Quality Checks

Use PHP linting during development:

```bash
php -l index.php
php -l includes/class-plugin.php
php -l includes/Admin/class-admin-page.php
php -l includes/Admin/class-b2b-settings-page.php
php -l includes/Frontend/class-shortcode.php
php -l includes/WooCommerce/class-settings.php
php -l includes/WooCommerce/class-customer.php
php -l includes/WooCommerce/class-variation-price-field.php
php -l includes/WooCommerce/class-product-price-field.php
php -l includes/WooCommerce/class-price-engine.php
```

## Extending This Template

Recommended next enhancements:

- Add plugin settings API support for configurable options.
- Add pagination and search for admin list entries.
- Add unit/integration tests with WordPress test framework.
- Add i18n translation files under `languages/`.

## Support

For project updates and usage guidance, open an issue in your repository or contact the maintainer.
