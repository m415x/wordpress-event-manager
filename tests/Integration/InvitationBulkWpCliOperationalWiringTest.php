<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

final class InvitationBulkWpCliOperationalWiringTest extends WP_UnitTestCase
{
    public function testRegisteredCliHandlerHasAProductionCompositionRoot(): void
    {
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        self::assertTrue(class_exists('WEM_Invitation_Bulk_Wp_Cli_Composition'));
        $handler = (new \WEM_Invitation_Bulk_Wp_Cli_Composition())->handler();
        self::assertInstanceOf(\WEM_Invitation_Bulk_Wp_Cli_Handler::class, $handler);
    }

    public function testConfiguredHandlerRejectsUnprivilegedActorWithoutEffects(): void
    {
        $subscriber = self::factory()->user->create(['role' => 'subscriber']);
        $handler = (new \WEM_Invitation_Bulk_Wp_Cli_Composition())->handler();
        $this->expectException(\RuntimeException::class);
        $handler->dispatch([
            'actor' => $subscriber,
            'event' => 1,
            'operation' => 'deny-cli',
            'private_dir' => sys_get_temp_dir() . '/nonexistent-wem73',
        ]);
    }
}
