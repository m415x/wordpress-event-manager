<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

/** Integration composition for mixed committed and unstarted credentials. */
final class InvitationBulkMixedStorageIntegrationTest extends WP_UnitTestCase
{
    private string $private;

    protected function setUp(): void
    {
        parent::setUp();
        $this->private = sys_get_temp_dir() . '/wem73-mixed-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->private, 0700));
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

    public function testMixedResumeRecoversFirstIssuesSecondAndExportsExactlyOnce(): void
    {
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        \WEM_Invitation_Credential_Schema::install();
        (new \WEM_Taxonomy_Manager())->register_taxonomy();
        global $wpdb;
        $table = \WEM_Invitation_Credential_Schema::table_name();

        $actor = self::factory()->user->create(['role' => 'administrator']);
        $event = self::factory()->term->create(['taxonomy' => 'evento', 'name' => 'WEM73 Mixed Resume']);
        $guests = [];
        for ($i = 0; $i < 1000 && count($guests) < 2; $i++) {
            $id = self::factory()->post->create(['post_type' => 'invitado', 'post_status' => 'publish']);
            if (
                (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table} WHERE guest_id = %d",
                    $id
                )) === 0
            ) {
                wp_set_object_terms($id, [$event], 'evento');
                $guests[] = $id;
            }
        }
        self::assertCount(2, $guests);

        $operation = 'mixed-real';
        $manifest = new \WEM_Invitation_Bulk_Manifest($this->private);
        $attempts = new \WEM_Invitation_Bulk_Attempt_Storage($this->private);
        $journal = new \WEM_Invitation_Private_Journal($this->private);
        $admission = new \WEM_Invitation_Bulk_Admission_Evidence($manifest, $attempts, $journal);
        $issuer = new \WEM_Invitation_Bulk_Admitted_Locked_Storage(
            new \WEM_Invitation_Bulk_Guest_Lock($this->private),
            $admission,
            $attempts,
            new \WEM_Invitation_Custody_Issuance_Bridge(new \WEM_Invitation_Credential_Service(), $journal),
            $actor
        );
        $first = $issuer->run($operation, $event, $guests, $guests[0]);

        self::assertTrue(class_exists('WEM_Invitation_Bulk_Mixed_Storage'));
        $exported = [];
        $runner = new \WEM_Invitation_Bulk_Mixed_Storage(
            $admission,
            new \WEM_Invitation_Bulk_Recovered_Storage(
                $admission,
                $journal,
                new \WEM_Invitation_Custody_Database_Reconciliation()
            ),
            $issuer,
            new \WEM_Invitation_Custody_Database_Reconciliation(),
            static function (array $rows) use (&$exported): void {
                $exported[] = $rows;
            }
        );
        $runner->run($operation, $event, $guests);

        self::assertCount(1, $exported);
        self::assertCount(2, $exported[0]);
        self::assertSame($first['token'], $exported[0][0]['token']);
        foreach ($guests as $guest) {
            self::assertSame(1, (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE guest_id = %d",
                $guest
            )));
        }
    }
}
