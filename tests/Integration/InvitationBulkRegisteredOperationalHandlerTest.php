<?php
declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

/** The registered CLI command must use the production composition root. */
final class InvitationBulkRegisteredOperationalHandlerTest extends WP_UnitTestCase
{
    public function testCliRegistrationProvidesConfiguredHandlerRatherThanBareClassName(): void
    {
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        $registered = [];
        $registration = new \WEM_Invitation_Bulk_Cli_Registration(
            static function (string $name, $handler) use (&$registered): void {
                $registered[] = [$name, $handler];
            }
        );

        $registration->register(false);
        self::assertSame([], $registered);

        $registration->register(true);
        self::assertCount(1, $registered);
        self::assertSame('wem invitations', $registered[0][0]);
        self::assertInstanceOf(\WEM_Invitation_Bulk_Wp_Cli_Handler::class, $registered[0][1]);

        $subscriber = self::factory()->user->create(['role' => 'subscriber']);
        $this->expectException(\RuntimeException::class);
        $registered[0][1]->dispatch([
            'actor' => $subscriber,
            'event' => 1,
            'operation' => 'denied-registration',
            'private_dir' => '/invalid',
        ]);
    }
}
