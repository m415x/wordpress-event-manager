<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use RuntimeException;
use WP_UnitTestCase;

/**
 * WEM-73: custody failure must precede and prevent credential commit.
 * Run against an isolated disposable database (WEM-72 harness limitation).
 */
final class InvitationCustodyBeforeCommitTest extends WP_UnitTestCase
{
    public function testFailedCustodyDoesNotPersistActiveCredential(): void
    {
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        \WEM_Invitation_Credential_Schema::install();

        $adminId = self::factory()->user->create(['role' => 'administrator']);
        $guestId = self::factory()->post->create([
            'post_type' => 'invitado',
            'post_status' => 'publish',
            'post_title' => 'WEM-73-CUSTODY-FAIL',
        ]);

        $called = false;
        $custody = static function (array $record) use (&$called, $guestId): void {
            $called = true;
            self::assertSame($guestId, $record['guest_id']);
            self::assertSame(1, $record['generation']);
            self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $record['token']);
            self::assertSame(hash('sha256', $record['token']), $record['token_digest']);
            throw new RuntimeException('simulated private storage failure');
        };

        $service = new \WEM_Invitation_Credential_Service();
        try {
            $service->issue($guestId, $adminId, $custody);
            self::fail('Custody failure must prevent issuing a credential.');
        } catch (RuntimeException $error) {
            self::assertSame('simulated private storage failure', $error->getMessage());
        }

        self::assertTrue($called);
        global $wpdb;
        $table = \WEM_Invitation_Credential_Schema::table_name();
        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE guest_id = %d",
            $guestId
        ));
        self::assertSame(0, $count, 'A custody failure must roll back the inserted credential.');
    }
}
