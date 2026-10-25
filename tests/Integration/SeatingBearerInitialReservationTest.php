<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

/**
 * WEM-92/T5 next contractual RED: public initial reservation must enforce
 * a revalidated WEM-33 bearer inside I2's guest-locked transaction.
 */
final class SeatingBearerInitialReservationTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
    }

    public function testOwnInitialReservationNeedsBearerScopedAdapter(): void
    {
        self::assertTrue(
            class_exists('WEM_Seating_Bearer_Initial_Reservation'),
            'WEM-92/T5 requires an adapter deriving the guest exclusively from a valid bearer.'
        );
        self::assertTrue(
            method_exists('WEM_Seating_Bearer_Initial_Reservation', 'reserve_for_bearer'),
            'WEM-92/T5 requires a bearer-and-table-only reservation entry point.'
        );
    }
}
