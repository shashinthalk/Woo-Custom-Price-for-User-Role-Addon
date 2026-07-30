<?php

namespace HexWp;

if (!defined('ABSPATH')) {
    exit;
}

class Activator {
    public static function activate() {
        $repository = new \HexWp\Data_Repository();
        $repository->create_table();
    }
}
