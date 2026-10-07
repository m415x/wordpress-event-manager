<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

final class MovementLedgerSchemaTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
    }

    public function testMovementLedgerSchemaCanBeInstalledOnWordPressDatabase(): void
    {
        self::assertTrue(
            class_exists('WEM_Movement_Schema'),
            'WEM-26 requires a dedicated movement-ledger schema installer.'
        );

        \WEM_Movement_Schema::install();

        global $wpdb;

        $tableName = $wpdb->prefix . 'wem_guest_movements';
        $actualTable = $wpdb->get_var(
            $wpdb->prepare('SHOW TABLES LIKE %s', $tableName)
        );

        self::assertSame($tableName, $actualTable);
    }
}
