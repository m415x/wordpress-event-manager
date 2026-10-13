<?php

declare(strict_types=1);

namespace WEM\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * WEM-65: a discoverable, administrator-only individual QR presentation.
 * Existing credentials are never reconstructed from their stored hash.
 */
final class AdminInvitationQrPanelContractTest extends TestCase
{
    public function testGuestEditorHasExplicitAdministratorOnlyOneShotQrPanel(): void
    {
        $root = dirname(__DIR__, 2);
        $panel = $root . '/includes/class-admin-invitation-qr-panel.php';

        self::assertFileExists($panel, 'An AJAX endpoint without an admin UI does not complete T3.');
        $source = (string) file_get_contents($panel);
        self::assertStringContainsString('add_meta_boxes_invitado', $source);
        self::assertStringContainsString('manage_options', $source);
        self::assertStringContainsString('wp_create_nonce', $source);
        self::assertStringContainsString('wem_invitation_qr_admin', $source);
        self::assertStringContainsString('wp_enqueue_script', $source);
        self::assertStringContainsString('post.php', $source);
        self::assertStringContainsString('post-new.php', $source);
        self::assertStringNotContainsString('get_post_meta($post->ID,', $source);
    }
}
