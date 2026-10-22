<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * WEM-81/T1. Runs only on isolated PHPUnit WordPress/MySQL, never manual 8890.
 * No WP_UnitTestCase outer transaction: observe real SQL writes and rollback.
 */
final class SeatingReservationTransactionTest extends TestCase
{
    public function testPostInsertExceptionRollsBackWithoutCommitOrPartialEvidence(): void
    {
        global $wpdb;

        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';

        self::assertTrue(
            class_exists('WEM_Seating_Transaction'),
            'WEM-81/T1 requires the public WEM_Seating_Transaction boundary.'
        );

        $tables = $wpdb->prefix . 'wem_event_tables';
        $reservations = $wpdb->prefix . 'wem_table_reservations';
        $audit = $wpdb->prefix . 'wem_seating_admin_audit';

        foreach ([$tables, $reservations, $audit] as $name) {
            $row = $wpdb->get_row($wpdb->prepare(
                'SELECT ENGINE, TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s',
                $name
            ), ARRAY_A);
            self::assertIsArray($row, 'Persistent table required: ' . $name);
            self::assertSame('InnoDB', $row['ENGINE'], 'Physical InnoDB required: ' . $name);
            self::assertSame('BASE TABLE', $row['TABLE_TYPE'], 'No temporary table: ' . $name);
        }

        $eventId = 915081;
        $guestId = 995081;
        $tableId = 0;
        $queries = [];
        $insertObserved = false;
        $caught = false;
        $filter = static function (string $sql) use (&$queries): string {
            $queries[] = $sql;
            return $sql;
        };
        $oldSuppress = $wpdb->suppress_errors(true);
        try {
            self::assertSame(1, $wpdb->insert($tables, [
                'event_term_id' => $eventId,
                'table_code' => 'WEM81-' . wp_generate_uuid4(),
                'capacity' => 2,
            ], ['%d', '%s', '%d']));
            $tableId = (int) $wpdb->insert_id;
            $count = static function () use ($wpdb, $reservations, $tableId): int {
                return (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$reservations} WHERE table_id = %d AND status = 'active' AND active_slot = 1",
                    $tableId
                ));
            };
            $beforeActive = $count();
            $beforeAudit = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$audit} WHERE guest_id = %d AND event_term_id = %d",
                $guestId, $eventId
            ));
            $capacity = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT capacity FROM {$tables} WHERE id = %d", $tableId
            ));
            $beforeAvailable = $capacity - $beforeActive;

            add_filter('query', $filter);
            try {
                \WEM_Seating_Transaction::run(static function () use (
                    $wpdb, $reservations, $guestId, $tableId, $eventId, &$insertObserved
                ): void {
                    $result = $wpdb->insert($reservations, [
                        'guest_id' => $guestId,
                        'table_id' => $tableId,
                        'event_term_id' => $eventId,
                        'status' => 'active',
                        'active_slot' => 1,
                        'created_at' => gmdate('Y-m-d H:i:s'),
                    ], ['%d', '%d', '%d', '%s', '%d', '%s']);
                    if ($result !== 1) {
                        throw new \RuntimeException('Fixture INSERT failure: ' . $wpdb->last_error);
                    }
                    $insertObserved = true;
                    throw new \DomainException('WEM81_POST_INSERT_FAULT');
                });
            } catch (\DomainException $e) {
                self::assertSame('WEM81_POST_INSERT_FAULT', $e->getMessage());
                $caught = true;
            } finally {
                remove_filter('query', $filter);
            }

            self::assertTrue($insertObserved, 'The reservation INSERT must succeed before fault injection.');
            self::assertTrue($caught, 'The controlled post-INSERT exception must propagate.');
            $normalized = array_map(static function (string $sql): string {
                return strtoupper(trim($sql));
            }, $queries);
            $begin = $insert = $rollback = $commit = -1;
            foreach ($normalized as $index => $sql) {
                if ($begin < 0 && preg_match('/^(START TRANSACTION|BEGIN)\\b/', $sql)) { $begin = $index; }
                if ($insert < 0 && str_starts_with($sql, 'INSERT INTO') && str_contains($sql, strtoupper($reservations))) { $insert = $index; }
                if ($rollback < 0 && str_starts_with($sql, 'ROLLBACK')) { $rollback = $index; }
                if ($commit < 0 && str_starts_with($sql, 'COMMIT')) { $commit = $index; }
            }
            self::assertGreaterThanOrEqual(0, $begin, 'START TRANSACTION must execute.');
            self::assertGreaterThan($begin, $insert, 'INSERT must occur after transaction start.');
            self::assertGreaterThan($insert, $rollback, 'ROLLBACK must follow successful INSERT and injected exception.');
            self::assertSame(-1, $commit, 'COMMIT must not execute on an exception path.');

            // Independent SELECTs after run() returns, outside its transaction.
            $afterActive = $count();
            $afterAudit = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$audit} WHERE guest_id = %d AND event_term_id = %d",
                $guestId, $eventId
            ));
            self::assertSame($beforeActive, $afterActive, 'Rollback must restore active occupancy.');
            self::assertSame($beforeAvailable, $capacity - $afterActive, 'Rollback must restore derived availability.');
            self::assertSame($beforeAudit, $afterAudit, 'Rollback must not leave partial audit.');
            self::assertSame(0, (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$reservations} WHERE guest_id = %d", $guestId
            )), 'No rolled-back reservation row may persist.');
        } finally {
            // Test-specific fixture only: never truncate/reset shared tables.
            if ($tableId > 0) {
                $wpdb->delete($tables, ['id' => $tableId], ['%d']);
            }
            $wpdb->suppress_errors($oldSuppress);
        }
    }
}
