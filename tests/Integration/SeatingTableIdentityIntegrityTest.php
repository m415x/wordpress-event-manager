<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

final class SeatingTableIdentityIntegrityTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
    }

    public function testTableIdentityIsUniquePerEventButReusableAcrossEventsAndNotBlank(): void
    {
        global $wpdb;

        \WEM_Event_Table_Schema::install();
        \WEM_Event_Table_Schema::install();
        \WEM_Event_Table_Schema::install();
        $table = \WEM_Event_Table_Schema::table_name();
        $code = 'WEM74-SCOPE-' . wp_generate_uuid4();

        self::assertSame(1, $wpdb->insert($table, [
            'event_term_id' => 101, 'table_code' => $code, 'capacity' => 1,
        ], ['%d', '%s', '%d']));

        $wpdb->suppress_errors(true);
        try {
            $duplicate = $wpdb->insert($table, [
                'event_term_id' => 101, 'table_code' => $code, 'capacity' => 2,
            ], ['%d', '%s', '%d']);
        } finally {
            $wpdb->suppress_errors(false);
        }
        self::assertFalse($duplicate, 'Table codes must be unique within an event.');

        self::assertSame(1, $wpdb->insert($table, [
            'event_term_id' => 102, 'table_code' => $code, 'capacity' => 1,
        ], ['%d', '%s', '%d']), 'Separate events may reuse table codes.');

        $wpdb->suppress_errors(true);
        try {
            $blank = $wpdb->insert($table, [
                'event_term_id' => 103, 'table_code' => '', 'capacity' => 1,
            ], ['%d', '%s', '%d']);
        } finally {
            $wpdb->suppress_errors(false);
        }
        self::assertFalse($blank, 'An empty table identity must not be persisted.');
    }
}
