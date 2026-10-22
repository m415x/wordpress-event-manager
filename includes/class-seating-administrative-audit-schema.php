<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Append-only evidence schema for administrative seating actions.
 *
 * This table records historical facts. WEM-75 owns authorized transactional
 * writers and must append a new row instead of updating audit records.
 */
final class WEM_Seating_Administrative_Audit_Schema
{
    public const TABLE_SUFFIX = 'wem_seating_admin_audit';

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
            actor_user_id BIGINT UNSIGNED NOT NULL,
            operation VARCHAR(32) NOT NULL,
            old_table_id BIGINT UNSIGNED NULL,
            new_table_id BIGINT UNSIGNED NULL,
            occurred_at DATETIME NOT NULL,
            reason TEXT NOT NULL,
            PRIMARY KEY  (id),
            KEY guest_event_time (guest_id, event_term_id, occurred_at),
            KEY event_time (event_term_id, occurred_at),
            KEY actor_time (actor_user_id, occurred_at)
        ) {$charset_collate};";

        dbDelta($sql);
    }
}
