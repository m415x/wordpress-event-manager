<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

final class PreissuedPublicFailClosedTest extends WP_UnitTestCase
{
    public function testPreissuedBearerHasDedicatedPublicRouteAndUnassignedCannotResolveToGuest(): void
    {
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        $route = new \WEM_Public_Invitation_Route();
        $vars = $route->register_query_var([]);
        self::assertContains('wem_preissued', $vars, 'P0 bearer requires explicit dedicated public routing.');
        self::assertNull(
            $route->resolve_request(['wem_preissued' => str_repeat('A', 43)]),
            'Unassigned, unknown or invalid inventory bearers must not resolve to guest PII.'
        );
    }
}
