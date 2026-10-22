<?php

declare(strict_types=1);

namespace WEM\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class SeatingParticipationEligibilitySchemaContractTest extends TestCase
{
    public function testCancellationHasDedicatedPersistentIneligibilityState(): void
    {
        $file = dirname(__DIR__, 2) . '/includes/class-seating-participation-schema.php';
        self::assertFileExists($file, 'WEM-74 needs canonical persistent participation eligibility, distinct from WEM-33 bearer and WEM-14 check-in.');
        $source = file_get_contents($file);
        self::assertIsString($source);
        self::assertStringContainsString('guest_id', $source);
        self::assertStringContainsString('event_term_id', $source);
        self::assertStringContainsString('participation_status', $source);
        self::assertStringContainsString('canceled_at', $source);
        self::assertStringContainsString('UNIQUE KEY guest_event', $source);
    }
}
