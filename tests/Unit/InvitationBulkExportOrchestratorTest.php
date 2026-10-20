<?php

declare(strict_types=1);

namespace WEM\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;

final class InvitationBulkExportOrchestratorTest extends TestCase
{
    public function testNeverExportsUnreconciledBearerOrRetriesAnUncertainIssue(): void
    {
        $calls = [];
        $issuer = static function (int $guest) use (&$calls): array {
            $calls[] = 'issue:' . $guest;
            if ($guest === 102) {
                throw new RuntimeException('commit outcome indeterminate');
            }
            return ['guest_id' => $guest, 'token' => str_repeat('A', 43)];
        };
        $reconcile = static function (array $record) use (&$calls): bool {
            $calls[] = 'reconcile:' . $record['guest_id'];
            return $record['guest_id'] === 101;
        };
        $export = static function (array $records) use (&$calls): void {
            $calls[] = 'export:' . count($records);
        };

        self::assertTrue(class_exists('WEM_Invitation_Bulk_Export_Orchestrator'));
        $orchestrator = new \WEM_Invitation_Bulk_Export_Orchestrator(
            $issuer,
            $reconcile,
            $export
        );
        try {
            $orchestrator->run([101, 102]);
            self::fail('Indeterminate issuance must block the complete export.');
        } catch (RuntimeException $exception) {
            self::assertSame('commit outcome indeterminate', $exception->getMessage());
        }
        self::assertSame(['issue:101', 'reconcile:101', 'issue:102'], $calls);
    }

    public function testRejectsAReconciliationFailureBeforeAnyExport(): void
    {
        $exports = 0;
        $orchestrator = new \WEM_Invitation_Bulk_Export_Orchestrator(
            static fn (int $guest): array => ['guest_id' => $guest, 'token' => str_repeat('B', 43)],
            static fn (array $record): bool => false,
            static function () use (&$exports): void { $exports++; }
        );
        $this->expectException(RuntimeException::class);
        try {
            $orchestrator->run([101]);
        } finally {
            self::assertSame(0, $exports);
        }
    }
}
