<?php
/**
 * Plugin Name: Woo Custom Price for User Role Addon
 * Description: This plugin provide feature to add custom prices for user roles and can handle tax option.
 * Author: Plappermaul Dev
 * Text Domain: woo-custom-price-addon
 * Domain Path: /languages
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('HEX_WP_VERSION', '1.3.0');
define('HEX_WP_PLUGIN_FILE', __FILE__);
define('HEX_WP_PLUGIN_DIR', \plugin_dir_path(__FILE__));
define('HEX_WP_PLUGIN_URL', \plugin_dir_url(__FILE__));
define('HEX_WP_TEXT_DOMAIN', 'woo-custom-price-addon');

require_once HEX_WP_PLUGIN_DIR . 'includes/class-autoloader.php';

$autoloader = new \HexWp\Autoloader();
$autoloader->register();

\register_activation_hook(__FILE__, ['\\HexWp\\Activator', 'activate']);
\register_deactivation_hook(__FILE__, ['\\HexWp\\Deactivator', 'deactivate']);

function hex_wp_run_plugin() {
    $plugin = new \HexWp\Plugin();
    $plugin->run();
}

hex_wp_run_plugin();