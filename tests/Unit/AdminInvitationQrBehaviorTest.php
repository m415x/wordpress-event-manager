<?php

declare(strict_types=1);

namespace WEM\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * WEM-65: behavior boundaries of a one-shot administrative QR response.
 */
final class AdminInvitationQrBehaviorTest extends TestCase
{
    public function testAdminQrOrchestratorSeparatesAuthorizationFromPresentation(): void
    {
        $path = dirname(__DIR__, 2) . '/includes/class-admin-invitation-qr-orchestrator.php';
        self::assertFileExists($path);
        $source = (string) file_get_contents($path);

        self::assertStringContainsString("user_can(\$actor_user_id, 'manage_options')", $source);
        self::assertStringContainsString("add_query_arg('wem_invitation', \$token, home_url('/'))", $source);
        self::assertStringContainsString('render_svg($url)', $source);

        // The public invitation bearer must never be recoverable from storage.
        self::assertStringNotContainsString('get_option(', $source);
        self::assertStringNotContainsString('get_post_meta(', $source);
        self::assertStringNotContainsString('token_digest', $source);
    }

    public function testAdminQrOrchestratorDoesNotImplementCompensatingCredentialActions(): void
    {
        $path = dirname(__DIR__, 2) . '/includes/class-admin-invitation-qr-orchestrator.php';
        $source = (string) file_get_contents($path);

        self::assertStringNotContainsString('->revoke(', $source);
        self::assertStringNotContainsString('ROLLBACK', $source);
        self::assertStringNotContainsString('file_put_contents(', $source);

        // Actual transport must be secured explicitly by the admin delivery layer.
        self::assertStringContainsString('no-store', $source);
    }
}
