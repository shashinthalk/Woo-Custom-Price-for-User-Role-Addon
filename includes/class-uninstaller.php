<?php

namespace HexWp;

if (!defined('ABSPATH')) {
    exit;
}

class Uninstaller {
    public static function uninstall() {
        $repository = new \HexWp\Data_Repository();
        $repository->drop_table();
    }
}
