<?php
declare(strict_types=1);

namespace WEM\Tests\Integration;

use PHPUnit\Framework\TestCase;

/** WEM-85/T5 — explicit server-authorized reassignment domain boundary. */
final class SeatingAdministrativeReassignmentTest extends TestCase
{
    public function testAdministrativeReassignmentExposesDedicatedServerBoundary(): void
    {
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';

        self::assertTrue(
            class_exists('WEM_Seating_Administrative_Reassignment_Service'),
            'Reassignment must use a dedicated authorized domain boundary.'
        );
        self::assertTrue(
            method_exists('WEM_Seating_Administrative_Reassignment_Service', 'reassign'),
            'WEM-85 requires a reassign(guest, target table, reason) entry point.'
        );
    }
}
