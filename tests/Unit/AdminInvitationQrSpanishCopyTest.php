<?php

declare(strict_types=1);

namespace WEM\Tests\Unit;

use PHPUnit\Framework\TestCase;

/** WEM-34 temporary event policy: all visible QR UI copy is in Spanish. */
final class AdminInvitationQrSpanishCopyTest extends TestCase
{
    public function testGuestEditorQrLabelsAndHelpAreInSpanish(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/includes/class-admin-invitation-qr-panel.php');

        foreach (array(
            'QR de invitación',
            'Emitir QR',
            'Rotar QR',
            'Reemitir QR',
            'Guardalos de forma segura',
        ) as $copy) {
            self::assertStringContainsString($copy, $source);
        }

        foreach (array('Invitation QR', 'Issue QR', 'Rotate QR', 'Reissue QR', 'Each action creates') as $english) {
            self::assertStringNotContainsString($english, $source);
        }
    }

    public function testBrowserConfirmationsStatusErrorsAndAccessibilityCopyAreInSpanish(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/assets/js/admin-invitation-qr.js');

        foreach (array(
            'Esta acción crea una nueva credencial de invitación.',
            'Generando QR de invitación',
            'Ya existe una credencial de invitación.',
            'No se puede reemitir mientras exista una credencial activa.',
            'Código QR de invitación',
            'URL de invitación',
            'Copiá la URL ahora.',
            'No vuelvas a intentar la operación sin verificar su estado.',
        ) as $copy) {
            self::assertStringContainsString($copy, $source);
        }

        foreach (array(
            'This action creates',
            'Generating invitation',
            'An invitation credential already exists',
            'Reissue is unavailable',
            'Invitation QR code',
            'Copy the URL now',
            'QR presentation failed',
        ) as $english) {
            self::assertStringNotContainsString($english, $source);
        }
    }
}
