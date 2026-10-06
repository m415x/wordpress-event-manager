<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

final class StaffEventAssignmentTest extends WP_UnitTestCase
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

    public function testAuthorizationKernelExposesScopeAssignmentApi(): void
    {
        self::assertTrue(
            method_exists('WEM_Authorization', 'set_authorized_event_ids'),
            'WEM-23 requires scope writes to stay behind the WEM authorization API.'
        );
    }

    public function testAssignmentGrantExpansionRevocationAndInvalidTermsAreImmediate(): void
    {
        $eventA = wp_insert_term('WEM-23 Event A', 'evento');
        $eventB = wp_insert_term('WEM-23 Event B', 'evento');

        self::assertNotWPError($eventA);
        self::assertNotWPError($eventB);

        $eventAId = (int) $eventA['term_id'];
        $eventBId = (int) $eventB['term_id'];

        $guestA = $this->createGuest('WEM-23 Guest A', $eventAId);
        $guestB = $this->createGuest('WEM-23 Guest B', $eventBId);

        $staffId = self::factory()->user->create(['role' => 'subscriber']);
        $staff = get_user_by('id', $staffId);
        self::assertInstanceOf(\WP_User::class, $staff);

        $staff->add_cap('wem_view_event_guests');
        $staff->add_cap('wem_operate_event_guests');

        self::assertTrue(\WEM_Authorization::set_authorized_event_ids($staffId, [$eventAId, 999999]));
        self::assertSame([$eventAId], \WEM_Authorization::get_authorized_event_ids($staffId));
        self::assertTrue(\WEM_Authorization::can_view_guest($staffId, $guestA));
        self::assertTrue(\WEM_Authorization::can_operate_guest($staffId, $guestA));
        self::assertFalse(\WEM_Authorization::can_view_guest($staffId, $guestB));

        self::assertTrue(\WEM_Authorization::set_authorized_event_ids($staffId, [$eventAId, $eventBId]));
        self::assertSame([$eventAId, $eventBId], \WEM_Authorization::get_authorized_event_ids($staffId));
        self::assertTrue(\WEM_Authorization::can_view_guest($staffId, $guestB));
        self::assertTrue(\WEM_Authorization::can_operate_guest($staffId, $guestB));

        self::assertTrue(\WEM_Authorization::set_authorized_event_ids($staffId, [$eventBId]));
        self::assertSame([$eventBId], \WEM_Authorization::get_authorized_event_ids($staffId));
        self::assertFalse(\WEM_Authorization::can_view_guest($staffId, $guestA));
        self::assertFalse(\WEM_Authorization::can_operate_guest($staffId, $guestA));
        self::assertTrue(\WEM_Authorization::can_view_guest($staffId, $guestB));
    }

    public function testAssignmentWithoutWemCapabilityStillDeniesAndAdminNeedsNoAssignment(): void
    {
        $event = wp_insert_term('WEM-23 Capability Event', 'evento');
        self::assertNotWPError($event);

        $eventId = (int) $event['term_id'];
        $guestId = $this->createGuest('WEM-23 Capability Guest', $eventId);

        $staffId = self::factory()->user->create(['role' => 'subscriber']);
        self::assertTrue(\WEM_Authorization::set_authorized_event_ids($staffId, [$eventId]));
        self::assertFalse(\WEM_Authorization::can_view_guest($staffId, $guestId));
        self::assertFalse(\WEM_Authorization::can_operate_guest($staffId, $guestId));

        $adminId = self::factory()->user->create(['role' => 'administrator']);
        self::assertSame([], \WEM_Authorization::get_authorized_event_ids($adminId));
        self::assertTrue(\WEM_Authorization::can_view_guest($adminId, $guestId));
        self::assertTrue(\WEM_Authorization::can_operate_guest($adminId, $guestId));
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
