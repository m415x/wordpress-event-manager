<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

/** WEM-73 P0: CLI-only retrieval. Never print bearer or overwrite target. */
final class InvitationBulkPrivateRetrievalCliTest extends WP_UnitTestCase
{
    private string $directory;
    private string $output;
    private string $outputDirectory;

    protected function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        $this->directory = sys_get_temp_dir() . '/wem73-retrieval-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->directory, 0700));
        $this->outputDirectory = sys_get_temp_dir() . '/wem73-delivery-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->outputDirectory, 0700));
        $this->output = $this->outputDirectory . '/invitations.csv';
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        if (is_file($this->output)) {
            unlink($this->output);
        }
        rmdir($this->directory);
        rmdir($this->outputDirectory);
        parent::tearDown();
    }

    public function testAuthorizedOperatorCanCopyImmutablePrivateCsvWithoutStdoutOrOverwrite(): void
    {
        $actor = self::factory()->user->create(['role' => 'administrator']);
        $csv = "guest_id,guest_name,event_id,invitation_url\n42,Guest,7,https://example.org/?wem_invitation=secret\n";
        (new \WEM_Invitation_Private_Csv_Storage($this->directory))->save('operation-one', $csv);
        $handler = (new \WEM_Invitation_Bulk_Wp_Cli_Composition())->handler();

        ob_start();
        try {
            $handler->retrieve([], [
                'actor' => (string) $actor,
                'operation' => 'operation-one',
                'private-dir' => $this->directory,
                'output' => $this->output,
            ]);
        } finally {
            $stdout = ob_get_clean();
        }
        self::assertSame('', $stdout);
        self::assertSame($csv, file_get_contents($this->output));
        self::assertSame(0, fileperms($this->output) & 0077);

        $this->expectException(\RuntimeException::class);
        $handler->retrieve([], [
            'actor' => (string) $actor,
            'operation' => 'operation-one',
            'private-dir' => $this->directory,
            'output' => $this->output,
        ]);
    }

    public function testSubscriberCannotRetrieveExistingPrivateCsv(): void
    {
        $actor = self::factory()->user->create(['role' => 'subscriber']);
        (new \WEM_Invitation_Private_Csv_Storage($this->directory))->save('operation-two', "private\n");
        $handler = (new \WEM_Invitation_Bulk_Wp_Cli_Composition())->handler();
        $this->expectException(\RuntimeException::class);
        $handler->retrieve([], [
            'actor' => (string) $actor,
            'operation' => 'operation-two',
            'private-dir' => $this->directory,
            'output' => $this->output,
        ]);
    }
}
