<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

/** WEM-66: bulk QR route must be server-wired and authenticated only. */
final class AdminInvitationQrBulkRegistrationTest extends WP_UnitTestCase
{
    public function testBulkQrRouteUsesRealServerOwnedDependencies(): void
    {
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';

        $registration = new \WEM_Admin_Invitation_QR_Bulk_Registration();
        $registration->register_hooks();

        self::assertNotFalse(
            has_action('wp_ajax_wem_invitation_qr_bulk', array($registration, 'handle')),
            'Authenticated bulk handler must be registered.'
        );
        self::assertFalse(
            has_action('wp_ajax_nopriv_wem_invitation_qr_bulk'),
            'Bulk credential issuance must not be available to anonymous users.'
        );

        $processor = $registration->build_processor();
        self::assertInstanceOf(\WEM_Admin_Invitation_QR_Bulk_Processor::class, $processor);
        $processor_properties = new \ReflectionClass($processor);
        foreach (array('revalidator', 'orchestrator') as $property_name) {
            $property = $processor_properties->getProperty($property_name);
            $property->setAccessible(true);
            self::assertInstanceOf(
                $property_name === 'revalidator'
                    ? \WEM_Admin_Invitation_QR_Bulk_Current_State::class
                    : \WEM_Admin_Invitation_QR_Orchestrator::class,
                $property->getValue($processor)
            );
        }
    }

    public function testPluginBootstrapRegistersBulkQrRoute(): void
    {
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        do_action('plugins_loaded');
        self::assertNotFalse(has_action('wp_ajax_wem_invitation_qr_bulk'));
        self::assertFalse(has_action('wp_ajax_nopriv_wem_invitation_qr_bulk'));
    }
}
