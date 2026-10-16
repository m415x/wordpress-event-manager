<?php

declare(strict_types=1);

namespace WEM\Tests\Integration\Support;

use WP_UnitTestCase;

/**
 * Bounded diagnostic harness: explicitly owned rows only; no global reset.
 */
abstract class WemTransactionalTestCase extends WP_UnitTestCase
{
    private $ownedOptions = array();
    private $baseline = array();

    protected function setUp(): void
    {
        parent::setUp();
        global $wpdb;
        $this->baseline = array(
            'posts' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts}"),
            'options' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options}"),
        );
    }

    protected function registerOwnedOption(string $name): void
    {
        $this->ownedOptions[] = $name;
    }

    protected function tearDown(): void
    {
        global $wpdb;
        $cleanupError = null;

        try {
            // Revert uncommitted writes first; explicit product COMMIT may have
            // already persisted owned fixtures, which require manual cleanup.
            $wpdb->query('ROLLBACK');

            foreach ($this->ownedOptions as $name) {
                $wpdb->delete($wpdb->options, array('option_name' => $name), array('%s'));
            }

            foreach ($this->baseline as $name => $count) {
                $table = $name === 'posts' ? $wpdb->posts : $wpdb->options;
                $actual = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
                if ($actual !== $count) {
                    throw new \RuntimeException(
                        "WEM transactional cleanup changed {$name} baseline: {$count} -> {$actual}"
                    );
                }
            }

            // With WordPress tests' autocommit=0, persist scoped cleanup before
            // the inherited tear_down issues its own ROLLBACK.
            $wpdb->query('COMMIT');
        } catch (\Throwable $error) {
            $wpdb->query('ROLLBACK');
            $cleanupError = $error;
        } finally {
            parent::tearDown();
        }

        if ($cleanupError !== null) {
            throw $cleanupError;
        }
    }
}
