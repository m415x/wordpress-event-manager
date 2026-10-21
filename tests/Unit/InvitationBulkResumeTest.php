<?php

declare(strict_types=1);

namespace WEM\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;

final class InvitationBulkResumeTest extends TestCase
{
    public function testResumesCustodiedCredentialWithoutReissuingAndStopsOnUncertainState(): void
    {
        $calls = [];
        $recover = static function (int $guest) use (&$calls): ?array {
            $calls[] = 'recover:' . $guest;
            return $guest === 101 ? ['guest_id' => 101, 'token' => str_repeat('A', 43)] : null;
        };
        $reconcile = static function (array $record) use (&$calls): bool {
            $calls[] = 'reconcile:' . $record['guest_id'];
            return true;
        };
        $issue = static function (int $guest) use (&$calls): array {
            $calls[] = 'issue:' . $guest;
            return ['guest_id' => $guest, 'token' => str_repeat('B', 43)];
        };
        $export = static function (array $records) use (&$calls): void {
            $calls[] = 'export:' . count($records);
        };

        require_once dirname(__DIR__, 2) . '/includes/class-invitation-bulk-resume.php';
        self::assertTrue(class_exists('WEM_Invitation_Bulk_Resume'));
        $runner = new \WEM_Invitation_Bulk_Resume($recover, $reconcile, $issue, $export);
        $runner->run([101, 102]);
        self::assertSame([
            'recover:101', 'reconcile:101',
            'recover:102', 'issue:102', 'reconcile:102', 'export:2',
        ], $calls);
    }

    public function testDoesNotReissueWhenRecoveredBearerFailsReconciliation(): void
    {
        $issued = 0;
        $exported = 0;
        $runner = new \WEM_Invitation_Bulk_Resume(
            static fn (int $guest): ?array => ['guest_id' => $guest, 'token' => str_repeat('A', 43)],
            static fn (array $record): bool => false,
            static function () use (&$issued): array {
                $issued++;
                return [];
            },
            static function () use (&$exported): void { $exported++; }
        );
        try {
            $runner->run([101]);
            self::fail('Failed reconciliation must block resume.');
        } catch (RuntimeException $exception) {
            self::assertSame(0, $issued);
            self::assertSame(0, $exported);
        }
    }
    public function testRevokedEarlierCredentialBlocksEntireResumedBatchExport(): void
    {
        $active = [101 => true, 102 => true];
        $exported = false;
        $runner = new \WEM_Invitation_Bulk_Resume(
            static fn (int $guest): ?array => $guest === 101
                ? ['guest_id' => 101, 'token' => str_repeat('A', 43)]
                : null,
            static fn (array $record): bool => $active[$record['guest_id']] ?? false,
            static function (int $guest) use (&$active): array {
                $active[101] = false;
                return ['guest_id' => $guest, 'token' => str_repeat('B', 43)];
            },
            static function () use (&$exported): void {
                $exported = true;
            }
        );

        try {
            $runner->run([101, 102]);
            self::fail('Revoked recovered credential must block the entire CSV.');
        } catch (RuntimeException $error) {
            self::assertSame('Resumed invitation credential reconciliation failed.', $error->getMessage());
        }
        self::assertFalse($exported);
    }

}
