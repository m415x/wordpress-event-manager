<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

final class InvitationBulkCsvPublicationIntegrationTest extends WP_UnitTestCase
{
    private string $private;

    protected function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        $this->private = sys_get_temp_dir() . '/wem73-csv-publish-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->private, 0700));
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

    public function testPublishesCompletePrivateCsvAndRejectsUnreconciledBatch(): void
    {
        $event = self::factory()->term->create(['taxonomy' => 'evento', 'name' => 'WEM73 CSV Publishing']);
        $guest = self::factory()->post->create([
            'post_type' => 'invitado', 'post_status' => 'publish', 'post_title' => 'Invitado test'
        ]);
        wp_set_object_terms($guest, [$event], 'evento');

        self::assertTrue(class_exists('WEM_Invitation_Bulk_Csv_Publication'));
        $storage = new \WEM_Invitation_Private_Csv_Storage($this->private);
        $record = ['guest_id' => $guest, 'event_id' => $event, 'generation' => 1,
            'token' => str_repeat('A', 43)];

        $publication = new \WEM_Invitation_Bulk_Csv_Publication(
            new \WEM_Invitation_Bulk_Csv_Rows(static fn (array $r): bool => true),
            new \WEM_Invitation_Bulk_Private_Export(
                new \WEM_Invitation_Private_Csv(),
                $storage,
                static fn (array $row): bool => true
            )
        );
        $publication->save('publish-1', $event, [$record]);
        $saved = $storage->read('publish-1');
        self::assertIsString($saved);
        self::assertStringContainsString('Invitado test', $saved);
        self::assertStringContainsString(str_repeat('A', 43), $saved);

        $denied = new \WEM_Invitation_Bulk_Csv_Publication(
            new \WEM_Invitation_Bulk_Csv_Rows(static fn (array $r): bool => false),
            new \WEM_Invitation_Bulk_Private_Export(
                new \WEM_Invitation_Private_Csv(),
                $storage,
                static fn (array $row): bool => true
            )
        );
        try {
            $denied->save('publish-denied', $event, [$record]);
            self::fail('Unreconciled custody must prevent CSV publication.');
        } catch (\RuntimeException $error) {
            self::assertNull($storage->read('publish-denied'));
        }
    }
}
