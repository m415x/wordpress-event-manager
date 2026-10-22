<?php

declare(strict_types=1);

namespace WEM\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class SeatingAdministrativeAuditSchemaContractTest extends TestCase
{
    public function testAdministrativeReservationChangesHaveDedicatedAppendOnlyAuditSchema(): void
    {
        $file = dirname(__DIR__, 2) . '/includes/class-seating-administrative-audit-schema.php';

        self::assertFileExists($file, 'WEM-74 requires dedicated administrative seating audit storage.');
        $source = file_get_contents($file);
        self::assertIsString($source);
        self::assertStringContainsString('guest_id', $source);
        self::assertStringContainsString('event_term_id', $source);
        self::assertStringContainsString('actor_user_id', $source);
        self::assertStringContainsString('operation', $source);
        self::assertStringContainsString('old_table_id', $source);
        self::assertStringContainsString('new_table_id', $source);
        self::assertStringContainsString('occurred_at', $source);
        self::assertStringContainsString('reason', $source);
    }
}
