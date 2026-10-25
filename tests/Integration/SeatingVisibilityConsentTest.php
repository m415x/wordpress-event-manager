<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

/** WEM-90/T3 contractual RED: revocable preference is bearer-scoped. */
final class SeatingVisibilityConsentTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
    }

    public function testVisibilityConsentRequiresOwnGuestEventBearerBoundary(): void
    {
        self::assertTrue(
            class_exists('WEM_Seating_Bearer_Consent'),
            'WEM-90/T3 requires a bearer-scoped, revocable visibility consent service.'
        );

        self::assertTrue(
            method_exists('WEM_Seating_Bearer_Consent', 'set_for_bearer'),
            'WEM-90/T3 requires own-guest consent mutation, not caller-supplied guest IDs.'
        );

        self::assertTrue(
            method_exists('WEM_Seating_Bearer_Consent', 'get_for_bearer'),
            'WEM-90/T3 requires visibility to default to false and remain revocable.'
        );
    }
}
