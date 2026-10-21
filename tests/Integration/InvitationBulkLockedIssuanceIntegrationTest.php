<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use RuntimeException;
use WP_UnitTestCase;

/** Exercises real WP/MySQL, private files, and the WEM-33 issuer. */
final class InvitationBulkLockedIssuanceIntegrationTest extends WP_UnitTestCase
{
    private string $private;

    protected function setUp(): void
    {
        parent::setUp();
        $this->private = sys_get_temp_dir() . '/wem73-locked-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->private, 0700, true));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->private . '/*') ?: [] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        if (is_dir($this->private)) {
            rmdir($this->private);
        }
        parent::tearDown();
    }

    public function testRealLockedIssuanceCannotReissueOnceStarted(): void
    {
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        \WEM_Invitation_Credential_Schema::install();
        (new \WEM_Taxonomy_Manager())->register_taxonomy();

        $admin = self::factory()->user->create(['role' => 'administrator']);
        $event = self::factory()->term->create(['taxonomy' => 'evento', 'name' => 'WEM73 Locked Issuance']);
        global $wpdb;
        $table = \WEM_Invitation_Credential_Schema::table_name();
        $guest = 0;
        for ($n = 0; $n < 1000; $n++) {
            $candidate = self::factory()->post->create(['post_type' => 'invitado', 'post_status' => 'publish']);
            if (
                (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table} WHERE guest_id = %d",
                    $candidate
                )) === 0
            ) {
                $guest = $candidate;
                break;
            }
        }
        self::assertGreaterThan(0, $guest);
        wp_set_object_terms($guest, [$event], 'evento');

        $operation = 'integration-locked';
        $manifest = new \WEM_Invitation_Bulk_Manifest($this->private);
        $manifest->create($operation, $event, [$guest]);
        $attempts = new \WEM_Invitation_Bulk_Attempt_Storage($this->private);
        $journal = new \WEM_Invitation_Private_Journal($this->private);
        $evidence = new \WEM_Invitation_Bulk_Evidence_Reader($manifest, $attempts, $journal);
        $lock = new \WEM_Invitation_Bulk_Guest_Lock($this->private);
        $bridge = new \WEM_Invitation_Custody_Issuance_Bridge(
            new \WEM_Invitation_Credential_Service(),
            $journal
        );

        $coordinator = new \WEM_Invitation_Bulk_Locked_Issuance(
            static fn (int $id, callable $callback) => $lock->run($id, $callback),
            static fn (int $id): string => $evidence->classify($operation, $id),
            static fn (int $id) => $attempts->mark_started($operation, $id),
            static fn (int $id) => $bridge->issue($id, $admin, $event, $operation)
        );

        $issued = $coordinator->run($guest);
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $issued['token']);
        self::assertSame('reconcile', $evidence->classify($operation, $guest));
        try {
            $coordinator->run($guest);
            self::fail('A completed issuance must never be reissued by resume.');
        } catch (RuntimeException $exception) {
            self::assertNotSame('', $exception->getMessage());
        }
        self::assertSame(1, (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE guest_id = %d",
            $guest
        )));
    }
}
