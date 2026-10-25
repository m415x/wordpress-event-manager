<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * WEM-92: deterministic revocation-first interleaving, two actual InnoDB
 * sessions. Complements the reservation-first guest-lock NOWAIT probe.
 * Not a full concurrent-process scheduler: verifies committed invalidation
 * before the adapter's revalidation and no resulting reservation writes.
 */
final class SeatingBearerRevocationFirstTest extends TestCase
{
    public function testCommittedSecondSessionRevocationDeniesInitialReservation(): void
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

        $guestId = $eventId = $tableId = $adminId = 0;
        $second = null;
        try {
            $term = wp_insert_term('T5-revocation-first-' . wp_generate_uuid4(), 'evento');
            self::assertFalse(is_wp_error($term));
            $eventId = (int) $term['term_id'];
            $guestId = (int) wp_insert_post([
                'post_type' => 'invitado',
                'post_status' => 'publish',
                'post_title' => 'T5-revocation-fixture-' . wp_generate_uuid4(),
            ]);
            self::assertGreaterThan(0, $guestId);
            wp_set_object_terms($guestId, [$eventId], 'evento', false);
            self::assertSame(1, $wpdb->insert(\WEM_Event_Table_Schema::table_name(), [
                'event_term_id' => $eventId,
                'table_code' => 'T5-' . wp_generate_uuid4(),
                'capacity' => 1,
            ]));
            $tableId = (int) $wpdb->insert_id;
            $adminId = (int) wp_create_user('wem-t5-' . wp_generate_uuid4(), wp_generate_password(32), 'wem-t5-' . wp_generate_uuid4() . '@example.invalid');
            self::assertGreaterThan(0, $adminId);
            (new \WP_User($adminId))->set_role('administrator');
            $token = (new \WEM_Invitation_Credential_Service())->issue($guestId, $adminId)['token'];

            $host = DB_HOST;
            $port = 3306;
            if (preg_match('/^([^:]+):(\d+)$/', $host, $matches)) {
                $host = $matches[1];
                $port = (int) $matches[2];
            }
            $second = new \mysqli($host, DB_USER, DB_PASSWORD, DB_NAME, $port);
            self::assertNotSame($wpdb->dbh->thread_id, $second->thread_id);
            self::assertTrue($second->begin_transaction());

            // This reproduces WEM-33's guest -> credential lock order on an
            // independent connection, without modifying the WEM-33 service.
            $locked = $second->query('SELECT ID FROM ' . $wpdb->posts . ' WHERE ID=' . $guestId . ' FOR UPDATE');
            self::assertInstanceOf(\mysqli_result::class, $locked);
            self::assertSame(1, $locked->num_rows);
            $locked->free();

            $credentialTable = \WEM_Invitation_Credential_Schema::table_name();
            $digest = hash('sha256', $token);
            $statement = $second->prepare(
                "UPDATE {$credentialTable} SET status='revoked', active_slot=NULL, invalidated_at=NOW(), invalidated_by_user_id=? WHERE guest_id=? AND token_digest=? AND status='active' AND active_slot=1"
            );
            self::assertInstanceOf(\mysqli_stmt::class, $statement);
            $statement->bind_param('iis', $adminId, $guestId, $digest);
            self::assertTrue($statement->execute());
            self::assertSame(1, $statement->affected_rows);
            $statement->close();
            self::assertTrue($second->commit());

            // A new authorization must reject the original bearer and never
            // write an active seat. Use I2 adapter rather than a raw INSERT.
            try {
                (new \WEM_Seating_Bearer_Initial_Reservation())->reserve_for_bearer($token, $tableId);
                self::fail('Committed revoke must deny the seat reservation.');
            } catch (\DomainException $expected) {
                self::assertSame(0, (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM " . \WEM_Table_Reservation_Schema::table_name() . " WHERE guest_id=%d",
                    $guestId
                )));
            }
        } finally {
            if ($second instanceof \mysqli) {
                $second->rollback();
                $second->close();
            }
            if ($guestId > 0) {
                $wpdb->delete(\WEM_Table_Reservation_Schema::table_name(), ['guest_id' => $guestId]);
                $wpdb->delete(\WEM_Invitation_Credential_Schema::table_name(), ['guest_id' => $guestId]);
            }
            if ($tableId > 0) {
                $wpdb->delete(\WEM_Event_Table_Schema::table_name(), ['id' => $tableId]);
            }
            if ($guestId > 0) {
                wp_delete_post($guestId, true);
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
