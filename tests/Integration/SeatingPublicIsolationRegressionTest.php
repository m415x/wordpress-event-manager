<?php
declare(strict_types=1);
namespace WEM\Tests\Integration;
use WP_UnitTestCase;

/** WEM-93/T6: guard against exposing staff endpoints or altering WEM-33. */
final class SeatingPublicIsolationRegressionTest extends WP_UnitTestCase
{
    public function testI3RouteDoesNotRegisterStaffAjaxOrChangeWem33Resolver(): void
    {
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        $route = new \WEM_Seating_Public_Http_Route();
        $route->register_hooks();
        do_action('rest_api_init');
        $server=rest_get_server();
        $routes=$server->get_routes();
        self::assertArrayHasKey('/wem/v1/seating/availability',$routes);
        self::assertArrayNotHasKey('/wem/v1/checkin',$routes);
        self::assertArrayNotHasKey('/wem/v1/list',$routes);

        foreach (['wem_checkin_ajax','wem_list_ajax'] as $action) {
            self::assertFalse(has_action('wp_ajax_nopriv_' . $action),
                'I3 must not publish an anonymous staff AJAX handler.');
        }
        $resolver=new \WEM_Public_Invitation_Resolver();
        self::assertNull($resolver->resolve('bad'));
        self::assertNull($resolver->resolve(str_repeat('A',43)));
    }
}
