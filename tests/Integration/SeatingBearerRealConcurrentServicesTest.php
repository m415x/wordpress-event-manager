<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * WEM-92/T5 — persistent InnoDB functional acceptance, no WP test outer TX.
 * Requires existing schema in the isolated PHPUnit environment.
 */
final class SeatingBearerRealConcurrentServicesTest extends TestCase
{
    public function testRealRevocationRacesRealBearerReservationWithValidLinearization(): void
    {
        global $wpdb;
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        if (!taxonomy_exists('evento')) {
            (new \WEM_Taxonomy_Manager())->register_taxonomy();
        }
        if (!post_type_exists('invitado')) {
            (new \WEM_CPT_Manager())->register_cpt();
        }
        self::assertSame(0, (int) $wpdb->get_var('SELECT @@in_transaction'));

        $guest = 0;
        $tableId = 0;
        $eventId = 0;
        $adminId = 0;
        $process = null;
        $pipes = [];
        $readyFile = null;
        try {
            $event = wp_insert_term('T5-persist-' . wp_generate_uuid4(), 'evento');
            self::assertFalse(is_wp_error($event));
            $eventId = (int) $event['term_id'];
            $guest = (int) wp_insert_post([
                'post_type' => 'invitado',
                'post_status' => 'publish',
                'post_title' => 'T5-persist-' . wp_generate_uuid4(),
            ]);
            self::assertGreaterThan(0, $guest);
            wp_set_object_terms($guest, [$eventId], 'evento', false);
            $wpdb->insert(\WEM_Event_Table_Schema::table_name(), [
                'event_term_id' => $eventId,
                'table_code' => 'T5-' . wp_generate_uuid4(),
                'capacity' => 2,
            ]);
            $tableId = (int) $wpdb->insert_id;
            self::assertGreaterThan(0, $tableId);

            $adminId = (int) wp_create_user('wem-t5-' . wp_generate_uuid4(), wp_generate_password(32), 'wem-t5-' . wp_generate_uuid4() . '@example.invalid');
            self::assertGreaterThan(0, $adminId);
            (new \WP_User($adminId))->set_role('administrator');
            $credentials = new \WEM_Invitation_Credential_Service();
            $issued = $credentials->issue($guest, $adminId);
            $adapter = new \WEM_Seating_Bearer_Initial_Reservation();
            self::assertTrue(function_exists('proc_open'));
            $readyFile = tempnam(sys_get_temp_dir(), 'wem-t5-');
            self::assertNotFalse($readyFile);
            $adapter = new class($guest, $adminId, $readyFile, $process, $pipes)
                extends \WEM_Seating_Bearer_Initial_Reservation {
                private $guest;
                private $admin;
                private $readyFile;
                private $processRef;
                private $pipesRef;
                public function __construct($guest, $admin, $readyFile, &$process, &$pipes) {
                    $this->guest=$guest; $this->admin=$admin; $this->readyFile=$readyFile;
                    $this->processRef=&$process; $this->pipesRef=&$pipes;
                }
                protected function resolve_identity($bearer) {
                    $identity=parent::resolve_identity($bearer);
                    \PHPUnit\Framework\Assert::assertIsArray($identity);
                    $this->processRef=proc_open(
                        [PHP_BINARY, __DIR__ . '/helpers/revoke-worker.php',
                            (string)$this->guest, (string)$this->admin, $this->readyFile],
                        [0=>['pipe','r'], 1=>['pipe','w'], 2=>['pipe','w']],
                        $this->pipesRef
                    );
                    \PHPUnit\Framework\Assert::assertIsResource($this->processRef);
                    fclose($this->pipesRef[0]);
                    $start=microtime(true);
                    while (trim((string)file_get_contents($this->readyFile)) !== 'READY'
                        && microtime(true)-$start < 5.0) {
                        usleep(10000);
                    }
                    \PHPUnit\Framework\Assert::assertSame('READY',
                        trim((string)file_get_contents($this->readyFile)),
                        'Independent worker never reached pre-revocation barrier.');
                    // Worker is active and now racing for guest lock. Do not wait
                    // for its result before entering I2.
                    return $identity;
                }
            };
            $reserved = false;
            try {
                $id=$adapter->reserve_for_bearer($issued['token'], $tableId);
                self::assertGreaterThan(0, $id);
                $reserved = true;
            } catch (\DomainException $expected) {
                // Revocation may legitimately linearize before reservation.
            }
            $stdout=stream_get_contents($pipes[1]);
            $stderr=stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]);
            self::assertSame(0, proc_close($process), 'Real WEM-33 worker failed: ' . $stderr);
            $process = null;
            self::assertSame('REVOKED', trim($stdout));
            self::assertNull((new \WEM_Seating_Bearer_Authorization())->resolve($issued['token']));
            $count=(int)$wpdb->get_var($wpdb->prepare(
                'SELECT COUNT(*) FROM ' . \WEM_Table_Reservation_Schema::table_name()
                . ' WHERE guest_id=%d', $guest
            ));
            self::assertSame($reserved ? 1 : 0, $count,
                'Reservation persistence must match the winner of the guest lock.');
        } finally {
            if (is_resource($process)) {
                proc_terminate($process);
                if (isset($pipes[1]) && is_resource($pipes[1])) fclose($pipes[1]);
                if (isset($pipes[2]) && is_resource($pipes[2])) fclose($pipes[2]);
                proc_close($process);
            }
            if (is_string($readyFile) && is_file($readyFile)) unlink($readyFile);
            if ($guest > 0) {
                $wpdb->delete(\WEM_Table_Reservation_Schema::table_name(), ['guest_id' => $guest]);
                $wpdb->delete(\WEM_Invitation_Credential_Schema::table_name(), ['guest_id' => $guest]);
            }
            if ($tableId > 0) {
                $wpdb->delete(\WEM_Event_Table_Schema::table_name(), ['id' => $tableId]);
            }
            if ($guest > 0) {
                wp_delete_post($guest, true);
            }
            if ($adminId > 0) {
                wp_delete_user($adminId);
            }
            if ($eventId > 0) {
                wp_delete_term($eventId, 'evento');
            }
        }
    }
    public function testRevokeFirstAfterConfirmedCommitDeniesWithoutInsert(): void
    {
        global $wpdb;
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        if (!taxonomy_exists('evento')) {(new \WEM_Taxonomy_Manager())->register_taxonomy();}
        if (!post_type_exists('invitado')) {(new \WEM_CPT_Manager())->register_cpt();}
        self::assertSame(0, (int) $wpdb->get_var('SELECT @@in_transaction'));

        $guest = $event = $table = $admin = 0;

        try {
            $term = wp_insert_term('T5-stale-' . wp_generate_uuid4(), 'evento');
            self::assertFalse(is_wp_error($term));
            $event = (int) $term['term_id'];
            $guest = (int) wp_insert_post(['post_type'=>'invitado','post_status'=>'publish','post_title'=>'T5-' . wp_generate_uuid4()]);
            self::assertGreaterThan(0, $guest);
            wp_set_object_terms($guest, [$event], 'evento', false);
            self::assertSame(1, $wpdb->insert(\WEM_Event_Table_Schema::table_name(), [
                'event_term_id'=>$event, 'table_code'=>'T5-' . wp_generate_uuid4(), 'capacity'=>2
            ]));
            $table = (int) $wpdb->insert_id;
            $admin = (int) wp_create_user('wem-t5-' . wp_generate_uuid4(), wp_generate_password(32), 'wem-t5-' . wp_generate_uuid4() . '@example.invalid');
            self::assertGreaterThan(0, $admin);
            (new \WP_User($admin))->set_role('administrator');
            $bearer = (new \WEM_Invitation_Credential_Service())->issue($guest, $admin)['token'];

            self::assertTrue(function_exists('proc_open'), 'Independent PHP workers must be available.');
            $adapter = new class($guest, $admin) extends \WEM_Seating_Bearer_Initial_Reservation {
                private $guest;
                private $admin;
                public $revoked = false;
                public function __construct($guest, $admin) {
                    $this->guest=$guest; $this->admin=$admin;
                }
                protected function resolve_identity($bearer) {
                    $identity = parent::resolve_identity($bearer);
                    \PHPUnit\Framework\Assert::assertIsArray($identity);
                    \PHPUnit\Framework\Assert::assertSame($this->guest, $identity['guest_id']);
                    $script = __DIR__ . '/helpers/revoke-worker.php';
                    $process = proc_open(
                        [PHP_BINARY, $script, (string)$this->guest, (string)$this->admin],
                        [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],
                        $pipes
                    );
                    \PHPUnit\Framework\Assert::assertIsResource($process);
                    fclose($pipes[0]);
                    $stdout = stream_get_contents($pipes[1]);
                    $stderr = stream_get_contents($pipes[2]);
                    fclose($pipes[1]);
                    fclose($pipes[2]);
                    $exit = proc_close($process);
                    \PHPUnit\Framework\Assert::assertSame(0, $exit, 'Real WEM-33 worker failed: ' . $stderr);
                    \PHPUnit\Framework\Assert::assertSame("REVOKED", trim($stdout));
                    $this->revoked=true;
                    return $identity;
                }
            };
            try {
                $adapter->reserve_for_bearer($bearer, $table);
                self::fail('A revoked bearer cannot authorize an I2 write after stale preflight.');
            } catch (\DomainException $expected) {
                self::assertTrue($adapter->revoked, 'Revocation must happen within preflight seam.');
                self::assertSame(0, (int)$wpdb->get_var($wpdb->prepare(
                    'SELECT COUNT(*) FROM ' . \WEM_Table_Reservation_Schema::table_name() . ' WHERE guest_id=%d', $guest
                )));
                $credentialsTable = \WEM_Invitation_Credential_Schema::table_name();
                $credentialState = $wpdb->get_row($wpdb->prepare(
                    "SELECT status, active_slot FROM {$credentialsTable} WHERE guest_id=%d AND token_digest=%s",
                    $guest, hash('sha256', $bearer)
                ), ARRAY_A);
                self::assertSame('revoked', $credentialState['status']);
                self::assertNull($credentialState['active_slot']);
                $reservationsTable = \WEM_Table_Reservation_Schema::table_name();
                self::assertSame(0, (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$reservationsTable} WHERE table_id=%d AND status='active' AND active_slot=1", $table
                )));
                self::assertNull((new \WEM_Seating_Bearer_Authorization())->resolve($bearer));
            }
        } finally {
            if ($guest>0) {
                $wpdb->delete(\WEM_Table_Reservation_Schema::table_name(), ['guest_id'=>$guest]);
                $wpdb->delete(\WEM_Invitation_Credential_Schema::table_name(), ['guest_id'=>$guest]);
            }
            if ($table>0) {$wpdb->delete(\WEM_Event_Table_Schema::table_name(), ['id'=>$table]);}
            if ($guest>0) {wp_delete_post($guest,true);}
            if ($admin>0) {wp_delete_user($admin);}
            if ($event>0) {wp_delete_term($event,'evento');}
        }
    }

    public function testReserveFirstWorkerAttemptsGuestLockBeforeReservationCommit(): void
    {
        global $wpdb;
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        if (!taxonomy_exists('evento')) {(new \WEM_Taxonomy_Manager())->register_taxonomy();}
        if (!post_type_exists('invitado')) {(new \WEM_CPT_Manager())->register_cpt();}
        self::assertSame(0, (int)$wpdb->get_var('SELECT @@in_transaction'));

        $guest=$event=$table=$admin=0;
        $reservationWorker=null; $revokeWorker=null; $reservationPipes=[]; $revokePipes=[];
        $reservationReady=null; $revokeReady=null; $gateOpen=false;
        try {
            $term=wp_insert_term('T5-two-orders-' . wp_generate_uuid4(), 'evento');
            self::assertFalse(is_wp_error($term));
            $event=(int)$term['term_id'];
            $guest=(int)wp_insert_post(['post_type'=>'invitado','post_status'=>'publish','post_title'=>'T5-' . wp_generate_uuid4()]);
            self::assertGreaterThan(0,$guest);
            wp_set_object_terms($guest,[$event],'evento',false);
            self::assertSame(1,$wpdb->insert(\WEM_Event_Table_Schema::table_name(),[
                'event_term_id'=>$event,'table_code'=>'T5-' . wp_generate_uuid4(),'capacity'=>1
            ]));
            $table=(int)$wpdb->insert_id;
            $admin=(int)wp_create_user('wem-t5-' . wp_generate_uuid4(),wp_generate_password(32),'wem-t5-' . wp_generate_uuid4() . '@example.invalid');
            self::assertGreaterThan(0,$admin);
            (new \WP_User($admin))->set_role('administrator');
            $bearer=(new \WEM_Invitation_Credential_Service())->issue($guest,$admin)['token'];

            $reservationReady=tempnam(sys_get_temp_dir(),'wem-seat-');
            $revokeReady=tempnam(sys_get_temp_dir(),'wem-revoke-');
            self::assertNotFalse($reservationReady);
            self::assertNotFalse($revokeReady);
            self::assertNotFalse($wpdb->query('START TRANSACTION'));
            $gateOpen=true;
            $locked=$wpdb->get_var($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE ID=%d FOR UPDATE",$guest
            ));
            self::assertSame($guest,(int)$locked);
            $reservationWorker=proc_open(
                [PHP_BINARY,__DIR__ . '/helpers/reserve-worker.php',$bearer,(string)$table,$reservationReady],
                [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$reservationPipes
            );
            self::assertIsResource($reservationWorker);
            fclose($reservationPipes[0]);

            // Readiness is NOT the evidence. Observe the exact worker connection
            // in InnoDB LOCK WAIT, while coordinator owns canonical guest lock.
            $waitForLock=static function ($path) use ($wpdb): int {
                $deadline=microtime(true)+8.0; $thread=0; $lastState=null; $queryError=''; $lastStage='';
                do {
                    $notice=trim((string)file_get_contents($path));
                    $lastStage=$notice;
                    if (strpos($notice, 'PREFLIGHT_DENIED:') === 0) {
                        return -1;
                    }
                    if (preg_match('/^(?:READY|PREFLIGHT_OK|PREFLIGHT_DENIED):(\d+)$/',$notice,$found)) {
                        $thread=(int)$found[1];
                        $state=$wpdb->get_var($wpdb->prepare(
                            "SELECT trx_state FROM information_schema.INNODB_TRX WHERE trx_mysql_thread_id=%d",
                            $thread
                        ));
                        $lastState=$state;
                        $queryError=(string)$wpdb->last_error;
                        if ($state==='LOCK WAIT') {return $thread;}
                    }
                    usleep(10000); // polling interval only, never evidence of ordering
                } while (microtime(true)<$deadline);
                self::fail('Worker ' . $thread . ' never entered InnoDB LOCK WAIT; stage=' . $lastStage . '; observed_state=' . var_export($lastState,true) . '; query_error=' . $queryError . '; innodb_trx_visible=' . (string)$wpdb->get_var('SELECT COUNT(*) FROM information_schema.INNODB_TRX') . '; mysql_errno=' . ($wpdb->dbh instanceof \mysqli ? $wpdb->dbh->errno : 'unknown') . '.');
            };
            if (trim((string)file_get_contents($reservationReady)) === 'PREFLIGHT_DENIED') {
                self::fail('Reserve worker denied preflight before I2.');
            }
            $reserveThread=$waitForLock($reservationReady);
            if ($reserveThread === -1) {
                $diagnostic = stream_get_contents($reservationPipes[2]);
                self::fail('Reserve worker preflight failed: ' . $diagnostic);
            }
            $revokeWorker=proc_open(
                [PHP_BINARY,__DIR__ . '/helpers/revoke-worker.php',
                    (string)$guest,(string)$admin,$revokeReady,'session'],
                [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$revokePipes
            );
            self::assertIsResource($revokeWorker);
            fclose($revokePipes[0]);
            $revokeThread=$waitForLock($revokeReady);
            self::assertNotSame($reserveThread,$revokeThread);
            self::assertNotSame((int)$wpdb->get_var('SELECT CONNECTION_ID()'),$reserveThread);
            self::assertSame(0,(int)$wpdb->get_var($wpdb->prepare(
                'SELECT COUNT(*) FROM ' . \WEM_Table_Reservation_Schema::table_name() . ' WHERE guest_id=%d',$guest
            )));
            self::assertNotFalse($wpdb->query('COMMIT'));
            $gateOpen=false;
            $finish=static function (&$proc, &$pipes, $label): string {
                $deadline=microtime(true)+8.0;
                while (is_resource($proc) && proc_get_status($proc)['running']) {
                    if (microtime(true)>=$deadline) {
                        proc_terminate($proc);
                        self::fail($label . ' failed to finish after releasing guest lock.');
                    }
                    usleep(10000);
                }
                $out=stream_get_contents($pipes[1]); $error=stream_get_contents($pipes[2]);
                fclose($pipes[1]); fclose($pipes[2]);
                $code=proc_close($proc); $proc=null;
                // proc_get_status may reap the process exit code in some PHP versions.
                self::assertSame('',trim($error),$label . ' stderr: ' . $error);
                self::assertContains($code,[0,-1],$label . ' exited abnormally.');
                return trim((string)$out);
            };
            $seatResult=$finish($reservationWorker,$reservationPipes,'reserve worker');
            $revokeResult=$finish($revokeWorker,$revokePipes,'revoke worker');
            self::assertStringStartsWith('RESERVED:',$seatResult);
            self::assertSame('REVOKED',$revokeResult);
            $reservationTable=\WEM_Table_Reservation_Schema::table_name();
            self::assertSame(1,(int)$wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$reservationTable} WHERE guest_id=%d AND table_id=%d AND status='active' AND active_slot=1",
                $guest,$table
            )));
            self::assertSame(1,(int)$wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$reservationTable} WHERE table_id=%d AND status='active' AND active_slot=1",$table
            )));
            $credentialTable=\WEM_Invitation_Credential_Schema::table_name();
            $credential=$wpdb->get_row($wpdb->prepare(
                "SELECT status,active_slot FROM {$credentialTable} WHERE token_digest=%s",
                hash('sha256',$bearer)
            ),ARRAY_A);
            self::assertSame('revoked',$credential['status']);
            self::assertNull($credential['active_slot']);
            self::assertNull((new \WEM_Seating_Bearer_Authorization())->resolve($bearer));
        } finally {
            if ($gateOpen) {$wpdb->query('ROLLBACK');}
            foreach ([$reservationWorker,$revokeWorker] as $proc) {
                if (is_resource($proc)) {proc_terminate($proc);proc_close($proc);}
            }
            foreach ([$reservationPipes,$revokePipes] as $set) {
                foreach ($set as $pipe) {if (is_resource($pipe)) {fclose($pipe);}}
            }
            foreach ([$reservationReady,$revokeReady] as $ready) {
                if (is_string($ready) && is_file($ready)) {unlink($ready);}
            }
            if ($guest>0) {
                $wpdb->delete(\WEM_Table_Reservation_Schema::table_name(),['guest_id'=>$guest]);
                $wpdb->delete(\WEM_Invitation_Credential_Schema::table_name(),['guest_id'=>$guest]);
            }
            if ($table>0) {$wpdb->delete(\WEM_Event_Table_Schema::table_name(),['id'=>$table]);}
            if ($guest>0) {wp_delete_post($guest,true);}
            if ($admin>0) {wp_delete_user($admin);}
            if ($event>0) {wp_delete_term($event,'evento');}
        }
    }
}
