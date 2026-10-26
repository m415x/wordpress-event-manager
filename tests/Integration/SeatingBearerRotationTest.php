<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * WEM-92/T5 — persistent InnoDB functional acceptance, no WP test outer TX.
 * Requires existing schema in the isolated PHPUnit environment.
 */
final class SeatingBearerRotationTest extends TestCase
{
    public function testRotatedBearerDeniedAndReplacementCanReserve(): void
    {
        global $wpdb;
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        if (!taxonomy_exists('evento')) {
            (new \WEM_Taxonomy_Manager())->register_taxonomy();
        }
        if (!post_type_exists('invitado')) {
            (new \WEM_CPT_Manager())->register_cpt();
        }
        self::assertSame(0, (int) $wpdb->get_var('SELECT @@in_transaction'));

        $guest = 0;
        $tableId = 0;
        $eventId = 0;
        $adminId = 0;
        try {
            $event = wp_insert_term('T5-persist-' . wp_generate_uuid4(), 'evento');
            self::assertFalse(is_wp_error($event));
            $eventId = (int) $event['term_id'];
            $guest = (int) wp_insert_post([
                'post_type' => 'invitado',
                'post_status' => 'publish',
                'post_title' => 'T5-persist-' . wp_generate_uuid4(),
            ]);
            self::assertGreaterThan(0, $guest);
            wp_set_object_terms($guest, [$eventId], 'evento', false);
            $wpdb->insert(\WEM_Event_Table_Schema::table_name(), [
                'event_term_id' => $eventId,
                'table_code' => 'T5-' . wp_generate_uuid4(),
                'capacity' => 2,
            ]);
            $tableId = (int) $wpdb->insert_id;
            self::assertGreaterThan(0, $tableId);

            $adminId = (int) wp_create_user('wem-t5-' . wp_generate_uuid4(), wp_generate_password(32), 'wem-t5-' . wp_generate_uuid4() . '@example.invalid');
            self::assertGreaterThan(0, $adminId);
            (new \WP_User($adminId))->set_role('administrator');
            $credentials = new \WEM_Invitation_Credential_Service();
            $issued = $credentials->issue($guest, $adminId);
            $adapter = new \WEM_Seating_Bearer_Initial_Reservation();
            $replacement = $credentials->rotate($guest, $adminId);
            self::assertNotSame($issued['token'], $replacement['token']);
            $reservations = \WEM_Table_Reservation_Schema::table_name();
            try {
                $adapter->reserve_for_bearer($issued['token'], $tableId);
                self::fail('Rotated bearer must be rejected.');
            } catch (\DomainException $expected) {
                self::assertSame(0, (int)$wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$reservations} WHERE guest_id=%d", $guest
                )));
            }
            $reservationId = $adapter->reserve_for_bearer($replacement['token'], $tableId);
            self::assertGreaterThan(0, $reservationId);
            self::assertSame(1, (int)$wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$reservations} WHERE guest_id=%d AND status='active' AND active_slot=1", $guest
            )));
        } finally {
            if ($guest > 0) {
                $wpdb->delete(\WEM_Table_Reservation_Schema::table_name(), ['guest_id' => $guest]);
                $wpdb->delete(\WEM_Invitation_Credential_Schema::table_name(), ['guest_id' => $guest]);
            }
            if ($tableId > 0) {
                $wpdb->delete(\WEM_Event_Table_Schema::table_name(), ['id' => $tableId]);
            }
            if ($guest > 0) {
                wp_delete_post($guest, true);
            }
            if ($adminId > 0) {
                wp_delete_user($adminId);
            }
            if ($eventId > 0) {
                wp_delete_term($eventId, 'evento');
            }
        }
    }
}
