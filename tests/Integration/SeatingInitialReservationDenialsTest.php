<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use PHPUnit\Framework\TestCase;

/** WEM-82/T2: durable DENY for cancellation and exhausted capacity. */
final class SeatingInitialReservationDenialsTest extends TestCase
{
    public function testCanceledParticipationAndFullTableLeaveReservationsUnchanged(): void
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
        $guests = [];
        $tableId = 0;
        $tables = $wpdb->prefix . 'wem_event_tables';
        $reservations = $wpdb->prefix . 'wem_table_reservations';
        $participation = $wpdb->prefix . 'wem_seating_participation';

        try {
            $term = wp_insert_term('WEM82-deny-' . wp_generate_uuid4(), 'evento');
            self::assertFalse(is_wp_error($term));
            $eventId = (int) $term['term_id'];
            foreach (['canceled', 'active', 'overflow'] as $name) {
                $guest = wp_insert_post([
                    'post_type' => 'invitado',
                    'post_status' => 'publish',
                    'post_title' => 'WEM82-' . $name . '-' . wp_generate_uuid4(),
                ], true);
                self::assertFalse(is_wp_error($guest));
                $id = (int) $guest;
                $guests[$name] = $id;
                self::assertFalse(is_wp_error(wp_set_object_terms($id, [$eventId], 'evento')));
            }

            self::assertSame(1, $wpdb->insert($tables, [
                'event_term_id' => $eventId,
                'table_code' => 'WEM82-deny-' . wp_generate_uuid4(),
                'capacity' => 1,
            ], ['%d', '%s', '%d']));
            $tableId = (int) $wpdb->insert_id;
            self::assertSame(1, $wpdb->insert($participation, [
                'guest_id' => $guests['canceled'],
                'event_term_id' => $eventId,
                'participation_status' => 'canceled',
                'canceled_at' => gmdate('Y-m-d H:i:s'),
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ], ['%d', '%d', '%s', '%s', '%s']));

            try {
                (new \WEM_Seating_Reservation_Service())->reserve_initial($guests['canceled'], $tableId);
                self::fail('Canceled participant must not reserve.');
            } catch (\DomainException $expected) {
                self::assertSame('Participation is canceled or invalid.', $expected->getMessage());
            }
            self::assertSame(0, (int) $wpdb->get_var("SELECT COUNT(*) FROM {$reservations} WHERE table_id=" . (int) $tableId));

            $firstId = (new \WEM_Seating_Reservation_Service())->reserve_initial($guests['active'], $tableId);
            self::assertGreaterThan(0, $firstId);
            try {
                (new \WEM_Seating_Reservation_Service())->reserve_initial($guests['overflow'], $tableId);
                self::fail('Capacity-one table must reject a second guest.');
            } catch (\DomainException $expected) {
                self::assertSame('Table is full or unavailable.', $expected->getMessage());
            }
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT id,guest_id,status FROM {$reservations} WHERE table_id=%d",
                $tableId
            ), ARRAY_A);
            self::assertCount(1, $rows, 'Denials cannot change table occupancy.');
            self::assertSame((int) $firstId, (int) $rows[0]['id']);
            self::assertSame($guests['active'], (int) $rows[0]['guest_id']);
            self::assertSame('active', $rows[0]['status']);
        } finally {
            foreach ($guests as $id) {
                $wpdb->delete($reservations, ['guest_id' => $id], ['%d']);
                $wpdb->delete($participation, ['guest_id' => $id], ['%d']);
            }
            if ($tableId > 0) {
                $wpdb->delete($tables, ['id' => $tableId], ['%d']);
            }
            foreach ($guests as $id) {
                wp_delete_post($id, true);
            }
            if ($eventId > 0) {
                wp_delete_term($eventId, 'evento');
            }
        }
    }
}
