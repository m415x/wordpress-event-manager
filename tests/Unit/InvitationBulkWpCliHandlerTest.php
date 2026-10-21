<?php

declare(strict_types=1);

namespace WEM\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;

final class InvitationBulkWpCliHandlerTest extends TestCase
{
    public function testUnconfiguredHandlerFailsClosedBeforeAnyEffect(): void
    {
        require_once dirname(__DIR__, 2) . '/includes/class-invitation-bulk-cli-command.php';
        require_once dirname(__DIR__, 2) . '/includes/class-invitation-bulk-wp-cli-handler.php';
        self::assertTrue(class_exists('WEM_Invitation_Bulk_Wp_Cli_Handler'));
        $handler = new \WEM_Invitation_Bulk_Wp_Cli_Handler();
        try {
            $handler->issue([], ['event' => '7', 'operation' => 'run-1']);
            self::fail('Unconfigured CLI must refuse issuance.');
        } catch (RuntimeException $error) {
            self::assertStringNotContainsString('token', strtolower($error->getMessage()));
        }
    }

    public function testConfiguredHandlerDelegatesOnlyAfterValidatedCommandBoundary(): void
    {
        $executions = 0;
        $workflow = static function (array $request) use (&$executions): void {
            $executions++;
        };
        $command = new \WEM_Invitation_Bulk_Cli_Command(
            static fn (int $actor): bool => $actor === 9,
            $workflow
        );
        $handler = new \WEM_Invitation_Bulk_Wp_Cli_Handler($command);
        $handler->dispatch(['actor' => 9, 'event' => 7, 'operation' => 'run-1', 'private_dir' => '/private']);
        self::assertSame(1, $executions);
    }
}
