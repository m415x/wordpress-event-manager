<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * WEM-92/T5: deterministic TOCTOU regression.
 * Resolve while active, then commit revocation in an independent InnoDB
 * session before the authoritative guest-locked reservation starts.
 * This is an interleaving test, not proof of simultaneous worker execution.
 */
final class SeatingBearerRealServiceInterleavingTest extends TestCase
{
    public function testSeparateWorkerRealRevokeBetweenBearerResolutionAndI2Lock(): void
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
                    \PHPUnit\Framework\Assert::assertSame("REVOKED\\n", $stdout);
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
}
