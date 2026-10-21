<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * New-installation schema for event-scoped tables.
 *
 * Reservation occupancy is never persisted here.
 */
final class WEM_Event_Table_Schema
{
    public const TABLE_SUFFIX = 'wem_event_tables';

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
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            event_term_id BIGINT UNSIGNED NOT NULL,
            table_code VARCHAR(100) NOT NULL,
            capacity INT UNSIGNED NOT NULL,
            CONSTRAINT wem_event_table_capacity_positive CHECK (capacity > 0),
            layout_x INT NULL,
            layout_y INT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY event_table_code (event_term_id, table_code),
            KEY event_term_id (event_term_id)
        ) {$charset_collate};";

        dbDelta($sql);
    }
}
