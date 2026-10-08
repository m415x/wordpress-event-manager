<?php

if (!defined('ABSPATH')) {
    exit;
}

final class WEM_Invitation_Credential_Schema
{
    public const TABLE_SUFFIX = 'wem_invitation_credentials';

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
            guest_id BIGINT UNSIGNED NOT NULL,
            generation BIGINT UNSIGNED NOT NULL,
            token_digest CHAR(64) NOT NULL,
            status VARCHAR(16) NOT NULL,
            active_slot TINYINT UNSIGNED NULL,
            issued_at DATETIME NOT NULL,
            issued_by_user_id BIGINT UNSIGNED NOT NULL,
            invalidated_at DATETIME NULL,
            invalidated_by_user_id BIGINT UNSIGNED NULL,
            PRIMARY KEY  (sequence),
            UNIQUE KEY token_digest (token_digest),
            UNIQUE KEY guest_generation (guest_id, generation),
            UNIQUE KEY guest_active_slot (guest_id, active_slot),
            KEY guest_status (guest_id, status)
        ) {$charset_collate};";

        dbDelta($sql);
    }
}
