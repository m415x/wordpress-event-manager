<?php

declare(strict_types=1);

namespace WEM\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class StaffAssignmentProvisioningContractTest extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        parent::setUp();

        $path = dirname(__DIR__, 2) . '/includes/class-staff-assignment-manager.php';
        self::assertFileExists($path);

        $source = file_get_contents($path);
        self::assertIsString($source);
        $this->source = $source;
    }

    public function testProfileManagerConsumesTheWemProvisioningApi(): void
    {
        self::assertStringContainsString(
            'WEM_Staff_Provisioning::get_state',
            $this->source,
            'WEM-46 requires the profile UI to read provisioning state through the WEM API.'
        );
        self::assertStringContainsString(
            'WEM_Staff_Provisioning::apply_preset',
            $this->source,
            'WEM-46 requires profile saves to apply explicit presets through the WEM API.'
        );
    }

    public function testProfileManagerOwnsAnExplicitProvisioningField(): void
    {
        self::assertStringContainsString(
            'wem_staff_provisioning_preset',
            $this->source,
            'WEM-46 requires a bounded None/Viewer/Operator profile control.'
        );
    }
}
