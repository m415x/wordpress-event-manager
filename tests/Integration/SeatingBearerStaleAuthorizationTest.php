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
final class SeatingBearerStaleAuthorizationTest extends TestCase
{
    public function testStalePreflightIdentityCannotAuthorizeAfterCommittedRevocation(): void
    {
        global $wpdb;
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        if (!taxonomy_exists('evento')) {(new \WEM_Taxonomy_Manager())->register_taxonomy();}
        if (!post_type_exists('invitado')) {(new \WEM_CPT_Manager())->register_cpt();}
        self::assertSame(0, (int) $wpdb->get_var('SELECT @@in_transaction'));

        $guest = $event = $table = $admin = 0;
        $other = null;
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

            // Explicitly demonstrate that an earlier valid bearer resolution
            // does not remain authoritative after another session revokes it.
            self::assertSame(['guest_id'=>$guest,'event_term_id'=>$event],
                (new \WEM_Seating_Bearer_Authorization())->resolve($bearer));

            $host = DB_HOST; $port = 3306;
            if (preg_match('/^([^:]+):(\d+)$/', $host, $m)) {$host=$m[1];$port=(int)$m[2];}
            $other = new \mysqli($host, DB_USER, DB_PASSWORD, DB_NAME, $port);
            self::assertNotSame($wpdb->dbh->thread_id, $other->thread_id);
            self::assertTrue($other->begin_transaction());
            $lock = $other->query('SELECT ID FROM ' . $wpdb->posts . ' WHERE ID=' . $guest . ' FOR UPDATE');
            self::assertInstanceOf(\mysqli_result::class, $lock);
            self::assertSame(1, $lock->num_rows);
            $lock->free();
            $credentials = \WEM_Invitation_Credential_Schema::table_name();
            $stmt = $other->prepare("UPDATE {$credentials} SET status='revoked', active_slot=NULL, invalidated_at=NOW(), invalidated_by_user_id=? WHERE guest_id=? AND token_digest=? AND status='active' AND active_slot=1");
            self::assertInstanceOf(\mysqli_stmt::class, $stmt);
            $digest = hash('sha256', $bearer);
            $stmt->bind_param('iis', $admin, $guest, $digest);
            self::assertTrue($stmt->execute());
            self::assertSame(1, $stmt->affected_rows);
            $stmt->close();
            self::assertTrue($other->commit());

            try {
                (new \WEM_Seating_Bearer_Initial_Reservation())->reserve_for_bearer($bearer, $table);
                self::fail('Stale bearer must not authorize an I2 write.');
            } catch (\DomainException $expected) {
                self::assertSame(0, (int)$wpdb->get_var($wpdb->prepare(
                    'SELECT COUNT(*) FROM ' . \WEM_Table_Reservation_Schema::table_name() . ' WHERE guest_id=%d', $guest
                )));
            }
        } finally {
            if ($other instanceof \mysqli) {$other->rollback();$other->close();}
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
