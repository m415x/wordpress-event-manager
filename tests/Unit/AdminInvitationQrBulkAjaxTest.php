<?php

declare(strict_types=1);

namespace {
    if (!defined('ABSPATH')) { define('ABSPATH', '/tmp/wem66-bulk-ajax/'); }
    if (!function_exists('nocache_headers')) { function nocache_headers() {} }
    if (!function_exists('is_user_logged_in')) { function is_user_logged_in() { return $GLOBALS['wem66_ajax_authorized'] ?? false; } }
    if (!function_exists('current_user_can')) { function current_user_can($cap) { return $cap === 'manage_options' && ($GLOBALS['wem66_ajax_authorized'] ?? false); } }
    if (!function_exists('check_ajax_referer')) { function check_ajax_referer($action, $field, $stop = true) { return $action === 'wem_invitation_qr_bulk_admin' && $field === 'nonce' && ($_POST['nonce'] ?? '') === 'valid'; } }
    if (!function_exists('get_current_user_id')) { function get_current_user_id() { return 42; } }
    if (!function_exists('wp_unslash')) { function wp_unslash($v) { return $v; } }
    if (!function_exists('wp_send_json_error')) { function wp_send_json_error($data, $code = 200) { throw new \RuntimeException('denied:' . $code); } }
    if (!function_exists('wp_send_json_success')) { function wp_send_json_success($data) { $GLOBALS['wem66_ajax_response'] = $data; } }
}

namespace WEM\Tests\Unit {
    use PHPUnit\Framework\TestCase;

    final class AdminInvitationQrBulkAjaxTest extends TestCase
    {
        protected function setUp(): void
        {
            $GLOBALS['wem66_ajax_authorized'] = true;
            unset($GLOBALS['wem66_ajax_response']);
            $_SERVER['REQUEST_METHOD'] = 'POST';
            $_POST = array('nonce' => 'valid', 'operation' => 'issue', 'guest_ids' => array('11', '12'));
            require_once dirname(__DIR__, 2) . '/includes/class-admin-invitation-qr-bulk-ajax.php';
        }

        /** @runInSeparateProcess @preserveGlobalState disabled */
        public function testAdminPostUsesExplicitSelectionAndProcessor(): void
        {
            $processor = new class {
                public $calls = array();
                public function execute($ids, $operation, $actor) {
                    $this->calls[] = array($ids, $operation, $actor);
                    return array(array('guest_id' => 11, 'status' => 'denied'));
                }
            };
            (new \WEM_Admin_Invitation_QR_Bulk_Ajax($processor))->handle();
            self::assertSame(array(array(array(11, 12), 'issue', 42)), $processor->calls);
            self::assertSame('denied', $GLOBALS['wem66_ajax_response'][0]['status']);
        }

        /**
         * @dataProvider deniedCases
         * @runInSeparateProcess
         * @preserveGlobalState disabled
         */
        public function testDenialsOccurBeforeProcessor($authenticated, $nonce, $method, $ids): void
        {
            $GLOBALS['wem66_ajax_authorized'] = $authenticated;
            $_POST['nonce'] = $nonce;
            $_POST['guest_ids'] = $ids;
            $_SERVER['REQUEST_METHOD'] = $method;
            $processor = new class {
                public $calls = 0;
                public function execute($ids, $operation, $actor) { ++$this->calls; return array(); }
            };
            try {
                (new \WEM_Admin_Invitation_QR_Bulk_Ajax($processor))->handle();
                self::fail('Invalid bulk request must fail.');
            } catch (\RuntimeException $error) {
                self::assertStringStartsWith('denied:', $error->getMessage());
            }
            self::assertSame(0, $processor->calls);
        }

        public static function deniedCases(): array
        {
            return array(
                'unprivileged' => array(false, 'valid', 'POST', array('11')),
                'nonce' => array(true, 'bad', 'POST', array('11')),
                'get' => array(true, 'valid', 'GET', array('11')),
                'malformed ID' => array(true, 'valid', 'POST', array('11x')),
                'duplicate ID' => array(true, 'valid', 'POST', array('11', '11')),
                'oversize' => array(true, 'valid', 'POST', range(1, 26)),
            );
        }
    }
}
