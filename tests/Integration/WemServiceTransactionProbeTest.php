<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

/**
 * Diagnostic only. These assertions are deliberately RED until transactional
 * service tests use an isolated connection/database instead of relying on
 * WP_UnitTestCase's outer transaction.
 *
 * Fixtures are individually removed in finally; no historic rows are touched.
 */
final class WemServiceTransactionProbeTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        if (!post_type_exists('invitado')) {
            register_post_type('invitado', array('public' => false));
        }
        if (!taxonomy_exists('evento')) {
            register_taxonomy('evento', 'invitado');
        }
    }

    private function createUnoccupiedGuest(): int
    {
        global $wpdb;
        $movementTable = \WEM_Movement_Schema::table_name();
        $credentialTable = \WEM_Invitation_Credential_Schema::table_name();
        $max = (int) $wpdb->get_var("SELECT GREATEST(\n            COALESCE((SELECT MAX(guest_id) FROM {$movementTable}), 0),\n            COALESCE((SELECT MAX(guest_id) FROM {$credentialTable}), 0),\n            COALESCE((SELECT MAX(ID) FROM {$wpdb->posts}), 0)\n        )");
        $guestId = wp_insert_post(array(
            'import_id' => $max + 10000,
            'post_type' => 'invitado',
            'post_status' => 'publish',
            'post_title' => 'WEM isolated transaction probe',
        ), true);
        self::assertNotWPError($guestId);
        self::assertSame($max + 10000, (int) $guestId);
        return (int) $guestId;
    }

    public function testMovementServiceDoesNotCommitOuterWordPressTestTransaction(): void
    {
        global $wpdb;
        $event = wp_insert_term('WEM harness probe movement ' . wp_generate_uuid4(), 'evento');
        self::assertNotWPError($event);
        $eventId = (int) $event['term_id'];
        $guestId = $this->createUnoccupiedGuest();
        $actorId = self::factory()->user->create(array('role' => 'subscriber'));
        wp_set_object_terms($guestId, array($eventId), 'evento', false);
        $movementId = null;
        $preexisting = (new \WEM_Movement_Ledger())->find_by_guest_event($guestId, $eventId);

        try {
            self::assertSame(array(), $preexisting, 'Probe requires an unoccupied guest/event pair.');
            self::assertSame('1', (string) $wpdb->get_var('SELECT @@in_transaction'));
            $result = (new \WEM_Movement_Service())->checkin($guestId, $eventId, $actorId, 'staff_web');
            $movementId = $result['movement']['movement_id'];
            $after = (string) $wpdb->get_var('SELECT @@in_transaction');
        } finally {
            // The service COMMIT may have persisted factory records too.
            $wpdb->query('ROLLBACK');
            if ($movementId !== null) {
                $wpdb->delete(\WEM_Movement_Schema::table_name(), array('movement_id' => $movementId), array('%s'));
            }
            wp_delete_post($guestId, true);
            wp_delete_term($eventId, 'evento');
            require_once ABSPATH . 'wp-admin/includes/user.php';
            wp_delete_user($actorId);
        }

        self::assertSame('1', $after, 'Movement service committed the outer PHPUnit transaction.');
    }

    public function testCredentialServiceDoesNotCommitOuterWordPressTestTransaction(): void
    {
        global $wpdb;
        $guestId = $this->createUnoccupiedGuest();
        $actorId = self::factory()->user->create(array('role' => 'administrator'));
        $credentialTable = \WEM_Invitation_Credential_Schema::table_name();
        $issuedDigest = null;

        try {
            $existing = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$credentialTable} WHERE guest_id = %d", $guestId));
            self::assertSame(0, $existing, 'Probe must not issue against a guest with historical credentials.');
            self::assertSame('1', (string) $wpdb->get_var('SELECT @@in_transaction'));
            $issued = (new \WEM_Invitation_Credential_Service())->issue($guestId, $actorId);
            self::assertSame(1, (int) $issued['generation']);
            $issuedDigest = hash('sha256', $issued['token']);
            $after = (string) $wpdb->get_var('SELECT @@in_transaction');
        } finally {
            $wpdb->query('ROLLBACK');
            if ($issuedDigest !== null) {
                $wpdb->delete($credentialTable, array('token_digest' => $issuedDigest), array('%s'));
            }
            wp_delete_post($guestId, true);
            require_once ABSPATH . 'wp-admin/includes/user.php';
            wp_delete_user($actorId);
        }

        self::assertSame('1', $after, 'Credential service committed the outer PHPUnit transaction.');
    }
}
