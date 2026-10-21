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

        \WEM_Event_Table_Schema::install();
        \WEM_Event_Table_Schema::install();

        $table = \WEM_Event_Table_Schema::table_name();
        self::assertSame($table, $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)));

        $wpdb->suppress_errors(true);
        try {
            $inserted = $wpdb->insert(
                $table,
                ['event_term_id' => 999001, 'table_code' => 'WEM-74-ZERO-CAPACITY', 'capacity' => 0],
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
}
