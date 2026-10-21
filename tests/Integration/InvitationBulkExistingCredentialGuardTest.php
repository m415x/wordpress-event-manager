<?php
declare(strict_types=1);

namespace WEM\Tests\Integration;

use RuntimeException;
use WP_UnitTestCase;

/** WEM-73 P0: pre-existing WEM-33 credentials cannot be issued again. */
final class InvitationBulkExistingCredentialGuardTest extends WP_UnitTestCase
{
    public function testBulkRefusesPreviouslyIssuedGuestAndDoesNotPublishCsv(): void
    {
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        \WEM_Invitation_Credential_Schema::install();
        (new \WEM_Taxonomy_Manager())->register_taxonomy();

        global $wpdb;
        $table = \WEM_Invitation_Credential_Schema::table_name();
        $maxIssued = (int) $wpdb->get_var("SELECT COALESCE(MAX(guest_id), 0) FROM {$table}");
        $maxPost = (int) $wpdb->get_var("SELECT COALESCE(MAX(ID), 0) FROM {$wpdb->posts}");
        $guestId = max($maxIssued, $maxPost) + 100;
        $guest = wp_insert_post([
            'import_id' => $guestId,
            'post_type' => 'invitado',
            'post_status' => 'publish',
            'post_title' => 'WEM73 Existing Guard',
        ], true);
        self::assertFalse(is_wp_error($guest));
        self::assertSame($guestId, (int) $guest);

        $admin = self::factory()->user->create(['role' => 'administrator']);
        $event = self::factory()->term->create([
            'taxonomy' => 'evento',
            'name' => 'WEM73 Existing Guard ' . bin2hex(random_bytes(4)),
        ]);
        wp_set_object_terms($guestId, [$event], 'evento');
        (new \WEM_Invitation_Credential_Service())->issue($guestId, $admin);
        $before = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE guest_id = %d", $guestId
        ));
        self::assertSame(1, $before);

        $dir = sys_get_temp_dir() . '/wem73-existing-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($dir, 0700));
        try {
            $rejected = false;
            try {
                (new \WEM_Invitation_Bulk_Operational_Cli())->execute([
                    'actor' => $admin, 'event' => $event,
                    'operation' => 'preexisting-guard', 'private_dir' => $dir,
                ]);
            } catch (RuntimeException $error) {
                $rejected = true;
            }
            self::assertTrue($rejected, 'Pre-existing credential must block batch emission.');
            self::assertSame($before, (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE guest_id = %d", $guestId
            )));
            self::assertNull((new \WEM_Invitation_Private_Csv_Storage($dir))->read('preexisting-guard'));
        } finally {
            foreach (glob($dir . '/*') ?: [] as $item) {
                if (is_file($item)) {
                    unlink($item);
                }
            }
            rmdir($dir);
        }
    }
}
