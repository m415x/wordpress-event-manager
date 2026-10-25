<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use PHPUnit\Framework\TestCase;

/** WEM-86: audit failure must restore both eligibility and active seat. */
final class SeatingParticipationCancellationRollbackTest extends TestCase
{
    public function testAuditFailureRollsBackParticipationAndSeatRelease(): void
    {
        global $wpdb;
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        if (!taxonomy_exists('evento')) {
            register_taxonomy('evento', 'invitado');
        }
        if (!post_type_exists('invitado')) {
            register_post_type('invitado', ['public' => false]);
        }
        $oldActor = get_current_user_id();
        $eventId = 0;
        $guestId = 0;
        $tableId = 0;
        $actorId = 0;
        $constraintAdded = false;
        $tables = $wpdb->prefix . 'wem_event_tables';
        $reservations = $wpdb->prefix . 'wem_table_reservations';
        $participation = $wpdb->prefix . 'wem_seating_participation';
        $audit = $wpdb->prefix . 'wem_seating_admin_audit';
        $constraint = 'wem86_audit_fault_' . substr(md5((string) microtime(true)), 0, 8);
        try {
            $term = wp_insert_term('WEM86-fault-' . wp_generate_uuid4(), 'evento');
            self::assertFalse(is_wp_error($term));
            $eventId = (int)$term['term_id'];
            $post = wp_insert_post(['post_type' => 'invitado','post_status' => 'publish','post_title' => 'WEM86 rollback'], true);
            self::assertFalse(is_wp_error($post));
            $guestId = (int)$post;
            self::assertFalse(is_wp_error(wp_set_object_terms($guestId, [$eventId], 'evento')));
            self::assertSame(1, $wpdb->insert($tables, [
                'event_term_id' => $eventId,'table_code' => 'WEM86-rollback-' . wp_generate_uuid4(),'capacity' => 1
            ]));
            $tableId = (int)$wpdb->insert_id;
            $reservationId = (new \WEM_Seating_Reservation_Service())->reserve_initial($guestId, $tableId);
            self::assertGreaterThan(0, $reservationId);
            $actorId = (int)wp_create_user('wem86-fault-' . wp_generate_uuid4(), wp_generate_password(), 'wem86-fault@example.invalid');
            self::assertGreaterThan(0, $actorId);
            (new \WP_User($actorId))->set_role('administrator');
            wp_set_current_user($actorId);
            // Inject an actual persistent audit failure before opening a transaction.
            self::assertNotFalse($wpdb->query(
                "ALTER TABLE {$audit} ADD CONSTRAINT {$constraint} CHECK (actor_user_id <> {$actorId})"
            ));
            $constraintAdded = true;
            $failure = null;
            try {
                (new \WEM_Seating_Participation_Cancellation_Service())->cancel($guestId, 'Audit failure rollback');
            } catch (\Throwable $caught) {
                $failure = $caught;
            }
            self::assertNotNull($failure, 'Audit rejection must abort cancellation.');
            self::assertSame('active', (string)$wpdb->get_var($wpdb->prepare(
                "SELECT status FROM {$reservations} WHERE id=%d",
                $reservationId
            )));
            self::assertSame(1, (int)$wpdb->get_var($wpdb->prepare(
                "SELECT active_slot FROM {$reservations} WHERE id=%d",
                $reservationId
            )));
            self::assertNull($wpdb->get_var($wpdb->prepare(
                "SELECT canceled_at FROM {$reservations} WHERE id=%d",
                $reservationId
            )));
            self::assertSame(0, (int)$wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$participation} WHERE guest_id=%d AND event_term_id=%d AND participation_status='canceled'",
                $guestId,
                $eventId
            )));
            self::assertSame(0, (int)$wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$audit} WHERE guest_id=%d AND operation='participation_cancel'",
                $guestId
            )));
        } finally {
            if ($constraintAdded) {
                $wpdb->query("ALTER TABLE {$audit} DROP CONSTRAINT {$constraint}");
            }
            wp_set_current_user($oldActor);
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
