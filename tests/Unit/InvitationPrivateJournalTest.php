<?php

declare(strict_types=1);

namespace WEM\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;

final class InvitationPrivateJournalTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/wem73-journal-test-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->root, 0700, true));
        require_once dirname(__DIR__, 2) . '/includes/class-invitation-private-journal.php';
    }

    protected function tearDown(): void
    {
        foreach (glob($this->root . '/*') ?: [] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        if (is_dir($this->root)) {
            rmdir($this->root);
        }
    }

    public function testPrecommitCustodyIsDurableAndRecoverableWithoutReissuing(): void
    {
        $journal = new \WEM_Invitation_Private_Journal($this->root);
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $record = [
            'operation_id' => 'synthetic-operation-1',
            'guest_id' => 501,
            'event_id' => 19,
            'generation' => 1,
            'token' => $token,
            'token_digest' => hash('sha256', $token),
        ];

        $journal->custody($record);

        // A fresh instance simulates a new PHP process after an interrupted COMMIT.
        $reopened = new \WEM_Invitation_Private_Journal($this->root);
        self::assertSame($record, $reopened->recover('synthetic-operation-1', 501, 1));
        $paths = glob($this->root . '/*') ?: [];
        self::assertCount(1, $paths);
        self::assertSame(0600, fileperms($paths[0]) & 0777);

        try {
            $reopened->custody(array_merge($record, ['token' => str_repeat('A', 43)]));
            self::fail('Private journal must never overwrite a previously custodied bearer.');
        } catch (RuntimeException $error) {
            self::assertStringNotContainsString($token, $error->getMessage());
        }

        self::assertSame($record, $reopened->recover('synthetic-operation-1', 501, 1));
    }

    public function testCustodyRejectsAPathInsideWebroot(): void
    {
        $webroot = $this->root . '/public';
        self::assertTrue(mkdir($webroot, 0700));
        $journal = new \WEM_Invitation_Private_Journal($webroot, [$this->root]);
        $this->expectException(RuntimeException::class);
        $journal->custody([
            'operation_id' => 'synthetic-operation-2',
            'guest_id' => 502,
            'event_id' => 19,
            'generation' => 1,
            'token' => str_repeat('A', 43),
            'token_digest' => hash('sha256', str_repeat('A', 43)),
        ]);
    }
}
