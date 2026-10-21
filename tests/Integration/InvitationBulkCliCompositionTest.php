<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use RuntimeException;
use WP_UnitTestCase;

/**
 * WEM-73: the production CLI composition must use real WordPress permissions.
 * It must deny before reaching any persistent workflow.
 */
final class InvitationBulkCliCompositionTest extends WP_UnitTestCase
{
    public function testCompositionRejectsSubscriberBeforePersistentWorkflow(): void
    {
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';

        $subscriber = self::factory()->user->create(['role' => 'subscriber']);
        $administrator = self::factory()->user->create(['role' => 'administrator']);
        $executions = [];
        $workflow = static function (array $request) use (&$executions): void {
            $executions[] = $request['actor'];
        };

        self::assertTrue(class_exists('WEM_Invitation_Bulk_Cli_Factory'));
        $command = (new \WEM_Invitation_Bulk_Cli_Factory())->command($workflow);

        $request = [
            'actor' => $subscriber,
            'event' => 7,
            'operation' => 'run-1',
            'private_dir' => '/private',
        ];
        try {
            $command->execute($request);
            self::fail('Subscriber must not reach the bulk workflow.');
        } catch (RuntimeException $error) {
            self::assertNotSame('', $error->getMessage());
        }
        self::assertSame([], $executions);

        $request['actor'] = $administrator;
        $command->execute($request);
        self::assertSame([$administrator], $executions);
    }
}
