<?php

declare(strict_types=1);

namespace WEM\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * WEM-66/T4: the server must check a fresh credential-state projection for
 * every selected guest; a previously approved preview is never authority.
 */
final class AdminInvitationQrBulkCurrentStateTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (!defined('ABSPATH')) {
            define('ABSPATH', '/tmp/wem66-bulk-current-state/');
        }
        require_once dirname(__DIR__, 2) . '/includes/class-admin-invitation-qr-bulk-eligibility.php';
        require_once dirname(__DIR__, 2) . '/includes/class-admin-invitation-qr-bulk-revalidation.php';
        require_once dirname(__DIR__, 2) . '/includes/class-admin-invitation-qr-bulk-current-state.php';
    }

    public function testEachSelectedGuestIsReadAfreshAndOnlyEligibleGuestProceeds(): void
    {
        $reads = array();
        $reader = function ($guest_id) use (&$reads) {
            $reads[] = $guest_id;
            if ($guest_id === 11) {
                return array('valid_guest' => true, 'credentials' => array());
            }
            return array(
                'valid_guest' => true,
                'credentials' => array(array('status' => 'active', 'active_slot' => 1)),
            );
        };

        $validator = new \WEM_Admin_Invitation_QR_Bulk_Current_State($reader);
        self::assertTrue($validator->permits(11, 'issue'));
        self::assertFalse($validator->permits(12, 'issue'));
        self::assertSame(array(11, 12), $reads);
    }

    public function testStalePreviewCannotOverrideChangedServerState(): void
    {
        $reads = 0;
        $validator = new \WEM_Admin_Invitation_QR_Bulk_Current_State(
            function ($guest_id) use (&$reads) {
                ++$reads;
                return $reads === 1
                    ? array('valid_guest' => true, 'credentials' => array())
                    : array('valid_guest' => true, 'credentials' => array(array('status' => 'active', 'active_slot' => 1)));
            }
        );

        self::assertTrue($validator->permits(11, 'issue'));
        self::assertFalse($validator->permits(11, 'issue'));
        self::assertSame(2, $reads);
    }

    public function testMissingMalformedAndThrowingReadersFailClosed(): void
    {
        foreach (array(null, array(), array('valid_guest' => true)) as $response) {
            $validator = new \WEM_Admin_Invitation_QR_Bulk_Current_State(
                function ($guest_id) use ($response) { return $response; }
            );
            self::assertFalse($validator->permits(11, 'issue'));
        }

        $validator = new \WEM_Admin_Invitation_QR_Bulk_Current_State(
            function ($guest_id) { throw new \RuntimeException('read failure'); }
        );
        self::assertFalse($validator->permits(11, 'issue'));
    }
}
