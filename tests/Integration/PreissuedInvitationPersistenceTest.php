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

        // Constraints protecting immutable identity require a DB-level guard:
        // inspecting SHOW CREATE TABLE alone cannot reveal triggers.
        $triggers = $wpdb->get_results($wpdb->prepare(
            "SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND EVENT_OBJECT_TABLE = %s AND EVENT_MANIPULATION = 'UPDATE'",
            $table
        ), ARRAY_A);
        self::assertNotEmpty($triggers, 'P0 requires SQL-level enforcement against reassignment/event mutation.');
    }
}
