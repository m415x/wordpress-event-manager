<?php
declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

/**
 * WEM-73 P0 volume acceptance. Use ONLY the disposable PHPUnit environment.
 * This test creates 1,000 synthetic guests, never customer records.
 *
 * @group wem73-volume
 */
final class InvitationBulkOperationalThousandGuestsTest extends WP_UnitTestCase
{
    public function testOneThousandGuestsGenerateExactlyOnePrivateCsvWithoutDuplicateCredentials(): void
    {
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        \WEM_Invitation_Credential_Schema::install();
        (new \WEM_Taxonomy_Manager())->register_taxonomy();

        global $wpdb;
        $table = \WEM_Invitation_Credential_Schema::table_name();
        $maxIssued = (int) $wpdb->get_var("SELECT COALESCE(MAX(guest_id), 0) FROM {$table}");
        $maxPost = (int) $wpdb->get_var("SELECT COALESCE(MAX(ID), 0) FROM {$wpdb->posts}");
        $reservedId = max($maxIssued, $maxPost) + 200;
        $reserved = wp_insert_post([
            'import_id' => $reservedId,
            'post_type' => 'post',
            'post_status' => 'draft',
            'post_title' => 'WEM73 volume ID reservation',
        ], true);
        self::assertFalse(is_wp_error($reserved));
        self::assertSame($reservedId, (int) $reserved);

        $admin = self::factory()->user->create(['role' => 'administrator']);
        $event = self::factory()->term->create([
            'taxonomy' => 'evento',
            'name' => 'WEM73 volume ' . bin2hex(random_bytes(6)),
        ]);
        $ids = [];
        for ($n = 0; $n < 1000; $n++) {
            $id = self::factory()->post->create([
                'post_type' => 'invitado',
                'post_status' => 'publish',
                'post_title' => sprintf('WEM73 synthetic %04d', $n),
            ]);
            wp_set_object_terms($id, [$event], 'evento');
            $ids[] = (int) $id;
        }
        self::assertCount(1000, array_unique($ids));
        self::assertSame(0, (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE guest_id BETWEEN %d AND %d",
            min($ids), max($ids)
        )));
        self::assertCount(1000, (new \WEM_Invitation_Bulk_Event_Discovery())->guests($event));

        $directory = sys_get_temp_dir() . '/wem73-volume-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($directory, 0700));
        $operation = 'volume-' . bin2hex(random_bytes(6));
        $started = microtime(true);
        try {
            ob_start();
            try {
                (new \WEM_Invitation_Bulk_Operational_Cli())->execute([
                    'actor' => $admin,
                    'event' => $event,
                    'operation' => $operation,
                    'private_dir' => $directory,
                ]);
            } finally {
                $stdout = ob_get_clean();
            }
            self::assertSame('', $stdout, 'Invitation URLs cannot be logged to standard output.');
            $elapsed = microtime(true) - $started;
            $csv = (new \WEM_Invitation_Private_Csv_Storage($directory))->read($operation);
            self::assertIsString($csv);
            $lines = array_filter(explode("\n", trim($csv)));
            self::assertCount(1001, $lines);
            self::assertSame(1, count(glob($directory . '/*.csv') ?: []));
            $seenIds = [];
            $seenUrls = [];
            foreach (array_slice($lines, 1) as $line) {
                $row = str_getcsv($line);
                self::assertCount(4, $row);
                self::assertSame((string) $event, $row[2]);
                self::assertStringContainsString('wem_invitation=', $row[3]);
                $seenIds[] = (int) $row[0];
                $seenUrls[] = $row[3];
            }
            self::assertCount(1000, array_unique($seenIds));
            self::assertCount(1000, array_unique($seenUrls));
            sort($seenIds);
            sort($ids);
            self::assertSame($ids, $seenIds);
            $count = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE guest_id BETWEEN %d AND %d",
                min($ids), max($ids)
            ));
            self::assertSame(1000, $count);
            // Metrics are counts and elapsed seconds ONLY, never bearer values.
            fwrite(STDERR, sprintf("WEM73 VOLUME: guests=1000 credentials=%d urls=%d elapsed_s=%.2f\n",
                $count, count($seenUrls), $elapsed));
        } finally {
            foreach (glob($directory . '/*') ?: [] as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
            @rmdir($directory);
        }
    }
}
