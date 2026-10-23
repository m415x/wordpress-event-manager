<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

final class PreissuedInvitationPersistenceTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
    }

    public function testInventorySchemaInstallsPhysicalUniqueConstraints(): void
    {
        global $wpdb;
        \WEM_Preissued_Invitation_Schema::install();

        $table = \WEM_Preissued_Invitation_Schema::table_name();
        $audit = \WEM_Preissued_Invitation_Schema::audit_table_name();
        foreach ([$table, $audit] as $name) {
            $definition = $wpdb->get_row("SHOW CREATE TABLE {$name}", ARRAY_N);
            self::assertIsArray($definition, 'Schema must exist: ' . $name);
            self::assertStringContainsString('ENGINE=InnoDB', $definition[1], 'Transactional InnoDB required.');
        }

        $index = $wpdb->get_results("SHOW INDEX FROM {$table}", ARRAY_A);
        $unique = [];
        foreach ($index as $row) {
            if ((int) $row['Non_unique'] === 0) {
                $unique[$row['Key_name']][] = $row['Column_name'];
            }
        }
        self::assertSame(['token_digest'], $unique['token_digest'] ?? null);
        self::assertSame(['assigned_guest_id'], $unique['assigned_guest'] ?? null);

        // A direct SQL writer must not bypass the immutable event/assignment
        // contract, even if PHP application validation is skipped.
        $ddl = $wpdb->get_row("SHOW CREATE TABLE {$table}", ARRAY_N);
        self::assertMatchesRegularExpression(
            '/(?:TRIGGER|CONSTRAINT)[^;]*immutable|immutable[^;]*(?:TRIGGER|CONSTRAINT)/i',
            $ddl[1],
            'P0 requires DB-enforced event identity and one-time assignment invariants.'
        );
    }
}
