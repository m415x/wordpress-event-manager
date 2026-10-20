<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

/**
 * Run alone on the disposable PHPUnit environment due to WEM-72.
 */
final class InvitationCustodyDatabaseReconciliationTest extends WP_UnitTestCase
{
    public function testExportRequiresConfirmedActiveCredentialAndExactEvent(): void
    {
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        \WEM_Invitation_Credential_Schema::install();

        $admin = self::factory()->user->create(['role' => 'administrator']);
        $event = self::factory()->term->create(['taxonomy' => 'evento', 'name' => 'WEM-73 synthetic event']);
        // WEM-72: older credential rows can survive the PHPUnit rollback and
        // collide with newly recycled wp_posts IDs. Do not delete those rows.
        global $wpdb;
        $credentialTable = \WEM_Invitation_Credential_Schema::table_name();
        $guest = 0;
        for ($attempt = 0; $attempt < 1000; $attempt++) {
            $candidate = self::factory()->post->create([
                'post_type' => 'invitado',
                'post_status' => 'publish',
            ]);
            $existing = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$credentialTable} WHERE guest_id = %d",
                $candidate
            ));
            if ($existing === 0) {
                $guest = $candidate;
                break;
            }
        }
        self::assertGreaterThan(0, $guest, 'Could not allocate an unused synthetic guest ID.');
        wp_set_object_terms($guest, [$event], 'evento');
        $issued = (new \WEM_Invitation_Credential_Service())->issue($guest, $admin);
        $custody = [
            'guest_id' => $guest,
            'event_id' => $event,
            'generation' => $issued['generation'],
            'token' => $issued['token'],
            'token_digest' => hash('sha256', $issued['token']),
        ];

        self::assertTrue(class_exists('WEM_Invitation_Custody_Database_Reconciliation'));
        $reader = new \WEM_Invitation_Custody_Database_Reconciliation();
        self::assertTrue($reader->is_exportable($custody));
        self::assertFalse($reader->is_exportable(array_merge($custody, ['event_id' => $event + 1])));
        self::assertFalse($reader->is_exportable(array_merge($custody, ['token_digest' => str_repeat('0', 64)])));

        (new \WEM_Invitation_Credential_Service())->revoke($guest, $admin);
        self::assertFalse($reader->is_exportable($custody));
    }
}
