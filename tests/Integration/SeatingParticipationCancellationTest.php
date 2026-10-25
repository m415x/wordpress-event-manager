<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use PHPUnit\Framework\TestCase;

/** WEM-86/T6: cancellation of event participation needs an explicit boundary. */
final class SeatingParticipationCancellationTest extends TestCase
{
    public function testParticipationCancellationHasDedicatedServerBoundary(): void
    {
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        self::assertTrue(
            class_exists('WEM_Seating_Participation_Cancellation_Service'),
            'WEM-86 requires a server-authorized participation cancellation boundary.'
        );
        self::assertTrue(
            method_exists('WEM_Seating_Participation_Cancellation_Service', 'cancel'),
            'Participation cancellation must be atomic with seat release and audit.'
        );
    }
}
