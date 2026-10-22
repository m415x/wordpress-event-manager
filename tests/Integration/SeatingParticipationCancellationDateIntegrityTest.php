<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

final class SeatingParticipationCancellationDateIntegrityTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
    }

    public function testCanceledParticipationRequiresDateAndEligibleParticipationHasNoCancellationDate(): void
    {
        global $wpdb;

        \WEM_Seating_Participation_Schema::install();
        $table = \WEM_Seating_Participation_Schema::table_name();
        $now = '2026-10-21 12:00:00';

        self::assertSame(1, $wpdb->insert($table, [
            'guest_id' => 992001, 'event_term_id' => 882001,
            'participation_status' => 'eligible', 'updated_at' => $now,
        ], ['%d', '%d', '%s', '%s']));
        self::assertSame(1, $wpdb->insert($table, [
            'guest_id' => 992002, 'event_term_id' => 882001,
            'participation_status' => 'canceled',
            'canceled_at' => $now, 'updated_at' => $now,
        ], ['%d', '%d', '%s', '%s', '%s']));

        $wpdb->suppress_errors(true);
        try {
            $missingDate = $wpdb->insert($table, [
                'guest_id' => 992003, 'event_term_id' => 882001,
                'participation_status' => 'canceled', 'updated_at' => $now,
            ], ['%d', '%d', '%s', '%s']);
        } finally {
            $wpdb->suppress_errors(false);
        }
        self::assertFalse($missingDate, 'Canceled participation must have a cancellation date.');

        $wpdb->suppress_errors(true);
        try {
            $wrongDate = $wpdb->insert($table, [
                'guest_id' => 992004, 'event_term_id' => 882001,
                'participation_status' => 'eligible',
                'canceled_at' => $now, 'updated_at' => $now,
            ], ['%d', '%d', '%s', '%s', '%s']);
        } finally {
            $wpdb->suppress_errors(false);
        }
        self::assertFalse($wrongDate, 'Eligible participation must not have a cancellation date.');
    }
}
