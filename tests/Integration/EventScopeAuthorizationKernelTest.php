<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

final class EventScopeAuthorizationKernelTest extends WP_UnitTestCase
{
    public function testAuthorizationKernelIsAvailableThroughPluginAutoloading(): void
    {
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';

        self::assertTrue(
            class_exists('WEM_Authorization'),
            'WEM-20 requires a reusable authorization kernel loaded through the plugin autoloader.'
        );
    }

    public function testAuthorizationKernelExposesBoundedViewOperateAndScopeContract(): void
    {
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';

        foreach ([
            'get_guest_event_term_id',
            'get_authorized_event_ids',
            'can_view_guest',
            'can_operate_guest',
        ] as $method) {
            self::assertTrue(
                method_exists('WEM_Authorization', $method),
                sprintf('WEM-20 authorization kernel must expose %s().', $method)
            );
        }
    }
}
