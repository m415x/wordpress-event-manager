<?php
declare(strict_types=1);
namespace WEM\Tests\Integration;

use PHPUnit\Framework\TestCase;

// PHPUnit's file-scoped runner does not autoload neighboring test classes.


/**
 * WEM-92/T5: an acceptable race result is not evidence of both race orders.
 * RED requires separately named witnesses with deterministic barriers.
 */
final class SeatingBearerBothOrdersContractTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__ . '/SeatingBearerRealConcurrentServicesTest.php';
    }

    public function testRevocationFirstRealServicesHasDeterministicWitness(): void
    {
        self::assertTrue(
            method_exists(SeatingBearerRealConcurrentServicesTest::class, 'testRevokeFirstAfterConfirmedCommitDeniesWithoutInsert'),
            'Missing deterministic revoke-first witness using real WEM-33 and bearer adapter.'
        );
    }

    public function testReservationFirstRealServicesHasBlockedRevokeWitness(): void
    {
        self::assertTrue(
            method_exists(SeatingBearerRealConcurrentServicesTest::class, 'testReserveFirstWorkerAttemptsGuestLockBeforeReservationCommit'),
            'Missing deterministic reserve-first witness: prove worker tries guest lock while I2 holds it.'
        );
    }
}
