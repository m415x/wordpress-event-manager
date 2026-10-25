<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use PHPUnit\Framework\TestCase;

/** WEM-85: an authorized move preserves one active reservation and appends audit. */
final class SeatingAdministrativeReassignmentPersistenceTest extends TestCase
{
    public function testAuthorizedMoveBetweenTablesPreservesOneActiveSeatAndAudit(): void
    {
        global $wpdb;
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        if (!taxonomy_exists('evento')) {
            register_taxonomy('evento', 'invitado');
        }
        if (!post_type_exists('invitado')) {
            register_post_type('invitado', ['public' => false]);
        }
        $previousActor = get_current_user_id();
        $eventId = 0;
        $guestId = 0;
        $actorId = 0;
        $tableIds = [];
        $tables = $wpdb->prefix . 'wem_event_tables';
        $reservations = $wpdb->prefix . 'wem_table_reservations';
        $audit = $wpdb->prefix . 'wem_seating_admin_audit';
        try {
            $event = wp_insert_term('WEM85-move-' . wp_generate_uuid4(), 'evento');
            self::assertFalse(is_wp_error($event));
            $eventId = (int) $event['term_id'];
            $post = wp_insert_post(['post_type' => 'invitado', 'post_status' => 'publish', 'post_title' => 'WEM85 guest'], true);
            self::assertFalse(is_wp_error($post));
            $guestId = (int) $post;
            self::assertFalse(is_wp_error(wp_set_object_terms($guestId, [$eventId], 'evento')));
            for ($i = 0; $i < 2; ++$i) {
                self::assertSame(1, $wpdb->insert($tables, [
                    'event_term_id' => $eventId,
                    'table_code' => 'WEM85-' . $i . '-' . wp_generate_uuid4(),
                    'capacity' => 1,
                ]));
                $tableIds[] = (int) $wpdb->insert_id;
            }
            $reservationId = (new \WEM_Seating_Reservation_Service())->reserve_initial($guestId, $tableIds[0]);
            self::assertGreaterThan(0, $reservationId);
            $actorId = (int) wp_create_user('wem85-' . wp_generate_uuid4(), wp_generate_password(), 'wem85@example.invalid');
            self::assertGreaterThan(0, $actorId);
            (new \WP_User($actorId))->set_role('administrator');
            wp_set_current_user($actorId);
            $failure = null;
            try {
                (new \WEM_Seating_Administrative_Reassignment_Service())->reassign(
                    $guestId,
                    $tableIds[1],
                    'Authorized table correction'
                );
            } catch (\Throwable $caught) {
                $failure = $caught;
            }
            self::assertNull($failure, 'Authorized move failed: ' . ($failure ? $failure->getMessage() : 'none'));
            $active = $wpdb->get_results($wpdb->prepare(
                "SELECT table_id,event_term_id,status,active_slot FROM {$reservations}
                 WHERE guest_id=%d AND status='active' AND active_slot=1",
                $guestId
            ), ARRAY_A);
            self::assertCount(1, $active);
            self::assertSame($tableIds[1], (int) $active[0]['table_id']);
            self::assertSame($eventId, (int) $active[0]['event_term_id']);
            $history = $wpdb->get_results($wpdb->prepare(
                "SELECT actor_user_id,operation,old_table_id,new_table_id,reason
                 FROM {$audit} WHERE guest_id=%d AND event_term_id=%d",
                $guestId,
                $eventId
            ), ARRAY_A);
            self::assertCount(1, $history);
            self::assertSame($actorId, (int) $history[0]['actor_user_id']);
            self::assertSame('reassign', $history[0]['operation']);
            self::assertSame($tableIds[0], (int) $history[0]['old_table_id']);
            self::assertSame($tableIds[1], (int) $history[0]['new_table_id']);
            self::assertSame('Authorized table correction', $history[0]['reason']);
        } finally {
            wp_set_current_user($previousActor);
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
