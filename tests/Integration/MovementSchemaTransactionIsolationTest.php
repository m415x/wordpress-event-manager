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
    public function testMovementSchemaIsReadyWithoutCommittingWordPressTestTransaction(): void
    {
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';

        global $wpdb;
        self::assertSame('1', (string) $wpdb->get_var('SELECT @@in_transaction'),
            'WordPress PHPUnit must start a transaction before this test.');

        self::assertSame(\WEM_Movement_Schema::table_name(), (string) $wpdb->get_var('SHOW TABLES LIKE \'wp_wem_guest_movements\''));

        self::assertSame('1', (string) $wpdb->get_var('SELECT @@in_transaction'),
            'Preinstalled movement schema must preserve the PHPUnit transaction.');
    }
}
