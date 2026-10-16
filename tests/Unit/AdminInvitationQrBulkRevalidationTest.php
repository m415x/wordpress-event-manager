<?php

declare(strict_types=1);

namespace WEM\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Bulk QR selection is a preview, not authorization to mutate credentials.
 */
final class AdminInvitationQrBulkRevalidationTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (!defined('ABSPATH')) {
            define('ABSPATH', '/tmp/wem66-bulk-revalidation-unit/');
        }
        require_once dirname(__DIR__, 2) . '/includes/class-admin-invitation-qr-bulk-revalidation.php';
    }

    public function testIssueRequiresCurrentNeverIssuedState(): void
    {
        self::assertTrue(\WEM_Admin_Invitation_QR_Bulk_Revalidation::permits('issue', 'never_issued'));
        self::assertFalse(\WEM_Admin_Invitation_QR_Bulk_Revalidation::permits('issue', 'active_unrecoverable'));
        self::assertFalse(\WEM_Admin_Invitation_QR_Bulk_Revalidation::permits('issue', 'inactive_reissue'));
    }

    public function testReissueRequiresCurrentInactiveHistory(): void
    {
        self::assertTrue(\WEM_Admin_Invitation_QR_Bulk_Revalidation::permits('reissue', 'inactive_reissue'));
        self::assertFalse(\WEM_Admin_Invitation_QR_Bulk_Revalidation::permits('reissue', 'never_issued'));
        self::assertFalse(\WEM_Admin_Invitation_QR_Bulk_Revalidation::permits('reissue', 'active_unrecoverable'));
    }

    public function testUnknownInvalidAndRotateAlwaysDeny(): void
    {
        foreach (array('issue', 'reissue', 'rotate', 'automatic') as $operation) {
            self::assertFalse(\WEM_Admin_Invitation_QR_Bulk_Revalidation::permits($operation, 'invalid'));
            self::assertFalse(\WEM_Admin_Invitation_QR_Bulk_Revalidation::permits($operation, 'unknown'));
        }
        self::assertFalse(\WEM_Admin_Invitation_QR_Bulk_Revalidation::permits('rotate', 'never_issued'));
    }
}
