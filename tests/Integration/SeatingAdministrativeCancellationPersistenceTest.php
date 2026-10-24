<?php
declare(strict_types=1);

namespace WEM\Tests\Integration;

use PHPUnit\Framework\TestCase;

/** WEM-84: admin cancellation must release a real seat and append evidence. */
final class SeatingAdministrativeCancellationPersistenceTest extends TestCase
{
    public function testAuthorizedCancellationReleasesSeatAndAppendsAudit(): void
    {
        global $wpdb;
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        if (!taxonomy_exists('evento')) { register_taxonomy('evento', 'invitado'); }
        if (!post_type_exists('invitado')) { register_post_type('invitado', ['public' => false]); }

        $priorActor = get_current_user_id();
        $eventId = 0;
        $guestId = 0;
        $tableId = 0;
        $actorId = 0;
        $reservations = $wpdb->prefix . 'wem_table_reservations';
        $tables = $wpdb->prefix . 'wem_event_tables';
        $audit = $wpdb->prefix . 'wem_seating_admin_audit';
        try {
            $term = wp_insert_term('WEM84-' . wp_generate_uuid4(), 'evento');
            self::assertFalse(is_wp_error($term));
            $eventId = (int) $term['term_id'];
            $post = wp_insert_post([
                'post_type'=>'invitado', 'post_status'=>'publish',
                'post_title'=>'WEM84-' . wp_generate_uuid4(),
            ], true);
            self::assertFalse(is_wp_error($post));
            $guestId = (int) $post;
            self::assertFalse(is_wp_error(wp_set_object_terms($guestId, [$eventId], 'evento')));
            self::assertSame(1, $wpdb->insert($tables, [
                'event_term_id'=>$eventId,
                'table_code'=>'WEM84-'.wp_generate_uuid4(),
                'capacity'=>1,
            ]));
            $tableId = (int) $wpdb->insert_id;
            $reservationId = (new \WEM_Seating_Reservation_Service())->reserve_initial($guestId, $tableId);
            self::assertGreaterThan(0, $reservationId);
            $actorId = (int) wp_create_user('wem84-'.wp_generate_uuid4(), wp_generate_password(), 'wem84@example.invalid');
            self::assertGreaterThan(0, $actorId);
            (new \WP_User($actorId))->set_role('administrator');
            wp_set_current_user($actorId);

            $failure = null;
            try {
                (new \WEM_Seating_Administrative_Cancellation_Service())->cancel($guestId, 'Authorized seat release');
            } catch (\Throwable $caught) {
                $failure = $caught;
            }
            self::assertNull($failure, 'Authorized cancellation must succeed; observed: '
                . ($failure ? get_class($failure) . ': ' . $failure->getMessage() : 'none'));
            self::assertSame(0, (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$reservations} WHERE guest_id=%d AND status='active' AND active_slot=1", $guestId
            )));
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT actor_user_id,operation,old_table_id,new_table_id,reason
                 FROM {$audit} WHERE guest_id=%d AND event_term_id=%d", $guestId, $eventId
            ), ARRAY_A);
            self::assertCount(1, $rows);
            self::assertSame($actorId, (int) $rows[0]['actor_user_id']);
            self::assertSame('cancel', $rows[0]['operation']);
            self::assertSame($tableId, (int) $rows[0]['old_table_id']);
            self::assertNull($rows[0]['new_table_id']);
            self::assertSame('Authorized seat release', $rows[0]['reason']);
        } finally {
            wp_set_current_user($priorActor);
            if ($guestId > 0) {
                $wpdb->delete($audit, ['guest_id'=>$guestId]);
                $wpdb->delete($reservations, ['guest_id'=>$guestId]);
            }
            if ($tableId > 0) { $wpdb->delete($tables, ['id'=>$tableId]); }
            if ($guestId > 0) { wp_delete_post($guestId, true); }
            if ($eventId > 0) { wp_delete_term($eventId, 'evento'); }
            if ($actorId > 0) { wp_delete_user($actorId); }
        }
    }
}
