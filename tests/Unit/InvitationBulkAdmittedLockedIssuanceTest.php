<?php

declare(strict_types=1);

namespace WEM\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;

final class InvitationBulkAdmittedLockedIssuanceTest extends TestCase
{
    public function testAdmissionAndEvidenceStayInsideGuestLockBeforeAttemptAndIssue(): void
    {
        require_once dirname(__DIR__, 2) . '/includes/class-invitation-bulk-admitted-locked-issuance.php';
        self::assertTrue(class_exists('WEM_Invitation_Bulk_Admitted_Locked_Issuance'));
        $steps = [];
        $state = 'not_started';
        $service = new \WEM_Invitation_Bulk_Admitted_Locked_Issuance(
            static function (int $guest, callable $callback) use (&$steps) {
                $steps[] = 'lock';
                try { return $callback(); }
                finally { $steps[] = 'unlock'; }
            },
            static function (string $operation, int $event, array $ids, int $guest) use (&$steps, &$state): string {
                $steps[] = 'admission_evidence';
                return $state;
            },
            static function (string $operation, int $guest) use (&$steps): void {
                $steps[] = 'attempt';
            },
            static function (int $guest) use (&$steps, &$state): array {
                $steps[] = 'issue';
                $state = 'reconcile';
                return ['guest_id' => $guest];
            }
        );

        self::assertSame(['guest_id' => 101], $service->run('batch-1', 7, [101], 101));
        self::assertSame(['lock', 'admission_evidence', 'attempt', 'issue', 'unlock'], $steps);

        try {
            $service->run('batch-1', 7, [101], 101);
            self::fail('A started issuance cannot be repeated.');
        } catch (RuntimeException $error) {
            self::assertNotSame('', $error->getMessage());
        }
        self::assertSame([
            'lock', 'admission_evidence', 'attempt', 'issue', 'unlock',
            'lock', 'admission_evidence', 'unlock',
        ], $steps);
    }

    public function testIndeterminateStateDoesNotStartAttemptOrIssue(): void
    {
        $events = [];
        $service = new \WEM_Invitation_Bulk_Admitted_Locked_Issuance(
            static fn (int $guest, callable $callback) => $callback(),
            static fn (string $operation, int $event, array $ids, int $guest): string => 'indeterminate',
            static function () use (&$events): void { $events[] = 'attempt'; },
            static function () use (&$events): array { $events[] = 'issue'; return []; }
        );
        $this->expectException(RuntimeException::class);
        try {
            $service->run('batch-1', 7, [101], 101);
        } finally {
            self::assertSame([], $events);
        }
    }
}
