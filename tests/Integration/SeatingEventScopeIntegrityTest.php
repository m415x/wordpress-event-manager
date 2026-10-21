<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

final class SeatingEventScopeIntegrityTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
    }

    public function testEventTableRejectsInvalidZeroEventScopeAtDatabaseBoundary(): void
    {
        global $wpdb;

        \WEM_Event_Table_Schema::install();
        \WEM_Event_Table_Schema::install();
        \WEM_Event_Table_Schema::install();
        $table = \WEM_Event_Table_Schema::table_name();

        $wpdb->suppress_errors(true);
        try {
            $inserted = $wpdb->insert($table, [
                'event_term_id' => 0,
                'table_code' => 'WEM74-ZERO-SCOPE-' . wp_generate_uuid4(),
                'capacity' => 1,
            ], ['%d', '%s', '%d']);
        } finally {
            $wpdb->suppress_errors(false);
        }

        self::assertFalse(
            $inserted,
            'A table must never be stored without a positive event scope.'
        );

        self::assertSame(1, $wpdb->insert($table, [
            'event_term_id' => 1,
            'table_code' => 'WEM74-POSITIVE-SCOPE-' . wp_generate_uuid4(),
            'capacity' => 1,
        ], ['%d', '%s', '%d']));
    }
}
