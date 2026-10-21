<?php

declare(strict_types=1);

namespace WEM\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class SeatingSchemaContractTest extends TestCase
{
    public function testDedicatedEventTableAndReservationSchemaExists(): void
    {
        $root = dirname(__DIR__, 2);

        self::assertFileExists(
            $root . '/includes/class-event-table-schema.php',
            'WEM-74 requires a dedicated event-scoped table schema, not guest mesa post meta.'
        );
        self::assertFileExists(
            $root . '/includes/class-table-reservation-schema.php',
            'WEM-74 requires reservations as the canonical assignment authority.'
        );
    }
}
