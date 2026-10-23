<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use PHPUnit\Framework\TestCase;

/** WEM-82: reject cross-event seating without mutating durable reservations. */
final class SeatingCrossEventDenialTest extends TestCase
{
    public function testGuestCannotReserveTableBelongingToAnotherEvent(): void
    {
        global $wpdb;
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        if (!taxonomy_exists('evento')) {
            register_taxonomy('evento', 'invitado');
        }
        if (!post_type_exists('invitado')) {
            register_post_type('invitado', ['public' => false]);
        }

        $terms = [];
        $guestId = 0;
        $tableId = 0;
        $tables = $wpdb->prefix . 'wem_event_tables';
        $reservations = $wpdb->prefix . 'wem_table_reservations';

        try {
            foreach (['A', 'B'] as $suffix) {
                $created = wp_insert_term('WEM82-cross-' . $suffix . '-' . wp_generate_uuid4(), 'evento');
                self::assertFalse(is_wp_error($created));
                $terms[] = (int) $created['term_id'];
            }
            $guest = wp_insert_post([
                'post_type' => 'invitado',
                'post_status' => 'publish',
                'post_title' => 'WEM82 cross-event synthetic',
            ], true);
            self::assertFalse(is_wp_error($guest));
            $guestId = (int) $guest;
            self::assertFalse(is_wp_error(wp_set_object_terms($guestId, [$terms[0]], 'evento')));

            self::assertSame(1, $wpdb->insert($tables, [
                'event_term_id' => $terms[1],
                'table_code' => 'WEM82-cross-' . wp_generate_uuid4(),
                'capacity' => 1,
            ], ['%d', '%s', '%d']));
            $tableId = (int) $wpdb->insert_id;
            $before = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$reservations} WHERE guest_id=%d", $guestId
            ));

            try {
                (new \WEM_Seating_Reservation_Service())->reserve_initial($guestId, $tableId);
                self::fail('Cross-event initial reservation must be denied.');
            } catch (\DomainException $expected) {
                self::assertSame(
                    'Guest and table belong to different events.',
                    $expected->getMessage()
                );
            }

            self::assertSame($before, (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$reservations} WHERE guest_id=%d", $guestId
            )), 'DENY must not mutate the reservation ledger.');
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
            foreach ($terms as $termId) {
                wp_delete_term($termId, 'evento');
            }
        }
    }
}
