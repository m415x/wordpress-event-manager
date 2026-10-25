<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

/**
 * WEM-89/T2 contract: server-side availability must be a bearer-scoped read,
 * not a client-selected event or browser-maintained reservation count.
 */
final class SeatingBearerAvailabilityTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
    }

    public function testAvailabilityHasSeparateBearerScopedReadBoundary(): void
    {
        self::assertTrue(
            class_exists('WEM_Seating_Bearer_Availability'),
            'WEM-89/T2 requires a bearer-authorized event-table availability boundary.'
        );

        self::assertTrue(
            method_exists('WEM_Seating_Bearer_Availability', 'list_for_bearer'),
            'WEM-89/T2 availability must derive event identity from a WEM-33 bearer.'
        );
    }
}
