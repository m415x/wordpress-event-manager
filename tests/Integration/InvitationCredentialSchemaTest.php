<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

final class InvitationCredentialSchemaTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
    }

    public function testDedicatedCredentialSchemaEnforcesLookupGenerationAndSingleActiveSlot(): void
    {
        self::assertTrue(
            class_exists('WEM_Invitation_Credential_Schema'),
            'WEM-57 requires a dedicated invitation-credential schema installer.'
        );

        \WEM_Invitation_Credential_Schema::install();
        \WEM_Invitation_Credential_Schema::install();

        global $wpdb;

        $tableName = \WEM_Invitation_Credential_Schema::table_name();
        $actualTable = $wpdb->get_var(
            $wpdb->prepare('SHOW TABLES LIKE %s', $tableName)
        );

        self::assertSame($tableName, $actualTable);

        $columns = $wpdb->get_results("SHOW COLUMNS FROM {$tableName}", ARRAY_A);
        self::assertSame(
            [
                'sequence',
                'guest_id',
                'generation',
                'token_digest',
                'status',
                'active_slot',
                'issued_at',
                'issued_by_user_id',
                'invalidated_at',
                'invalidated_by_user_id',
            ],
            array_column($columns, 'Field')
        );

        $indexes = $wpdb->get_results("SHOW INDEX FROM {$tableName}", ARRAY_A);
        $indexNames = array_values(array_unique(array_column($indexes, 'Key_name')));

        self::assertContains('PRIMARY', $indexNames);
        self::assertContains('token_digest', $indexNames);
        self::assertContains('guest_generation', $indexNames);
        self::assertContains('guest_active_slot', $indexNames);
        self::assertContains('guest_status', $indexNames);

        $now = '2026-10-08 12:00:00';

        $activeInserted = $wpdb->insert(
            $tableName,
            [
                'guest_id' => 701,
                'generation' => 1,
                'token_digest' => str_repeat('a', 64),
                'status' => 'active',
                'active_slot' => 1,
                'issued_at' => $now,
                'issued_by_user_id' => 11,
            ],
            ['%d', '%d', '%s', '%s', '%d', '%s', '%d']
        );
        self::assertSame(1, $activeInserted);

        $secondActive = $wpdb->insert(
            $tableName,
            [
                'guest_id' => 701,
                'generation' => 2,
                'token_digest' => str_repeat('b', 64),
                'status' => 'active',
                'active_slot' => 1,
                'issued_at' => $now,
                'issued_by_user_id' => 11,
            ],
            ['%d', '%d', '%s', '%s', '%d', '%s', '%d']
        );
        self::assertFalse($secondActive, 'The unique guest_active_slot index must reject a second active slot.');

        $historicalOne = $wpdb->insert(
            $tableName,
            [
                'guest_id' => 702,
                'generation' => 1,
                'token_digest' => str_repeat('c', 64),
                'status' => 'rotated',
                'active_slot' => null,
                'issued_at' => $now,
                'issued_by_user_id' => 11,
                'invalidated_at' => $now,
                'invalidated_by_user_id' => 11,
            ],
            ['%d', '%d', '%s', '%s', '%d', '%s', '%d', '%s', '%d']
        );
        $historicalTwo = $wpdb->insert(
            $tableName,
            [
                'guest_id' => 702,
                'generation' => 2,
                'token_digest' => str_repeat('d', 64),
                'status' => 'revoked',
                'active_slot' => null,
                'issued_at' => $now,
                'issued_by_user_id' => 11,
                'invalidated_at' => $now,
                'invalidated_by_user_id' => 11,
            ],
            ['%d', '%d', '%s', '%s', '%d', '%s', '%d', '%s', '%d']
        );

        self::assertSame(1, $historicalOne);
        self::assertSame(1, $historicalTwo);

        $duplicateDigest = $wpdb->insert(
            $tableName,
            [
                'guest_id' => 703,
                'generation' => 1,
                'token_digest' => str_repeat('c', 64),
                'status' => 'active',
                'active_slot' => 1,
                'issued_at' => $now,
                'issued_by_user_id' => 11,
            ],
            ['%d', '%d', '%s', '%s', '%d', '%s', '%d']
        );
        self::assertFalse($duplicateDigest, 'Credential digest lookup material must be globally unique.');

        $duplicateGeneration = $wpdb->insert(
            $tableName,
            [
                'guest_id' => 702,
                'generation' => 1,
                'token_digest' => str_repeat('e', 64),
                'status' => 'revoked',
                'active_slot' => null,
                'issued_at' => $now,
                'issued_by_user_id' => 11,
                'invalidated_at' => $now,
                'invalidated_by_user_id' => 11,
            ],
            ['%d', '%d', '%s', '%s', '%d', '%s', '%d', '%s', '%d']
        );
        self::assertFalse($duplicateGeneration, 'A guest generation must be immutable and unique.');
    }
}
