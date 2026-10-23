<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

final class PreissuedAssignmentDenialTest extends WP_UnitTestCase
{
    public function testUnauthorizedAndCrossEventAssignmentsLeaveInventoryAndAuditUnchanged(): void
    {
        global $wpdb;
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        (new \WEM_CPT_Manager())->register_cpt();
        (new \WEM_Taxonomy_Manager())->register_taxonomy();
        \WEM_Preissued_Invitation_Schema::install();
        \WEM_Invitation_Credential_Schema::install();

        $admin = self::factory()->user->create(['role' => 'administrator']);
        $subscriber = self::factory()->user->create(['role' => 'subscriber']);
        $eventA = self::factory()->term->create(['taxonomy' => 'evento']);
        $eventB = self::factory()->term->create(['taxonomy' => 'evento']);
        self::assertNotWPError($eventA);
        self::assertNotWPError($eventB);
        $guest = self::factory()->post->create(['post_type' => 'invitado', 'post_status' => 'publish']);
        self::assertNotWPError(wp_set_object_terms($guest, [(int) $eventB], 'evento'));

        $table = \WEM_Preissued_Invitation_Schema::table_name();
        $audit = \WEM_Preissued_Invitation_Schema::audit_table_name();
        self::assertSame(1, $wpdb->insert($table, [
            'event_term_id' => (int) $eventA,
            'token_digest' => hash('sha256', random_bytes(32)),
            'state' => 'unassigned',
            'issued_by' => $admin,
            'issued_at' => gmdate('Y-m-d H:i:s'),
        ]));
        $id = (int) $wpdb->insert_id;
        $service = new \WEM_Preissued_Invitation_Service();

        foreach ([$subscriber, $admin] as $actor) {
            try {
                $service->assign_once($id, (int) $guest, (int) $actor);
                self::fail('Unauthorized or cross-event assignment must be rejected.');
            } catch (\RuntimeException $expected) {
                self::assertNotSame('', $expected->getMessage());
            }
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT state, assigned_guest_id FROM {$table} WHERE id = %d", $id
            ), ARRAY_A);
            self::assertSame('unassigned', $row['state']);
            self::assertNull($row['assigned_guest_id']);
            self::assertSame(0, (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$audit} WHERE invitation_id = %d", $id
            )));
        }
    }
}
