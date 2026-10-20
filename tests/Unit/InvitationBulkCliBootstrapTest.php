<?php

declare(strict_types=1);

namespace WEM\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * WEM-73 wiring contract: CLI only, with the issue handler remaining
 * deliberately fail-closed until operational orchestration is approved.
 */
final class InvitationBulkCliBootstrapTest extends TestCase
{
    public function testPluginBootstrapRegistersCliCommandOnlyWhenWpCliIsActive(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/wordpress-event-manager.php');
        self::assertIsString($source);
        self::assertStringContainsString('defined(\'WP_CLI\')', $source);
        self::assertStringContainsString('WP_CLI', $source);
        self::assertStringContainsString('WEM_Invitation_Bulk_Cli_Registration', $source);
        self::assertStringContainsString('->register(true)', $source);
    }
}
