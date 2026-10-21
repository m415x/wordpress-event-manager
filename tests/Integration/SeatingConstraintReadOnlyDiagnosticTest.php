<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

final class SeatingConstraintReadOnlyDiagnosticTest extends WP_UnitTestCase
{
    public function testReadOnlyConstraintCatalogFromActualPhpunitConnection(): void
    {
        global $wpdb;

        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';

        $database = $wpdb->get_var('SELECT DATABASE()');
        $table = \WEM_Event_Table_Schema::table_name();
        $exists = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s',
                $table
            )
        );
        $constraints = $wpdb->get_results(
            "SELECT tc.CONSTRAINT_SCHEMA, tc.TABLE_NAME, tc.CONSTRAINT_NAME, tc.CONSTRAINT_TYPE,
                    cc.CHECK_CLAUSE
             FROM information_schema.TABLE_CONSTRAINTS tc
             LEFT JOIN information_schema.CHECK_CONSTRAINTS cc
               ON cc.CONSTRAINT_SCHEMA = tc.CONSTRAINT_SCHEMA
              AND cc.CONSTRAINT_NAME = tc.CONSTRAINT_NAME
             WHERE tc.CONSTRAINT_SCHEMA = DATABASE() AND tc.CONSTRAINT_TYPE = 'CHECK'
             ORDER BY tc.TABLE_NAME, tc.CONSTRAINT_NAME",
            ARRAY_A
        );
        $create = null;
        if ($exists === $table) {
            $row = $wpdb->get_row("SHOW CREATE TABLE `{$table}`", ARRAY_N);
            $create = is_array($row) ? ($row[1] ?? null) : null;
        }

        $snapshot = [
            'database' => $database,
            'table_name' => $table,
            'table_exists' => $exists === $table,
            'checks' => $constraints,
            'show_create_table' => $create,
            'historical_error_sql' => "ALTER TABLE {$table} ADD CONSTRAINT wem_event_table_capacity_positive CHECK (capacity > 0)",
            'historical_error_message' => "Duplicate CHECK constraint name 'wem_event_table_capacity_positive'",
            'historical_error_code' => 'not captured in previous execution',
            'catalog_query_error' => $wpdb->last_error,
        ];

        fwrite(STDERR, "\nWEM-74 READ-ONLY DIAGNOSTIC\n" . wp_json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

        self::assertIsString($database);
        self::assertNotSame('', $database);
    }
}
