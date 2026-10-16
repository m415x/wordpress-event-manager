<?php

declare(strict_types=1);

namespace WEM\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AdminInvitationQrReissueConflictContractTest extends TestCase
{
    public function testActiveReissueConflictHasSafeSpecificClientMessage(): void
    {
        $root = dirname(__DIR__, 2);
        $orchestrator = (string) file_get_contents($root . '/includes/class-admin-invitation-qr-orchestrator.php');
        $delivery = (string) file_get_contents($root . '/includes/class-admin-invitation-qr-delivery.php');
        $client = (string) file_get_contents($root . '/assets/js/admin-invitation-qr.js');

        self::assertStringContainsString('WEM_Invitation_QR_Active_Reissue_Conflict', $orchestrator);
        self::assertStringContainsString('invitation_qr_active_reissue_conflict', $delivery);
        self::assertStringContainsString('invitation_qr_active_reissue_conflict', $client);
        self::assertStringContainsString('invitation_qr_unavailable', $delivery);
        self::assertStringNotContainsString('getMessage()', $delivery);
    }
}
