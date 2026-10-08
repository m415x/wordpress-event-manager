<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use RuntimeException;
use WP_UnitTestCase;

final class InvitationCredentialLifecycleTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
        \WEM_Invitation_Credential_Schema::install();

        global $wpdb;

        $tableName = \WEM_Invitation_Credential_Schema::table_name();
        $wpdb->query("DELETE FROM {$tableName}");
    }

    public function testIssueCreatesOneOpaqueActiveCredentialWithoutPersistingBearerSecret(): void
    {
        self::assertTrue(
            class_exists('WEM_Invitation_Credential_Service'),
            'WEM-58 requires an explicit invitation credential lifecycle service.'
        );

        $adminId = self::factory()->user->create(['role' => 'administrator']);
        $guestId = self::factory()->post->create([
            'post_type' => 'invitado',
            'post_status' => 'publish',
            'post_title' => 'WEM-58-ISSUE-001',
        ]);

        $service = new \WEM_Invitation_Credential_Service();
        $issued = $service->issue($guestId, $adminId);

        self::assertIsArray($issued);
        self::assertSame(1, $issued['generation']);
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $issued['token']);

        global $wpdb;

        $tableName = \WEM_Invitation_Credential_Schema::table_name();
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT guest_id, generation, token_digest, status, active_slot, issued_by_user_id,
                        invalidated_at, invalidated_by_user_id
                 FROM {$tableName}
                 WHERE guest_id = %d",
                $guestId
            ),
            ARRAY_A
        );

        self::assertCount(1, $rows);
        self::assertSame($guestId, (int) $rows[0]['guest_id']);
        self::assertSame(1, (int) $rows[0]['generation']);
        self::assertSame(hash('sha256', $issued['token']), $rows[0]['token_digest']);
        self::assertSame('active', $rows[0]['status']);
        self::assertSame(1, (int) $rows[0]['active_slot']);
        self::assertSame($adminId, (int) $rows[0]['issued_by_user_id']);
        self::assertNull($rows[0]['invalidated_at']);
        self::assertNull($rows[0]['invalidated_by_user_id']);

        $serializedRow = wp_json_encode($rows[0]);
        self::assertIsString($serializedRow);
        self::assertStringNotContainsString($issued['token'], $serializedRow);

        $this->expectException(RuntimeException::class);
        $service->issue($guestId, $adminId);
    }

    public function testRotateInvalidatesPreviousGenerationAndCreatesNewActiveCredential(): void
    {
        $adminId = self::factory()->user->create(['role' => 'administrator']);
        $guestId = self::factory()->post->create([
            'post_type' => 'invitado',
            'post_status' => 'publish',
            'post_title' => 'WEM-58-ROTATE-001',
        ]);

        $service = new \WEM_Invitation_Credential_Service();
        $first = $service->issue($guestId, $adminId);
        $second = $service->rotate($guestId, $adminId);

        self::assertSame(2, $second['generation']);
        self::assertNotSame($first['token'], $second['token']);

        global $wpdb;

        $tableName = \WEM_Invitation_Credential_Schema::table_name();
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT generation, token_digest, status, active_slot, invalidated_at, invalidated_by_user_id
                 FROM {$tableName}
                 WHERE guest_id = %d
                 ORDER BY generation ASC",
                $guestId
            ),
            ARRAY_A
        );

        self::assertCount(2, $rows);

        self::assertSame(1, (int) $rows[0]['generation']);
        self::assertSame(hash('sha256', $first['token']), $rows[0]['token_digest']);
        self::assertSame('rotated', $rows[0]['status']);
        self::assertNull($rows[0]['active_slot']);
        self::assertNotNull($rows[0]['invalidated_at']);
        self::assertSame($adminId, (int) $rows[0]['invalidated_by_user_id']);

        self::assertSame(2, (int) $rows[1]['generation']);
        self::assertSame(hash('sha256', $second['token']), $rows[1]['token_digest']);
        self::assertSame('active', $rows[1]['status']);
        self::assertSame(1, (int) $rows[1]['active_slot']);
        self::assertNull($rows[1]['invalidated_at']);
        self::assertNull($rows[1]['invalidated_by_user_id']);
    }

    public function testRevokeInvalidatesActiveCredentialWithoutCreatingNewGeneration(): void
    {
        $adminId = self::factory()->user->create(['role' => 'administrator']);
        $guestId = self::factory()->post->create([
            'post_type' => 'invitado',
            'post_status' => 'publish',
            'post_title' => 'WEM-58-REVOKE-001',
        ]);

        $service = new \WEM_Invitation_Credential_Service();
        $issued = $service->issue($guestId, $adminId);

        $service->revoke($guestId, $adminId);

        global $wpdb;

        $tableName = \WEM_Invitation_Credential_Schema::table_name();
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT generation, token_digest, status, active_slot, invalidated_at, invalidated_by_user_id
                 FROM {$tableName}
                 WHERE guest_id = %d
                 ORDER BY generation ASC",
                $guestId
            ),
            ARRAY_A
        );

        self::assertCount(1, $rows);
        self::assertSame(1, (int) $rows[0]['generation']);
        self::assertSame(hash('sha256', $issued['token']), $rows[0]['token_digest']);
        self::assertSame('revoked', $rows[0]['status']);
        self::assertNull($rows[0]['active_slot']);
        self::assertNotNull($rows[0]['invalidated_at']);
        self::assertSame($adminId, (int) $rows[0]['invalidated_by_user_id']);

        $activeCount = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*)
                 FROM {$tableName}
                 WHERE guest_id = %d
                   AND status = %s
                   AND active_slot = %d",
                $guestId,
                'active',
                1
            )
        );

        self::assertSame(0, $activeCount);
    }

    public function testReissueAfterRevokeCreatesNewGenerationAndPreservesHistoricalRow(): void
    {
        $adminId = self::factory()->user->create(['role' => 'administrator']);
        $guestId = self::factory()->post->create([
            'post_type' => 'invitado',
            'post_status' => 'publish',
            'post_title' => 'WEM-58-REISSUE-001',
        ]);

        $service = new \WEM_Invitation_Credential_Service();
        $first = $service->issue($guestId, $adminId);
        $service->revoke($guestId, $adminId);
        $second = $service->reissue($guestId, $adminId);

        self::assertSame(2, $second['generation']);
        self::assertNotSame($first['token'], $second['token']);

        global $wpdb;

        $tableName = \WEM_Invitation_Credential_Schema::table_name();
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT generation, token_digest, status, active_slot, invalidated_at, invalidated_by_user_id
                 FROM {$tableName}
                 WHERE guest_id = %d
                 ORDER BY generation ASC",
                $guestId
            ),
            ARRAY_A
        );

        self::assertCount(2, $rows);

        self::assertSame(1, (int) $rows[0]['generation']);
        self::assertSame(hash('sha256', $first['token']), $rows[0]['token_digest']);
        self::assertSame('revoked', $rows[0]['status']);
        self::assertNull($rows[0]['active_slot']);
        self::assertNotNull($rows[0]['invalidated_at']);
        self::assertSame($adminId, (int) $rows[0]['invalidated_by_user_id']);

        self::assertSame(2, (int) $rows[1]['generation']);
        self::assertSame(hash('sha256', $second['token']), $rows[1]['token_digest']);
        self::assertSame('active', $rows[1]['status']);
        self::assertSame(1, (int) $rows[1]['active_slot']);
        self::assertNull($rows[1]['invalidated_at']);
        self::assertNull($rows[1]['invalidated_by_user_id']);

        $activeCount = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*)
                 FROM {$tableName}
                 WHERE guest_id = %d
                   AND status = %s
                   AND active_slot = %d",
                $guestId,
                'active',
                1
            )
        );

        self::assertSame(1, $activeCount);
    }

    public function testLifecycleFailsClosedOnSemanticStatusActiveSlotDrift(): void
    {
        $adminId = self::factory()->user->create(['role' => 'administrator']);
        $guestId = self::factory()->post->create([
            'post_type' => 'invitado',
            'post_status' => 'publish',
            'post_title' => 'WEM-58-DRIFT-001',
        ]);

        global $wpdb;

        $tableName = \WEM_Invitation_Credential_Schema::table_name();
        $inserted = $wpdb->insert(
            $tableName,
            [
                'guest_id' => $guestId,
                'generation' => 1,
                'token_digest' => str_repeat('f', 64),
                'status' => 'active',
                'active_slot' => null,
                'issued_at' => '2026-10-08 12:00:00',
                'issued_by_user_id' => $adminId,
                'invalidated_at' => null,
                'invalidated_by_user_id' => null,
            ],
            ['%d', '%d', '%s', '%s', '%d', '%s', '%d', '%s', '%d']
        );

        self::assertSame(1, $inserted);

        $service = new \WEM_Invitation_Credential_Service();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invitation credential state is inconsistent.');

        $service->reissue($guestId, $adminId);
    }
}
