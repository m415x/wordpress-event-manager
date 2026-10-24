<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * WEM-82/T2: real InnoDB reservation, no WP_UnitTestCase transaction wrapper.
 * Synthetic WP identities and seating rows are scoped and removed in finally.
 */
final class SeatingInitialReservationPersistenceTest extends TestCase
{
    public function testEligibleGuestCanReserveOneSeatAtomically(): void
    {
        global $wpdb;
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';

        if (!taxonomy_exists('evento')) {
            register_taxonomy('evento', 'invitado');
        }
        if (!post_type_exists('invitado')) {
            register_post_type('invitado', ['public' => false]);
        }

        $eventId = 0;
        $guestId = 0;
        $tableId = 0;
        $tables = $wpdb->prefix . 'wem_event_tables';
        $reservations = $wpdb->prefix . 'wem_table_reservations';
        $label = 'WEM82-' . wp_generate_uuid4();

        try {
            $term = wp_insert_term($label, 'evento');
            self::assertFalse(is_wp_error($term), 'Synthetic evento must be created.');
            $eventId = (int) $term['term_id'];

            $guestId = (int) wp_insert_post([
                'post_type' => 'invitado',
                'post_status' => 'publish',
                'post_title' => $label,
            ], true);
            self::assertGreaterThan(0, $guestId);
            self::assertFalse(is_wp_error(wp_set_object_terms($guestId, [$eventId], 'evento')));

            self::assertSame(1, $wpdb->insert($tables, [
                'event_term_id' => $eventId,
                'table_code' => $label,
                'capacity' => 1,
            ], ['%d', '%s', '%d']));
            $tableId = (int) $wpdb->insert_id;

            $transactionState = $wpdb->get_row(
                'SELECT CONNECTION_ID() AS connection_id, @@in_transaction AS in_transaction, @@autocommit AS autocommit',
                ARRAY_A
            );
            self::assertIsArray($transactionState);
            self::assertSame(
                0,
                (int) $transactionState['in_transaction'],
                'DIAGNOSTIC WEM-87: after fixture setup connection=' .
                $transactionState['connection_id'] . ', autocommit=' .
                $transactionState['autocommit'] . '; shared PHPUnit transaction is still open.'
            );

            $failure = null;
            try {
                (new \WEM_Seating_Reservation_Service())->reserve_initial($guestId, $tableId);
            } catch (\Throwable $caught) {
                $failure = $caught;
            }

            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT guest_id,table_id,event_term_id,status,active_slot
                 FROM {$reservations} WHERE guest_id=%d AND table_id=%d",
                $guestId, $tableId
            ), ARRAY_A);

            self::assertCount(1, $rows, 'Eligible same-event guest must receive one durable reservation; boundary failure: '
                . ($failure ? get_class($failure) . ': ' . $failure->getMessage() : 'none'));
            self::assertSame($eventId, (int) $rows[0]['event_term_id']);
            self::assertSame('active', $rows[0]['status']);
            self::assertSame(1, (int) $rows[0]['active_slot']);
            self::assertNull($failure, 'Successful reserve_initial must not throw.');
            
            // Same guest may not reserve twice, even the same table; first row
            // remains unchanged and the failed attempt must not create a row.
            $firstReservationId = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$reservations} WHERE guest_id=%d AND active_slot=1",
                $guestId
            ));
            self::assertGreaterThan(0, $firstReservationId);
            try {
                (new \WEM_Seating_Reservation_Service())->reserve_initial($guestId, $tableId);
                self::fail('Duplicate initial reservation must be denied.');
            } catch (\DomainException $expected) {
                self::assertSame('Guest already has an active reservation.', $expected->getMessage());
            }
            self::assertSame(1, (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$reservations} WHERE guest_id=%d", $guestId
            )), 'Duplicate DENY must not write another reservation.');
            self::assertSame($firstReservationId, (int) $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$reservations} WHERE guest_id=%d AND active_slot=1",
                $guestId
            )), 'Duplicate DENY must preserve the original reservation.');

        } finally {
            if ($guestId > 0) {
                $wpdb->delete($reservations, ['guest_id' => $guestId], ['%d']);
            }
            if ($tableId > 0) {
                $wpdb->delete($tables, ['id' => $tableId], ['%d']);
            }
            if ($guestId > 0) {
                wp_delete_post($guestId, true);
            }
            if ($eventId > 0) {
                wp_delete_term($eventId, 'evento');
            }
        }
    }
}
