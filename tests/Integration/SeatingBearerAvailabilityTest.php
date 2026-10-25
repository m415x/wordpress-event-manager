<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

/** WEM-89/T2: seat counts are scoped by bearer and derived from active rows. */
final class SeatingBearerAvailabilityTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        \WEM_Invitation_Credential_Schema::install();
        \WEM_Event_Table_Schema::install();
        \WEM_Table_Reservation_Schema::install();
        (new \WEM_Taxonomy_Manager())->register_taxonomy();
        global $wpdb;
        $wpdb->query('DELETE FROM ' . \WEM_Invitation_Credential_Schema::table_name());
        $wpdb->query('DELETE FROM ' . \WEM_Table_Reservation_Schema::table_name());
        $wpdb->query('DELETE FROM ' . \WEM_Event_Table_Schema::table_name());
    }

    public function testReadIsBearerScopedAndCountsOnlyActiveReservations(): void
    {
        global $wpdb;
        $admin = self::factory()->user->create(['role' => 'administrator']);
        $eventA = self::factory()->term->create(['taxonomy' => 'evento', 'name' => 'Seat availability event A']);
        $eventB = self::factory()->term->create(['taxonomy' => 'evento', 'name' => 'Seat availability event B']);
        $guest = self::factory()->post->create(['post_type' => 'invitado', 'post_status' => 'publish']);
        wp_set_object_terms($guest, [$eventA], 'evento', false);
        $bearer = (new \WEM_Invitation_Credential_Service())->issue($guest, $admin)['token'];

        $tables = \WEM_Event_Table_Schema::table_name();
        $reservations = \WEM_Table_Reservation_Schema::table_name();
        $wpdb->insert($tables, ['event_term_id' => $eventA, 'table_code' => 'A1', 'capacity' => 3], ['%d','%s','%d']);
        $tableA = (int) $wpdb->insert_id;
        $wpdb->insert($tables, ['event_term_id' => $eventB, 'table_code' => 'B-SECRET', 'capacity' => 4], ['%d','%s','%d']);

        $member = self::factory()->post->create(['post_type' => 'invitado', 'post_status' => 'publish']);
        wp_set_object_terms($member, [$eventA], 'evento', false);
        $wpdb->insert($reservations, ['guest_id'=>$member, 'table_id'=>$tableA, 'event_term_id'=>$eventA, 'status'=>'active', 'active_slot'=>1, 'created_at'=>gmdate('Y-m-d H:i:s')]);
        $former = self::factory()->post->create(['post_type'=>'invitado','post_status'=>'publish']);
        wp_set_object_terms($former, [$eventA], 'evento', false);
        $wpdb->insert($reservations, ['guest_id'=>$former, 'table_id'=>$tableA, 'event_term_id'=>$eventA, 'status'=>'canceled', 'active_slot'=>null, 'created_at'=>gmdate('Y-m-d H:i:s'), 'canceled_at'=>gmdate('Y-m-d H:i:s')]);

        $reader = new \WEM_Seating_Bearer_Availability();
        self::assertSame([
            ['table_code'=>'A1', 'capacity'=>3, 'occupied'=>1, 'free'=>2],
        ], $reader->list_for_bearer($bearer));
        self::assertNull($reader->list_for_bearer('invalid'));
        (new \WEM_Invitation_Credential_Service())->revoke($guest, $admin);
        self::assertNull($reader->list_for_bearer($bearer));
    }
}
