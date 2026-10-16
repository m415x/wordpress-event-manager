<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

/** WEM-66: read-only preview must use a real reader and no anonymous route. */
final class AdminInvitationQrBulkPreviewRegistrationTest extends WP_UnitTestCase
{
    public function testBulkRegistrationExposesAuthenticatedPreviewOnly(): void
    {
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';

        $registration = new \WEM_Admin_Invitation_QR_Bulk_Registration();
        $registration->register_hooks();

        self::assertNotFalse(
            has_action('wp_ajax_wem_invitation_qr_bulk_preview', array($registration, 'handle_preview'))
        );
        self::assertFalse(has_action('wp_ajax_nopriv_wem_invitation_qr_bulk_preview'));
    }

    public function testBulkPreviewUsesWordPressReaderWithoutWriter(): void
    {
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';

        $registration = new \WEM_Admin_Invitation_QR_Bulk_Registration();
        $preview = $registration->build_preview();
        self::assertInstanceOf(\WEM_Admin_Invitation_QR_Bulk_Preview::class, $preview);

        $reflection = new \ReflectionClass($preview);
        $property = $reflection->getProperty('reader');
        $property->setAccessible(true);
        self::assertInstanceOf(
            \WEM_Admin_Invitation_QR_Bulk_WordPress_Reader::class,
            $property->getValue($preview)
        );
    }

    public function testPluginBootstrapWiresBulkPreviewRegistration(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/wordpress-event-manager.php');
        self::assertStringContainsString('new WEM_Admin_Invitation_QR_Bulk_Registration()', $source);
        self::assertStringContainsString('$bulk_qr_registration->register_hooks()', $source);
    }
}
