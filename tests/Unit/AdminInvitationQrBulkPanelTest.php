<?php

declare(strict_types=1);

namespace WEM\Tests\Unit;

use PHPUnit\Framework\TestCase;

/** WEM-66: admin-only Spanish bulk QR UI requires explicit preview and consent. */
final class AdminInvitationQrBulkPanelTest extends TestCase
{
    public function testBulkPanelIsAdminOnlyAndProvidesSpanishControls(): void
    {
        $path = dirname(__DIR__, 2) . '/includes/class-admin-invitation-qr-bulk-panel.php';
        self::assertFileExists($path);
        $source = (string) file_get_contents($path);

        self::assertStringContainsString("manage_options", $source);
        self::assertStringContainsString("wem_invitation_qr_bulk_admin", $source);
        self::assertStringContainsString("data-wem-bulk-qr-panel", $source);
        self::assertStringContainsString("Previsualizar elegibilidad", $source);
        self::assertStringContainsString("Emitir QR seleccionados", $source);
        self::assertStringContainsString("Reemitir QR seleccionados", $source);
        self::assertStringContainsString("Máximo 25 invitados", $source);
        self::assertStringNotContainsString("Issue selected", $source);
        self::assertStringNotContainsString("Preview eligibility", $source);
    }

    public function testBulkScriptRequiresExplicitUserConfirmationAndSeparatePreview(): void
    {
        $path = dirname(__DIR__, 2) . '/assets/js/admin-invitation-qr-bulk.js';
        self::assertFileExists($path);
        $source = (string) file_get_contents($path);

        self::assertStringContainsString("wem_invitation_qr_bulk_preview", $source);
        self::assertStringContainsString("wem_invitation_qr_bulk", $source);
        self::assertStringContainsString("window.confirm", $source);
        self::assertStringContainsString("no-store", $source);
        self::assertStringContainsString("no-referrer", $source);
        self::assertStringContainsString("Revisá la selección antes de continuar", $source);
        self::assertStringContainsString("No repitas la operación sin verificar el estado", $source);
        self::assertStringNotContainsString("Generating QR", $source);
        self::assertStringNotContainsString("Continue?", $source);
    }
}
