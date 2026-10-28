<?php
declare(strict_types=1);
namespace WEM\Tests\Integration;
use WP_UnitTestCase;

/** WEM-93: public reservation transport must fail closed before enabling I2 writes. */
final class SeatingPublicReservationRestTest extends WP_UnitTestCase
{
    public function testPostReservationRequiresBearerAndDoesNotRevealTable(): void
    {
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        (new \WEM_Seating_Public_Http_Route())->register_hooks();
        do_action('rest_api_init');
        $request = new \WP_REST_Request('POST', '/wem/v1/seating/reservations');
        $request->set_param('table_id', 999999);
        $response = rest_get_server()->dispatch($request);
        self::assertSame(403, $response->get_status());
        self::assertSame(['code' => 'seating_access_unavailable'], $response->get_data());
        self::assertSame('private, no-store', $response->get_headers()['Cache-Control'] ?? null);
        self::assertSame('no-referrer', $response->get_headers()['Referrer-Policy'] ?? null);
    }
}
