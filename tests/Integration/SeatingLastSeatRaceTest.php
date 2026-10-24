<?php
declare(strict_types=1);

namespace WEM\Tests\Integration;

use PHPUnit\Framework\TestCase;

/** WEM-83: two PHP processes independently contend for one durable seat. */
final class SeatingLastSeatRaceTest extends TestCase
{
    public function testTwoRealRequestsCannotOverbookLastSeat(): void
    {
        global $wpdb;
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        if (!taxonomy_exists('evento')) { register_taxonomy('evento', 'invitado'); }
        if (!post_type_exists('invitado')) { register_post_type('invitado', ['public' => false]); }
        $eventId = 0;
        $guests = [];
        $tableId = 0;
        $jobs = [];
        $gate = sys_get_temp_dir() . '/wem83-race-' . bin2hex(random_bytes(12));
        $tables = $wpdb->prefix . 'wem_event_tables';
        $reservations = $wpdb->prefix . 'wem_table_reservations';
        try {
            self::assertTrue(function_exists('proc_open'), 'Independent workers require proc_open.');
            $term = wp_insert_term('WEM83-race-' . wp_generate_uuid4(), 'evento');
            self::assertFalse(is_wp_error($term));
            $eventId = (int) $term['term_id'];
            for ($i = 0; $i < 2; ++$i) {
                $post = wp_insert_post([
                    'post_type'=>'invitado',
                    'post_status'=>'publish',
                    'post_title'=>'WEM83 synthetic '.$i,
                ], true);
                self::assertFalse(is_wp_error($post));
                $id = (int) $post;
                $guests[] = $id;
                self::assertFalse(is_wp_error(wp_set_object_terms($id, [$eventId], 'evento')));
            }
            self::assertSame(1, $wpdb->insert($tables, [
                'event_term_id'=>$eventId,
                'table_code'=>'WEM83-race-'.wp_generate_uuid4(),
                'capacity'=>1,
            ], ['%d','%s','%d']));
            $tableId = (int) $wpdb->insert_id;

            $worker = __DIR__ . '/fixtures/wem83-seat-race-worker.php';
            foreach ($guests as $guest) {
                $spec = [1=>['pipe','w'], 2=>['pipe','w']];
                $pipes = [];
                $process = proc_open([PHP_BINARY, $worker, $gate, (string)$guest, (string)$tableId], $spec, $pipes);
                self::assertIsResource($process, 'Could not start independent PHP worker.');
                $jobs[] = [$process, $pipes];
            }
            // Workers wait for the same release gate before calling the service.
            usleep(300000);
            self::assertSame(1, file_put_contents($gate, '1'));
            $outcomes = [];
            foreach ($jobs as [$process, $pipes]) {
                $stdout = trim(stream_get_contents($pipes[1]));
                $stderr = trim(stream_get_contents($pipes[2]));
                fclose($pipes[1]);
                fclose($pipes[2]);
                $status = proc_close($process);
                foreach ($jobs as $index => $job) {
                    if ($job[0] === $process) { unset($jobs[$index]); break; }
                }
                self::assertSame(0, $status, 'Worker failed: '.$stderr);
                self::assertMatchesRegularExpression('/^(WIN|DENY):[1-9][0-9]*$/D', $stdout);
                $outcomes[] = explode(':', $stdout);
            }
            $jobs = [];
            self::assertNotSame($outcomes[0][1], $outcomes[1][1], 'Workers must use different MySQL connections.');
            $kinds = array_map(static fn($value) => $value[0], $outcomes);
            sort($kinds);
            self::assertSame(['DENY', 'WIN'], $kinds);
            self::assertSame(1, count(array_filter($outcomes, static fn($value) => $value[0] === 'WIN')));
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT guest_id FROM {$reservations} WHERE table_id=%d AND status='active' AND active_slot=1",
                $tableId
            ), ARRAY_A);
            self::assertCount(1, $rows, 'Two independent requests must never overbook capacity one.');
            self::assertContains((int) $rows[0]['guest_id'], $guests);
        } finally {
            foreach ($jobs as [$process, $pipes]) {
                if (is_resource($process)) { proc_terminate($process); }
                foreach ($pipes as $pipe) { if (is_resource($pipe)) { fclose($pipe); } }
                if (is_resource($process)) { proc_close($process); }
            }
            if (is_file($gate)) { unlink($gate); }
            foreach ($guests as $guest) {
                $wpdb->delete($reservations, ['guest_id'=>$guest], ['%d']);
            }
            if ($tableId > 0) { $wpdb->delete($tables, ['id'=>$tableId], ['%d']); }
            foreach ($guests as $guest) { wp_delete_post($guest, true); }
            if ($eventId > 0) { wp_delete_term($eventId, 'evento'); }
        }
    }
}
