<?php

declare(strict_types=1);

namespace WEM\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;

final class InvitationBulkPrivateExportTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        require_once dirname(__DIR__, 2) . '/includes/class-invitation-private-csv.php';
        require_once dirname(__DIR__, 2) . '/includes/class-invitation-private-csv-storage.php';
        require_once dirname(__DIR__, 2) . '/includes/class-invitation-bulk-private-export.php';
        $this->directory = sys_get_temp_dir() . '/wem73-export-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->directory, 0700));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }
    }

    public function testReconcilesEveryCredentialBeforeWritingCompletePrivateCsv(): void
    {
        self::assertTrue(class_exists('WEM_Invitation_Bulk_Private_Export'));
        $storage = new \WEM_Invitation_Private_Csv_Storage($this->directory);
        $export = new \WEM_Invitation_Bulk_Private_Export(
            new \WEM_Invitation_Private_Csv(),
            $storage,
            static fn (array $record): bool => true
        );
        $records = [
            ['guest_id' => 101, 'guest_name' => '=HYPERLINK(1)', 'event_id' => 7,
             'invitation_url' => 'https://example.test/?wem_invitation=' . str_repeat('A', 43)],
            ['guest_id' => 102, 'guest_name' => 'Guest 102', 'event_id' => 7,
             'invitation_url' => 'https://example.test/?wem_invitation=' . str_repeat('B', 43)],
        ];
        $export->save('run-1', $records);
        $csv = $storage->read('run-1');
        self::assertIsString($csv);
        self::assertCount(3, array_filter(explode("\n", trim($csv))));
        self::assertStringContainsString("'=HYPERLINK(1)", $csv);
        self::assertSame(0600, fileperms((glob($this->directory . '/*.csv') ?: [])[0]) & 0777);
    }

    public function testUnreconciledGuestBlocksAnyCsvPublication(): void
    {
        $storage = new \WEM_Invitation_Private_Csv_Storage($this->directory);
        $export = new \WEM_Invitation_Bulk_Private_Export(
            new \WEM_Invitation_Private_Csv(),
            $storage,
            static fn (array $record): bool => ($record['guest_id'] ?? null) !== 102
        );
        try {
            $export->save('run-2', [
                [
                    'guest_id' => 101,
                    'guest_name' => 'One',
                    'event_id' => 7,
                    'invitation_url' => 'https://example.test/one',
                ],
                [
                    'guest_id' => 102,
                    'guest_name' => 'Two',
                    'event_id' => 7,
                    'invitation_url' => 'https://example.test/two',
                ],
            ]);
            self::fail('A failed final reconciliation must deny publication.');
        } catch (RuntimeException $error) {
            self::assertNull($storage->read('run-2'));
        }
    }
}
