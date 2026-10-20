<?php

declare(strict_types=1);

namespace WEM\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;

final class InvitationBulkManifestTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/wem73-manifest-' . bin2hex(random_bytes(8));
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

    public function testManifestIsImmutableAndRecoversExactEventGuestSelection(): void
    {
        require_once dirname(__DIR__, 2) . '/includes/class-invitation-bulk-manifest.php';
        self::assertTrue(class_exists('WEM_Invitation_Bulk_Manifest'));
        $store = new \WEM_Invitation_Bulk_Manifest($this->directory);
        $store->create('run-1', 7, [101, 102, 103]);
        $reopened = new \WEM_Invitation_Bulk_Manifest($this->directory);
        self::assertSame(['operation_id' => 'run-1', 'event_id' => 7, 'guest_ids' => [101, 102, 103]],
            $reopened->read('run-1'));
        $files = glob($this->directory . '/*') ?: [];
        self::assertCount(1, $files);
        self::assertSame(0600, fileperms($files[0]) & 0777);
        $this->expectException(RuntimeException::class);
        $reopened->create('run-1', 7, [101, 104]);
    }

    public function testRejectsDuplicateGuestsAndPublicManifestDirectory(): void
    {
        $store = new \WEM_Invitation_Bulk_Manifest($this->directory);
        try {
            $store->create('run-2', 7, [101, 101]);
            self::fail('Duplicate guest IDs must be rejected.');
        } catch (RuntimeException $exception) {
            self::assertNotSame('', $exception->getMessage());
        }
        $public = new \WEM_Invitation_Bulk_Manifest($this->directory, [$this->directory]);
        $this->expectException(RuntimeException::class);
        $public->create('run-3', 7, [101]);
    }
}
