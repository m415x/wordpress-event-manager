<?php
declare(strict_types=1);

namespace WEM\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * WEM-84/T4 entry contract: admin cancellation is an explicit domain
 * boundary, never a direct modification of the occupancy ledger.
 */
final class SeatingAdministrativeCancellationTest extends TestCase
{
    public function testAdministrativeCancellationHasExplicitServerAuthoritativeEntryPoint(): void
    {
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';

        self::assertTrue(
            class_exists('WEM_Seating_Administrative_Cancellation_Service'),
            'WEM-84 requires a dedicated administrative cancellation service.'
        );
        self::assertTrue(
            method_exists('WEM_Seating_Administrative_Cancellation_Service', 'cancel'),
            'WEM-84 requires a server-authorized cancel boundary.'
        );
    }
}
