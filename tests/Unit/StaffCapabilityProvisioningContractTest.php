<?php

declare(strict_types=1);

namespace WEM\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class StaffCapabilityProvisioningContractTest extends TestCase
{
    public function testWemOwnsAStaffCapabilityProvisioningClassFile(): void
    {
        self::assertFileExists(
            dirname(__DIR__, 2) . '/includes/class-staff-provisioning.php',
            'WEM-45 requires a WEM-owned provisioning API for None, Viewer and Operator.'
        );
    }
}
