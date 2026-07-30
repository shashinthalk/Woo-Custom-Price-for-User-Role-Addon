<?php

namespace HexWp;

if (!defined('ABSPATH')) {
    exit;
}

class Data_Repository {
    public function get_table_name() {
        global $wpdb;

        return $wpdb->prefix . 'sap_entries';
    }

    public function create_table() {
        global $wpdb;

        $table_name = $this->get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            time datetime NOT NULL,
            message text NOT NULL,
            PRIMARY KEY  (id)
        ) {$charset_collate};";

        require_once \ABSPATH . 'wp-admin/includes/upgrade.php';
        \dbDelta($sql);
    }

    public function get_recent_entries($limit = 10) {
        global $wpdb;

        $limit = \absint($limit);
        if ($limit < 1) {
            $limit = 10;
        }

        $table_name = $this->get_table_name();
        $sql = $wpdb->prepare(
            "SELECT id, time, message FROM {$table_name} ORDER BY time DESC LIMIT %d",
            $limit
        );

        return $wpdb->get_results($sql);
    }

    public function insert_message($message) {
        global $wpdb;

        $result = $wpdb->insert(
            $this->get_table_name(),
            [
                'time' => \current_time('mysql'),
                'message' => $message,
            ],
            ['%s', '%s']
        );

        return $result !== false;
    }

    public function drop_table() {
        global $wpdb;

        $table_name = $this->get_table_name();
        $wpdb->query("DROP TABLE IF EXISTS {$table_name}");
    }
}
