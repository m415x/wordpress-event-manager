<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

/** WEM-66: the bulk QR screen and script must remain admin-only. */
final class AdminInvitationQrBulkPanelRegistrationTest extends WP_UnitTestCase
{
    public function testPluginBootstrapWiresAdminOnlyBulkPanel(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/wordpress-event-manager.php');
        self::assertStringContainsString('new WEM_Admin_Invitation_QR_Bulk_Panel()', $source);
        self::assertStringContainsString('$bulk_qr_panel->register_hooks()', $source);

        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        $panel = new \WEM_Admin_Invitation_QR_Bulk_Panel();
        $panel->register_hooks();
        self::assertNotFalse(has_action('admin_menu', array($panel, 'register_page')));
        self::assertNotFalse(has_action('admin_enqueue_scripts', array($panel, 'enqueue_assets')));
    }

    public function testUnprivilegedActorCannotRegisterBulkMenuOrEnqueueScript(): void
    {
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        wp_set_current_user(self::factory()->user->create(array('role' => 'subscriber')));

        $panel = new \WEM_Admin_Invitation_QR_Bulk_Panel();
        $panel->register_page();
        $panel->enqueue_assets('irrelevant_page_wem-invitation-qr-bulk');

        global $submenu;
        $items = isset($submenu['edit.php?post_type=invitado'])
            ? $submenu['edit.php?post_type=invitado']
            : array();
        foreach ($items as $item) {
            self::assertNotSame('wem-invitation-qr-bulk', $item[2] ?? null);
        }
        self::assertFalse(wp_script_is('wem-admin-invitation-qr-bulk', 'enqueued'));
    }
}
