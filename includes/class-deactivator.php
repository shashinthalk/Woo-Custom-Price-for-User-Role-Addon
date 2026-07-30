<?php

namespace HexWp;

if (!defined('ABSPATH')) {
    exit;
}

class Deactivator {
    public static function deactivate() {
        // Keep data intact on deactivation. Cleanup is handled in uninstall.php.
    }
}
