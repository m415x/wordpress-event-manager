<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * WEM-92/T5 — persistent InnoDB functional acceptance, no WP test outer TX.
 * Requires existing schema in the isolated PHPUnit environment.
 */
final class SeatingBearerReservationPersistenceTest extends TestCase
{
    public function testAuthorizedInitialWriteAndRevokedBearerNoMutation(): void
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

            $admin = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
            self::assertNotEmpty($admin, 'Use existing administrator in the isolated PHPUnit fixture.');
            $credentials = new \WEM_Invitation_Credential_Service();
            $issued = $credentials->issue($guest, (int) $admin[0]);
            $adapter = new \WEM_Seating_Bearer_Initial_Reservation();
            $reservationId = $adapter->reserve_for_bearer($issued['token'], $tableId);
            self::assertGreaterThan(0, $reservationId);

            $reservations = \WEM_Table_Reservation_Schema::table_name();
            $count = static function () use ($wpdb, $reservations, $guest): int {
                return (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$reservations} WHERE guest_id=%d AND status='active' AND active_slot=1",
                    $guest
                ));
            };
            self::assertSame(1, $count());
            $credentials->revoke($guest, (int) $admin[0]);
            try {
                $adapter->reserve_for_bearer($issued['token'], $tableId);
                self::fail('Revoked bearer must be rejected.');
            } catch (\DomainException $expected) {
                self::assertSame(1, $count(), 'Denied request must not mutate active reservations.');
            }
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
            if ($eventId > 0) {
                wp_delete_term($eventId, 'evento');
            }
        }
    }
}
