<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use RuntimeException;
use WP_UnitTestCase;

/** A resumable operation must use one durable admission and real SQL evidence. */
final class InvitationBulkAdmissionEvidenceIntegrationTest extends WP_UnitTestCase
{
    private string $private;

    protected function setUp(): void
    {
        parent::setUp();
        $this->private = sys_get_temp_dir() . '/wem73-admit-evidence-' . bin2hex(random_bytes(8));
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

    public function testDurableAdmissionAllowsOnlyUnstartedGuestAndNeverReplacesSelection(): void
    {
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        \WEM_Invitation_Credential_Schema::install();

        global $wpdb;
        $table = \WEM_Invitation_Credential_Schema::table_name();
        $guest = 0;
        for ($n = 0; $n < 1000; $n++) {
            $candidate = self::factory()->post->create([
                'post_type' => 'invitado',
                'post_status' => 'publish',
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
        self::assertTrue(class_exists('WEM_Invitation_Bulk_Admission_Evidence'));

        $guard = new \WEM_Invitation_Bulk_Admission_Evidence(
            new \WEM_Invitation_Bulk_Manifest($this->private),
            new \WEM_Invitation_Bulk_Attempt_Storage($this->private),
            new \WEM_Invitation_Private_Journal($this->private)
        );
        self::assertSame('not_started', $guard->admit_and_classify('run-integrated', 7, [$guest], $guest));
        self::assertSame('not_started', $guard->admit_and_classify('run-integrated', 7, [$guest], $guest));

        try {
            $guard->admit_and_classify('run-integrated', 8, [$guest], $guest);
            self::fail('Operation cannot change event after admission.');
        } catch (RuntimeException $error) {
            self::assertNotSame('', $error->getMessage());
        }

        (new \WEM_Invitation_Bulk_Attempt_Storage($this->private))
            ->mark_started('run-integrated', $guest);
        self::assertSame(
            'indeterminate',
            $guard->admit_and_classify('run-integrated', 7, [$guest], $guest)
        );
    }
}
