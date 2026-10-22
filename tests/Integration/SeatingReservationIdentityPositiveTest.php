<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

final class SeatingReservationIdentityPositiveTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
    }

    public function testReservationRejectsZeroCanonicalIdentities(): void
    {
        global $wpdb;

        \WEM_Table_Reservation_Schema::install();
        \WEM_Table_Reservation_Schema::install();
        \WEM_Table_Reservation_Schema::install();
        $table = \WEM_Table_Reservation_Schema::table_name();
        $now = '2026-10-21 12:00:00';
        \WEM_Event_Table_Schema::install();
        $parent = \WEM_Event_Table_Schema::table_name();
        self::assertSame(1, $wpdb->insert($parent, [
            'event_term_id' => 911,
            'table_code' => 'WEM74-IDENTITY-' . wp_generate_uuid4(),
            'capacity' => 5,
        ], ['%d', '%s', '%d']));
        $tableId = (int) $wpdb->insert_id;


        self::assertSame(1, $wpdb->insert($table, [
            'guest_id' => 994001, 'table_id' => $tableId, 'event_term_id' => 911,
            'status' => 'active', 'active_slot' => 1, 'created_at' => $now,
        ], ['%d', '%d', '%d', '%s', '%d', '%s']));

        foreach (['guest_id', 'table_id', 'event_term_id'] as $index => $invalidColumn) {
            $data = [
                'guest_id' => 994002 + $index, 'table_id' => $tableId,
                'event_term_id' => 911, 'status' => 'active',
                'active_slot' => 1, 'created_at' => $now,
            ];
            $data[$invalidColumn] = 0;
            $wpdb->suppress_errors(true);
            try {
                $inserted = $wpdb->insert($table, $data, ['%d', '%d', '%d', '%s', '%d', '%s']);
            } finally {
                $wpdb->suppress_errors(false);
            }
            self::assertFalse($inserted, $invalidColumn . ' must be strictly positive at the database boundary.');
        }
    }
}
