<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WordPress wiring for administrator-only explicit bulk QR issuance.
 */
final class WEM_Admin_Invitation_QR_Bulk_Registration
{
    public function register_hooks()
    {
        add_action('wp_ajax_wem_invitation_qr_bulk', array($this, 'handle'));
    }

    public function build_processor()
    {
        $reader = new WEM_Admin_Invitation_QR_Bulk_WordPress_Reader();
        $validator = new WEM_Admin_Invitation_QR_Bulk_Current_State(
            array($reader, 'read')
        );
        return new WEM_Admin_Invitation_QR_Bulk_Processor(
            $validator,
            new WEM_Admin_Invitation_QR_Orchestrator()
        );
    }

    public function handle()
    {
        $delivery = new WEM_Admin_Invitation_QR_Bulk_Ajax(
            $this->build_processor()
        );
        $delivery->handle();
    }
}
