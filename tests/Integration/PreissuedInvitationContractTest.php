<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

final class PreissuedInvitationContractTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';
    }

    public function testInventoryHasIndependentIdentityAndOneTimeBindingBoundary(): void
    {
        self::assertTrue(
            class_exists('WEM_Preissued_Invitation_Service'),
            'P0 requires an event-scoped unassigned invitation service independent of guest credentials.'
        );
        self::assertTrue(
            method_exists('WEM_Preissued_Invitation_Service', 'issue_unassigned'),
            'P0 requires private unassigned issuance.'
        );
        self::assertTrue(
            method_exists('WEM_Preissued_Invitation_Service', 'assign_once'),
            'P0 requires an irreversible one-time binding boundary.'
        );
    }
}
