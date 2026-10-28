<?php
declare(strict_types=1);
namespace WEM\Tests\Integration;
use WP_UnitTestCase;

/** WEM-93: plugin bootstrap must expose vetted I3 endpoints at rest_api_init. */
final class SeatingPublicBootstrapRegistrationTest extends WP_UnitTestCase
{
    public function testPluginRegistersPublicSeatingRoutesWithoutManualI3Hooks(): void
    {
        require_once dirname(__DIR__,2).'/wordpress-event-manager.php';
        do_action('plugins_loaded');
        do_action('rest_api_init');
        $routes=rest_get_server()->get_routes();
        self::assertArrayHasKey('/wem/v1/seating/availability',$routes);
        self::assertArrayHasKey('/wem/v1/seating/members',$routes);
        self::assertArrayHasKey('/wem/v1/seating/reservations',$routes);
    }
}
