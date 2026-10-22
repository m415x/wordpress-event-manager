<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Physical reference between a reservation and its event-scoped table.
 *
 * Must run AFTER both base schema installers. Foreign keys cannot be created
 * on WordPress PHPUnit TEMPORARY tables; no fallback to a weaker constraint.
 */
final class WEM_Seating_Referential_Integrity_Schema
{
    private const PARENT_INDEX = 'wem_event_table_id_event';
    private const FOREIGN_KEY = 'wem_reservation_table_event_fk';

    public static function install()
    {
        global $wpdb;

        $parent = WEM_Event_Table_Schema::table_name();
        $child = WEM_Table_Reservation_Schema::table_name();
        $database = $wpdb->get_var('SELECT DATABASE()');
        if (!is_string($database) || $database === '') {
            throw new RuntimeException('Seating referential schema: database not resolved.');
        }

        $tables = $wpdb->get_results($wpdb->prepare(
            'SELECT TABLE_NAME, ENGINE, TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA=%s AND TABLE_NAME IN (%s,%s)',
            $database, $parent, $child
        ), ARRAY_A);
        if (!is_array($tables) || count($tables) !== 2) {
            throw new RuntimeException('Seating referential schema requires both persistent base tables.');
        }
        foreach ($tables as $table) {
            if ($table['TABLE_TYPE'] !== 'BASE TABLE' || strtoupper((string)$table['ENGINE']) !== 'INNODB') {
                throw new RuntimeException('Seating referential schema requires persistent InnoDB tables.');
            }
        }

        $columns = $wpdb->get_results($wpdb->prepare(
            "SELECT TABLE_NAME,COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA=%s AND ((TABLE_NAME=%s AND COLUMN_NAME IN ('id','event_term_id'))
             OR (TABLE_NAME=%s AND COLUMN_NAME IN ('table_id','event_term_id')))",
            $database, $parent, $child
        ), ARRAY_A);
        if (!is_array($columns) || count($columns) !== 4) {
            throw new RuntimeException('Seating FK reference columns missing.');
        }
        foreach ($columns as $column) {
            if (strtolower((string)$column['COLUMN_TYPE']) !== 'bigint(20) unsigned' || $column['IS_NULLABLE'] !== 'NO') {
                throw new RuntimeException('Seating FK reference column type incompatible.');
            }
        }

        $p = self::quote_identifier($parent);
        $r = self::quote_identifier($child);
        $keys = self::parent_index($database, $parent);
        if (!$keys) {
            self::execute("ALTER TABLE {$p} ADD INDEX `" . self::PARENT_INDEX . "` (`id`, `event_term_id`)");
            $keys = self::parent_index($database, $parent);
        }
        if (count($keys) !== 2 ||
            $keys[0]['COLUMN_NAME'] !== 'id' ||
            $keys[1]['COLUMN_NAME'] !== 'event_term_id') {
            throw new RuntimeException('Seating FK parent index absent or inconsistent.');
        }

        $fk = self::foreign_key($database, $child);
        if (!$fk) {
            self::execute("ALTER TABLE {$r} ADD CONSTRAINT `" . self::FOREIGN_KEY .
                "` FOREIGN KEY (`table_id`,`event_term_id`) REFERENCES {$p} (`id`,`event_term_id`)" .
                " ON UPDATE RESTRICT ON DELETE RESTRICT");
            $fk = self::foreign_key($database, $child);
        }
        if (count($fk) !== 1 ||
            $fk[0]['REFERENCED_TABLE_NAME'] !== $parent ||
            $fk[0]['UPDATE_RULE'] !== 'RESTRICT' ||
            $fk[0]['DELETE_RULE'] !== 'RESTRICT') {
            throw new RuntimeException('Seating FK definition absent or inconsistent.');
        }
        $mapping = $wpdb->get_results($wpdb->prepare(
            'SELECT COLUMN_NAME,REFERENCED_COLUMN_NAME,ORDINAL_POSITION
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE CONSTRAINT_SCHEMA=%s AND TABLE_NAME=%s AND CONSTRAINT_NAME=%s
             ORDER BY ORDINAL_POSITION',
            $database, $child, self::FOREIGN_KEY
        ), ARRAY_A);
        if (!is_array($mapping) || count($mapping) !== 2 ||
            $mapping[0]['COLUMN_NAME'] !== 'table_id' ||
            $mapping[0]['REFERENCED_COLUMN_NAME'] !== 'id' ||
            $mapping[1]['COLUMN_NAME'] !== 'event_term_id' ||
            $mapping[1]['REFERENCED_COLUMN_NAME'] !== 'event_term_id') {
            throw new RuntimeException('Seating FK column mapping inconsistent.');
        }

        $definition = $wpdb->get_row("SHOW CREATE TABLE {$r}", ARRAY_N);
        if (!is_array($definition) || !isset($definition[1]) ||
            strpos((string)$definition[1], 'CONSTRAINT `' . self::FOREIGN_KEY . '` FOREIGN KEY') === false) {
            throw new RuntimeException('Seating FK absent from physical table definition.');
        }
    }

    private static function quote_identifier($value)
    {
        return '`' . str_replace('`', '``', $value) . '`';
    }

    private static function parent_index($database, $parent)
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            'SELECT INDEX_NAME,SEQ_IN_INDEX,COLUMN_NAME FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA=%s AND TABLE_NAME=%s AND INDEX_NAME=%s ORDER BY SEQ_IN_INDEX',
            $database, $parent, self::PARENT_INDEX
        ), ARRAY_A);
    }

    private static function foreign_key($database, $child)
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            'SELECT CONSTRAINT_NAME,REFERENCED_TABLE_NAME,UPDATE_RULE,DELETE_RULE
             FROM information_schema.REFERENTIAL_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA=%s AND TABLE_NAME=%s AND CONSTRAINT_NAME=%s',
            $database, $child, self::FOREIGN_KEY
        ), ARRAY_A);
    }

    private static function execute($statement)
    {
        global $wpdb;
        if ($wpdb->query($statement) === false) {
            throw new RuntimeException('Could not install seating referential integrity: ' . $wpdb->last_error);
        }
    }
}
