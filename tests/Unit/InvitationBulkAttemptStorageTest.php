<?php

declare(strict_types=1);

namespace WEM\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;

final class InvitationBulkAttemptStorageTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/wem73-attempt-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->root, 0700));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->root . '/*') ?: [] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        if (is_dir($this->root)) {
            rmdir($this->root);
        }
    }

    public function testMarkerIsPrivateImmutableAndRecoverableAcrossInstances(): void
    {
        self::assertTrue(class_exists('WEM_Invitation_Bulk_Attempt_Storage'));
        $storage = new \WEM_Invitation_Bulk_Attempt_Storage($this->root);
        $storage->mark_started('operation-1', 501);

        $reopened = new \WEM_Invitation_Bulk_Attempt_Storage($this->root);
        self::assertTrue($reopened->has_started('operation-1', 501));
        self::assertFalse($reopened->has_started('operation-1', 502));
        self::assertFalse($reopened->has_started('operation-2', 501));

        $files = glob($this->root . '/*') ?: [];
        self::assertCount(1, $files);
        self::assertSame(0600, fileperms($files[0]) & 0777);
        self::assertStringNotContainsString('wem_invitation=', (string) file_get_contents($files[0]));

        $this->expectException(RuntimeException::class);
        $reopened->mark_started('operation-1', 501);
    }

    public function testMarkerCannotBeWrittenInsideKnownWebroot(): void
    {
        $storage = new \WEM_Invitation_Bulk_Attempt_Storage($this->root, [$this->root]);
        $this->expectException(RuntimeException::class);
        $storage->mark_started('operation-1', 501);
    }
}
