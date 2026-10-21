<?php

declare(strict_types=1);

namespace WEM\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;

final class InvitationBulkMixedResumeTest extends TestCase
{
    public function testRecoversCustodiedGuestAndIssuesOnlyVerifiedNotStartedGuest(): void
    {
        self::assertTrue(class_exists('WEM_Invitation_Bulk_Mixed_Resume'));
        $events = [];
        $states = [101 => 'reconcile', 102 => 'not_started'];

        $resume = new \WEM_Invitation_Bulk_Mixed_Resume(
            static function (int $guest) use (&$states, &$events): string {
                $events[] = 'state:' . $guest;
                return $states[$guest];
            },
            static function (int $guest) use (&$events): array {
                $events[] = 'recover:' . $guest;
                return ['guest_id' => $guest, 'token' => str_repeat('A', 43)];
            },
            static function (int $guest) use (&$events): array {
                $events[] = 'issue:' . $guest;
                return ['guest_id' => $guest, 'token' => str_repeat('B', 43)];
            },
            static function (array $record) use (&$events): bool {
                $events[] = 'reconcile:' . $record['guest_id'];
                return true;
            },
            static function (array $records) use (&$events): void {
                $events[] = 'export:' . count($records);
            }
        );
        $resume->run([101, 102]);

        self::assertSame([
            'state:101', 'recover:101', 'reconcile:101',
            'state:102', 'issue:102', 'reconcile:102',
            'reconcile:101', 'reconcile:102', 'export:2',
        ], $events);
    }

    public function testIndeterminateGuestBlocksEntireBatchWithoutIssuingOrExporting(): void
    {
        $events = [];
        $resume = new \WEM_Invitation_Bulk_Mixed_Resume(
            static fn (int $guest): string => $guest === 101 ? 'reconcile' : 'indeterminate',
            static fn (int $guest): array => ['guest_id' => $guest, 'token' => str_repeat('A', 43)],
            static function (int $guest) use (&$events): array {
                $events[] = 'issue';
                return ['guest_id' => $guest];
            },
            static fn (array $record): bool => true,
            static function () use (&$events): void { $events[] = 'export'; }
        );
        $this->expectException(RuntimeException::class);
        try {
            $resume->run([101, 102]);
        } finally {
            self::assertSame([], $events);
        }
    }
}
