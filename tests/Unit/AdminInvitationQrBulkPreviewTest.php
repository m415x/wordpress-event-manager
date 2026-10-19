<?php

declare(strict_types=1);

namespace WEM\Tests\Unit;

use PHPUnit\Framework\TestCase;

/** WEM-66: preview is a read-only hint, never mutation authorization. */
final class AdminInvitationQrBulkPreviewTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (!defined('ABSPATH')) {
            define('ABSPATH', '/tmp/wem66-bulk-preview/');
        }
        require_once dirname(__DIR__, 2) . '/includes/class-admin-invitation-qr-bulk-selection.php';
        require_once dirname(__DIR__, 2) . '/includes/class-admin-invitation-qr-bulk-eligibility.php';
        require_once dirname(__DIR__, 2) . '/includes/class-admin-invitation-qr-bulk-preview.php';
    }

    public function testPreviewReadsEachSelectedGuestAndReturnsOnlyEligibility(): void
    {
        $reads = array();
        $reader = new class($reads) {
            private $reads;
            public function __construct(&$reads) { $this->reads = &$reads; }
            public function read($id) {
                $this->reads[] = $id;
                return $id === 11
                    ? array('valid_guest' => true, 'credentials' => array())
                    : array('valid_guest' => true, 'credentials' => array(array('status' => 'active', 'active_slot' => 1)));
            }
        };

        $preview = new \WEM_Admin_Invitation_QR_Bulk_Preview($reader);
        self::assertSame(
            array(
                array('guest_id' => 11, 'eligibility' => 'never_issued'),
                array('guest_id' => 12, 'eligibility' => 'active_unrecoverable'),
            ),
            $preview->inspect(array(11, 12))
        );
        self::assertSame(array(11, 12), $reads);
    }

    public function testFailedReadReturnsInvalidWithoutSecretOrException(): void
    {
        $reader = new class {
            public function read($id) { throw new \RuntimeException('Sensitive token leaked'); }
        };
        $preview = new \WEM_Admin_Invitation_QR_Bulk_Preview($reader);
        $result = $preview->inspect(array(11));
        self::assertSame(array(array('guest_id' => 11, 'eligibility' => 'invalid')), $result);
    }

    public function testInvalidSelectionIsRejectedBeforeReading(): void
    {
        $reads = 0;
        $reader = new class($reads) {
            private $reads;
            public function __construct(&$reads) { $this->reads = &$reads; }
            public function read($id) { ++$this->reads; return array(); }
        };
        $preview = new \WEM_Admin_Invitation_QR_Bulk_Preview($reader);
        $this->expectException(\InvalidArgumentException::class);
        try {
            $preview->inspect(array(11, 11));
        } finally {
            self::assertSame(0, $reads);
        }
    }
}
