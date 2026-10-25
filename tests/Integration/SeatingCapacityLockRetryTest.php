<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use PHPUnit\Framework\TestCase;

/** WEM-83: an independent MySQL session locks the last available table. */
final class SeatingCapacityLockRetryTest extends TestCase
{
    public function testCapacityLockTimeoutHasBoundedRetries(): void
    {
        global $wpdb;
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        if (!taxonomy_exists('evento')) {
            register_taxonomy('evento', 'invitado');
        }
        if (!post_type_exists('invitado')) {
            register_post_type('invitado', ['public' => false]);
        }
        $event = wp_insert_term('wem83-' . wp_generate_uuid4(), 'evento');
        self::assertFalse(is_wp_error($event));
        $eventId = (int) $event['term_id'];
        $guest = wp_insert_post(['post_type' => 'invitado','post_status' => 'publish','post_title' => 'WEM83 synthetic'], true);
        self::assertFalse(is_wp_error($guest));
        $guest = (int) $guest;
        wp_set_object_terms($guest, [$eventId], 'evento');
        $tableName = $wpdb->prefix . 'wem_event_tables';
        $reservationName = $wpdb->prefix . 'wem_table_reservations';
        $wpdb->insert($tableName, ['event_term_id' => $eventId,'table_code' => 'wem83-' . wp_generate_uuid4(),'capacity' => 1]);
        $tableId = (int) $wpdb->insert_id;
        $locker = null;
        $beforeTimeout = (int) $wpdb->get_var('SELECT @@SESSION.innodb_lock_wait_timeout');
        $attempts = 0;
        $track = static function ($sql) use (&$attempts) {
            if (strtoupper(trim($sql)) === 'START TRANSACTION') {
                ++$attempts;
            }
            return $sql;
        };
        try {
            $locker = new \mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
            self::assertNotSame((int) $wpdb->get_var('SELECT CONNECTION_ID()'), $locker->thread_id);
            $locker->begin_transaction();
            $held = $locker->query("SELECT id FROM {$tableName} WHERE id={$tableId} FOR UPDATE");
            self::assertSame(1, $held->num_rows);
            $wpdb->query('SET SESSION innodb_lock_wait_timeout=1');
            add_filter('query', $track);
            try {
                $result = (new \WEM_Seating_Reservation_Service())->reserve_initial($guest, $tableId);
                self::fail('Expected capacity lock contention, got reservation ' . $result);
            } catch (\DomainException $actual) {
                self::assertSame('Seating temporarily unavailable.', $actual->getMessage(), 'Lock timeout must be classified as controlled retry exhaustion, not table ineligibility.');
            } catch (\RuntimeException $actual) {
                self::assertSame('Seating temporarily unavailable.', $actual->getMessage(), 'Lock timeout must produce a controlled unavailable result.');
            } finally {
                remove_filter('query', $track);
            }
            self::assertSame(3, $attempts, 'Exactly one initial attempt and two bounded retries.');
            self::assertSame(0, (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$reservationName} WHERE guest_id=%d", $guest)));
        } finally {
            if ($locker instanceof \mysqli) {
                $locker->rollback();
                $locker->close();
            }
            $wpdb->query('SET SESSION innodb_lock_wait_timeout=' . $beforeTimeout);
            $wpdb->delete($reservationName, ['guest_id' => $guest]);
            $wpdb->delete($tableName, ['id' => $tableId]);
            wp_delete_post($guest, true);
            wp_delete_term($eventId, 'evento');
        }
    }
}
