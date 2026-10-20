<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use RuntimeException;
use WP_UnitTestCase;

/** WEM-73 complete admission→lock→SQL evidence→attempt→WEM-33 custody path. */
final class InvitationBulkAdmittedLockedIntegrationTest extends WP_UnitTestCase
{
    private string $private;

    protected function setUp(): void
    {
        parent::setUp();
        $this->private = sys_get_temp_dir() . '/wem73-admitted-locked-' . bin2hex(random_bytes(8));
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

    public function testRealAdmittedLockedIssuanceNeverRepeatsCommittedCredential(): void
    {
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        \WEM_Invitation_Credential_Schema::install();
        (new \WEM_Taxonomy_Manager())->register_taxonomy();

        $admin = self::factory()->user->create(['role' => 'administrator']);
        $event = self::factory()->term->create(['taxonomy' => 'evento', 'name' => 'WEM73 Admitted Locked']);
        global $wpdb;
        $table = \WEM_Invitation_Credential_Schema::table_name();
        $guest = 0;
        for ($n = 0; $n < 1000; $n++) {
            $candidate = self::factory()->post->create(['post_type' => 'invitado', 'post_status' => 'publish']);
            if ((int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE guest_id = %d", $candidate
            )) === 0) {
                $guest = $candidate;
                break;
            }
        }
        self::assertGreaterThan(0, $guest);
        wp_set_object_terms($guest, [$event], 'evento');

        $operation = 'admitted-integrated';
        $manifest = new \WEM_Invitation_Bulk_Manifest($this->private);
        $attempts = new \WEM_Invitation_Bulk_Attempt_Storage($this->private);
        $journal = new \WEM_Invitation_Private_Journal($this->private);
        $admission = new \WEM_Invitation_Bulk_Admission_Evidence($manifest, $attempts, $journal);
        $lock = new \WEM_Invitation_Bulk_Guest_Lock($this->private);
        $bridge = new \WEM_Invitation_Custody_Issuance_Bridge(
            new \WEM_Invitation_Credential_Service(), $journal
        );
        self::assertTrue(class_exists('WEM_Invitation_Bulk_Admitted_Locked_Storage'));

        $service = new \WEM_Invitation_Bulk_Admitted_Locked_Storage(
            $lock, $admission, $attempts, $bridge, $admin
        );
        $issued = $service->run($operation, $event, [$guest], $guest);
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $issued['token']);
        self::assertSame('reconcile', $admission->admit_and_classify($operation, $event, [$guest], $guest));

        try {
            $service->run($operation, $event, [$guest], $guest);
            self::fail('Re-entry must not emit a second credential.');
        } catch (RuntimeException $error) {
            self::assertNotSame('', $error->getMessage());
        }
        self::assertSame(1, (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE guest_id = %d", $guest
        )));
    }
}
