<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use PHPUnit\Framework\TestCase;

/** WEM-86: canceled participation remains ineligible for new reservation. */
final class SeatingCanceledParticipationNoRebookingTest extends TestCase
{
    public function testCanceledGuestCannotReserveAgainEvenWhenSeatIsFree(): void
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
        $actorId = 0;
        $tableId = 0;
        $tables = $wpdb->prefix . 'wem_event_tables';
        $reservations = $wpdb->prefix . 'wem_table_reservations';
        $audit = $wpdb->prefix . 'wem_seating_admin_audit';
        $participation = $wpdb->prefix . 'wem_seating_participation';
        try {
            $term = wp_insert_term('WEM86-rebook-' . wp_generate_uuid4(), 'evento');
            self::assertFalse(is_wp_error($term));
            $termId = (int)$term['term_id'];
            $post = wp_insert_post(['post_type' => 'invitado','post_status' => 'publish','post_title' => 'WEM86 rebook'], true);
            self::assertFalse(is_wp_error($post));
            $guestId = (int)$post;
            self::assertFalse(is_wp_error(wp_set_object_terms($guestId, [$termId], 'evento')));
            self::assertSame(1, $wpdb->insert($tables, [
                'event_term_id' => $termId,'table_code' => 'WEM86-rebook-' . wp_generate_uuid4(),'capacity' => 1
            ]));
            $tableId = (int)$wpdb->insert_id;
            self::assertGreaterThan(0, (new \WEM_Seating_Reservation_Service())->reserve_initial($guestId, $tableId));
            $actorId = (int)wp_create_user('wem86-rebook-' . wp_generate_uuid4(), wp_generate_password(), 'wem86-rebook@example.invalid');
            self::assertGreaterThan(0, $actorId);
            (new \WP_User($actorId))->set_role('administrator');
            wp_set_current_user($actorId);
            (new \WEM_Seating_Participation_Cancellation_Service())->cancel($guestId, 'Cancel eligibility');
            $beforeAudit = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$audit} WHERE guest_id=%d",
                $guestId
            ));
            $failure = null;
            try {
                (new \WEM_Seating_Reservation_Service())->reserve_initial($guestId, $tableId);
            } catch (\Throwable $caught) {
                $failure = $caught;
            }
            self::assertInstanceOf(\DomainException::class, $failure);
            self::assertSame('Participation is canceled or invalid.', $failure->getMessage());
            self::assertSame(0, (int)$wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$reservations} WHERE guest_id=%d AND status='active' AND active_slot=1",
                $guestId
            )));
            self::assertSame($beforeAudit, (int)$wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$audit} WHERE guest_id=%d",
                $guestId
            )));
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
            if ($termId > 0) {
                wp_delete_term($termId, 'evento');
            }
            if ($actorId > 0) {
                wp_delete_user($actorId);
            }
        }
    }
}
