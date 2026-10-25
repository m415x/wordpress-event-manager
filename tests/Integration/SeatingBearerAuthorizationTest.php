<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

/**
 * WEM-88/T1 contractual RED: a separate public seating authorization boundary
 * must derive identity solely from a valid WEM-33 bearer.
 */
final class SeatingBearerAuthorizationTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
    }

    public function testSeatingRequiresIndependentBearerScopedBoundary(): void
    {
        self::assertTrue(
            class_exists('WEM_Seating_Bearer_Authorization'),
            'WEM-88/T1 requires a separate bearer-scoped seating authorization boundary.'
        );

        self::assertTrue(
            method_exists('WEM_Seating_Bearer_Authorization', 'resolve'),
            'WEM-88/T1 requires bearer-only resolution before any seating read or write.'
        );
    }
}
