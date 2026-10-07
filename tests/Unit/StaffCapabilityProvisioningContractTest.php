<?php

declare(strict_types=1);

namespace WEM\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class StaffCapabilityProvisioningContractTest extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        parent::setUp();

        $path = dirname(__DIR__, 2) . '/includes/class-staff-provisioning.php';
        self::assertFileExists(
            $path,
            'WEM-45 requires a WEM-owned provisioning API for None, Viewer and Operator.'
        );

        $source = file_get_contents($path);
        self::assertIsString($source);
        $this->source = $source;
    }

    public function testProvisioningBoundaryDeclaresCanonicalAndNonCanonicalStates(): void
    {
        foreach (['STATE_NONE', 'STATE_VIEWER', 'STATE_OPERATOR', 'STATE_NON_CANONICAL'] as $constant) {
            self::assertStringContainsString(
                'const ' . $constant,
                $this->source,
                sprintf('WEM-45 requires explicit provisioning state %s.', $constant)
            );
        }
    }

    public function testProvisioningBoundaryExposesReadOnlyStateDetection(): void
    {
        self::assertStringContainsString(
            'function get_state',
            $this->source,
            'WEM-45 requires provisioning-state detection before preset mutation.'
        );
    }
}
