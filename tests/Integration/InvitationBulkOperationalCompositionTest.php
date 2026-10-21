<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use RuntimeException;
use WP_UnitTestCase;

/** Production CLI composition must reject unauthorized actors before writing. */
final class InvitationBulkOperationalCompositionTest extends WP_UnitTestCase
{
    private string $private;

    protected function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        $this->private = sys_get_temp_dir() . '/wem73-operational-' . bin2hex(random_bytes(8));
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

    public function testOperationalFactoryRejectsUnprivilegedActorBeforeCreatingBatchArtifacts(): void
    {
        self::assertTrue(class_exists('WEM_Invitation_Bulk_Operational_Cli'));
        $subscriber = self::factory()->user->create(['role' => 'subscriber']);
        $event = self::factory()->term->create(['taxonomy' => 'evento', 'name' => 'WEM73 Operations']);
        $guest = self::factory()->post->create([
            'post_type' => 'invitado', 'post_status' => 'publish',
        ]);
        wp_set_object_terms($guest, [$event], 'evento');

        $cli = new \WEM_Invitation_Bulk_Operational_Cli();
        try {
            $cli->execute([
                'actor' => $subscriber,
                'event' => $event,
                'operation' => 'deny-operation',
                'private_dir' => $this->private,
            ]);
            self::fail('Unprivileged actor must not start a bulk operation.');
        } catch (RuntimeException $error) {
            self::assertNotSame('', $error->getMessage());
        }
        self::assertSame([], glob($this->private . '/*') ?: []);
    }
}
