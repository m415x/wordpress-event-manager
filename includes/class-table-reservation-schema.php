<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Canonical individual seat assignment schema.
 *
 * A NULL active_slot allows historical canceled rows while the physical
 * unique key prevents more than one active reservation per guest.
 */
final class WEM_Table_Reservation_Schema
{
    public const TABLE_SUFFIX = 'wem_table_reservations';

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
            guest_id BIGINT UNSIGNED NOT NULL,
            table_id BIGINT UNSIGNED NOT NULL,
            event_term_id BIGINT UNSIGNED NOT NULL,
            status VARCHAR(16) NOT NULL,
            active_slot TINYINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            canceled_at DATETIME NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY guest_active_slot (guest_id, active_slot),
            KEY table_active (table_id, active_slot),
            KEY event_table (event_term_id, table_id)
        ) {$charset_collate};";

        dbDelta($sql);
    }
}
