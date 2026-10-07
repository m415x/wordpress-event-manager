<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

final class StaffCapabilityProvisioningTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
    }

    public function testWemOwnsAStaffCapabilityProvisioningApi(): void
    {
        self::assertTrue(
            class_exists('WEM_Staff_Provisioning'),
            'WEM-45 requires a WEM-owned provisioning API for None, Viewer and Operator.'
        );
    }
}
