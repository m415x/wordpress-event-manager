<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

final class SeatingReservationEventTableIntegrityTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
    }

    public function testReservationCannotReferenceTableFromAnotherEvent(): void
    {
        global $wpdb;

        \WEM_Event_Table_Schema::install();
        \WEM_Table_Reservation_Schema::install();
        $tables = \WEM_Event_Table_Schema::table_name();
        $reservations = \WEM_Table_Reservation_Schema::table_name();
        $eventA = 915001;
        $eventB = 915002;
        $now = '2026-10-21 12:00:00';

        self::assertSame(1, $wpdb->insert($tables, [
            'event_term_id' => $eventA,
            'table_code' => 'WEM74-SCOPE-' . wp_generate_uuid4(),
            'capacity' => 2,
        ], ['%d', '%s', '%d']));
        $tableId = (int) $wpdb->insert_id;

        self::assertSame(1, $wpdb->insert($reservations, [
            'guest_id' => 995001,
            'table_id' => $tableId,
            'event_term_id' => $eventA,
            'status' => 'active', 'active_slot' => 1, 'created_at' => $now,
        ], ['%d', '%d', '%d', '%s', '%d', '%s']));

        $wpdb->suppress_errors(true);
        try {
            $crossEvent = $wpdb->insert($reservations, [
                'guest_id' => 995002,
                'table_id' => $tableId,
                'event_term_id' => $eventB,
                'status' => 'active', 'active_slot' => 1, 'created_at' => $now,
            ], ['%d', '%d', '%d', '%s', '%d', '%s']);
        } finally {
            $wpdb->suppress_errors(false);
        }

        self::assertFalse($crossEvent, 'A reservation must not reference a table from another event.');
    }
}
