<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

final class PreissuedAssignmentRollbackTest extends WP_UnitTestCase
{
    public function testAuditInsertFailureRollsBackAssignment(): void
    {
        global $wpdb;
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        (new \WEM_CPT_Manager())->register_cpt();
        (new \WEM_Taxonomy_Manager())->register_taxonomy();
        \WEM_Preissued_Invitation_Schema::install();
        \WEM_Invitation_Credential_Schema::install();

        $admin = self::factory()->user->create(['role' => 'administrator']);
        $event = self::factory()->term->create(['taxonomy' => 'evento']);
        self::assertNotWPError($event);
        $table = \WEM_Preissued_Invitation_Schema::table_name();
        $audit = \WEM_Preissued_Invitation_Schema::audit_table_name();
        // Existing persistent PHPUnit inventory may retain synthetic guest IDs
        // from other runs. The fixture must not collide with assigned_guest.
        $guest = 0;
        for ($attempt = 0; $attempt < 50; ++$attempt) {
            $candidate = (int) self::factory()->post->create([
                'post_type' => 'invitado', 'post_status' => 'publish',
            ]);
            $alreadyAssigned = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE assigned_guest_id = %d", $candidate
            ));
            if ($alreadyAssigned === null) {
                $guest = $candidate;
                break;
            }
        }
        self::assertGreaterThan(0, $guest, 'A fresh, unassigned synthetic guest is required.');
        self::assertNotWPError(wp_set_object_terms($guest, [(int) $event], 'evento'));

        self::assertSame(1, $wpdb->insert($table, [
            'event_term_id' => (int) $event,
            'token_digest' => hash('sha256', random_bytes(32)),
            'state' => 'unassigned',
            'issued_by' => $admin,
            'issued_at' => gmdate('Y-m-d H:i:s'),
        ]));
        $id = (int) $wpdb->insert_id;

        // A synthetic duplicate audit key forces INSERT failure after the
        // inventory UPDATE; no production trigger or runtime hook is altered.
        self::assertSame(1, $wpdb->insert($audit, [
            'invitation_id' => $id,
            'guest_id' => $guest,
            'event_term_id' => $event,
            'actor_user_id' => $admin,
            'operation' => 'assign',
            'occurred_at' => gmdate('Y-m-d H:i:s'),
        ]));
        try {
            (new \WEM_Preissued_Invitation_Service())->assign_once($id, $guest, $admin);
            self::fail('Audit persistence failure must reject the assignment.');
        } catch (\RuntimeException $expected) {
            self::assertSame('Assignment audit or commit failed.', $expected->getMessage());
        }

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT state, assigned_guest_id, assigned_by, assigned_at FROM {$table} WHERE id = %d",
            $id
        ), ARRAY_A);
        self::assertSame('unassigned', $row['state']);
        self::assertNull($row['assigned_guest_id']);
        self::assertNull($row['assigned_by']);
        self::assertNull($row['assigned_at']);
        self::assertSame(1, (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$audit} WHERE invitation_id = %d AND operation = 'assign'",
            $id
        )), 'Only the injected preexisting audit row must remain.');
    }
}
