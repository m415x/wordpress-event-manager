<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_Ajax_UnitTestCase;

final class StaffScopedCheckinTransitionTest extends WP_Ajax_UnitTestCase
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

        $ajax = new \WEM_Ajax_Handler();
        $ajax->register_ajax_handlers();
    }

    public function testAuthorizedSameEventOperatorCanCheckInGuestWhileCrossEventGuestRemainsDenied(): void
    {
        $eventA = wp_insert_term('WEM-22 Event A', 'evento');
        $eventB = wp_insert_term('WEM-22 Event B', 'evento');

        self::assertNotWPError($eventA);
        self::assertNotWPError($eventB);

        $eventAId = (int) $eventA['term_id'];
        $eventBId = (int) $eventB['term_id'];

        $guestA = $this->createGuest('WEM-22 Guest A', $eventAId);
        $guestB = $this->createGuest('WEM-22 Guest B', $eventBId);

        $operatorId = self::factory()->user->create(['role' => 'subscriber']);
        $operator = get_user_by('id', $operatorId);
        self::assertInstanceOf(\WP_User::class, $operator);

        $operator->add_cap('wem_view_event_guests');
        $operator->add_cap('wem_operate_event_guests');
        update_user_meta($operatorId, 'wem_authorized_event_ids', [$eventAId]);
        wp_set_current_user($operatorId);

        $this->dispatchCheckin($guestA);

        self::assertSame('1', (string) get_post_meta($guestA, 'wem_checkin', true));
        self::assertNotSame('', (string) get_post_meta($guestA, 'wem_checkin_at', true));
        self::assertNotSame('', (string) get_post_meta($guestA, 'wem_checkin_by', true));

        $this->_last_response = '';
        $this->dispatchCheckin($guestB);

        self::assertSame(
            ['success' => false, 'data' => ['code' => 'guest_access_unavailable']],
            json_decode($this->_last_response, true)
        );
        self::assertSame('', (string) get_post_meta($guestB, 'wem_checkin', true));
        self::assertSame('', (string) get_post_meta($guestB, 'wem_checkin_at', true));
        self::assertSame('', (string) get_post_meta($guestB, 'wem_checkin_by', true));
    }


    public function testReentryDeniesCheckoutWithoutPriorCheckinAndPreservesState(): void
    {
        $event = wp_insert_term('WEM-22 Invalid Reentry Event', 'evento');
        self::assertNotWPError($event);

        $eventId = (int) $event['term_id'];
        $guestId = $this->createGuest('WEM-22 Invalid Reentry Guest', $eventId);

        update_post_meta($guestId, 'wem_checkout', 1);
        update_post_meta($guestId, 'wem_checkout_at', '2026-10-05 10:00:00');
        update_post_meta($guestId, 'wem_checkout_by', 'preexisting-operator');

        $operatorId = self::factory()->user->create(['role' => 'subscriber']);
        $operator = get_user_by('id', $operatorId);
        self::assertInstanceOf(\WP_User::class, $operator);

        $operator->add_cap('wem_operate_event_guests');
        update_user_meta($operatorId, 'wem_authorized_event_ids', [$eventId]);
        wp_set_current_user($operatorId);

        $_POST = [
            'action' => 'wem_checkin_ajax',
            'post_id' => $guestId,
            'check_action' => 'checkin_again',
            'observ' => '',
            'nonce' => wp_create_nonce('wem_checkin_nonce'),
        ];
        $_REQUEST = $_POST;

        try {
            $this->_handleAjax('wem_checkin_ajax');
        } catch (\WPAjaxDieContinueException $exception) {
            // JSON response completed.
        }

        self::assertFalse((bool) json_decode($this->_last_response, true)['success']);
        self::assertSame('', (string) get_post_meta($guestId, 'wem_checkin', true));
        self::assertSame('', (string) get_post_meta($guestId, 'wem_checkin_at', true));
        self::assertSame('', (string) get_post_meta($guestId, 'wem_checkin_by', true));
        self::assertSame('1', (string) get_post_meta($guestId, 'wem_checkout', true));
        self::assertSame('2026-10-05 10:00:00', (string) get_post_meta($guestId, 'wem_checkout_at', true));
        self::assertSame('preexisting-operator', (string) get_post_meta($guestId, 'wem_checkout_by', true));
    }

    private function dispatchCheckin(int $guestId): void
    {
        $_POST = [
            'action' => 'wem_checkin_ajax',
            'post_id' => $guestId,
            'check_action' => 'checkin',
            'observ' => '',
            'nonce' => wp_create_nonce('wem_checkin_nonce'),
        ];
        $_REQUEST = $_POST;

        try {
            $this->_handleAjax('wem_checkin_ajax');
        } catch (\WPAjaxDieContinueException $exception) {
            // JSON response completed.
        }
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
