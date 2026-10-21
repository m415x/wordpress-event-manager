<?php

declare(strict_types=1);

namespace WEM\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;

final class InvitationBulkGuestLockTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/wem73-lock-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->dir, 0700));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        if (is_dir($this->dir)) {
            rmdir($this->dir);
        }
    }

    public function testExcludesConcurrentIssuanceForSameGuestAcrossOperations(): void
    {
        require_once dirname(__DIR__, 2) . '/includes/class-invitation-bulk-guest-lock.php';
        self::assertTrue(class_exists('WEM_Invitation_Bulk_Guest_Lock'));
        $first = new \WEM_Invitation_Bulk_Guest_Lock($this->dir);
        $second = new \WEM_Invitation_Bulk_Guest_Lock($this->dir);
        $calls = [];
        $first->run(101, function () use ($second, &$calls): void {
            $calls[] = 'first-entered';
            try {
                $second->run(101, static function () use (&$calls): void {
                    $calls[] = 'second-entered';
                });
                self::fail('Second writer must not enter while the guest is locked.');
            } catch (RuntimeException $exception) {
                self::assertStringNotContainsString('token', strtolower($exception->getMessage()));
            }
        });
        self::assertSame(['first-entered'], $calls);
        $second->run(101, static function () use (&$calls): void {
            $calls[] = 'second-after-release';
        });
        self::assertSame(['first-entered', 'second-after-release'], $calls);
    }
}
