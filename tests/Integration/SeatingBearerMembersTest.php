<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

final class SeatingBearerMembersTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        \WEM_Invitation_Credential_Schema::install();
        \WEM_Event_Table_Schema::install();
        \WEM_Table_Reservation_Schema::install();
        \WEM_Seating_Visibility_Consent_Schema::install();
        (new \WEM_Taxonomy_Manager())->register_taxonomy();
        global $wpdb;
        foreach ([\WEM_Invitation_Credential_Schema::table_name(), \WEM_Table_Reservation_Schema::table_name(), \WEM_Event_Table_Schema::table_name(), \WEM_Seating_Visibility_Consent_Schema::table_name()] as $table) {
            $wpdb->query("DELETE FROM {$table}");
        }
    }

    public function testMembersRemainPrivateDespiteConsentAndAvailabilityStaysCanonical(): void
    {
        global $wpdb;
        $admin = self::factory()->user->create(['role' => 'administrator']);
        $event = self::factory()->term->create(['taxonomy' => 'evento', 'name' => 'Privacy Event']);
        $otherEvent = self::factory()->term->create(['taxonomy' => 'evento', 'name' => 'Other Privacy Event']);
        $viewer = self::factory()->post->create(['post_type' => 'invitado', 'post_status' => 'publish']);
        $member = self::factory()->post->create(['post_type' => 'invitado', 'post_status' => 'publish', 'post_title' => 'SECRET-TICKET']);
        $outsider = self::factory()->post->create(['post_type' => 'invitado', 'post_status' => 'publish']);
        wp_set_object_terms($viewer, [$event], 'evento', false);
        wp_set_object_terms($member, [$event], 'evento', false);
        wp_set_object_terms($outsider, [$otherEvent], 'evento', false);
        update_post_meta($member, 'wem_nombre', 'Ana Del Valle');
        $credentials = new \WEM_Invitation_Credential_Service();
        $viewerToken = $credentials->issue($viewer, $admin)['token'];
        $memberToken = $credentials->issue($member, $admin)['token'];
        $outsiderToken = $credentials->issue($outsider, $admin)['token'];

        $tables = \WEM_Event_Table_Schema::table_name();
        $reservations = \WEM_Table_Reservation_Schema::table_name();
        $wpdb->insert($tables, ['event_term_id'=>$event, 'table_code'=>'M1', 'capacity'=>3]);
        $tableId = (int) $wpdb->insert_id;
        $wpdb->insert($tables, ['event_term_id'=>$otherEvent, 'table_code'=>'SECRET-OTHER', 'capacity'=>3]);
        $otherTable = (int) $wpdb->insert_id;
        foreach ([[$member,$tableId,$event],[$outsider,$otherTable,$otherEvent]] as $seat) {
            $wpdb->insert($reservations, ['guest_id'=>$seat[0], 'table_id'=>$seat[1], 'event_term_id'=>$seat[2], 'status'=>'active', 'active_slot'=>1, 'created_at'=>gmdate('Y-m-d H:i:s')]);
        }

        $reader = new \WEM_Seating_Bearer_Members();
        $expected = [['table_code'=>'M1', 'members'=>['Integrante privado']]];
        self::assertSame($expected, $reader->list_for_bearer($viewerToken));
        self::assertTrue((new \WEM_Seating_Bearer_Consent())->set_for_bearer($memberToken, true));
        self::assertSame($expected, $reader->list_for_bearer($viewerToken));
        self::assertTrue((new \WEM_Seating_Bearer_Consent())->set_for_bearer($memberToken, false));
        self::assertSame($expected, $reader->list_for_bearer($viewerToken));
        self::assertSame([['table_code'=>'M1','capacity'=>3,'occupied'=>1,'free'=>2]], (new \WEM_Seating_Bearer_Availability())->list_for_bearer($viewerToken));
        self::assertNull($reader->list_for_bearer('invalid'));
        $credentials->revoke($viewer, $admin);
        self::assertNull($reader->list_for_bearer($viewerToken));
        self::assertSame([['table_code'=>'SECRET-OTHER', 'members'=>['Integrante privado']]], $reader->list_for_bearer($outsiderToken));
    }
}
