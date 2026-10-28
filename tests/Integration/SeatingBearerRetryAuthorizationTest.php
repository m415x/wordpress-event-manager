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
final class SeatingBearerRetryAuthorizationTest extends TestCase
{
    public function testAuthorizationIsRecheckedOnEveryTransactionalRetry(): void
    {
        global $wpdb;
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        if (!taxonomy_exists('evento')) {
            (new \WEM_Taxonomy_Manager())->register_taxonomy();
        }
        if (!post_type_exists('invitado')) {
            (new \WEM_CPT_Manager())->register_cpt();
        }

        self::assertSame(0, (int) $wpdb->get_var('SELECT @@in_transaction'), 'I2 requires an idle connection; do not use WP_UnitTestCase.');

        $guestId = 0;
        $eventId = 0;
        $tableId = 0;

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

            $attempts = 0;
            $reservationId = (new \WEM_Seating_Reservation_Service())->reserve_initial(
                $guestId, $tableId,
                static function ($lockedGuestId) use ($guestId, &$attempts): void {
                    self::assertSame($guestId, (int)$lockedGuestId);
                    self::assertSame(1, (int)$GLOBALS['wpdb']->get_var('SELECT @@in_transaction'));
                    ++$attempts;
                    if ($attempts === 1) {
                        throw new \WEM_Seating_Transient_Lock_Exception('Injected transient abort before any write.');
                    }
                }
            );
            self::assertSame(2, $attempts, 'Post-lock authorization callback must rerun after rollback.');
            self::assertGreaterThan(0, $reservationId);
            self::assertSame(1, (int)$wpdb->get_var($wpdb->prepare(
                'SELECT COUNT(*) FROM ' . \WEM_Table_Reservation_Schema::table_name() .
                ' WHERE guest_id=%d AND status=%s AND active_slot=1', $guestId, 'active'
            )));
        } finally {
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
