<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use PHPUnit\Framework\TestCase;

/** WEM-85: audit failure must roll back the reassignment, including occupancy. */
final class SeatingAdministrativeReassignmentRollbackTest extends TestCase
{
    public function testAuditInsertFailurePreservesOriginalTableAndNoPartialHistory(): void
    {
        global $wpdb;
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        if (!taxonomy_exists('evento')) {
            register_taxonomy('evento', 'invitado');
        }
        if (!post_type_exists('invitado')) {
            register_post_type('invitado', ['public' => false]);
        }

        $priorActor = get_current_user_id();
        $eventId = 0;
        $guestId = 0;
        $actorId = 0;
        $tableIds = [];
        $tables = $wpdb->prefix . 'wem_event_tables';
        $reservations = $wpdb->prefix . 'wem_table_reservations';
        $audit = $wpdb->prefix . 'wem_seating_admin_audit';
        $constraint = 'wem85_audit_fault_' . substr(md5((string) microtime(true)), 0, 8);
        $constraintAdded = false;
        try {
            $term = wp_insert_term('WEM85-rollback-' . wp_generate_uuid4(), 'evento');
            self::assertFalse(is_wp_error($term));
            $eventId = (int) $term['term_id'];
            $post = wp_insert_post([
                'post_type' => 'invitado',
                'post_status' => 'publish',
                'post_title' => 'WEM85 rollback'
            ], true);
            self::assertFalse(is_wp_error($post));
            $guestId = (int) $post;
            self::assertFalse(is_wp_error(wp_set_object_terms($guestId, [$eventId], 'evento')));
            for ($i = 0; $i < 2; ++$i) {
                self::assertSame(1, $wpdb->insert($tables, [
                    'event_term_id' => $eventId,
                    'table_code' => 'WEM85-rollback-' . $i . '-' . wp_generate_uuid4(),
                    'capacity' => 1,
                ]));
                $tableIds[] = (int) $wpdb->insert_id;
            }
            $reservationId = (new \WEM_Seating_Reservation_Service())->reserve_initial($guestId, $tableIds[0]);
            self::assertGreaterThan(0, $reservationId);
            $actorId = (int) wp_create_user(
                'wem85-audit-' . wp_generate_uuid4(),
                wp_generate_password(),
                'wem85-audit@example.invalid'
            );
            self::assertGreaterThan(0, $actorId);
            (new \WP_User($actorId))->set_role('administrator');
            wp_set_current_user($actorId);

            // DDL before the transactional operation, never within its transaction.
            self::assertNotFalse($wpdb->query(
                "ALTER TABLE {$audit} ADD CONSTRAINT {$constraint} CHECK (actor_user_id <> {$actorId})"
            ));
            $constraintAdded = true;

            $failure = null;
            try {
                (new \WEM_Seating_Administrative_Reassignment_Service())->reassign(
                    $guestId,
                    $tableIds[1],
                    'Simulated reassignment audit fault'
                );
            } catch (\Throwable $caught) {
                $failure = $caught;
            }
            self::assertNotNull($failure, 'Audit failure must abort reassignment.');
            $active = $wpdb->get_results($wpdb->prepare(
                "SELECT table_id,active_slot,status FROM {$reservations}
                 WHERE guest_id=%d AND status='active' AND active_slot=1",
                $guestId
            ), ARRAY_A);
            self::assertCount(1, $active);
            self::assertSame($tableIds[0], (int) $active[0]['table_id']);
            self::assertSame(1, (int) $active[0]['active_slot']);
            self::assertSame(0, (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$audit} WHERE guest_id=%d AND operation='reassign'",
                $guestId
            )));
        } finally {
            if ($constraintAdded) {
                $wpdb->query("ALTER TABLE {$audit} DROP CONSTRAINT {$constraint}");
            }
            wp_set_current_user($priorActor);
            if ($guestId > 0) {
                $wpdb->delete($audit, ['guest_id' => $guestId]);
                $wpdb->delete($reservations, ['guest_id' => $guestId]);
            }
            foreach ($tableIds as $id) {
                $wpdb->delete($tables, ['id' => $id]);
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
