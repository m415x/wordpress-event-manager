<?php

declare(strict_types=1);

namespace WEM\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;

final class InvitationPrivateCsvStorageTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/wem73-csv-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->root, 0700));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->root . '/*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        if (is_dir($this->root)) {
            rmdir($this->root);
        }
    }

    public function testPrivateCsvIsExclusiveAndReadableAfterReopening(): void
    {
        self::assertTrue(class_exists('WEM_Invitation_Private_Csv_Storage'), 'WEM-73 requires private CSV storage.');
        $csv = "guest_id,guest_name,event_id,invitation_url\n501,Invitado,19,https://example.test/?wem_invitation=SECRET\n";
        $storage = new \WEM_Invitation_Private_Csv_Storage($this->root);
        $storage->save('operation-1', $csv);
        $reopened = new \WEM_Invitation_Private_Csv_Storage($this->root);
        self::assertSame($csv, $reopened->read('operation-1'));
        $files = glob($this->root . '/*') ?: [];
        self::assertCount(1, $files);
        self::assertSame(0600, fileperms($files[0]) & 0777);
        try {
            $reopened->save('operation-1', 'different sensitive content');
            self::fail('Previously published CSV must never be overwritten.');
        } catch (RuntimeException $exception) {
            self::assertStringNotContainsString('SECRET', $exception->getMessage());
        }
        self::assertSame($csv, $reopened->read('operation-1'));
    }

    public function testRejectsCsvStorageUnderKnownWebroot(): void
    {
        self::assertTrue(class_exists('WEM_Invitation_Private_Csv_Storage'));
        $storage = new \WEM_Invitation_Private_Csv_Storage($this->root, [$this->root]);
        $this->expectException(RuntimeException::class);
        $storage->save('operation-2', "not public\n");
    }
}
