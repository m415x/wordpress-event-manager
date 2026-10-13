<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

/**
 * WordPress hook registration regression for admin-only QR credential actions.
 * A separate live HTTP test is still required for response status and headers.
 */
final class AdminInvitationQrRegistrationTest extends WP_UnitTestCase
{
    public function testQrCredentialActionsAreRegisteredForAuthenticatedUsersOnly(): void
    {
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';

        $delivery = new \WEM_Admin_Invitation_QR_Delivery();
        $delivery->register_hooks();

        foreach (['issue', 'rotate', 'reissue'] as $operation) {
            $action = 'wem_invitation_qr_' . $operation;

            self::assertNotFalse(
                has_action('wp_ajax_' . $action, [$delivery, 'handle_' . $operation]),
                $action . ' must be registered for authenticated WordPress users.'
            );
            self::assertFalse(
                has_action('wp_ajax_nopriv_' . $action),
                $action . ' must never have an anonymous handler.'
            );
        }
    }
}
