<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use PHPUnit\Framework\TestCase;

/** WEM-85: a full destination must preserve source seat and audit history. */
final class SeatingAdministrativeReassignmentCapacityTest extends TestCase
{
    public function testFullDestinationDeniesMoveWithoutMutation(): void
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
        $actorId = 0;
        $guests = [];
        $tables = [];
        $tableSql = $wpdb->prefix . 'wem_event_tables';
        $reservationSql = $wpdb->prefix . 'wem_table_reservations';
        $auditSql = $wpdb->prefix . 'wem_seating_admin_audit';
        try {
            $term = wp_insert_term('WEM85-full-' . wp_generate_uuid4(), 'evento');
            self::assertFalse(is_wp_error($term));
            $eventId = (int) $term['term_id'];
            for ($i = 0; $i < 2; ++$i) {
                $post = wp_insert_post([
                    'post_type' => 'invitado', 'post_status' => 'publish',
                    'post_title' => 'WEM85 capacity ' . $i,
                ], true);
                self::assertFalse(is_wp_error($post));
                $guest = (int) $post;
                $guests[] = $guest;
                self::assertFalse(is_wp_error(wp_set_object_terms($guest, [$eventId], 'evento')));
                self::assertSame(1, $wpdb->insert($tableSql, [
                    'event_term_id' => $eventId,
                    'table_code' => 'WEM85-full-' . $i . '-' . wp_generate_uuid4(),
                    'capacity' => 1,
                ]));
                $tables[] = (int) $wpdb->insert_id;
                self::assertGreaterThan(0, (new \WEM_Seating_Reservation_Service())->reserve_initial($guest, $tables[$i]));
            }
            $actorId = (int) wp_create_user('wem85-full-' . wp_generate_uuid4(), wp_generate_password(), 'wem85-full@example.invalid');
            self::assertGreaterThan(0, $actorId);
            (new \WP_User($actorId))->set_role('administrator');
            wp_set_current_user($actorId);

            $error = null;
            try {
                (new \WEM_Seating_Administrative_Reassignment_Service())->reassign($guests[0], $tables[1], 'Full table');
            } catch (\Throwable $caught) {
                $error = $caught;
            }
            self::assertInstanceOf(\DomainException::class, $error);
            self::assertSame('Target table is full or unavailable.', $error->getMessage());

            foreach ($guests as $i => $guest) {
                self::assertSame($tables[$i], (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT table_id FROM {$reservationSql}
                     WHERE guest_id=%d AND status='active' AND active_slot=1",
                    $guest
                )));
            }
            self::assertSame(0, (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$auditSql} WHERE guest_id=%d AND operation='reassign'",
                $guests[0]
            )));
        } finally {
            wp_set_current_user($priorActor);
            foreach ($guests as $guest) {
                $wpdb->delete($auditSql, ['guest_id' => $guest]);
                $wpdb->delete($reservationSql, ['guest_id' => $guest]);
            }
            foreach ($tables as $table) {
                $wpdb->delete($tableSql, ['id' => $table]);
            }
            foreach ($guests as $guest) {
                wp_delete_post($guest, true);
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
