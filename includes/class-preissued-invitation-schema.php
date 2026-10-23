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
        // Permanent database guard: event/digest/issuance identity is immutable.
        // Only the first unassigned -> assigned transition may set its guest.
        // Never permit a direct SQL writer to reassign, unassign or delete.
        $trigger = $wpdb->prefix . 'wem_preissued_guard_update';
        $existing = $wpdb->get_var($wpdb->prepare(
            'SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND TRIGGER_NAME = %s',
            $trigger
        ));
        if ($existing === null) {
            $sql = "CREATE TRIGGER {$trigger} BEFORE UPDATE ON {$table} FOR EACH ROW
                BEGIN
                    IF NOT (OLD.id <=> NEW.id)
                       OR NOT (OLD.event_term_id <=> NEW.event_term_id)
                       OR NOT (OLD.token_digest <=> NEW.token_digest)
                       OR NOT (OLD.issued_by <=> NEW.issued_by)
                       OR NOT (OLD.issued_at <=> NEW.issued_at)
                    THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Immutable invitation identity';
                    END IF;
                    IF OLD.state = 'unassigned' THEN
                        IF NOT (
                            (NEW.state = 'unassigned'
                             AND NEW.assigned_guest_id IS NULL
                             AND NEW.assigned_by IS NULL
                             AND NEW.assigned_at IS NULL)
                            OR
                            (NEW.state = 'assigned'
                             AND OLD.assigned_guest_id IS NULL
                             AND NEW.assigned_guest_id IS NOT NULL
                             AND NEW.assigned_by IS NOT NULL
                             AND NEW.assigned_at IS NOT NULL)
                            OR
                            (NEW.state = 'revoked'
                             AND OLD.assigned_guest_id IS NULL
                             AND NEW.assigned_guest_id IS NULL)
                        ) THEN
                            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid invitation transition';
                        END IF;
                    ELSEIF NOT (OLD.state <=> NEW.state)
                        OR NOT (OLD.assigned_guest_id <=> NEW.assigned_guest_id)
                        OR NOT (OLD.assigned_by <=> NEW.assigned_by)
                        OR NOT (OLD.assigned_at <=> NEW.assigned_at)
                    THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Reassignment prohibited';
                    END IF;
                END";
            if ($wpdb->query($sql) === false) {
                throw new RuntimeException('Could not install preissued update integrity guard.');
            }
        }
        $installed = $wpdb->get_var($wpdb->prepare(
            'SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND EVENT_OBJECT_TABLE = %s AND TRIGGER_NAME = %s',
            $table, $trigger
        ));
        if ($installed !== $trigger) {
            throw new RuntimeException('Preissued update integrity guard absent.');
        }
        // Physical retention: an assigned invitation cannot be erased by SQL.
        $deleteTrigger = $wpdb->prefix . 'wem_preissued_guard_delete';
        $deleteExisting = $wpdb->get_var($wpdb->prepare(
            'SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND TRIGGER_NAME = %s AND EVENT_OBJECT_TABLE = %s AND EVENT_MANIPULATION = %s',
            $deleteTrigger, $table, 'DELETE'
        ));
        if ($deleteExisting === null) {
            $deleteSql = "CREATE TRIGGER {$deleteTrigger} BEFORE DELETE ON {$table} FOR EACH ROW
                BEGIN
                    IF OLD.state = 'assigned' OR OLD.assigned_guest_id IS NOT NULL THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Assigned invitation deletion prohibited';
                    END IF;
                END";
            if ($wpdb->query($deleteSql) === false) {
                throw new RuntimeException('Could not install preissued deletion integrity guard.');
            }
        }
        $verifiedDeleteTrigger = $wpdb->get_var($wpdb->prepare(
            'SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND TRIGGER_NAME = %s AND EVENT_OBJECT_TABLE = %s AND EVENT_MANIPULATION = %s',
            $deleteTrigger, $table, 'DELETE'
        ));
        if ($verifiedDeleteTrigger !== $deleteTrigger) {
            throw new RuntimeException('Preissued deletion integrity guard absent.');
        }
        // Fail closed if physical constraints were not established.
        foreach ([$table, $audit] as $name) {
            $row = $wpdb->get_row("SHOW TABLE STATUS LIKE '" . esc_sql($name) . "'", ARRAY_A);
            if (!is_array($row) || strtoupper((string) ($row['Engine'] ?? '')) !== 'INNODB') {
                throw new RuntimeException('Preissued invitation requires persistent InnoDB.');
            }
        }
    }
}
