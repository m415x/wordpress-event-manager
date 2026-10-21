<?php

declare(strict_types=1);

namespace WEM\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;

final class InvitationBulkWpCliIssueDispatchTest extends TestCase
{
    protected function setUp(): void
    {
        require_once dirname(__DIR__, 2) . '/includes/class-invitation-bulk-cli-command.php';
        require_once dirname(__DIR__, 2) . '/includes/class-invitation-bulk-wp-cli-handler.php';
    }

    public function testConfiguredCliHandlerDispatchesTypedValidatedParameters(): void
    {
        $requests = [];
        $command = new \WEM_Invitation_Bulk_Cli_Command(
            static fn (int $actor): bool => $actor === 9,
            static function (array $request) use (&$requests): void { $requests[] = $request; }
        );
        (new \WEM_Invitation_Bulk_Wp_Cli_Handler($command))->issue([], [
            'actor' => '9',
            'event' => '7',
            'operation' => 'batch-01',
            'private-dir' => '/secure/invitations',
        ]);
        self::assertSame([[
            'actor' => 9, 'event' => 7, 'operation' => 'batch-01',
            'private_dir' => '/secure/invitations',
        ]], $requests);
    }

    public function testMalformedCliArgumentsAreRejectedBeforeWorkflow(): void
    {
        $calls = 0;
        $command = new \WEM_Invitation_Bulk_Cli_Command(
            static fn (int $actor): bool => true,
            static function () use (&$calls): void { $calls++; }
        );
        $handler = new \WEM_Invitation_Bulk_Wp_Cli_Handler($command);
        foreach ([
            ['actor' => '9x', 'event' => '7', 'operation' => 'batch-01', 'private-dir' => '/secure'],
            ['actor' => '9', 'event' => '-7', 'operation' => 'batch-01', 'private-dir' => '/secure'],
            ['actor' => '9', 'event' => '7', 'operation' => '../escape', 'private-dir' => '/secure'],
        ] as $args) {
            try {
                $handler->issue([], $args);
                self::fail('Malformed CLI arguments must not invoke the workflow.');
            } catch (RuntimeException $error) {
                self::assertNotSame('', $error->getMessage());
            }
        }
        self::assertSame(0, $calls);
    }
}
