<?php

declare(strict_types=1);

namespace {
    if (!defined('ABSPATH')) { define('ABSPATH', '/tmp/wem66-preview-ajax/'); }
    if (!function_exists('nocache_headers')) { function nocache_headers() {} }
    if (!function_exists('is_user_logged_in')) { function is_user_logged_in() { return $GLOBALS['wem66_preview_authorized'] ?? false; } }
    if (!function_exists('current_user_can')) { function current_user_can($cap) { return $cap === 'manage_options' && ($GLOBALS['wem66_preview_authorized'] ?? false); } }
    if (!function_exists('check_ajax_referer')) { function check_ajax_referer($action, $field, $stop = true) { return $action === 'wem_invitation_qr_bulk_admin' && $field === 'nonce' && ($_POST['nonce'] ?? '') === 'valid'; } }
    if (!function_exists('wp_unslash')) { function wp_unslash($value) { return $value; } }
    if (!function_exists('wp_send_json_error')) { function wp_send_json_error($data, $status = 200) { throw new \RuntimeException('denied:' . $status); } }
    if (!function_exists('wp_send_json_success')) { function wp_send_json_success($data) { $GLOBALS['wem66_preview_response'] = $data; } }
}

namespace WEM\Tests\Unit {
    use PHPUnit\Framework\TestCase;

    final class AdminInvitationQrBulkPreviewAjaxTest extends TestCase
    {
        protected function setUp(): void
        {
            $GLOBALS['wem66_preview_authorized'] = true;
            $GLOBALS['wem66_ajax_authorized'] = true;
            unset($GLOBALS['wem66_preview_response']);
            $_SERVER['REQUEST_METHOD'] = 'POST';
            $_POST = array('nonce' => 'valid', 'guest_ids' => array('11', '12'));
            require_once dirname(__DIR__, 2) . '/includes/class-admin-invitation-qr-bulk-preview-ajax.php';
        }

        /** @runInSeparateProcess @preserveGlobalState disabled */
        public function testAuthorizedSelectionOnlyInvokesReadOnlyPreview(): void
        {
            $preview = new class {
                public $calls = array();
                public function inspect($ids) {
                    $this->calls[] = $ids;
                    return array(array('guest_id' => 11, 'eligibility' => 'never_issued'));
                }
            };
            (new \WEM_Admin_Invitation_QR_Bulk_Preview_Ajax($preview))->handle();
            self::assertSame(array(array(11, 12)), $preview->calls);
            $response = $GLOBALS['wem66_preview_response'] ?? $GLOBALS['wem66_ajax_response'] ?? null;
            self::assertSame(array(array('guest_id' => 11, 'eligibility' => 'never_issued')), $response);
        }

        /** @dataProvider deniedCases
         * @runInSeparateProcess
         * @preserveGlobalState disabled
         */
        public function testDeniedRequestsNeverReadState($admin, $nonce, $method, $ids): void
        {
            $GLOBALS['wem66_preview_authorized'] = $admin;
            $GLOBALS['wem66_ajax_authorized'] = $admin;
            $_POST['nonce'] = $nonce;
            $_SERVER['REQUEST_METHOD'] = $method;
            $_POST['guest_ids'] = $ids;
            $preview = new class {
                public $calls = 0;
                public function inspect($ids) { ++$this->calls; return array(); }
            };
            try {
                (new \WEM_Admin_Invitation_QR_Bulk_Preview_Ajax($preview))->handle();
                self::fail('The request must be denied.');
            } catch (\RuntimeException $error) {
                self::assertStringStartsWith('denied:', $error->getMessage());
            }
            self::assertSame(0, $preview->calls);
        }

        public static function deniedCases(): array
        {
            return array(
                'unprivileged' => array(false, 'valid', 'POST', array('11')),
                'bad nonce' => array(true, 'bad', 'POST', array('11')),
                'GET' => array(true, 'valid', 'GET', array('11')),
                'malformed ID' => array(true, 'valid', 'POST', array('11junk')),
                'duplicate' => array(true, 'valid', 'POST', array('11', '11')),
                'over limit' => array(true, 'valid', 'POST', array_map('strval', range(1, 26))),
            );
        }
    }
}
