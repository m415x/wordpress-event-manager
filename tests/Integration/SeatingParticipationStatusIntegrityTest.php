<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

final class SeatingParticipationStatusIntegrityTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
    }

    public function testParticipationStoresCanonicalStatusPerGuestAndEventOnly(): void
    {
        global $wpdb;

        \WEM_Seating_Participation_Schema::install();
        $table = \WEM_Seating_Participation_Schema::table_name();
        $now = '2026-10-21 12:00:00';

        self::assertSame(1, $wpdb->insert($table, [
            'guest_id' => 991101, 'event_term_id' => 881101, 'updated_at' => $now,
        ], ['%d', '%d', '%s']));
        self::assertSame('eligible', (string) $wpdb->get_var($wpdb->prepare(
            "SELECT participation_status FROM {$table} WHERE guest_id = %d AND event_term_id = %d",
            991101, 881101
        )), 'A newly persisted participation defaults to eligible.');

        self::assertSame(1, $wpdb->update($table, [
            'participation_status' => 'canceled', 'canceled_at' => $now, 'updated_at' => $now,
        ], ['guest_id' => 991101, 'event_term_id' => 881101],
            ['%s', '%s', '%s'], ['%d', '%d']));
        self::assertSame('canceled', (string) $wpdb->get_var($wpdb->prepare(
            "SELECT participation_status FROM {$table} WHERE guest_id = %d AND event_term_id = %d",
            991101, 881101
        )));

        $wpdb->suppress_errors(true);
        try {
            $duplicate = $wpdb->insert($table, [
                'guest_id' => 991101, 'event_term_id' => 881101,
                'participation_status' => 'eligible', 'updated_at' => $now,
            ], ['%d', '%d', '%s', '%s']);
        } finally {
            $wpdb->suppress_errors(false);
        }
        self::assertFalse($duplicate, 'Duplicate guest/event participation identity is forbidden.');

        self::assertSame(1, $wpdb->insert($table, [
            'guest_id' => 991101, 'event_term_id' => 881102, 'updated_at' => $now,
        ], ['%d', '%d', '%s']), 'The same guest may have independent per-event participation records.');

        $wpdb->suppress_errors(true);
        try {
            $invalid = $wpdb->insert($table, [
                'guest_id' => 991102, 'event_term_id' => 881101,
                'participation_status' => 'unknown', 'updated_at' => $now,
            ], ['%d', '%d', '%s', '%s']);
        } finally {
            $wpdb->suppress_errors(false);
        }
        self::assertFalse($invalid, 'An unknown participation state must be rejected at the database boundary.');
    }
}
