<?php

declare(strict_types=1);

namespace WEM\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;

final class InvitationBulkRecoveredCredentialTest extends TestCase
{
    public function testRecoveredCredentialRequiresCommittedEvidenceAndReconciliation(): void
    {
        self::assertTrue(class_exists('WEM_Invitation_Bulk_Recovered_Credential'));
        $events = [];
        $service = new \WEM_Invitation_Bulk_Recovered_Credential(
            static function (string $operation, int $guest) use (&$events): string {
                $events[] = 'evidence';
                return 'reconcile';
            },
            static function (string $operation, int $guest) use (&$events): array {
                $events[] = 'recover';
                return ['operation_id' => $operation, 'guest_id' => $guest, 'event_id' => 7,
                    'generation' => 1, 'token' => 'opaque'];
            },
            static function (array $record) use (&$events): bool {
                $events[] = 'reconcile';
                return true;
            }
        );
        self::assertSame('opaque', $service->recover('batch-1', 7, 101)['token']);
        self::assertSame(['evidence', 'recover', 'reconcile'], $events);
    }

    public function testIndeterminateOrRevokedCredentialNeverReturnsBearer(): void
    {
        $reads = 0;
        $service = new \WEM_Invitation_Bulk_Recovered_Credential(
            static fn (string $operation, int $guest): string => 'indeterminate',
            static function () use (&$reads): array { $reads++; return ['token' => 'opaque']; },
            static fn (array $record): bool => true
        );
        try {
            $service->recover('batch-1', 7, 101);
            self::fail('Indeterminate evidence must block recovery.');
        } catch (RuntimeException $error) {
            self::assertSame(0, $reads);
        }

        $service = new \WEM_Invitation_Bulk_Recovered_Credential(
            static fn (string $operation, int $guest): string => 'reconcile',
            static fn (string $operation, int $guest): array => [
                'operation_id' => $operation, 'guest_id' => $guest,
                'event_id' => 7, 'generation' => 1, 'token' => 'opaque'
            ],
            static fn (array $record): bool => false
        );
        $this->expectException(RuntimeException::class);
        $service->recover('batch-1', 7, 101);
    }
}
