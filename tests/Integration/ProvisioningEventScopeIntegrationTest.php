<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

final class ProvisioningEventScopeIntegrationTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';

        if (!taxonomy_exists('evento')) {
            register_taxonomy('evento', 'invitado');
        }

        if (!post_type_exists('invitado')) {
            register_post_type('invitado', ['public' => false]);
        }
    }

    public function testProvisioningAndScopeRemainIndependentAndRevocationIsImmediate(): void
    {
        $eventAId = $this->createEvent('WEM-47 Event A');
        $eventBId = $this->createEvent('WEM-47 Event B');
        $guestA = $this->createGuest('WEM-47 Guest A', $eventAId);
        $guestB = $this->createGuest('WEM-47 Guest B', $eventBId);

        $staffId = self::factory()->user->create(['role' => 'subscriber']);

        self::assertTrue(
            \WEM_Staff_Provisioning::apply_preset(
                $staffId,
                \WEM_Staff_Provisioning::STATE_VIEWER
            )
        );

        self::assertFalse(
            \WEM_Authorization::can_view_guest($staffId, $guestA),
            'Capability without event scope must deny.'
        );
        self::assertFalse(\WEM_Authorization::can_operate_guest($staffId, $guestA));

        self::assertTrue(
            \WEM_Authorization::set_authorized_event_ids($staffId, [$eventAId])
        );
        self::assertTrue(\WEM_Authorization::can_view_guest($staffId, $guestA));
        self::assertFalse(\WEM_Authorization::can_operate_guest($staffId, $guestA));
        self::assertFalse(\WEM_Authorization::can_view_guest($staffId, $guestB));

        self::assertTrue(
            \WEM_Staff_Provisioning::apply_preset(
                $staffId,
                \WEM_Staff_Provisioning::STATE_OPERATOR
            )
        );
        self::assertTrue(\WEM_Authorization::can_view_guest($staffId, $guestA));
        self::assertTrue(\WEM_Authorization::can_operate_guest($staffId, $guestA));
        self::assertFalse(\WEM_Authorization::can_operate_guest($staffId, $guestB));

        self::assertTrue(
            \WEM_Staff_Provisioning::apply_preset(
                $staffId,
                \WEM_Staff_Provisioning::STATE_VIEWER
            )
        );
        self::assertSame([$eventAId], \WEM_Authorization::get_authorized_event_ids($staffId));
        self::assertTrue(\WEM_Authorization::can_view_guest($staffId, $guestA));
        self::assertFalse(
            \WEM_Authorization::can_operate_guest($staffId, $guestA),
            'Operator to Viewer must revoke mutation authority immediately.'
        );

        self::assertTrue(
            \WEM_Staff_Provisioning::apply_preset(
                $staffId,
                \WEM_Staff_Provisioning::STATE_NONE
            )
        );
        self::assertSame(
            [$eventAId],
            \WEM_Authorization::get_authorized_event_ids($staffId),
            'Revoking capabilities must not erase persisted event scope.'
        );
        self::assertFalse(\WEM_Authorization::can_view_guest($staffId, $guestA));
        self::assertFalse(\WEM_Authorization::can_operate_guest($staffId, $guestA));
    }

    public function testScopeWithoutCapabilityNeverAuthorizesAndScopeChangesAreImmediate(): void
    {
        $eventAId = $this->createEvent('WEM-47 Scope Event A');
        $eventBId = $this->createEvent('WEM-47 Scope Event B');
        $guestA = $this->createGuest('WEM-47 Scope Guest A', $eventAId);
        $guestB = $this->createGuest('WEM-47 Scope Guest B', $eventBId);

        $staffId = self::factory()->user->create(['role' => 'editor']);

        self::assertTrue(
            \WEM_Authorization::set_authorized_event_ids($staffId, [$eventAId])
        );
        self::assertFalse(\WEM_Authorization::can_view_guest($staffId, $guestA));
        self::assertFalse(\WEM_Authorization::can_operate_guest($staffId, $guestA));

        self::assertTrue(
            \WEM_Staff_Provisioning::apply_preset(
                $staffId,
                \WEM_Staff_Provisioning::STATE_OPERATOR
            )
        );
        self::assertTrue(\WEM_Authorization::can_operate_guest($staffId, $guestA));
        self::assertFalse(\WEM_Authorization::can_operate_guest($staffId, $guestB));

        self::assertTrue(
            \WEM_Authorization::set_authorized_event_ids($staffId, [$eventBId])
        );
        self::assertFalse(\WEM_Authorization::can_view_guest($staffId, $guestA));
        self::assertFalse(\WEM_Authorization::can_operate_guest($staffId, $guestA));
        self::assertTrue(\WEM_Authorization::can_view_guest($staffId, $guestB));
        self::assertTrue(\WEM_Authorization::can_operate_guest($staffId, $guestB));
    }

    public function testManageOptionsBoundaryRemainsGlobalWithoutProvisioningOrScope(): void
    {
        $eventId = $this->createEvent('WEM-47 Admin Event');
        $guestId = $this->createGuest('WEM-47 Admin Guest', $eventId);

        $adminId = self::factory()->user->create(['role' => 'administrator']);

        self::assertSame(
            \WEM_Staff_Provisioning::STATE_NONE,
            \WEM_Staff_Provisioning::get_state($adminId)
        );
        self::assertSame([], \WEM_Authorization::get_authorized_event_ids($adminId));
        self::assertTrue(\WEM_Authorization::can_view_guest($adminId, $guestId));
        self::assertTrue(\WEM_Authorization::can_operate_guest($adminId, $guestId));
    }

    private function createEvent(string $name): int
    {
        $term = wp_insert_term($name, 'evento');
        self::assertNotWPError($term);

        return (int) $term['term_id'];
    }

    private function createGuest(string $title, int $eventId): int
    {
        $guestId = self::factory()->post->create(
            [
                'post_type' => 'invitado',
                'post_title' => $title,
                'post_status' => 'publish',
            ]
        );

        wp_set_object_terms($guestId, [$eventId], 'evento', false);

        return $guestId;
    }
}
