<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Schema idempotence must be verified outside WP_UnitTestCase transactions:
 * dbDelta() may issue an implicit commit and invalidate rollback isolation.
 */
final class MovementSchemaIdempotenceOutsideTransactionTest extends TestCase
{
    public function testRepeatedSchemaInstallationPreservesExistingMovements(): void
    {
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';

        global $wpdb;
        self::assertSame('0', (string) $wpdb->get_var('SELECT @@in_transaction'),
            'Schema installation must run outside the WordPress PHPUnit transaction.');

        $table = \WEM_Movement_Schema::table_name();
        $before = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");

        \WEM_Movement_Schema::install();
        \WEM_Movement_Schema::install();

        $after = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        self::assertSame($before, $after, 'Reinstall must preserve movement rows.');
        self::assertSame('0', (string) $wpdb->get_var('SELECT @@in_transaction'));
    }
}
