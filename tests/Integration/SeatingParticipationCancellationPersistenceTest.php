<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use PHPUnit\Framework\TestCase;

/** WEM-86: authorized participation cancellation must release seat and append audit. */
final class SeatingParticipationCancellationPersistenceTest extends TestCase
{
    public function testAuthorizedParticipationCancellationIsAtomicWithSeatRelease(): void
    {
        global $wpdb;
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        if (!taxonomy_exists('evento')) {
            register_taxonomy('evento', 'invitado');
        }
        if (!post_type_exists('invitado')) {
            register_post_type('invitado', ['public' => false]);
        }

        $prior = get_current_user_id();
        $eventId = 0;
        $guestId = 0;
        $tableId = 0;
        $actorId = 0;
        $tables = $wpdb->prefix . 'wem_event_tables';
        $reservations = $wpdb->prefix . 'wem_table_reservations';
        $participation = $wpdb->prefix . 'wem_seating_participation';
        $audit = $wpdb->prefix . 'wem_seating_admin_audit';
        try {
            $term = wp_insert_term('WEM86-cancel-' . wp_generate_uuid4(), 'evento');
            self::assertFalse(is_wp_error($term));
            $eventId = (int) $term['term_id'];
            $post = wp_insert_post(['post_type' => 'invitado','post_status' => 'publish','post_title' => 'WEM86 synthetic'], true);
            self::assertFalse(is_wp_error($post));
            $guestId = (int) $post;
            self::assertFalse(is_wp_error(wp_set_object_terms($guestId, [$eventId], 'evento')));
            self::assertSame(1, $wpdb->insert($tables, [
                'event_term_id' => $eventId,
                'table_code' => 'WEM86-' . wp_generate_uuid4(),
                'capacity' => 1,
            ]));
            $tableId = (int) $wpdb->insert_id;
            $reservationId = (new \WEM_Seating_Reservation_Service())->reserve_initial($guestId, $tableId);
            self::assertGreaterThan(0, $reservationId);
            $actorId = (int) wp_create_user('wem86-' . wp_generate_uuid4(), wp_generate_password(), 'wem86@example.invalid');
            self::assertGreaterThan(0, $actorId);
            (new \WP_User($actorId))->set_role('administrator');
            wp_set_current_user($actorId);

            $failure = null;
            try {
                (new \WEM_Seating_Participation_Cancellation_Service())->cancel($guestId, 'Canceled participation');
            } catch (\Throwable $caught) {
                $failure = $caught;
            }
            self::assertNull($failure, 'Authorized cancellation failed: ' . ($failure ? $failure->getMessage() : 'none'));
            $state = $wpdb->get_row($wpdb->prepare(
                "SELECT participation_status,canceled_at FROM {$participation}
                 WHERE guest_id=%d AND event_term_id=%d",
                $guestId,
                $eventId
            ), ARRAY_A);
            self::assertIsArray($state);
            self::assertSame('canceled', $state['participation_status']);
            self::assertNotEmpty($state['canceled_at']);
            self::assertSame(0, (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$reservations} WHERE guest_id=%d AND status='active' AND active_slot=1",
                $guestId
            )));
            $history = $wpdb->get_results($wpdb->prepare(
                "SELECT actor_user_id,operation,old_table_id,new_table_id,reason
                 FROM {$audit} WHERE guest_id=%d AND event_term_id=%d",
                $guestId,
                $eventId
            ), ARRAY_A);
            self::assertCount(1, $history);
            self::assertSame($actorId, (int) $history[0]['actor_user_id']);
            self::assertSame('participation_cancel', $history[0]['operation']);
            self::assertSame($tableId, (int) $history[0]['old_table_id']);
            self::assertNull($history[0]['new_table_id']);
            self::assertSame('Canceled participation', $history[0]['reason']);
        } finally {
            wp_set_current_user($prior);
            if ($guestId > 0) {
                $wpdb->delete($audit, ['guest_id' => $guestId]);
                $wpdb->delete($reservations, ['guest_id' => $guestId]);
                $wpdb->delete($participation, ['guest_id' => $guestId]);
            }
            if ($tableId > 0) {
                $wpdb->delete($tables, ['id' => $tableId]);
            }
            if ($guestId > 0) {
                wp_delete_post($guestId, true);
            }
            if ($eventId > 0) {
                wp_delete_term($eventId, 'evento');
            }
            if ($actorId > 0) {
                wp_delete_user($actorId);
            }
        }
    }
}
