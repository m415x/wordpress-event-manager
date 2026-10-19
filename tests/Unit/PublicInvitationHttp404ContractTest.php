<?php
declare(strict_types=1);

namespace {
    if (!defined('ABSPATH')) define('ABSPATH', '/wordpress/');
    if (!function_exists('wp_unslash')) {
        function wp_unslash($value) { return $value; }
    }
    if (!function_exists('status_header')) {
        function status_header($status) { $GLOBALS['wem68_http_statuses'][] = $status; }
    }
}

namespace WEM\Tests\Unit {
    use PHPUnit\Framework\TestCase;
    require_once dirname(__DIR__, 2) . '/includes/class-public-invitation-route.php';

    /** WEM-68: rejection must be an actual HTTP 404, not just an empty HTTP 200. */
    final class PublicInvitationHttp404ContractTest extends TestCase {
        private $previousGet;
        protected function setUp(): void {
            parent::setUp();
            $this->previousGet = $_GET;
            $GLOBALS['wem68_http_statuses'] = [];
        }
        protected function tearDown(): void {
            $_GET = $this->previousGet;
            unset($GLOBALS['wem68_http_statuses']);
            parent::tearDown();
        }
        public function testForbiddenParameterRendersNeutral404WithSecurityHeaders(): void {
            $_GET = ['wem_invitation' => 'bad', 'ticket' => 'secret'];
            $headers = [];
            $route = new \WEM_Public_Invitation_Route(static function ($line) use (&$headers) {
                $headers[] = $line;
            });
            ob_start();
            try {
                $handled = $route->dispatch_request();
                $body = ob_get_contents();
            } finally {
                ob_end_clean();
            }
            self::assertTrue($handled);
            self::assertSame('', $body, 'Never reveal token, invitation, or error details.');
            self::assertSame([404], $GLOBALS['wem68_http_statuses'], 'Rejected QR must have actual HTTP 404.');
            self::assertContains('Cache-Control: private, no-store', $headers);
            self::assertContains('Referrer-Policy: no-referrer', $headers);
            self::assertContains('X-Robots-Tag: noindex, nofollow, noarchive', $headers);
        }
    }
}
