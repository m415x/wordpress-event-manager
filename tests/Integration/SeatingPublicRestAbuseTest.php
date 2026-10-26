<?php
declare(strict_types=1);
namespace WEM\Tests\Integration;
use WP_UnitTestCase;

/** WEM-93/T6: verify real REST dispatch consumes abuse attempts. */
final class SeatingPublicRestAbuseTest extends WP_UnitTestCase
{
    public function testDeniedRestCallsConsumeSharedBoundedAbuseBucketWithoutTokenPersistence(): void
    {
        global $wpdb;
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        $route = new \WEM_Seating_Public_Http_Route();
        $route->register_hooks();
        do_action('rest_api_init');
        $server = rest_get_server();
        $previousIp = $_SERVER['REMOTE_ADDR'] ?? null;
        $_SERVER['REMOTE_ADDR'] = '198.51.100.87';
        // This request identity is an IP, not a bearer; a single bucket must
        // be shared across unknown credentials to prevent token probing.
        $bucket = 'seating:availability:198.51.100.87';
        $name = 'wem_i3_rate_' . hash_hmac('sha256', $bucket, wp_salt('auth'));
        try {
            $wpdb->delete($wpdb->options, ['option_name' => $name]);
            foreach (['bad', str_repeat('Z', 43)] as $bearer) {
                $request = new \WP_REST_Request('GET', '/wem/v1/seating/availability');
                $request->set_header('X-WEM-Invitation', $bearer);
                $response = $server->dispatch($request);
                self::assertSame(403, $response->get_status());
                self::assertSame(['code' => 'seating_access_unavailable'], $response->get_data());
            }
            $stored = $wpdb->get_var($wpdb->prepare(
                "SELECT option_value FROM {$wpdb->options} WHERE option_name=%s", $name
            ));
            self::assertIsString($stored, 'REST transport must invoke atomic guard, not bypass it.');
            self::assertMatchesRegularExpression('/^2:[0-9]+$/', $stored);
            self::assertStringNotContainsString('bad', $stored);
        } finally {
            $wpdb->delete($wpdb->options, ['option_name' => $name]);
            if ($previousIp === null) { unset($_SERVER['REMOTE_ADDR']); }
            else { $_SERVER['REMOTE_ADDR'] = $previousIp; }
        }
    }
}
