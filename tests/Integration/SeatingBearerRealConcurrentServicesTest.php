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
}
