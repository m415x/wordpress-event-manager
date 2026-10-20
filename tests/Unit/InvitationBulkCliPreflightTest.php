<?php

declare(strict_types=1);

namespace WEM\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;

final class InvitationBulkCliPreflightTest extends TestCase
{
    public function testRejectsUnsafeCliInputsBeforeAnyGuestProcessing(): void
    {
        $discoveryCalls = 0;
        $discover = static function (int $event) use (&$discoveryCalls): array {
            $discoveryCalls++;
            return [101, 102];
        };
        $administrator = static function (int $actor): bool {
            return $actor === 1;
        };

        self::assertTrue(class_exists('WEM_Invitation_Bulk_Cli_Preflight'));
        $preflight = new \WEM_Invitation_Bulk_Cli_Preflight($administrator, $discover);
        foreach ([
            [2, 7, 'operation-safe', '/private/journal', '/private/csv'],
            [1, 0, 'operation-safe', '/private/journal', '/private/csv'],
            [1, 7, '../unsafe', '/private/journal', '/private/csv'],
            [1, 7, 'operation-safe', '', '/private/csv'],
            [1, 7, 'operation-safe', '/private/journal', ''],
        ] as $input) {
            try {
                $preflight->prepare(...$input);
                self::fail('Unsafe bulk issuance request must be rejected.');
            } catch (RuntimeException $exception) {
                self::assertNotSame('', $exception->getMessage());
            }
        }
        self::assertSame(0, $discoveryCalls);

        self::assertSame([101, 102], $preflight->prepare(
            1,
            7,
            'operation-safe',
            '/private/journal',
            '/private/csv'
        ));
        self::assertSame(1, $discoveryCalls);
    }
}
