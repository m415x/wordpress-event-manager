<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * WEM-73 CLI composition: validation must happen before any batch work.
 * The supplied workflow is a spy, not an issuer.
 */
final class InvitationBulkCliRuntimeCompositionTest extends TestCase
{
    public function testRejectsUnprovisionedPrivateDirectoryBeforeWorkflow(): void
    {
        require_once dirname(__DIR__, 2) . '/includes/class-invitation-bulk-cli-command.php';
        require_once dirname(__DIR__, 2) . '/includes/class-invitation-bulk-cli-runtime.php';
        self::assertTrue(class_exists('WEM_Invitation_Bulk_Cli_Runtime'));

        $invoked = 0;
        $runtime = new \WEM_Invitation_Bulk_Cli_Runtime(
            static fn (int $actor): bool => $actor === 9,
            static function (array $request) use (&$invoked): void {
                $invoked++;
            }
        );
        $request = [
            'actor' => 9,
            'event' => 7,
            'operation' => 'batch-1',
            'private_dir' => '/nonexistent/wem73-' . bin2hex(random_bytes(8)),
        ];
        try {
            $runtime->execute($request);
            self::fail('Unprovisioned private storage must fail closed.');
        } catch (RuntimeException $error) {
            self::assertSame(0, $invoked);
        }
    }

    public function testAcceptsPrivateProvisionedDirectoryOnlyAfterAuthorization(): void
    {
        $directory = sys_get_temp_dir() . '/wem73-runtime-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($directory, 0700));
        try {
            $invoked = [];
            $runtime = new \WEM_Invitation_Bulk_Cli_Runtime(
                static fn (int $actor): bool => $actor === 9,
                static function (array $request) use (&$invoked): void {
                    $invoked[] = $request;
                }
            );
            $request = ['actor' => 9, 'event' => 7, 'operation' => 'batch-1', 'private_dir' => $directory];
            $runtime->execute($request);
            self::assertSame([$request], $invoked);
            $request['actor'] = 10;
            try {
                $runtime->execute($request);
                self::fail('Unauthorized actor must be denied.');
            } catch (RuntimeException $error) {
                self::assertCount(1, $invoked);
            }
        } finally {
            rmdir($directory);
        }
    }
    public function testRejectsDirectoryInsideDocumentRootEvenWhenOutsideWordPressInstall(): void
    {
        $root = sys_get_temp_dir() . '/wem73-public-' . bin2hex(random_bytes(8));
        $directory = $root . '/invitations';
        self::assertTrue(mkdir($root, 0700));
        self::assertTrue(mkdir($directory, 0700));
        $previous = $_SERVER['DOCUMENT_ROOT'] ?? null;
        $_SERVER['DOCUMENT_ROOT'] = $root;
        try {
            $invoked = 0;
            $runtime = new \WEM_Invitation_Bulk_Cli_Runtime(
                static fn (int $actor): bool => $actor === 9,
                static function (array $request) use (&$invoked): void {
                    $invoked++;
                }
            );
            $rejected = false;
            try {
                $runtime->execute([
                    'actor' => 9,
                    'event' => 7,
                    'operation' => 'public-directory',
                    'private_dir' => $directory,
                ]);
            } catch (RuntimeException $error) {
                $rejected = true;
            }
            self::assertTrue($rejected, 'Document-root storage must be rejected before the workflow.');
            self::assertSame(0, $invoked);
        } finally {
            if ($previous === null) {
                unset($_SERVER['DOCUMENT_ROOT']);
            } else {
                $_SERVER['DOCUMENT_ROOT'] = $previous;
            }
            rmdir($directory);
            rmdir($root);
        }
    }
}
