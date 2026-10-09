<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Authenticated, one-time AJAX delivery of a newly issued invitation QR.
 * Viewer, Operator and anonymous callers never receive credential material.
 */
final class WEM_Admin_Invitation_QR_Delivery
{
    public function register_hooks()
    {
        foreach (array('issue', 'rotate', 'reissue') as $operation) {
            add_action(
                'wp_ajax_wem_invitation_qr_' . $operation,
                array($this, 'handle_' . $operation)
            );
        }
    }

    public function handle_issue()
    {
        $this->deliver('issue');
    }

    public function handle_rotate()
    {
        $this->deliver('rotate');
    }

    public function handle_reissue()
    {
        $this->deliver('reissue');
    }

    private function deliver($operation)
    {
        // Protect every denial, including permission and nonce failures.
        nocache_headers();
        header('Cache-Control: private, no-store', true);
        header('Referrer-Policy: no-referrer', true);
        header('X-Robots-Tag: noindex, nofollow, noarchive', true);

        if (
            !is_user_logged_in()
            || !current_user_can('manage_options')
            || strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST'
        ) {
            wp_send_json_error(array('code' => 'invitation_qr_unavailable'), 403);
            return;
        }

        check_ajax_referer('wem_invitation_qr_admin', 'nonce');

        $guest_id = isset($_POST['guest_id']) && is_scalar($_POST['guest_id'])
            ? absint(wp_unslash((string) $_POST['guest_id']))
            : 0;
        if ($guest_id <= 0) {
            wp_send_json_error(array('code' => 'invitation_qr_unavailable'), 400);
            return;
        }

        $orchestrator = new WEM_Admin_Invitation_QR_Orchestrator();

        try {
            $result = $orchestrator->{$operation}($guest_id, get_current_user_id());
        } catch (Throwable $error) {
            // A credential may already be committed. Never leak tokens,
            // exception messages or offer an implicit retry/rotation.
            wp_send_json_error(array('code' => 'invitation_qr_unavailable'), 500);
            return;
        }

        wp_send_json_success(
            array(
                'url' => $result['url'],
                'svg' => $result['svg'],
                'generation' => $result['generation'],
            )
        );
    }
}
