<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

/**
 * Domain-neutral causal probe for the WP_UnitTestCase transaction boundary.
 *
 * Run only this file during diagnosis. The second test observes the first
 * test's post-teardown state and removes only this probe's own marker.
 */
final class HarnessNestedTransactionProbeTest extends WP_UnitTestCase
{
    private const MARKER = 'wem_harness_nested_transaction_probe_only';

    public function test01ExplicitCommitInsideWordPressTestTransaction(): void
    {
        global $wpdb;

        self::assertSame('1', (string) $wpdb->get_var('SELECT @@in_transaction'));

        $wpdb->query('START TRANSACTION');
        self::assertSame('1', (string) $wpdb->get_var('SELECT @@in_transaction'));

        $written = $wpdb->insert(
            $wpdb->options,
            array('option_name' => self::MARKER, 'option_value' => 'probe', 'autoload' => 'no'),
            array('%s', '%s', '%s')
        );
        self::assertSame(1, $written);

        $wpdb->query('COMMIT');
        self::assertSame('0', (string) $wpdb->get_var('SELECT @@in_transaction'));
    }

    /** @depends test01ExplicitCommitInsideWordPressTestTransaction */
    public function test02CommittedWriteMustNotSurvivePreviousTestTeardown(): void
    {
        global $wpdb;

        $persisted = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name = %s",
                self::MARKER
            )
        );

        // Remove only this probe's synthetic marker, even when assertion fails.
        $wpdb->delete($wpdb->options, array('option_name' => self::MARKER), array('%s'));
        self::assertSame(
            0,
            $persisted,
            'Explicit COMMIT escaped WP_UnitTestCase rollback across tests.'
        );
    }
}
