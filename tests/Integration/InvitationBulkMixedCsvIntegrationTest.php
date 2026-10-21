<?php
declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

final class InvitationBulkMixedCsvIntegrationTest extends WP_UnitTestCase
{
    private string $private;

    protected function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        $this->private = sys_get_temp_dir() . '/wem73-mixed-csv-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->private, 0700));
        \WEM_Invitation_Credential_Schema::install();
        (new \WEM_Taxonomy_Manager())->register_taxonomy();
    }

    protected function tearDown(): void
    {
        foreach (glob($this->private . '/*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        if (is_dir($this->private)) {
            rmdir($this->private);
        }
        parent::tearDown();
    }

    public function testMixedPersistentBatchPublishesOneCompletePrivateCsv(): void
    {
        global $wpdb;
        $table = \WEM_Invitation_Credential_Schema::table_name();
        $actor = self::factory()->user->create(['role' => 'administrator']);
        $event = self::factory()->term->create(['taxonomy' => 'evento', 'name' => 'WEM73 Mixed CSV']);
        $guests = [];
        for ($i = 0; $i < 1000 && count($guests) < 2; $i++) {
            $id = self::factory()->post->create([
                'post_type' => 'invitado',
                'post_status' => 'publish',
                'post_title' => 'WEM73 Guest ' . $i,
            ]);
            if ((int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE guest_id = %d", $id
            )) === 0) {
                wp_set_object_terms($id, [$event], 'evento');
                $guests[] = $id;
            }
        }
        self::assertCount(2, $guests);

        $operation = 'mixed-csv-real';
        $manifest = new \WEM_Invitation_Bulk_Manifest($this->private);
        $attempts = new \WEM_Invitation_Bulk_Attempt_Storage($this->private);
        $journal = new \WEM_Invitation_Private_Journal($this->private);
        $admission = new \WEM_Invitation_Bulk_Admission_Evidence($manifest, $attempts, $journal);
        $reconcile = new \WEM_Invitation_Custody_Database_Reconciliation();
        $issuer = new \WEM_Invitation_Bulk_Admitted_Locked_Storage(
            new \WEM_Invitation_Bulk_Guest_Lock($this->private),
            $admission,
            $attempts,
            new \WEM_Invitation_Custody_Issuance_Bridge(
                new \WEM_Invitation_Credential_Service(), $journal
            ),
            $actor
        );
        $first = $issuer->run($operation, $event, $guests, $guests[0]);
        $storage = new \WEM_Invitation_Private_Csv_Storage($this->private);
        $publication = new \WEM_Invitation_Bulk_Csv_Publication(
            new \WEM_Invitation_Bulk_Csv_Rows(
                static fn (array $record): bool => $reconcile->is_exportable($record)
            ),
            new \WEM_Invitation_Bulk_Private_Export(
                new \WEM_Invitation_Private_Csv(),
                $storage,
                static fn (array $row): bool => true
            )
        );

        self::assertTrue(class_exists('WEM_Invitation_Bulk_Mixed_Csv_Workflow'));
        $workflow = new \WEM_Invitation_Bulk_Mixed_Csv_Workflow(
            $admission,
            new \WEM_Invitation_Bulk_Recovered_Storage($admission, $journal, $reconcile),
            $issuer,
            $reconcile,
            $publication
        );
        $workflow->run($operation, $event, $guests);

        $csv = $storage->read($operation);
        self::assertIsString($csv);
        self::assertStringContainsString($first['token'], $csv);
        self::assertCount(3, array_filter(explode("\n", trim($csv))));
        self::assertCount(1, glob($this->private . '/*.csv') ?: []);
        foreach ($guests as $guest) {
            self::assertSame(1, (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE guest_id = %d", $guest
            )));
        }
    }
}
