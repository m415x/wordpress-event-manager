<?php

declare(strict_types=1);

namespace WEM\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;

final class InvitationBulkAttemptBoundaryTest extends TestCase
{
    public function testPersistsAttemptBeforeCallingIssuer(): void
    {
        $events = [];
        $mark = static function (string $operation, int $guest) use (&$events): void {
            $events[] = "started:{$operation}:{$guest}";
        };
        $issue = static function (int $guest) use (&$events): void {
            $events[] = "issue:{$guest}";
        };

        require_once dirname(__DIR__, 2) . '/includes/class-invitation-bulk-attempt-boundary.php';
        self::assertTrue(class_exists('WEM_Invitation_Bulk_Attempt_Boundary'));
        $boundary = new \WEM_Invitation_Bulk_Attempt_Boundary($mark);
        $boundary->issue('run-1', 101, $issue);
        self::assertSame(['started:run-1:101', 'issue:101'], $events);
    }

    public function testFailedDurableAttemptMarkerPreventsIssuance(): void
    {
        $invocations = 0;
        $boundary = new \WEM_Invitation_Bulk_Attempt_Boundary(
            static function (): void {
                throw new RuntimeException('attempt storage unavailable');
            }
        );

        try {
            $boundary->issue('run-2', 102, static function () use (&$invocations): void {
                $invocations++;
            });
            self::fail('Issuance may not begin without durable attempt evidence.');
        } catch (RuntimeException $exception) {
            self::assertSame('attempt storage unavailable', $exception->getMessage());
        }
        self::assertSame(0, $invocations);
    }
}
