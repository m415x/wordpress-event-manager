<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use PHPUnit\Framework\TestCase;

/** WEM-82/T2: non-published invitado is ineligible even with a valid event. */
final class SeatingUnpublishedGuestDenialTest extends TestCase
{
    public function testDraftGuestCannotReserveAndNoRowsAreWritten(): void
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
        $audit = $wpdb->prefix . 'wem_seating_admin_audit';

        try {
            $term = wp_insert_term('WEM82-draft-' . wp_generate_uuid4(), 'evento');
            self::assertFalse(is_wp_error($term));
            $eventId = (int) $term['term_id'];

            $post = wp_insert_post([
                'post_type' => 'invitado',
                'post_status' => 'draft',
                'post_title' => 'WEM82 draft synthetic',
            ], true);
            self::assertFalse(is_wp_error($post));
            $guestId = (int) $post;
            self::assertFalse(is_wp_error(wp_set_object_terms($guestId, [$eventId], 'evento')));

            self::assertSame(1, $wpdb->insert($tables, [
                'event_term_id' => $eventId,
                'table_code' => 'WEM82-draft-' . wp_generate_uuid4(),
                'capacity' => 1,
            ], ['%d', '%s', '%d']));
            $tableId = (int) $wpdb->insert_id;

            $beforeReservations = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$reservations} WHERE guest_id=%d",
                $guestId
            ));
            $beforeAudit = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$audit} WHERE guest_id=%d",
                $guestId
            ));

            try {
                (new \WEM_Seating_Reservation_Service())->reserve_initial($guestId, $tableId);
                self::fail('Draft guest must be denied.');
            } catch (\DomainException $expected) {
                self::assertSame('Guest is not eligible.', $expected->getMessage());
            }

            self::assertSame($beforeReservations, (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$reservations} WHERE guest_id=%d",
                $guestId
            )));
            self::assertSame($beforeAudit, (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$audit} WHERE guest_id=%d",
                $guestId
            )));
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
