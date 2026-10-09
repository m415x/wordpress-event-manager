<?php

declare(strict_types=1);

namespace {
    if (!defined('ABSPATH')) {
        define('ABSPATH', '/tmp/wem34-delivery-test/');
    }
    if (!function_exists('nocache_headers')) {
        function nocache_headers() {}
    }
    if (!function_exists('is_user_logged_in')) {
        function is_user_logged_in() { return $GLOBALS['wem34_delivery_authorized'] ?? false; }
    }
    if (!function_exists('current_user_can')) {
        function current_user_can($capability) { return $capability === 'manage_options' && ($GLOBALS['wem34_delivery_authorized'] ?? false); }
    }
    if (!function_exists('check_ajax_referer')) {
        function check_ajax_referer($action, $field) {
            if ($action !== 'wem_invitation_qr_admin' || $field !== 'nonce') {
                throw new \RuntimeException('Unexpected nonce contract');
            }
        }
    }
    if (!function_exists('absint')) {
        function absint($value) { return abs((int) $value); }
    }
    if (!function_exists('wp_unslash')) {
        function wp_unslash($value) { return $value; }
    }
    if (!function_exists('get_current_user_id')) {
        function get_current_user_id() { return 42; }
    }
    if (!function_exists('wp_send_json_error')) {
        function wp_send_json_error($data, $status = 200) { throw new \RuntimeException('denied:' . $status); }
    }
    if (!function_exists('wp_send_json_success')) {
        function wp_send_json_success($data) {
            $GLOBALS['wem34_delivery_response'] = $data;
        }
    }
}

namespace WEM\Tests\Unit {
    use PHPUnit\Framework\TestCase;

    final class AdminInvitationQrDeliveryFunctionalTest extends TestCase
    {
        protected function setUp(): void
        {
            $GLOBALS['wem34_delivery_authorized'] = true;
            unset($GLOBALS['wem34_delivery_response']);
            $_SERVER['REQUEST_METHOD'] = 'POST';
            $_POST = array('guest_id' => '17', 'nonce' => 'valid');
            require_once dirname(__DIR__, 2) . '/includes/class-admin-invitation-qr-delivery.php';
        }

        public function testAuthorizedRequestDelegatesExactlyOnceToInjectedOrchestrator(): void
        {
            $fake = new class {
                public $calls = array();
                public function issue($guest_id, $actor_id)
                {
                    $this->calls[] = array($guest_id, $actor_id);
                    return array(
                        'url' => 'https://example.test/?wem_invitation=' . str_repeat('A', 43),
                        'svg' => '<svg></svg>',
                        'generation' => 1,
                    );
                }
            };

            $delivery = new \WEM_Admin_Invitation_QR_Delivery($fake);
            $delivery->handle_issue();

            self::assertSame(array(array(17, 42)), $fake->calls);
            self::assertSame('<svg></svg>', $GLOBALS['wem34_delivery_response']['svg'] ?? null);
        }
    }
}
