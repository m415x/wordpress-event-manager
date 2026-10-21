<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

final class SeatingCapacityIntegrityTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
    }

    public function testEventTableCapacityMustBePositiveAtDatabaseBoundary(): void
    {
        global $wpdb;

        $table = \WEM_Event_Table_Schema::table_name();
        $this->dumpCatalog('before_first_install', $table);

        try {
            \WEM_Event_Table_Schema::install();
            $this->dumpCatalog('after_first_install', $table);
        } catch (\Throwable $error) {
            $this->dumpCatalog('first_install_error', $table, $error);
            throw $error;
        }

        try {
            \WEM_Event_Table_Schema::install();
            $this->dumpCatalog('after_second_install', $table);
        } catch (\Throwable $error) {
            $this->dumpCatalog('second_install_error', $table, $error);
            throw $error;
        }

        $table = \WEM_Event_Table_Schema::table_name();
        self::assertSame($table, $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)));

        $schema = $wpdb->get_row("SHOW CREATE TABLE {$table}", ARRAY_N);
        self::assertIsArray($schema);
        self::assertMatchesRegularExpression(
            '/CONSTRAINT\s+[`"]?wem_event_table_capacity_positive[`"]?\s+CHECK\s*\(/i',
            (string) $schema[1],
            'WEM-74 installer must persist a real named positive-capacity constraint.'
        );

        $code = 'WEM-74-ZERO-' . wp_generate_uuid4();

        $wpdb->suppress_errors(true);
        try {
            $inserted = $wpdb->insert(
                $table,
                ['event_term_id' => 999001, 'table_code' => $code, 'capacity' => 0],
                ['%d', '%s', '%d']
            );
        } finally {
            $wpdb->suppress_errors(false);
        }

        self::assertFalse(
            $inserted,
            'WEM-74 must reject zero-capacity event tables at the database boundary.'
        );
    }
    /**
     * Diagnostic-only read snapshot from the exact PHPUnit connection.
     */
    private function dumpCatalog(string $stage, string $table, ?\Throwable $error = null): void
    {
        global $wpdb;

        $database = $wpdb->get_var('SELECT DATABASE()');
        $exists = $wpdb->get_var($wpdb->prepare(
            'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s',
            $table
        ));

        $checks = $wpdb->get_results(
            "SELECT tc.CONSTRAINT_SCHEMA, tc.TABLE_NAME, tc.CONSTRAINT_NAME, tc.CONSTRAINT_TYPE, cc.CHECK_CLAUSE
               FROM information_schema.TABLE_CONSTRAINTS tc
               LEFT JOIN information_schema.CHECK_CONSTRAINTS cc
                 ON cc.CONSTRAINT_SCHEMA = tc.CONSTRAINT_SCHEMA
                AND cc.CONSTRAINT_NAME = tc.CONSTRAINT_NAME
              WHERE tc.CONSTRAINT_SCHEMA = DATABASE() AND tc.CONSTRAINT_TYPE = 'CHECK'
              ORDER BY tc.TABLE_NAME, tc.CONSTRAINT_NAME",
            ARRAY_A
        );
        $catalogError = $wpdb->last_error;
        $create = null;
        if ($exists === $table) {
            $created = $wpdb->get_row("SHOW CREATE TABLE `{$table}`", ARRAY_N);
            $create = is_array($created) ? ($created[1] ?? null) : null;
        }
        $snapshot = [
            'stage' => $stage,
            'database' => $database,
            'table_name' => $table,
            'table_exists' => $exists === $table,
            'checks' => $checks,
            'show_create_table' => $create,
            'catalog_error' => $catalogError,
            'installer_error_class' => $error ? get_class($error) : null,
            'installer_error_code' => $error ? $error->getCode() : null,
            'installer_error_message' => $error ? $error->getMessage() : null,
            'wpdb_last_error' => $wpdb->last_error,
            'wpdb_last_query' => $wpdb->last_query,
        ];

        fwrite(STDERR, "\nWEM-74 INSTALL STAGE " . $stage . "\n"
            . wp_json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    }

}
