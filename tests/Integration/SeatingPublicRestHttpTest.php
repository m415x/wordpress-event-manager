<?php
declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

/** WEM-93/T6 RED: exercise WordPress REST dispatch, not the internal PHP facade. */
final class SeatingPublicRestHttpTest extends WP_UnitTestCase
{
    public function testActualRestDispatchFailsClosedWithSafeHeaders(): void
    {
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        $server = rest_get_server();
        $route = new \WEM_Seating_Public_Http_Route();
        $route->register_hooks();
        do_action('rest_api_init');

        $responses = [];
        foreach (['', 'bad', str_repeat('A', 43)] as $bearer) {
            $request = new \WP_REST_Request('GET', '/wem/v1/seating/availability');
            if ($bearer !== '') {
                $request->set_header('X-WEM-Invitation', $bearer);
            }
            $response = $server->dispatch($request);
            self::assertSame(403, $response->get_status());
            self::assertSame(['code' => 'seating_access_unavailable'], $response->get_data());
            $headers = $response->get_headers();
            self::assertSame('private, no-store', $headers['Cache-Control'] ?? null);
            self::assertSame('no-referrer', $headers['Referrer-Policy'] ?? null);
            self::assertSame('noindex, nofollow, noarchive', $headers['X-Robots-Tag'] ?? null);
            self::assertStringNotContainsString($bearer ?: 'never-present-value', wp_json_encode($response->get_data()));
            $responses[] = [$response->get_status(), $response->get_data()];
        }
        self::assertSame($responses[0], $responses[1]);
        self::assertSame($responses[1], $responses[2]);
    }
}
