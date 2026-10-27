<?php
declare(strict_types=1);
namespace WEM\Tests\Integration;
use WP_UnitTestCase;

/** WEM-93: publicly exposed I3 members must be fail-closed and private. */
final class SeatingPublicMembersRestTest extends WP_UnitTestCase
{
    public function testMembersRouteRequiresBearerAndNeverDisclosesIdentity(): void
    {
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        (new \WEM_Seating_Public_Http_Route())->register_hooks();
        do_action('rest_api_init');
        $server=rest_get_server();
        self::assertArrayHasKey('/wem/v1/seating/members',$server->get_routes());
        $request=new \WP_REST_Request('GET','/wem/v1/seating/members');
        $response=$server->dispatch($request);
        self::assertSame(403,$response->get_status());
        self::assertSame(['code'=>'seating_access_unavailable'],$response->get_data());
        self::assertSame('private, no-store',$response->get_headers()['Cache-Control']??null);
    }
}
