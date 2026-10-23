<?php

if (!defined('ABSPATH')) { exit; }

/** P0: dedicated inventory, never a synthetic WordPress guest. */
final class WEM_Preissued_Invitation_Schema
{
    public static function table_name(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'wem_preissued_invitations';
    }

    public static function audit_table_name(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'wem_preissued_assignment_audit';
    }

    public static function install(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = self::table_name();
        $audit = self::audit_table_name();
        $collation = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            event_term_id BIGINT UNSIGNED NOT NULL,
            token_digest CHAR(64) NOT NULL,
            state VARCHAR(16) NOT NULL,
            assigned_guest_id BIGINT UNSIGNED NULL,
            issued_by BIGINT UNSIGNED NOT NULL,
            issued_at DATETIME NOT NULL,
            assigned_by BIGINT UNSIGNED NULL,
            assigned_at DATETIME NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY token_digest (token_digest),
            UNIQUE KEY assigned_guest (assigned_guest_id),
            KEY event_state (event_term_id, state)
        ) {$collation};");
        dbDelta("CREATE TABLE {$audit} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            invitation_id BIGINT UNSIGNED NOT NULL,
            guest_id BIGINT UNSIGNED NOT NULL,
            event_term_id BIGINT UNSIGNED NOT NULL,
            actor_user_id BIGINT UNSIGNED NOT NULL,
            operation VARCHAR(32) NOT NULL,
            occurred_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY invitation_assignment (invitation_id, operation),
            KEY guest_event (guest_id, event_term_id)
        ) {$collation};");
        // Fail closed if physical constraints were not established.
        foreach ([$table, $audit] as $name) {
            $row = $wpdb->get_row("SHOW TABLE STATUS LIKE '" . esc_sql($name) . "'", ARRAY_A);
            if (!is_array($row) || strtoupper((string) ($row['Engine'] ?? '')) !== 'INNODB') {
                throw new RuntimeException('Preissued invitation requires persistent InnoDB.');
            }
        }
    }
}
