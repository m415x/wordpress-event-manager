<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use PHPUnit\Framework\TestCase;

/** WEM-84: failed audit INSERT must roll back the seat release. */
final class SeatingAdministrativeCancellationRollbackTest extends TestCase
{
    public function testFailedAuditInsertRollsBackCanceledReservation(): void
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
        $termId = 0;
        $guestId = 0;
        $tableId = 0;
        $actorId = 0;
        $tables = $wpdb->prefix . 'wem_event_tables';
        $reservations = $wpdb->prefix . 'wem_table_reservations';
        $audit = $wpdb->prefix . 'wem_seating_admin_audit';
        $constraint = 'wem84_audit_fault_' . substr(md5((string) microtime(true)), 0, 8);
        $constraintAdded = false;
        try {
            $term = wp_insert_term('WEM84-rollback-' . wp_generate_uuid4(), 'evento');
            self::assertFalse(is_wp_error($term));
            $termId = (int) $term['term_id'];
            $guest = wp_insert_post([
                'post_type' => 'invitado',
                'post_status' => 'publish',
                'post_title' => 'WEM84-rollback'
            ], true);
            self::assertFalse(is_wp_error($guest));
            $guestId = (int) $guest;
            self::assertFalse(is_wp_error(wp_set_object_terms($guestId, [$termId], 'evento')));
            self::assertSame(1, $wpdb->insert($tables, [
                'event_term_id' => $termId,'table_code' => 'WEM84-rollback-' . wp_generate_uuid4(),'capacity' => 1,
            ]));
            $tableId = (int) $wpdb->insert_id;
            $reservationId = (new \WEM_Seating_Reservation_Service())->reserve_initial($guestId, $tableId);
            self::assertGreaterThan(0, $reservationId);
            $actorId = (int) wp_create_user(
                'wem84-audit-' . wp_generate_uuid4(),
                wp_generate_password(),
                'wem84-audit@example.invalid'
            );
            self::assertGreaterThan(0, $actorId);
            (new \WP_User($actorId))->set_role('administrator');
            wp_set_current_user($actorId);

            // DDL is intentionally installed BEFORE the operation, never inside a transaction.
            // A CHECK that rejects this test's audit actor simulates a persistent INSERT failure.
            $ddl = "ALTER TABLE {$audit} ADD CONSTRAINT {$constraint} CHECK (actor_user_id <> {$actorId})";
            self::assertNotFalse($wpdb->query($ddl));
            $constraintAdded = true;

            $failure = null;
            try {
                (new \WEM_Seating_Administrative_Cancellation_Service())->cancel($guestId, 'Simulated audit failure');
            } catch (\Throwable $caught) {
                $failure = $caught;
            }
            self::assertNotNull($failure, 'Audit insertion must fail and cancel must not return success.');
            self::assertSame('active', (string) $wpdb->get_var($wpdb->prepare(
                "SELECT status FROM {$reservations} WHERE id=%d",
                $reservationId
            )));
            self::assertSame(1, (int) $wpdb->get_var($wpdb->prepare(
                "SELECT active_slot FROM {$reservations} WHERE id=%d",
                $reservationId
            )));
            self::assertNull($wpdb->get_var($wpdb->prepare(
                "SELECT canceled_at FROM {$reservations} WHERE id=%d",
                $reservationId
            )));
            self::assertSame(0, (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$audit} WHERE guest_id=%d AND actor_user_id=%d",
                $guestId,
                $actorId
            )));
        } finally {
            if ($constraintAdded) {
                $wpdb->query("ALTER TABLE {$audit} DROP CONSTRAINT {$constraint}");
            }
            wp_set_current_user($prior);
            if ($guestId > 0) {
                $wpdb->delete($audit, ['guest_id' => $guestId]);
                $wpdb->delete($reservations, ['guest_id' => $guestId]);
            }
            if ($tableId > 0) {
                $wpdb->delete($tables, ['id' => $tableId]);
            }
            if ($guestId > 0) {
                wp_delete_post($guestId, true);
            }
            if ($termId > 0) {
                wp_delete_term($termId, 'evento');
            }
            if ($actorId > 0) {
                wp_delete_user($actorId);
            }
        }
    }
}
