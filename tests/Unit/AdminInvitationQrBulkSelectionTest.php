<?php

declare(strict_types=1);

namespace WEM\Tests\Unit;

use PHPUnit\Framework\TestCase;

/** WEM-66/T4: bulk selection must be explicit, bounded and non-rotating. */
final class AdminInvitationQrBulkSelectionTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (!defined('ABSPATH')) {
            define('ABSPATH', '/tmp/wem66-bulk-selection-unit/');
        }
        require_once dirname(__DIR__, 2) . '/includes/class-admin-invitation-qr-bulk-selection.php';
    }

    public function testExplicitIssueOrReissueAcceptsUniquePositiveIntegerIds(): void
    {
        self::assertSame(
            array(5, 7),
            \WEM_Admin_Invitation_QR_Bulk_Selection::validate(array(5, 7), 'issue')
        );
        self::assertSame(
            array(7),
            \WEM_Admin_Invitation_QR_Bulk_Selection::validate(array(7), 'reissue')
        );
    }

    /**
     * @dataProvider invalidSelections
     */
    public function testRejectsInvalidOrImplicitSelections($ids, $operation): void
    {
        $this->expectException(\InvalidArgumentException::class);
        \WEM_Admin_Invitation_QR_Bulk_Selection::validate($ids, $operation);
    }

    public static function invalidSelections(): array
    {
        return array(
            'empty' => array(array(), 'issue'),
            'duplicate' => array(array(5, 5), 'issue'),
            'zero' => array(array(0), 'issue'),
            'negative' => array(array(-1), 'reissue'),
            'malformed' => array(array('5xyz'), 'issue'),
            'string ID' => array(array('5'), 'issue'),
            'mixed type' => array(array(5, '7'), 'issue'),
            'over limit' => array(range(1, 26), 'issue'),
            'bulk rotate prohibited' => array(array(5), 'rotate'),
            'unknown operation' => array(array(5), 'automatic'),
        );
    }
}
