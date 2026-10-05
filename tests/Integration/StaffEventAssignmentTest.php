<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

final class StaffEventAssignmentTest extends WP_UnitTestCase
{
    public function testAuthorizationKernelExposesScopeAssignmentApi(): void
    {
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';

        self::assertTrue(
            method_exists('WEM_Authorization', 'set_authorized_event_ids'),
            'WEM-23 requires scope writes to stay behind the WEM authorization API.'
        );
    }
}
