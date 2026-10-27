<?php
declare(strict_types=1);
namespace WEM\Tests\Integration;
use WP_UnitTestCase;

/** WEM-93: public seating credential must be header-only. */
final class SeatingPublicBearerTransportTest extends WP_UnitTestCase
{
    public function testAvailabilityNeverReadsBearerFromQuery(): void
    {
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        (new \WEM_Seating_Public_Http_Route())->register_hooks();
        do_action('rest_api_init');
        $request = new \WP_REST_Request('GET', '/wem/v1/seating/availability');
        $request->set_param('wem_invitation', str_repeat('A', 43));
        $request->set_param('bearer', str_repeat('B', 43));
        $response = rest_get_server()->dispatch($request);
        self::assertSame(403, $response->get_status());
        self::assertSame(['code' => 'seating_access_unavailable'], $response->get_data());
        self::assertSame('private, no-store', $response->get_headers()['Cache-Control'] ?? null);
    }

    public function testMembersNeverReadsBearerFromQuery(): void
    {
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        (new \WEM_Seating_Public_Http_Route())->register_hooks();
        do_action('rest_api_init');
        $request = new \WP_REST_Request('GET', '/wem/v1/seating/members');
        $request->set_param('bearer', str_repeat('C', 43));
        $response = rest_get_server()->dispatch($request);
        self::assertSame(403, $response->get_status());
        self::assertSame(['code' => 'seating_access_unavailable'], $response->get_data());
    }
}
