<?php
declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

/** P0 recovery: persisted partial issuance resumes without issuing twice. */
final class InvitationBulkOperationalInterruptedResumeTest extends WP_UnitTestCase
{
    public function testOperationalResumeReusesCommittedCustodyAndPublishesOneCsv(): void
    {
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        \WEM_Invitation_Credential_Schema::install();
        (new \WEM_Taxonomy_Manager())->register_taxonomy();

        global $wpdb;
        $table = \WEM_Invitation_Credential_Schema::table_name();
        $maxIssued = (int) $wpdb->get_var("SELECT COALESCE(MAX(guest_id), 0) FROM {$table}");
        $maxPost = (int) $wpdb->get_var("SELECT COALESCE(MAX(ID), 0) FROM {$wpdb->posts}");
        $reservation = wp_insert_post([
            'import_id' => max($maxIssued, $maxPost) + 100,
            'post_type' => 'post',
            'post_status' => 'draft',
            'post_title' => 'WEM73 interrupted fixture reservation',
        ], true);
        self::assertFalse(is_wp_error($reservation));

        $actor = self::factory()->user->create(['role' => 'administrator']);
        $event = self::factory()->term->create([
            'taxonomy' => 'evento',
            'name' => 'WEM73 resume ' . bin2hex(random_bytes(4)),
        ]);
        $guests = [];
        foreach (['A', 'B'] as $suffix) {
            $id = self::factory()->post->create([
                'post_type' => 'invitado', 'post_status' => 'publish',
                'post_title' => 'WEM73 resume guest ' . $suffix,
            ]);
            wp_set_object_terms($id, [$event], 'evento');
            $guests[] = $id;
        }
        self::assertSame(0, (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE guest_id IN (%d,%d)",
            $guests[0], $guests[1]
        )));

        $dir = sys_get_temp_dir() . '/wem73-interrupted-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($dir, 0700));
        $operation = 'interrupted-resume';
        try {
            $manifest = new \WEM_Invitation_Bulk_Manifest($dir);
            $attempts = new \WEM_Invitation_Bulk_Attempt_Storage($dir);
            $journal = new \WEM_Invitation_Private_Journal($dir);
            $admission = new \WEM_Invitation_Bulk_Admission_Evidence($manifest, $attempts, $journal);
            $issuer = new \WEM_Invitation_Bulk_Admitted_Locked_Storage(
                new \WEM_Invitation_Bulk_Guest_Lock($dir),
                $admission,
                $attempts,
                new \WEM_Invitation_Custody_Issuance_Bridge(
                    new \WEM_Invitation_Credential_Service(), $journal
                ),
                $actor
            );
            // Simulate interruption immediately after first durable committed issue,
            // before the second guest and CSV publication.
            $first = $issuer->run($operation, $event, $guests, $guests[0]);
            self::assertNull((new \WEM_Invitation_Private_Csv_Storage($dir))->read($operation));

            (new \WEM_Invitation_Bulk_Operational_Cli())->execute([
                'actor' => $actor, 'event' => $event,
                'operation' => $operation, 'private_dir' => $dir,
            ]);

            $csv = (new \WEM_Invitation_Private_Csv_Storage($dir))->read($operation);
            self::assertIsString($csv);
            self::assertStringContainsString($first['token'], $csv);
            self::assertCount(3, array_filter(explode("\n", trim($csv))));
            foreach ($guests as $id) {
                self::assertSame(1, (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table} WHERE guest_id = %d", $id
                )));
            }
            self::assertCount(1, glob($dir . '/*.csv') ?: []);
        } finally {
            foreach (glob($dir . '/*') ?: [] as $item) {
                if (is_file($item)) {
                    unlink($item);
                }
            }
            rmdir($dir);
        }
    }
}
