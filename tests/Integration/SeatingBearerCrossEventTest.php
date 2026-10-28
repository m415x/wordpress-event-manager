<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * WEM-92/T5 — persistent InnoDB functional acceptance, no WP test outer TX.
 * Requires existing schema in the isolated PHPUnit environment.
 */
final class SeatingBearerCrossEventTest extends TestCase
{
    public function testBearerCannotReserveTableFromAnotherEvent(): void
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
        $otherEventId = 0;
        $otherTableId = 0;
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
            $other = wp_insert_term('T5-other-' . wp_generate_uuid4(), 'evento');
            self::assertFalse(is_wp_error($other));
            $otherEventId = (int) $other['term_id'];
            self::assertSame(1, $wpdb->insert(\WEM_Event_Table_Schema::table_name(), [
                'event_term_id' => $otherEventId,
                'table_code' => 'T5-other-' . wp_generate_uuid4(),
                'capacity' => 2,
            ]));
            $otherTableId = (int) $wpdb->insert_id;
            try {
                $adapter->reserve_for_bearer($issued['token'], $otherTableId);
                self::fail('A bearer for one event must not reserve a table from another event.');
            } catch (\DomainException $expected) {
                self::assertSame(0, (int) $wpdb->get_var($wpdb->prepare(
                    'SELECT COUNT(*) FROM ' . \WEM_Table_Reservation_Schema::table_name() . ' WHERE guest_id=%d',
                    $guest
                )));
            }
        } finally {
            if ($guest > 0) {
                $wpdb->delete(\WEM_Table_Reservation_Schema::table_name(), ['guest_id' => $guest]);
                $wpdb->delete(\WEM_Invitation_Credential_Schema::table_name(), ['guest_id' => $guest]);
            }
            if ($otherTableId > 0) {
                $wpdb->delete(\WEM_Event_Table_Schema::table_name(), ['id' => $otherTableId]);
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
            if ($otherEventId > 0) {
                wp_delete_term($otherEventId, 'evento');
            }
            if ($eventId > 0) {
                wp_delete_term($eventId, 'evento');
            }
        }
    }
}
