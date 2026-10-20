<?php

declare(strict_types=1);

namespace WEM\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Entry-boundary test for the future WP-CLI command.
 * This handler must never reach the workflow on unauthenticated/invalid input.
 */
final class InvitationBulkCliCommandTest extends TestCase
{
    public function testRejectsMissingOrUnauthorizedOperatorBeforeWorkflow(): void
    {
        $executions = 0;
        $workflow = static function (array $request) use (&$executions): void {
            $executions++;
        };
        $permissions = static fn (int $actor): bool => $actor === 42;

        self::assertTrue(class_exists('WEM_Invitation_Bulk_Cli_Command'));
        $command = new \WEM_Invitation_Bulk_Cli_Command($permissions, $workflow);

        foreach ([
            ['actor' => 0, 'event' => 7, 'operation' => 'run-1', 'private_dir' => '/private'],
            ['actor' => 19, 'event' => 7, 'operation' => 'run-1', 'private_dir' => '/private'],
            ['actor' => 42, 'event' => 0, 'operation' => 'run-1', 'private_dir' => '/private'],
            ['actor' => 42, 'event' => 7, 'operation' => '../invalid', 'private_dir' => '/private'],
            ['actor' => 42, 'event' => 7, 'operation' => 'run-1', 'private_dir' => ''],
        ] as $request) {
            try {
                $command->execute($request);
                self::fail('Invalid WP-CLI request must be denied.');
            } catch (RuntimeException $error) {
                self::assertNotSame('', $error->getMessage());
            }
        }

        self::assertSame(0, $executions, 'Denied requests must not reach persistent workflow.');
        $command->execute([
            'actor' => 42, 'event' => 7, 'operation' => 'run-1', 'private_dir' => '/private',
        ]);
        self::assertSame(1, $executions);
    }
}
