<?php

declare(strict_types=1);

namespace WEM\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;

final class InvitationBulkManifestAdmissionStorageTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        require_once dirname(__DIR__, 2) . '/includes/class-invitation-bulk-manifest.php';
        require_once dirname(__DIR__, 2) . '/includes/class-invitation-bulk-manifest-admission.php';
        require_once dirname(__DIR__, 2) . '/includes/class-invitation-bulk-manifest-admission-storage.php';
        $this->directory = sys_get_temp_dir() . '/wem73-admission-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->directory, 0700));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }
    }

    public function testStorageAdapterAdmitsExactResumeAndRejectsSelectionDrift(): void
    {
        self::assertTrue(class_exists('WEM_Invitation_Bulk_Manifest_Admission_Storage'));
        $storage = new \WEM_Invitation_Bulk_Manifest($this->directory);
        $admission = new \WEM_Invitation_Bulk_Manifest_Admission_Storage($storage);
        self::assertSame([101, 102], $admission->admit('batch-1', 7, [101, 102]));

        $reopened = new \WEM_Invitation_Bulk_Manifest_Admission_Storage(
            new \WEM_Invitation_Bulk_Manifest($this->directory)
        );
        self::assertSame([101, 102], $reopened->admit('batch-1', 7, [101, 102]));
        try {
            $reopened->admit('batch-1', 7, [101, 103]);
            self::fail('Resumption cannot alter the batch selection.');
        } catch (RuntimeException $error) {
            self::assertNotSame('', $error->getMessage());
        }
        self::assertSame(
            ['operation_id' => 'batch-1', 'event_id' => 7, 'guest_ids' => [101, 102]],
            $storage->read('batch-1')
        );
    }
}
