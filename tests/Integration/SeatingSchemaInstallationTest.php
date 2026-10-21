<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

final class SeatingSchemaInstallationTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
    }

    public function testBothSeatingSchemaInstallersAreRegisteredOnActivation(): void
    {
        $activation = 'activate_wordpress-event-manager/wordpress-event-manager.php';

        self::assertNotFalse(
            has_action($activation, ['WEM_Event_Table_Schema', 'install']),
            'WEM-74 must provision event tables during actual plugin activation.'
        );
        self::assertNotFalse(
            has_action($activation, ['WEM_Table_Reservation_Schema', 'install']),
            'WEM-74 must provision reservations during actual plugin activation.'
        );
    }
}
