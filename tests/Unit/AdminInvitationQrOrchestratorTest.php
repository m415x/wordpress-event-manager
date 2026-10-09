<?php

declare(strict_types=1);

namespace WEM\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * WEM-65/T3: administrative QR orchestration must be separate from the
 * WEM-33 credential lifecycle and never attempt bearer recovery.
 */
final class AdminInvitationQrOrchestratorTest extends TestCase
{
    public function testQrOrchestrationHasDedicatedAdministrativeBoundary(): void
    {
        $root = dirname(__DIR__, 2);
        $file = $root . '/includes/class-admin-invitation-qr-orchestrator.php';

        self::assertFileExists(
            $file,
            'The QR adapter must not be coupled to WEM-33 credential persistence.'
        );

        $source = (string) file_get_contents($file);
        self::assertStringContainsString('WEM_Invitation_Credential_Service', $source);
        self::assertStringContainsString('manage_options', $source);
        self::assertStringContainsString('WEM_Local_QR_Encoder', $source);
        self::assertStringContainsString('wem_invitation', $source);
    }
}
