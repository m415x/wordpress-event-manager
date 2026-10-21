<?php
declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

/** P0 acceptance: legacy CSV import through private invitation export. */
final class InvitationBulkImportToPrivateUrlsE2ETest extends WP_UnitTestCase
{
    public function testCsvImportThroughIssuedCredentialsToPrivateRetrieval(): void
    {
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        \WEM_Invitation_Credential_Schema::install();
        (new \WEM_Taxonomy_Manager())->register_taxonomy();
        $admin = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($admin);
        $eventName = 'WEM73-E2E-' . bin2hex(random_bytes(4));
        $operation = 'e2e-' . bin2hex(random_bytes(6));
        $directory = sys_get_temp_dir() . '/wem73-e2e-' . bin2hex(random_bytes(8));
        $delivery = sys_get_temp_dir() . '/wem73-e2e-out-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($directory, 0700));
        self::assertTrue(mkdir($delivery, 0700));
        $file = tempnam(sys_get_temp_dir(), 'wem73-import-');
        $target = $delivery . '/urls.csv';
        $previousFiles = $_FILES;
        $previousPost = $_POST;

        try {
            file_put_contents($file, "titulo,nombre,organizacion,mesa,evento,observaciones,checkin\n"
                . "E2E-A-" . $operation . ",Ana,Test,,{$eventName},,0\n"
                . "E2E-B-" . $operation . ",Beto,Test,,{$eventName},,0\n");
            $_FILES['wem_csv'] = ['tmp_name' => $file];
            $_POST = ['wem_do_import' => '1', 'wem_import_nonce' => wp_create_nonce('wem_import_action'), 'separador' => ','];

            // Exercise the existing form importer rather than bypassing it with post factories.
            $import = new \WEM_Import_Export();
            $method = new \ReflectionMethod($import, 'handle_import');
            $method->setAccessible(true);
            ob_start();
            try {
                $method->invoke($import);
            } finally {
                $notice = ob_get_clean();
            }
            self::assertStringContainsString('Nuevos: 2', $notice);
            $term = get_term_by('name', $eventName, 'evento');
            self::assertNotFalse($term);
            $event = (int) $term->term_id;
            $guests = (new \WEM_Invitation_Bulk_Event_Discovery())->guests($event);
            self::assertCount(2, $guests);

            // The credential table can retain IDs across WordPress test transactions.
            // Establish that these imported fixtures are genuinely unissued before
            // diagnosing the production fail-closed classifier.
            global $wpdb;
            $credentialTable = \WEM_Invitation_Credential_Schema::table_name();
            foreach ($guests as $guestId) {
                $priorCount = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$credentialTable} WHERE guest_id = %d",
                    $guestId
                ));
                self::assertSame(
                    0,
                    $priorCount,
                    'E2E synthetic guest ID collides with pre-existing credential history; do not auto-reissue.'
                );
            }

            (new \WEM_Invitation_Bulk_Operational_Cli())->execute([
                'actor' => $admin, 'event' => $event, 'operation' => $operation, 'private_dir' => $directory,
            ]);

            $handler = (new \WEM_Invitation_Bulk_Wp_Cli_Composition())->handler();
            ob_start();
            try {
                $handler->retrieve([], [
                    'actor' => (string) $admin,
                    'operation' => $operation,
                    'private-dir' => $directory,
                    'output' => $target,
                ]);
            } finally {
                $stdout = ob_get_clean();
            }
            self::assertSame('', $stdout);
            $contents = file_get_contents($target);
            self::assertIsString($contents);
            $rows = array_map('str_getcsv', array_filter(explode("\n", trim($contents))));
            self::assertCount(3, $rows);
            self::assertSame(['guest_id', 'guest_name', 'event_id', 'invitation_url'], $rows[0]);
            $seen = [];
            foreach (array_slice($rows, 1) as $row) {
                self::assertCount(4, $row);
                self::assertSame((string) $event, $row[2]);
                self::assertStringContainsString('wem_invitation=', $row[3]);
                $seen[] = (int) $row[0];
            }
            sort($seen);
            sort($guests);
            self::assertSame($guests, $seen);
            self::assertSame(0, fileperms($target) & 0077);
        } finally {
            $_FILES = $previousFiles;
            $_POST = $previousPost;
            @unlink($file);
            @unlink($target);
            @rmdir($delivery);
            foreach (glob($directory . '/*') ?: [] as $item) {
                if (is_file($item)) {
                    @unlink($item);
                }
            }
            @rmdir($directory);
        }
    }
}
