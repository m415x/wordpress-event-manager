<?php

declare(strict_types=1);

namespace WEM\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Duplicate Issue is a pre-commit rejection, not a QR presentation failure.
 * The admin must get a fixed, non-sensitive machine-readable error code.
 */
final class AdminInvitationQrDuplicateIssueContractTest extends TestCase
{
    public function testDuplicateIssueIsDistinguishedFromPostCommitPresentationError(): void
    {
        $root = dirname(__DIR__, 2);
        $delivery = (string) file_get_contents($root . '/includes/class-admin-invitation-qr-delivery.php');
        $client = (string) file_get_contents($root . '/assets/js/admin-invitation-qr.js');

        self::assertStringContainsString(
            'invitation_qr_already_issued',
            $delivery,
            'Duplicate issue must have a stable, non-secret error code.'
        );
        self::assertStringContainsString(
            'invitation_qr_already_issued',
            $client,
            'Admin UI must explain duplicate issue without implying QR rendering failed.'
        );
        self::assertStringContainsString(
            'invitation_qr_unavailable',
            $delivery,
            'Unclassified failures must remain generic.'
        );
        self::assertStringNotContainsString('getMessage()', $delivery);
    }
}
