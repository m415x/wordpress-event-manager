<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

final class PreissuedAssignmentServiceTest extends WP_UnitTestCase
{
    public function testFirstAssignmentIsAuditedAndSecondAssignmentIsDenied(): void
    {
        global $wpdb;
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';

        // WordPress init can precede this test's plugin include.
        (new \WEM_CPT_Manager())->register_cpt();
        (new \WEM_Taxonomy_Manager())->register_taxonomy();
        \WEM_Preissued_Invitation_Schema::install();
        \WEM_Invitation_Credential_Schema::install();
        $actor = self::factory()->user->create(['role' => 'administrator']);
        $event = self::factory()->term->create(['taxonomy' => 'evento']);
        self::assertNotWPError($event);
        $guest = self::factory()->post->create([
            'post_type' => 'invitado',
            'post_status' => 'publish',
        ]);
        self::assertNotWPError(wp_set_object_terms($guest, [(int) $event], 'evento'));

        $table = \WEM_Preissued_Invitation_Schema::table_name();
        $audit = \WEM_Preissued_Invitation_Schema::audit_table_name();
        $digest = hash('sha256', random_bytes(32));
        self::assertSame(1, $wpdb->insert($table, [
            'event_term_id' => (int) $event,
            'token_digest' => $digest,
            'state' => 'unassigned',
            'issued_by' => (int) $actor,
            'issued_at' => gmdate('Y-m-d H:i:s'),
        ]));
        $id = (int) $wpdb->insert_id;
        $service = new \WEM_Preissued_Invitation_Service();
        $service->assign_once($id, (int) $guest, (int) $actor);

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT state, assigned_guest_id, event_term_id FROM {$table} WHERE id = %d", $id
        ), ARRAY_A);
        self::assertSame('assigned', $row['state']);
        self::assertSame((string) $guest, (string) $row['assigned_guest_id']);
        self::assertSame((string) $event, (string) $row['event_term_id']);
        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$audit} WHERE invitation_id = %d AND operation = 'assign' AND guest_id = %d",
            $id, $guest
        ));
        self::assertSame(1, $count, 'Assignment and append-only audit must commit together.');

        try {
            $service->assign_once($id, (int) $guest, (int) $actor);
            self::fail('Even assignment to the same guest must be rejected.');
        } catch (\RuntimeException $expected) {
            self::assertSame('Invitation is unavailable for assignment.', $expected->getMessage());
        }
        self::assertSame(1, (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$audit} WHERE invitation_id = %d", $id
        )));
    }
}
