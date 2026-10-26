<?php
declare(strict_types=1);

namespace WEM\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * WEM-93/T6 HTTP boundary RED. No T5, WEM-33 or staff surface is changed.
 * Actual HTTP response and abuse regressions must follow this missing-boundary RED.
 */
final class SeatingPublicHttpSecurityTest extends TestCase
{
    public function testI3PublicOperationsRequireDedicatedSecurityBoundary(): void
    {
        self::assertTrue(
            class_exists('WEM_Seating_Public_Http_Route'),
            'WEM-93 RED: public I3 HTTP route with fail-closed authentication and safe headers is absent.'
        );
    }

    public function testI3PublicOperationsRequireBoundedAbuseControl(): void
    {
        self::assertTrue(
            class_exists('WEM_Seating_Public_Abuse_Guard'),
            'WEM-93 RED: bounded attempts/requests guard for bearer-scoped I3 HTTP operations is absent.'
        );
    }
}
