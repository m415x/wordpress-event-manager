<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

/** WEM-91/T4: server-derived, consent-gated member labels. */
final class SeatingBearerMembersTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
    }

    public function testMemberProjectionRequiresSeparatePrivacyBoundary(): void
    {
        self::assertTrue(
            class_exists('WEM_Seating_Bearer_Members'),
            'WEM-91/T4 requires a bearer-scoped, consent-gated members projection.'
        );
        self::assertTrue(
            method_exists('WEM_Seating_Bearer_Members', 'list_for_bearer'),
            'WEM-91/T4 must derive event and visible members on the server.'
        );
    }
}
