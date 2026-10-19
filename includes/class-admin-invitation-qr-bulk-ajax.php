<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Authenticated AJAX boundary for explicit bulk QR requests.
 * Bulk delivery is restricted to WordPress administrators and never retries.
 */
final class WEM_Admin_Invitation_QR_Bulk_Ajax
{
    private $processor;

    public function __construct($processor = null)
    {
        $this->processor = $processor;
    }

    public function handle()
    {
        nocache_headers();
        header('Cache-Control: private, no-store', true);
        header('Referrer-Policy: no-referrer', true);
        header('X-Robots-Tag: noindex, nofollow, noarchive', true);

        if (!is_user_logged_in()
            || !current_user_can('manage_options')
            || strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST'
            || false === check_ajax_referer('wem_invitation_qr_bulk_admin', 'nonce', false)
        ) {
            wp_send_json_error(array('code' => 'invitation_qr_bulk_unavailable'), 403);
            return;
        }

        $operation = isset($_POST['operation']) && is_string($_POST['operation'])
            ? wp_unslash($_POST['operation']) : '';
        $raw_ids = $_POST['guest_ids'] ?? null;

        if (!in_array($operation, array('issue', 'reissue'), true)
            || !is_array($raw_ids)
            || count($raw_ids) < 1
            || count($raw_ids) > 25
        ) {
            wp_send_json_error(array('code' => 'invitation_qr_bulk_unavailable'), 400);
            return;
        }

        $ids = array();
        $seen = array();
        foreach ($raw_ids as $raw_id) {
            if (!is_string($raw_id)
                || !preg_match('/^[1-9][0-9]*$/D', $raw_id)
                || filter_var($raw_id, FILTER_VALIDATE_INT) === false
            ) {
                wp_send_json_error(array('code' => 'invitation_qr_bulk_unavailable'), 400);
                return;
            }

            $id = (int) $raw_id;
            if (isset($seen[$id])) {
                wp_send_json_error(array('code' => 'invitation_qr_bulk_unavailable'), 400);
                return;
            }
            $seen[$id] = true;
            $ids[] = $id;
        }

        if ($this->processor === null) {
            wp_send_json_error(array('code' => 'invitation_qr_bulk_unavailable'), 503);
            return;
        }

        try {
            $result = $this->processor->execute($ids, $operation, get_current_user_id());
            wp_send_json_success($result);
        } catch (Throwable $error) {
            wp_send_json_error(array('code' => 'invitation_qr_bulk_unavailable'), 500);
        }
    }
}
