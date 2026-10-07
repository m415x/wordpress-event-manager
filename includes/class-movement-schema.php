<?php

if (!defined('ABSPATH')) {
    exit;
}

final class WEM_Movement_Schema
{
    public const TABLE_SUFFIX = 'wem_guest_movements';

    public static function table_name()
    {
        global $wpdb;

        return $wpdb->prefix . self::TABLE_SUFFIX;
    }

    public static function install()
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table_name = self::table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            sequence BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            movement_id CHAR(36) NOT NULL,
            guest_id BIGINT UNSIGNED NOT NULL,
            event_term_id BIGINT UNSIGNED NOT NULL,
            movement_type VARCHAR(16) NOT NULL,
            occurred_at DATETIME NOT NULL,
            actor_user_id BIGINT UNSIGNED NOT NULL,
            source VARCHAR(32) NOT NULL,
            decision VARCHAR(16) NOT NULL,
            PRIMARY KEY  (sequence),
            UNIQUE KEY movement_id (movement_id),
            KEY guest_event_sequence (guest_id, event_term_id, sequence)
        ) {$charset_collate};";

        dbDelta($sql);
    }
}
