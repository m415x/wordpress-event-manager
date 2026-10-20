<?php

declare(strict_types=1);

namespace WEM\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;

final class InvitationCustodyIssuanceBridgeTest extends TestCase
{
    public function testCustodiesBeforeIssuanceCanReturnAndNeverRetriesAnAmbiguousCommit(): void
    {
        $calls = [];
        $bearer = rtrim(strtr(base64_encode(str_repeat('z', 32)), '+/', '-_'), '=');
        $service = new class($calls, $bearer) {
            public array $calls = [];
            private string $bearer;
            public function __construct(&$calls, string $bearer)
            {
                $this->calls =& $calls;
                $this->bearer = $bearer;
            }
            public function issue($guest, $actor, $custody = null): array
            {
                $this->calls[] = 'insert';
                $custody([
                    'guest_id' => $guest,
                    'generation' => 1,
                    'token' => $this->bearer,
                    'token_digest' => hash('sha256', $this->bearer),
                ]);
                $this->calls[] = 'commit-attempt';
                throw new RuntimeException('commit outcome uncertain');
            }
        };
        $journal = new class($calls) {
            public array $calls = [];
            public array $record = [];
            public function __construct(&$calls)
            {
                $this->calls =& $calls;
            }
            public function custody(array $record): void
            {
                $this->calls[] = 'durable-custody';
                $this->record = $record;
            }
        };

        $bridge = new \WEM_Invitation_Custody_Issuance_Bridge($service, $journal);
        try {
            $bridge->issue(31, 8, 4, 'run-1');
            self::fail('Ambiguous commit must remain unresolved.');
        } catch (RuntimeException $exception) {
            self::assertSame('commit outcome uncertain', $exception->getMessage());
        }
        self::assertSame(['insert', 'durable-custody', 'commit-attempt'], $calls);
        self::assertSame(4, $journal->record['event_id']);
        self::assertSame('run-1', $journal->record['operation_id']);
        self::assertSame(31, $journal->record['guest_id']);
        self::assertSame(1, $journal->record['generation']);
        self::assertSame($bearer, $journal->record['token']);
    }
}
