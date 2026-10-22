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

        // Explicit IS NOT NULL is required: SQL CHECK accepts UNKNOWN from NULL = 1.
        // dbDelta ignores CHECK. Inspect the effective PHPUnit temporary table.
        $constraint = 'wem_reservation_status_slot_consistent';
        $definition = self::table_definition($table_name);
        $name_pattern = '/CONSTRAINT\\s+[`"]?' . $constraint . '[`"]?\\s+CHECK\\s*\\(/i';
        if (!preg_match($name_pattern, $definition)) {
            $expression = "((status = 'active' AND active_slot IS NOT NULL AND active_slot = 1 AND canceled_at IS NULL) OR (status = 'canceled' AND active_slot IS NULL AND canceled_at IS NOT NULL))";
            $added = $wpdb->query("ALTER TABLE {$table_name} ADD CONSTRAINT {$constraint} CHECK ({$expression})");
            if ($added === false) {
                throw new RuntimeException('Could not install reservation status/slot constraint.');
            }
            $definition = self::table_definition($table_name);
        }
        $expected = '/CONSTRAINT\\s+[`"]?' . $constraint
            . '[`"]?\\s+CHECK\\s*\\(.*?[`"]?status[`"]?\\s*=\\s*\\x27active\\x27.*?[`"]?active_slot[`"]?\\s+IS\\s+NOT\\s+NULL.*?[`"]?active_slot[`"]?\\s*=\\s*1.*?[`"]?canceled_at[`"]?\\s+IS\\s+NULL.*?\\bOR\\b.*?[`"]?status[`"]?\\s*=\\s*\\x27canceled\\x27.*?[`"]?active_slot[`"]?\\s+IS\\s+NULL.*?[`"]?canceled_at[`"]?\\s+IS\\s+NOT\\s+NULL.*?\\)/is';
        if (!preg_match($expected, $definition)) {
            throw new RuntimeException('Reservation status/slot constraint is absent or inconsistent.');
        }
    }
    private static function table_definition($table_name)
    {
        global $wpdb;
        $row = $wpdb->get_row("SHOW CREATE TABLE {$table_name}", ARRAY_N);
        if (!is_array($row) || !isset($row[1])) {
            throw new RuntimeException('Could not inspect reservation schema.');
        }
        return (string) $row[1];
    }
}
