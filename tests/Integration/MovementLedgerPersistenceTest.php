<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

final class MovementLedgerPersistenceTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        \WEM_Movement_Schema::install();
    }

    public function testAcceptedMovementsAppendWithDurableMonotonicOrder(): void
    {
        self::assertTrue(
            class_exists('WEM_Movement_Ledger'),
            'WEM-26 requires an append/read movement ledger API.'
        );

        $ledger = new \WEM_Movement_Ledger();

        $first = $ledger->append([
            'guest_id' => 101,
            'event_term_id' => 7,
            'movement_type' => 'checkin',
            'occurred_at' => '2026-10-06 09:00:00',
            'actor_user_id' => 3,
            'source' => 'staff_web',
            'decision' => 'accepted',
        ]);

        $second = $ledger->append([
            'guest_id' => 101,
            'event_term_id' => 7,
            'movement_type' => 'checkout',
            'occurred_at' => '2026-10-06 09:00:00',
            'actor_user_id' => 3,
            'source' => 'staff_web',
            'decision' => 'accepted',
        ]);

        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $first['movement_id']
        );
        self::assertNotSame($first['movement_id'], $second['movement_id']);
        self::assertLessThan($second['sequence'], $first['sequence']);

        $history = $ledger->find_by_guest_event(101, 7);

        self::assertCount(2, $history);
        self::assertSame('checkin', $history[0]['movement_type']);
        self::assertSame('checkout', $history[1]['movement_type']);
        self::assertLessThan($history[1]['sequence'], $history[0]['sequence']);
    }

    public function testSchemaRerunPreservesExistingMovementAndUniqueUuidConstraint(): void
    {
        self::assertTrue(class_exists('WEM_Movement_Ledger'));

        $ledger = new \WEM_Movement_Ledger();

        $movement = $ledger->append([
            'guest_id' => 202,
            'event_term_id' => 8,
            'movement_type' => 'checkin',
            'occurred_at' => '2026-10-06 10:00:00',
            'actor_user_id' => 4,
            'source' => 'staff_web',
            'decision' => 'accepted',
        ]);

        \WEM_Movement_Schema::install();

        $history = $ledger->find_by_guest_event(202, 8);

        self::assertCount(1, $history);
        self::assertSame($movement['movement_id'], $history[0]['movement_id']);

        global $wpdb;
        $tableName = \WEM_Movement_Schema::table_name();

        $duplicate = $wpdb->insert(
            $tableName,
            [
                'movement_id' => $movement['movement_id'],
                'guest_id' => 202,
                'event_term_id' => 8,
                'movement_type' => 'checkout',
                'occurred_at' => '2026-10-06 10:01:00',
                'actor_user_id' => 4,
                'source' => 'staff_web',
                'decision' => 'accepted',
            ]
        );

        self::assertFalse($duplicate, 'movement_id must remain unique after schema rerun.');
    }
}
