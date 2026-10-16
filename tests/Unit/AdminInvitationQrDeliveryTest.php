<?php

declare(strict_types=1);

namespace WEM\Tests\Unit;

use PHPUnit\Framework\TestCase;

/** WEM-65: protected admin-only transport for one-shot QR presentation. */
final class AdminInvitationQrDeliveryTest extends TestCase
{
    public function testAdminDeliveryRegistersAuthenticatedActionsAndEnforcesSecurity(): void
    {
        $root = dirname(__DIR__, 2);
        $path = $root . '/includes/class-admin-invitation-qr-delivery.php';

        self::assertFileExists($path, 'An orchestrator alone does not protect an HTTP response.');

        $source = (string) file_get_contents($path);
        self::assertStringContainsString('wp_ajax_wem_invitation_qr_', $source);
        self::assertStringContainsString('current_user_can', $source);
        self::assertStringContainsString('manage_options', $source);
        self::assertStringContainsString('check_ajax_referer', $source);
        self::assertStringContainsString('nocache_headers', $source);
        self::assertStringContainsString('no-store', $source);
        self::assertStringContainsString('WEM_Admin_Invitation_QR_Orchestrator', $source);
        self::assertStringNotContainsString('wp_ajax_nopriv_', $source);
    }
}
