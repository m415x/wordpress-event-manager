<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

/** WEM-73: real private filesystem + MySQL evidence, isolated PHPUnit DB. */
final class InvitationBulkEvidenceReaderTest extends WP_UnitTestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir() . '/wem73-evidence-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->directory, 0700, true));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }
        parent::tearDown();
    }

    public function testReadsManifestAttemptAndMySqlWithoutTreatingStartedAsUnissued(): void
    {
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        \WEM_Invitation_Credential_Schema::install();

        global $wpdb;
        $table = \WEM_Invitation_Credential_Schema::table_name();
        $guest = 0;
        for ($n = 0; $n < 1000; $n++) {
            $candidate = self::factory()->post->create([
                'post_type' => 'invitado', 'post_status' => 'publish',
            ]);
            if (
                (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table} WHERE guest_id = %d",
                    $candidate
                )) === 0
            ) {
                $guest = $candidate;
                break;
            }
        }
        self::assertGreaterThan(0, $guest);

        $manifest = new \WEM_Invitation_Bulk_Manifest($this->directory);
        $attempts = new \WEM_Invitation_Bulk_Attempt_Storage($this->directory);
        $journal = new \WEM_Invitation_Private_Journal($this->directory);
        $manifest->create('test-evidence-run', 7, [$guest]);

        self::assertTrue(class_exists('WEM_Invitation_Bulk_Evidence_Reader'));
        $reader = new \WEM_Invitation_Bulk_Evidence_Reader($manifest, $attempts, $journal);
        self::assertSame('not_started', $reader->classify('test-evidence-run', $guest));
        self::assertSame('indeterminate', $reader->classify('test-evidence-run', $guest + 1));

        $attempts->mark_started('test-evidence-run', $guest);
        self::assertSame('indeterminate', $reader->classify('test-evidence-run', $guest));
    }
}
