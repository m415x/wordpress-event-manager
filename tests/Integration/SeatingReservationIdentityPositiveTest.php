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

        self::assertSame(1, $wpdb->insert($table, [
            'guest_id' => 994001, 'table_id' => 1111, 'event_term_id' => 911,
            'status' => 'active', 'active_slot' => 1, 'created_at' => $now,
        ], ['%d', '%d', '%d', '%s', '%d', '%s']));

        foreach (['guest_id', 'table_id', 'event_term_id'] as $index => $invalidColumn) {
            $data = [
                'guest_id' => 994002 + $index, 'table_id' => 1112 + $index,
                'event_term_id' => 912 + $index, 'status' => 'active',
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
