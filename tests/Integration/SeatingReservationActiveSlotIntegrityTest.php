<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

final class SeatingReservationActiveSlotIntegrityTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
    }

    public function testActiveReservationSlotIsCanonicalAndUniquePerGuest(): void
    {
        global $wpdb;

        \WEM_Table_Reservation_Schema::install();
        \WEM_Table_Reservation_Schema::install();
        \WEM_Table_Reservation_Schema::install();
        $table = \WEM_Table_Reservation_Schema::table_name();
        $now = '2026-10-21 12:00:00';

        self::assertSame(1, $wpdb->insert($table, [
            'guest_id' => 993001, 'table_id' => 1001, 'event_term_id' => 901,
            'status' => 'active', 'active_slot' => 1, 'created_at' => $now,
        ], ['%d', '%d', '%d', '%s', '%d', '%s']));

        $wpdb->suppress_errors(true);
        try {
            $duplicate = $wpdb->insert($table, [
                'guest_id' => 993001, 'table_id' => 1002, 'event_term_id' => 901,
                'status' => 'active', 'active_slot' => 1, 'created_at' => $now,
            ], ['%d', '%d', '%d', '%s', '%d', '%s']);
        } finally {
            $wpdb->suppress_errors(false);
        }
        self::assertFalse($duplicate, 'Two active reservations for one guest must be physically impossible.');

        self::assertSame(1, $wpdb->insert($table, [
            'guest_id' => 993002, 'table_id' => 1001, 'event_term_id' => 901,
            'status' => 'canceled', 'active_slot' => null, 'created_at' => $now,
            'canceled_at' => $now,
        ], ['%d', '%d', '%d', '%s', '%d', '%s', '%s']));

        $wpdb->suppress_errors(true);
        try {
            $invalid = $wpdb->insert($table, [
                'guest_id' => 993003, 'table_id' => 1001, 'event_term_id' => 901,
                'status' => 'active', 'active_slot' => null, 'created_at' => $now,
            ], ['%d', '%d', '%d', '%s', '%s', '%s']);
        } finally {
            $wpdb->suppress_errors(false);
        }
        self::assertFalse($invalid, 'Active status must require active_slot=1, never NULL.');
    }
}
