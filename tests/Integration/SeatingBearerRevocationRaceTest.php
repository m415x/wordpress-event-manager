<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * WEM-92/T5 — transaction-level lock probe.
 *
 * Deliberately NOT WP_UnitTestCase: WordPress' test fixture starts an outer
 * transaction and prevents WEM_Seating_Transaction::run from starting its own.
 * Run only in the dedicated, isolated PHPUnit WordPress/MySQL instance.
 */
final class SeatingBearerRevocationRaceTest extends TestCase
{
    public function testPostGuestLockCallbackSerializesAgainstIndependentConnection(): void
    {
        global $wpdb;
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';

        self::assertSame(0, (int) $wpdb->get_var('SELECT @@in_transaction'), 'I2 requires an idle connection; do not use WP_UnitTestCase.');

        $guestId = 0;
        $eventId = 0;
        $tableId = 0;
        $second = null;

        try {
            $event = wp_insert_term('T5-' . wp_generate_uuid4(), 'evento');
            self::assertFalse(is_wp_error($event), 'The event fixture must be a real taxonomy term.');
            $eventId = (int) $event['term_id'];
            $guestId = (int) wp_insert_post([
                'post_type' => 'invitado',
                'post_status' => 'publish',
                'post_title' => 'T5-fixture-' . wp_generate_uuid4(),
            ]);
            self::assertGreaterThan(0, $guestId);
            wp_set_object_terms($guestId, [$eventId], 'evento', false);

            $tables = \WEM_Event_Table_Schema::table_name();
            self::assertSame(1, $wpdb->insert($tables, [
                'event_term_id' => $eventId,
                'table_code' => 'T5-' . wp_generate_uuid4(),
                'capacity' => 1,
            ]));
            $tableId = (int) $wpdb->insert_id;

            $host = DB_HOST;
            $port = 3306;
            if (preg_match('/^([^:]+):(\d+)$/', $host, $matches)) {
                $host = $matches[1];
                $port = (int) $matches[2];
            }
            $second = new \mysqli($host, DB_USER, DB_PASSWORD, DB_NAME, $port);
            self::assertNotSame($wpdb->dbh->thread_id, $second->thread_id, 'Distinct database sessions are mandatory.');

            $callbackCalled = false;
            (new \WEM_Seating_Reservation_Service())->reserve_initial(
                $guestId,
                $tableId,
                static function ($lockedGuestId) use ($guestId, $second, &$callbackCalled): void {
                    self::assertSame($guestId, (int) $lockedGuestId);
                    $callbackCalled = true;
                    // NOWAIT must reject a competing WEM-33-style guest lock.
                    $blocked = false;
                    try {
                        $result = $second->query(
                            'SELECT ID FROM ' . $GLOBALS['wpdb']->posts .
                            ' WHERE ID=' . $guestId . ' FOR UPDATE NOWAIT'
                        );
                        if ($result instanceof \mysqli_result) {
                            $result->free();
                        }
                    } catch (\mysqli_sql_exception $error) {
                        $blocked = in_array((int) $error->getCode(), [1205, 3572], true);
                    }
                    self::assertTrue($blocked, 'Concurrent session must be unable to acquire the guest lock.');
                }
            );
            self::assertTrue($callbackCalled, 'I2 currently ignores the optional post-lock callback: contractual RED.');
        } finally {
            if ($second instanceof \mysqli) {
                $second->close();
            }
            if ($guestId > 0) {
                $wpdb->delete(\WEM_Table_Reservation_Schema::table_name(), ['guest_id' => $guestId], ['%d']);
            }
            if ($tableId > 0) {
                $wpdb->delete(\WEM_Event_Table_Schema::table_name(), ['id' => $tableId], ['%d']);
            }
            if ($guestId > 0) {
                wp_delete_post($guestId, true);
            }
            if ($eventId > 0) {
                wp_delete_term($eventId, 'evento');
            }
        }
    }
}
