<?php

declare(strict_types=1);

namespace WEM\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * WEM-66/T4: classification is read-only and cannot grant bulk mutation authority.
 */
final class AdminInvitationQrBulkEligibilityTest extends TestCase
{
    public function testNeverIssuedGuestIsEligibleOnlyForIssue(): void
    {
        require_once dirname(__DIR__, 2) . '/includes/class-admin-invitation-qr-bulk-eligibility.php';

        self::assertSame(
            'never_issued',
            \WEM_Admin_Invitation_QR_Bulk_Eligibility::classify(true, array())
        );
    }

    public function testActiveCredentialIsUnrecoverableAndNotBulkEligible(): void
    {
        self::assertSame(
            'active_unrecoverable',
            \WEM_Admin_Invitation_QR_Bulk_Eligibility::classify(
                true,
                array(array('status' => 'active', 'active_slot' => 1))
            )
        );
    }

    public function testRevokedOrOtherwiseInactiveHistoryRequiresExplicitReissue(): void
    {
        self::assertSame(
            'inactive_reissue',
            \WEM_Admin_Invitation_QR_Bulk_Eligibility::classify(
                true,
                array(array('status' => 'revoked', 'active_slot' => null))
            )
        );
        self::assertSame(
            'inactive_reissue',
            \WEM_Admin_Invitation_QR_Bulk_Eligibility::classify(
                true,
                array(array('status' => 'rotated', 'active_slot' => null))
            )
        );
    }

    public function testInvalidGuestAndInconsistentCredentialHistoryFailClosed(): void
    {
        self::assertSame(
            'invalid',
            \WEM_Admin_Invitation_QR_Bulk_Eligibility::classify(false, array())
        );
        self::assertSame(
            'invalid',
            \WEM_Admin_Invitation_QR_Bulk_Eligibility::classify(
                true,
                array(array('status' => 'active', 'active_slot' => null))
            )
        );
        self::assertSame(
            'invalid',
            \WEM_Admin_Invitation_QR_Bulk_Eligibility::classify(
                true,
                array(array('status' => 'active', 'active_slot' => 1), array('status' => 'active', 'active_slot' => 1))
            )
        );
    }
}
