<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

final class InvitationBulkOperationalAuthorizedIntegrationTest extends WP_UnitTestCase
{
    private string $private;

    protected function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        $this->private = sys_get_temp_dir() . '/wem73-cli-authorized-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->private, 0700));
        \WEM_Invitation_Credential_Schema::install();
        (new \WEM_Taxonomy_Manager())->register_taxonomy();
    }

    protected function tearDown(): void
    {
        foreach (glob($this->private . '/*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        if (is_dir($this->private)) {
            rmdir($this->private);
        }
        parent::tearDown();
    }

    public function testAuthorizedOperationalCliPublishesWholeEventExactlyOnce(): void
    {
        global $wpdb;
        $table = \WEM_Invitation_Credential_Schema::table_name();
        $admin = self::factory()->user->create(['role' => 'administrator']);
        $event = self::factory()->term->create(['taxonomy' => 'evento', 'name' => 'WEM73 Authorized Batch']);
        $guests = [];
        for ($attempt = 0; $attempt < 1000 && count($guests) < 2; $attempt++) {
            $id = self::factory()->post->create([
                'post_type' => 'invitado',
                'post_status' => 'publish',
                'post_title' => 'WEM73 Operational ' . $attempt,
            ]);
            if (
                (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table} WHERE guest_id = %d",
                    $id
                )) === 0
            ) {
                wp_set_object_terms($id, [$event], 'evento');
                $guests[] = $id;
            }
        }
        self::assertCount(2, $guests);

        $request = [
            'actor' => $admin,
            'event' => $event,
            'operation' => 'operational-authorized',
            'private_dir' => $this->private,
        ];
        $cli = new \WEM_Invitation_Bulk_Operational_Cli();
        $cli->execute($request);

        $csv = (new \WEM_Invitation_Private_Csv_Storage($this->private))
            ->read('operational-authorized');
        self::assertIsString($csv);
        self::assertCount(3, array_filter(explode("\n", trim($csv))));
        foreach ($guests as $guest) {
            self::assertSame(1, (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE guest_id = %d",
                $guest
            )));
        }
        self::assertCount(1, glob($this->private . '/*.csv') ?: []);
    }
}
