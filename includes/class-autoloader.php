<?php

namespace HexWp;

if (!defined('ABSPATH')) {
    exit;
}

class Autoloader {
    public function register() {
        spl_autoload_register([$this, 'autoload']);
    }

    private function autoload($class_name) {
        $prefix = __NAMESPACE__ . '\\';
        $base_dir = HEX_WP_PLUGIN_DIR . 'includes/';

        $prefix_length = strlen($prefix);
        if (strncmp($prefix, $class_name, $prefix_length) !== 0) {
            return;
        }

        $relative_class = substr($class_name, $prefix_length);
        $parts = explode('\\', $relative_class);
        $class = array_pop($parts);
        $class_file = 'class-' . strtolower(str_replace('_', '-', $class)) . '.php';

        $path = $base_dir;
        if (!empty($parts)) {
            $path .= implode('/', $parts) . '/';
        }

        $file = $path . $class_file;

        if (file_exists($file)) {
            require_once $file;
        }
    }
}
