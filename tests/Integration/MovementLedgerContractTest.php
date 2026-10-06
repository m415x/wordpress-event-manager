<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use InvalidArgumentException;
use WP_UnitTestCase;

final class MovementLedgerContractTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        \WEM_Movement_Schema::install();
    }

    public function testSchemaExposesCanonicalColumnsAndIndexes(): void
    {
        global $wpdb;

        $tableName = \WEM_Movement_Schema::table_name();

        $columns = $wpdb->get_results("SHOW COLUMNS FROM {$tableName}", ARRAY_A);
        $columnNames = array_column($columns, 'Field');

        self::assertSame(
            [
                'sequence',
                'movement_id',
                'guest_id',
                'event_term_id',
                'movement_type',
                'occurred_at',
                'actor_user_id',
                'source',
                'decision',
            ],
            $columnNames
        );

        $indexes = $wpdb->get_results("SHOW INDEX FROM {$tableName}", ARRAY_A);
        $indexNames = array_values(array_unique(array_column($indexes, 'Key_name')));

        self::assertContains('PRIMARY', $indexNames);
        self::assertContains('movement_id', $indexNames);
        self::assertContains('guest_event_sequence', $indexNames);
    }

    /**
     * @dataProvider invalidMovementProvider
     */
    public function testLedgerRejectsNonCanonicalMovementFacts(array $movement): void
    {
        $ledger = new \WEM_Movement_Ledger();

        $this->expectException(InvalidArgumentException::class);

        $ledger->append($movement);
    }

    public function invalidMovementProvider(): array
    {
        $valid = [
            'guest_id' => 301,
            'event_term_id' => 9,
            'movement_type' => 'checkin',
            'occurred_at' => '2026-10-06 11:00:00',
            'actor_user_id' => 5,
            'source' => 'staff_web',
            'decision' => 'accepted',
        ];

        return [
            'rejected decision is not a movement' => [
                array_merge($valid, ['decision' => 'rejected']),
            ],
            'unknown movement type is rejected' => [
                array_merge($valid, ['movement_type' => 'cancelled']),
            ],
            'unknown source is rejected in WEM-14' => [
                array_merge($valid, ['source' => 'public_qr']),
            ],
            'missing guest identity is rejected' => [
                array_merge($valid, ['guest_id' => 0]),
            ],
            'missing event identity is rejected' => [
                array_merge($valid, ['event_term_id' => 0]),
            ],
            'missing actor identity is rejected' => [
                array_merge($valid, ['actor_user_id' => 0]),
            ],
        ];
    }
}
