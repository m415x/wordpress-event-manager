<?php

declare(strict_types=1);

namespace WEM\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;

final class InvitationBulkLockedIssuanceTest extends TestCase
{
    public function testLockEnclosesEvidenceAttemptAndIssuance(): void
    {
        self::assertTrue(class_exists('WEM_Invitation_Bulk_Locked_Issuance'));
        $events = [];
        $lock = static function (int $guest, callable $callback) use (&$events) {
            $events[] = 'lock:' . $guest;
            try {
                return $callback();
            } finally {
                $events[] = 'unlock:' . $guest;
            }
        };
        $evidence = static function (int $guest) use (&$events): string {
            $events[] = 'evidence:' . $guest;
            return 'not_started';
        };
        $attempt = static function (int $guest) use (&$events): void {
            $events[] = 'attempt:' . $guest;
        };
        $issuer = static function (int $guest) use (&$events): string {
            $events[] = 'issue:' . $guest;
            return 'issued';
        };

        $coordinator = new \WEM_Invitation_Bulk_Locked_Issuance($lock, $evidence, $attempt, $issuer);
        self::assertSame('issued', $coordinator->run(101));
        self::assertSame([
            'lock:101', 'evidence:101', 'attempt:101', 'issue:101', 'unlock:101',
        ], $events);
    }

    public function testIndeterminateEvidencePreventsAttemptAndIssuance(): void
    {
        $events = [];
        $coordinator = new \WEM_Invitation_Bulk_Locked_Issuance(
            static fn (int $guest, callable $callback) => $callback(),
            static fn (int $guest): string => 'indeterminate',
            static function (int $guest) use (&$events): void { $events[] = 'attempt'; },
            static function (int $guest) use (&$events): void { $events[] = 'issue'; }
        );
        try {
            $coordinator->run(101);
            self::fail('Indeterminate state cannot lead to issuance.');
        } catch (RuntimeException $exception) {
            self::assertSame([], $events);
        }
    }
}
