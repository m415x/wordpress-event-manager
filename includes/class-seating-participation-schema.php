<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Canonical participation eligibility per guest and event.
 *
 * A missing record is not itself proof of eligibility; the reservation
 * boundary must resolve canonical guest/event membership and cancellation.
 * WEM-75 owns the atomic cancel-and-seat-release operation.
 */
final class WEM_Seating_Participation_Schema
{
    public const TABLE_SUFFIX = 'wem_seating_participation';

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
            event_term_id BIGINT UNSIGNED NOT NULL,
            participation_status VARCHAR(16) NOT NULL DEFAULT 'eligible',
            canceled_at DATETIME NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY guest_event (guest_id, event_term_id),
            KEY event_status (event_term_id, participation_status)
        ) {$charset_collate};";

        dbDelta($sql);
    }
}
