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

        // dbDelta does not parse CHECK clauses. Inspect the effective table
        // definition: PHPUnit can shadow persistent tables with TEMPORARY ones.
        self::ensure_positive_constraint(
            $table_name,
            'wem_event_table_capacity_positive',
            'capacity'
        );
        self::ensure_positive_constraint(
            $table_name,
            'wem_event_table_event_positive',
            'event_term_id'
        );
        self::ensure_nonempty_table_code($table_name);
    }

    private static function ensure_positive_constraint($table_name, $constraint_name, $column_name)
    {
        global $wpdb;

        $definition = self::create_table_definition($table_name);
        $name_pattern = '/CONSTRAINT\\s+[`"]?' . preg_quote($constraint_name, '/')
            . '[`"]?\\s+CHECK\\s*\\(/i';

        if (preg_match($name_pattern, $definition)) {
            self::assert_positive_constraint($definition, $constraint_name, $column_name);
            return;
        }

        $added = $wpdb->query(
            "ALTER TABLE {$table_name} ADD CONSTRAINT {$constraint_name} CHECK ({$column_name} > 0)"
        );
        if ($added === false) {
            throw new RuntimeException('Could not install positive ' . $column_name . ' constraint.');
        }

        self::assert_positive_constraint(
            self::create_table_definition($table_name),
            $constraint_name,
            $column_name
        );
    }

    private static function ensure_nonempty_table_code($table_name)
    {
        global $wpdb;

        $constraint = 'wem_event_table_code_nonempty';
        $definition = self::create_table_definition($table_name);
        $name_pattern = '/CONSTRAINT\\s+[`"]?' . preg_quote($constraint, '/')
            . '[`"]?\\s+CHECK\\s*\\(/i';
        $expression_pattern = '/CONSTRAINT\\s+[`"]?' . preg_quote($constraint, '/')
            . '[`"]?\\s+CHECK\\s*\\(\\s*\\(*\\s*char_length\\s*\\(\\s*[`"]?table_code[`"]?\\s*\\)\\s*>\\s*0\\s*\\)*\\s*\\)/i';

        if (preg_match($name_pattern, $definition)) {
            if (!preg_match($expression_pattern, $definition)) {
                throw new RuntimeException('Event-table code nonempty constraint is inconsistent.');
            }
            return;
        }

        $result = $wpdb->query(
            "ALTER TABLE {$table_name} ADD CONSTRAINT {$constraint} CHECK (CHAR_LENGTH(table_code) > 0)"
        );
        if ($result === false) {
            throw new RuntimeException('Could not install nonempty event-table code constraint.');
        }

        if (!preg_match($expression_pattern, self::create_table_definition($table_name))) {
            throw new RuntimeException('Event-table code nonempty constraint was not installed.');
        }
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

    private static function assert_positive_constraint($definition, $constraint_name, $column_name)
    {
        // MariaDB normalizes CHECK expressions with optional identifier quoting
        // and parenthesization. Never silently accept a divergent definition.
        $pattern = '/CONSTRAINT\\s+[`"]?' . preg_quote($constraint_name, '/')
            . '[`"]?\\s+CHECK\\s*\\(\\s*\\(*\\s*[`"]?'
            . preg_quote($column_name, '/') . '[`"]?\\s*>\\s*0\\s*\\)*\\s*\\)/i';

        if (!preg_match($pattern, $definition)) {
            throw new RuntimeException('Event-table positive ' . $column_name . ' constraint is absent or inconsistent.');
        }
    }
}
