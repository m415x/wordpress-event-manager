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

    public function testMovementServiceDoesNotCommitOuterWordPressTestTransaction(): void
    {
        global $wpdb;
        $event = wp_insert_term('WEM harness probe movement ' . wp_generate_uuid4(), 'evento');
        self::assertNotWPError($event);
        $eventId = (int) $event['term_id'];
        $guestId = self::factory()->post->create(array('post_type' => 'invitado'));
        $actorId = self::factory()->user->create(array('role' => 'subscriber'));
        wp_set_object_terms($guestId, array($eventId), 'evento', false);

        try {
            self::assertSame('1', (string) $wpdb->get_var('SELECT @@in_transaction'));
            (new \WEM_Movement_Service())->checkin($guestId, $eventId, $actorId, 'staff_web');
            $after = (string) $wpdb->get_var('SELECT @@in_transaction');
        } finally {
            // The service COMMIT may have persisted factory records too.
            $wpdb->query('ROLLBACK');
            $wpdb->delete(\WEM_Movement_Schema::table_name(), array('guest_id' => $guestId), array('%d'));
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
        $guestId = self::factory()->post->create(array('post_type' => 'invitado'));
        $actorId = self::factory()->user->create(array('role' => 'administrator'));
        $credentialTable = \WEM_Invitation_Credential_Schema::table_name();

        try {
            self::assertSame('1', (string) $wpdb->get_var('SELECT @@in_transaction'));
            $issued = (new \WEM_Invitation_Credential_Service())->issue($guestId, $actorId);
            self::assertSame(1, (int) $issued['generation']);
            $after = (string) $wpdb->get_var('SELECT @@in_transaction');
        } finally {
            $wpdb->query('ROLLBACK');
            $wpdb->delete($credentialTable, array('guest_id' => $guestId), array('%d'));
            wp_delete_post($guestId, true);
            require_once ABSPATH . 'wp-admin/includes/user.php';
            wp_delete_user($actorId);
        }

        self::assertSame('1', $after, 'Credential service committed the outer PHPUnit transaction.');
    }
}
