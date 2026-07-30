<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

require_once __DIR__ . '/includes/class-data-repository.php';
require_once __DIR__ . '/includes/class-uninstaller.php';

\HexWp\Uninstaller::uninstall();
