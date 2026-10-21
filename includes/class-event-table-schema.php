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
            layout_x INT NULL,
            layout_y INT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY event_table_code (event_term_id, table_code),
            KEY event_term_id (event_term_id)
        ) {$charset_collate};";

        dbDelta($sql);

        // dbDelta does not parse CHECK. SHOW CREATE TABLE includes constraints
        // on WordPress PHPUnit temporary tables, unlike information_schema.
        $constraint_name = 'wem_event_table_capacity_positive';
        $definition = self::create_table_definition($table_name);

        if (preg_match('/CONSTRAINT\\s+[`"]?' . $constraint_name . '[`"]?\\s+CHECK\\s*\\(/i', $definition)) {
            self::assert_positive_capacity_definition($definition, $constraint_name);
            return;
        }

        $added = $wpdb->query(
            "ALTER TABLE {$table_name} ADD CONSTRAINT {$constraint_name} CHECK (capacity > 0)"
        );

        if ($added === false) {
            throw new RuntimeException('Could not install positive table-capacity constraint.');
        }

        self::assert_positive_capacity_definition(
            self::create_table_definition($table_name),
            $constraint_name
        );
    }

    private static function create_table_definition($table_name)
    {
        global $wpdb;

        $result = $wpdb->get_row("SHOW CREATE TABLE {$table_name}", ARRAY_N);
        if (!is_array($result) || !isset($result[1])) {
            throw new RuntimeException('Could not inspect event-table schema.');
        }

        return (string) $result[1];
    }

    private static function assert_positive_capacity_definition($definition, $constraint_name)
    {
        // MariaDB normalizes CHECK expressions with identifier quoting and
        // optional parenthesization.
        $pattern = '/CONSTRAINT\\s+[`"]?' . $constraint_name
            . '[`"]?\\s+CHECK\\s*\\(\\s*\\(*\\s*[`"]?capacity[`"]?\\s*>\\s*0\\s*\\)*\\s*\\)/i';

        if (!preg_match($pattern, $definition)) {
            throw new RuntimeException('Event-table positive capacity constraint is absent or inconsistent.');
        }
    }
}
