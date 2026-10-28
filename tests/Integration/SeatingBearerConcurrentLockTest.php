<?php
declare(strict_types=1);
namespace WEM\Tests\Integration;
use PHPUnit\Framework\TestCase;

/**
 * WEM-92/T5: overlapping InnoDB sessions, not sequential NOWAIT probes.
 * Uses mysqli async query on a separate connection while I2 holds guest lock.
 * The competing guest lock can complete only after I2 commits.
 */
final class SeatingBearerConcurrentLockTest extends TestCase
{
    public function testConcurrentGuestLockWaitsUntilI2ReservationCommits(): void
    {
        global $wpdb;
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        if (!taxonomy_exists('evento')) {(new \WEM_Taxonomy_Manager())->register_taxonomy();}
        if (!post_type_exists('invitado')) {(new \WEM_CPT_Manager())->register_cpt();}
        self::assertSame(0, (int) $wpdb->get_var('SELECT @@in_transaction'));
        self::assertTrue(defined('MYSQLI_ASYNC'), 'mysqli async support required for independently overlapping workers.');
        $guest = $table = $event = 0;
        $second = null;
        try {
            $term = wp_insert_term('T5-concurrent-' . wp_generate_uuid4(), 'evento');
            self::assertFalse(is_wp_error($term));
            $event = (int) $term['term_id'];
            $guest = (int) wp_insert_post(['post_type'=>'invitado','post_status'=>'publish','post_title'=>'T5-' . wp_generate_uuid4()]);
            self::assertGreaterThan(0, $guest);
            wp_set_object_terms($guest, [$event], 'evento', false);
            self::assertSame(1, $wpdb->insert(\WEM_Event_Table_Schema::table_name(), ['event_term_id'=>$event,'table_code'=>'C-' . wp_generate_uuid4(),'capacity'=>1]));
            $table = (int) $wpdb->insert_id;
            $host = DB_HOST;
            $port = 3306;
            if (preg_match('/^([^:]+):(\d+)$/', $host, $m)) {$host=$m[1];$port=(int)$m[2];}
            $second = new \mysqli($host, DB_USER, DB_PASSWORD, DB_NAME, $port);
            self::assertNotSame($wpdb->dbh->thread_id, $second->thread_id);
            self::assertTrue($second->begin_transaction());
            $queryStarted = false;
            $id = (new \WEM_Seating_Reservation_Service())->reserve_initial($guest, $table,
                static function ($lockedGuest) use ($guest, $second, &$queryStarted): void {
                    self::assertSame($guest, (int) $lockedGuest);
                    $queryStarted = $second->query(
                        'SELECT ID FROM ' . $GLOBALS['wpdb']->posts . ' WHERE ID=' . $guest . ' FOR UPDATE',
                        MYSQLI_ASYNC
                    );
                    self::assertTrue($queryStarted, 'Second worker must dispatch an asynchronous blocking lock query.');
                    // Do not await/reap here: primary must commit before the second session may acquire guest lock.
                    $read = [$second]; $errors = [$second]; $reject = [$second];
                    $ready = \mysqli::poll($read, $errors, $reject, 0, 100000);
                    self::assertSame(0, $ready, 'Competing guest lock must still be pending during I2 transaction.');
                }
            );
            self::assertTrue($queryStarted);
            self::assertGreaterThan(0, $id);
            $read = [$second]; $errors = [$second]; $reject = [$second];
            self::assertGreaterThan(0, \mysqli::poll($read, $errors, $reject, 5, 0), 'Second worker should acquire lock after I2 commit.');
            $result = $second->reap_async_query();
            self::assertInstanceOf(\mysqli_result::class, $result);
            self::assertSame(1, $result->num_rows);
            $result->free();
            self::assertTrue($second->commit());
            self::assertSame(1, (int)$wpdb->get_var($wpdb->prepare(
                'SELECT COUNT(*) FROM ' . \WEM_Table_Reservation_Schema::table_name() . ' WHERE guest_id=%d AND status=%s',
                $guest, 'active'
            )));
        } finally {
            if ($second instanceof \mysqli) {$second->rollback();$second->close();}
            if ($guest>0) {$wpdb->delete(\WEM_Table_Reservation_Schema::table_name(),['guest_id'=>$guest]);}
            if ($table>0) {$wpdb->delete(\WEM_Event_Table_Schema::table_name(),['id'=>$table]);}
            if ($guest>0) {wp_delete_post($guest,true);}
            if ($event>0) {wp_delete_term($event,'evento');}
        }
    }
}
