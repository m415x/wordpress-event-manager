<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_Ajax_UnitTestCase;

/**
 * Dispatch via WordPress' actual AJAX hook system, not direct controller calls.
 * Header/status acceptance remains a separate live HTTP gate.
 */
final class AdminInvitationQrAjaxDenyTest extends WP_Ajax_UnitTestCase
{
    /**
     * @dataProvider deniedOperations
     */
    public function testAuthenticatedNonAdminCannotIssueOrRotate(string $operation): void
    {
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        $delivery = new \WEM_Admin_Invitation_QR_Delivery();
        $delivery->register_hooks();

        $actorId = self::factory()->user->create(['role' => 'subscriber']);
        wp_set_current_user($actorId);

        $guestId = self::factory()->post->create([
            'post_type' => 'invitado',
            'post_status' => 'publish',
        ]);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'action' => 'wem_invitation_qr_' . $operation,
            'guest_id' => (string) $guestId,
            'nonce' => wp_create_nonce('wem_invitation_qr_admin'),
        ];
        $_REQUEST = $_POST;

        try {
            $this->_handleAjax('wem_invitation_qr_' . $operation);
        } catch (\WPAjaxDieContinueException $error) {
            // WordPress AJAX test runner terminates through this exception.
        }

        self::assertSame(
            ['success' => false, 'data' => ['code' => 'invitation_qr_unavailable']],
            json_decode($this->_last_response, true)
        );

        // Even a syntactically valid nonce never upgrades actor capability.
        global $wpdb;
        $table = \WEM_Invitation_Credential_Schema::table_name();
        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE guest_id = %d",
            $guestId
        ));
        self::assertSame(0, $count, 'DENY cannot persist an invitation credential.');
    }

    public static function deniedOperations(): array
    {
        return [
            'issue' => ['issue'],
            'rotate' => ['rotate'],
            'reissue' => ['reissue'],
        ];
    }
}
