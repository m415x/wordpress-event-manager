<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

require_once __DIR__ . '/Support/WemTransactionalTestCase.php';

use WEM\Tests\Integration\Support\WemTransactionalTestCase;

/** Focused RED/GREEN spike, excluded from the default integration suite. */
final class HarnessScopedCleanupSpikeTest extends WemTransactionalTestCase
{
    private const MARKER = 'wem_scoped_cleanup_spike_only';

    public function test01ExplicitCommitIsReconciledByOwnedCleanup(): void
    {
        global $wpdb;
        self::assertSame(
            0,
            (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name = %s",
                self::MARKER
            ))
        );
        $this->registerOwnedOption(self::MARKER);
        self::assertSame('1', (string) $wpdb->get_var('SELECT @@in_transaction'));
        self::assertSame(1, $wpdb->query('START TRANSACTION'));
        self::assertSame(
            1,
            $wpdb->insert($wpdb->options, array(
                'option_name' => self::MARKER,
                'option_value' => 'synthetic',
                'autoload' => 'no',
            ))
        );
        self::assertSame(1, $wpdb->query('COMMIT'));
    }

    /** @depends test01ExplicitCommitIsReconciledByOwnedCleanup */
    public function test02NoPriorCommittedMarkerIsVisible(): void
    {
        global $wpdb;
        self::assertSame(
            0,
            (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name = %s",
                self::MARKER
            ))
        );
    }
}
