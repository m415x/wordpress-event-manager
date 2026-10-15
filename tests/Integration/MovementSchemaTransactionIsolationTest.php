<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

/**
 * Regression: the movement schema bootstrap must not terminate the
 * transaction managed by WordPress PHPUnit.
 */
final class MovementSchemaTransactionIsolationTest extends WP_UnitTestCase
{
    public function testMovementSchemaInstallationPreservesWordPressTestTransaction(): void
    {
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';

        global $wpdb;
        self::assertSame('1', (string) $wpdb->get_var('SELECT @@in_transaction'),
            'WordPress PHPUnit must start a transaction before this test.');

        \WEM_Movement_Schema::install();

        self::assertSame('1', (string) $wpdb->get_var('SELECT @@in_transaction'),
            'Movement schema installation must not commit the PHPUnit transaction.');
    }
}
