<?php
declare(strict_types=1);
namespace WEM\Tests\Integration;
use PHPUnit\Framework\TestCase;

/**
 * T5 RED: deterministic adapter-level interleaving requires an overridable
 * preflight boundary to commit revocation after identity resolution but
 * before I2 obtains the canonical guest lock.
 */
final class SeatingBearerPreflightRaceTest extends TestCase
{
    public function testAdapterExposesPreflightBoundaryForDeterministicRace(): void
    {
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        $method = new \ReflectionMethod(\WEM_Seating_Bearer_Initial_Reservation::class, 'resolve_identity');
        self::assertTrue($method->isProtected(), 'Preflight seam must not become public API.');
        self::assertFalse($method->isFinal(), 'Test subclass must inject revocation after real preflight resolution.');
    }
}
