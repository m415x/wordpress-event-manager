<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

final class EventScopeAuthorizationKernelTest extends WP_UnitTestCase
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

    public function testAuthorizationKernelIsAvailableThroughPluginAutoloading(): void
    {
        self::assertTrue(
            class_exists('WEM_Authorization'),
            'WEM-20 requires a reusable authorization kernel loaded through the plugin autoloader.'
        );
    }

    public function testAuthorizationKernelExposesBoundedViewOperateAndScopeContract(): void
    {
        foreach (
            [
                'get_guest_event_term_id',
                'get_authorized_event_ids',
                'can_view_guest',
                'can_operate_guest',
            ] as $method
        ) {
            self::assertTrue(
                method_exists('WEM_Authorization', $method),
                sprintf('WEM-20 authorization kernel must expose %s().', $method)
            );
        }
    }

    public function testCanonicalGuestEventRequiresExactlyOneEventoTerm(): void
    {
        $eventA = wp_insert_term('WEM-20 Event A', 'evento');
        $eventB = wp_insert_term('WEM-20 Event B', 'evento');

        self::assertNotWPError($eventA);
        self::assertNotWPError($eventB);

        $eventAId = (int) $eventA['term_id'];
        $eventBId = (int) $eventB['term_id'];

        $singleEventGuest = $this->createGuest('WEM-20 Single Event Guest');
        $noEventGuest = $this->createGuest('WEM-20 No Event Guest');
        $multiEventGuest = $this->createGuest('WEM-20 Multi Event Guest');

        wp_set_object_terms($singleEventGuest, [$eventAId], 'evento', false);
        wp_set_object_terms($multiEventGuest, [$eventAId, $eventBId], 'evento', false);

        self::assertSame(
            $eventAId,
            \WEM_Authorization::get_guest_event_term_id($singleEventGuest),
            'Exactly one evento term must resolve to its canonical numeric term_id.'
        );
        self::assertNull(
            \WEM_Authorization::get_guest_event_term_id($noEventGuest),
            'A guest with no evento term must fail closed.'
        );
        self::assertNull(
            \WEM_Authorization::get_guest_event_term_id($multiEventGuest),
            'A guest with multiple evento terms must fail closed.'
        );
    }

    public function testViewAndOperateAuthorizationRequireCapabilityAndMatchingEventScope(): void
    {
        $eventAId = $this->createEvent('WEM-20 Scoped Event A');
        $eventBId = $this->createEvent('WEM-20 Scoped Event B');
        $guestA = $this->createGuestForEvent('WEM-20 Scoped Guest A', $eventAId);
        $guestB = $this->createGuestForEvent('WEM-20 Scoped Guest B', $eventBId);

        $noCapabilityUserId = self::factory()->user->create(['role' => 'subscriber']);
        $viewerId = self::factory()->user->create(['role' => 'subscriber']);
        $operatorId = self::factory()->user->create(['role' => 'subscriber']);

        $viewer = get_user_by('id', $viewerId);
        $operator = get_user_by('id', $operatorId);

        self::assertInstanceOf(\WP_User::class, $viewer);
        self::assertInstanceOf(\WP_User::class, $operator);

        $viewer->add_cap('wem_view_event_guests');
        $operator->add_cap('wem_view_event_guests');
        $operator->add_cap('wem_operate_event_guests');

        update_user_meta($viewerId, 'wem_authorized_event_ids', [$eventAId]);
        update_user_meta($operatorId, 'wem_authorized_event_ids', [$eventAId]);

        self::assertSame([$eventAId], \WEM_Authorization::get_authorized_event_ids($viewerId));

        self::assertFalse(\WEM_Authorization::can_view_guest(0, $guestA));
        self::assertFalse(\WEM_Authorization::can_view_guest($noCapabilityUserId, $guestA));

        self::assertTrue(\WEM_Authorization::can_view_guest($viewerId, $guestA));
        self::assertFalse(\WEM_Authorization::can_view_guest($viewerId, $guestB));
        self::assertFalse(\WEM_Authorization::can_operate_guest($viewerId, $guestA));

        self::assertTrue(\WEM_Authorization::can_view_guest($operatorId, $guestA));
        self::assertTrue(\WEM_Authorization::can_operate_guest($operatorId, $guestA));
        self::assertFalse(\WEM_Authorization::can_operate_guest($operatorId, $guestB));
    }


    public function testGlobalAdminScopeStillRequiresValidSingleEventGuestResource(): void
    {
        $eventAId = $this->createEvent('WEM-20 Admin Event A');
        $eventBId = $this->createEvent('WEM-20 Admin Event B');

        $guestA = $this->createGuestForEvent('WEM-20 Admin Guest A', $eventAId);
        $guestB = $this->createGuestForEvent('WEM-20 Admin Guest B', $eventBId);
        $noEventGuest = $this->createGuest('WEM-20 Admin No Event Guest');
        $multiEventGuest = $this->createGuest('WEM-20 Admin Multi Event Guest');
        wp_set_object_terms($multiEventGuest, [$eventAId, $eventBId], 'evento', false);

        $nonGuestPostId = self::factory()->post->create(
            [
                'post_type' => 'post',
                'post_title' => 'WEM-20 Non Guest Resource',
                'post_status' => 'publish',
            ]
        );
        wp_set_object_terms($nonGuestPostId, [$eventAId], 'evento', false);

        $adminId = self::factory()->user->create(['role' => 'administrator']);

        self::assertSame([], \\WEM_Authorization::get_authorized_event_ids($adminId));

        self::assertTrue(\\WEM_Authorization::can_view_guest($adminId, $guestA));
        self::assertTrue(\\WEM_Authorization::can_view_guest($adminId, $guestB));
        self::assertTrue(\\WEM_Authorization::can_operate_guest($adminId, $guestA));
        self::assertTrue(\\WEM_Authorization::can_operate_guest($adminId, $guestB));

        self::assertFalse(\\WEM_Authorization::can_view_guest($adminId, $noEventGuest));
        self::assertFalse(\\WEM_Authorization::can_operate_guest($adminId, $noEventGuest));
        self::assertFalse(\\WEM_Authorization::can_view_guest($adminId, $multiEventGuest));
        self::assertFalse(\\WEM_Authorization::can_operate_guest($adminId, $multiEventGuest));
        self::assertFalse(\\WEM_Authorization::can_view_guest($adminId, $nonGuestPostId));
        self::assertFalse(\\WEM_Authorization::can_operate_guest($adminId, $nonGuestPostId));
    }

    private function createEvent(string $name): int
    {
        $term = wp_insert_term($name, 'evento');
        self::assertNotWPError($term);

        return (int) $term['term_id'];
    }

    private function createGuestForEvent(string $title, int $eventId): int
    {
        $guestId = $this->createGuest($title);
        wp_set_object_terms($guestId, [$eventId], 'evento', false);

        return $guestId;
    }

    private function createGuest(string $title): int
    {
        return self::factory()->post->create(
            [
                'post_type' => 'invitado',
                'post_title' => $title,
                'post_status' => 'publish',
            ]
        );
    }
}
