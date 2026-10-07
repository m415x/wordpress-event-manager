<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_Ajax_UnitTestCase;

final class MovementAjaxRejectionTest extends WP_Ajax_UnitTestCase
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

        \WEM_Movement_Schema::install();

        $ajax = new \WEM_Ajax_Handler();
        $ajax->register_ajax_handlers();
    }

    public function testRejectedAjaxAttemptsNeverAppendMovementOrMutateProjection(): void
    {
        $eventA = wp_insert_term('WEM-29 Reject Event A', 'evento');
        $eventB = wp_insert_term('WEM-29 Reject Event B', 'evento');

        self::assertNotWPError($eventA);
        self::assertNotWPError($eventB);

        $eventAId = (int) $eventA['term_id'];
        $eventBId = (int) $eventB['term_id'];

        $readOnlyGuest = $this->createGuest('WEM-29 Read Only', [$eventAId]);
        $badNonceGuest = $this->createGuest('WEM-29 Bad Nonce', [$eventAId]);
        $crossEventGuest = $this->createGuest('WEM-29 Cross Event', [$eventBId]);
        $zeroEventGuest = $this->createGuest('WEM-29 Zero Event', []);
        $multiEventGuest = $this->createGuest('WEM-29 Multi Event', [$eventAId, $eventBId]);

        $viewerId = self::factory()->user->create(['role' => 'subscriber']);
        $viewer = get_user_by('id', $viewerId);
        self::assertInstanceOf(\WP_User::class, $viewer);
        $viewer->add_cap('wem_view_event_guests');
        \WEM_Authorization::set_authorized_event_ids($viewerId, [$eventAId]);
        wp_set_current_user($viewerId);

        $this->dispatch($readOnlyGuest, wp_create_nonce('wem_checkin_nonce'));
        $this->assertDeniedAndUntouched($readOnlyGuest, [$eventAId]);

        $operatorId = self::factory()->user->create(['role' => 'subscriber']);
        $operator = get_user_by('id', $operatorId);
        self::assertInstanceOf(\WP_User::class, $operator);
        $operator->add_cap('wem_operate_event_guests');
        \WEM_Authorization::set_authorized_event_ids($operatorId, [$eventAId]);
        wp_set_current_user($operatorId);

        $this->dispatch($badNonceGuest, 'invalid-nonce');
        $this->assertDeniedAndUntouched($badNonceGuest, [$eventAId]);

        $this->dispatch($crossEventGuest, wp_create_nonce('wem_checkin_nonce'));
        $this->assertDeniedAndUntouched($crossEventGuest, [$eventBId]);

        $this->dispatch($zeroEventGuest, wp_create_nonce('wem_checkin_nonce'));
        $this->assertDeniedAndUntouched($zeroEventGuest, []);

        $adminId = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($adminId);

        $this->dispatch($multiEventGuest, wp_create_nonce('wem_checkin_nonce'));
        $this->assertDeniedAndUntouched($multiEventGuest, [$eventAId, $eventBId]);
    }

    private function createGuest(string $title, array $eventIds): int
    {
        $guestId = self::factory()->post->create([
            'post_type' => 'invitado',
            'post_title' => $title,
            'post_status' => 'publish',
        ]);

        if ($eventIds) {
            wp_set_object_terms($guestId, $eventIds, 'evento', false);
        }

        return $guestId;
    }

    private function dispatch(int $guestId, string $nonce): void
    {
        $this->_last_response = '';
        $_POST = [
            'action' => 'wem_checkin_ajax',
            'post_id' => $guestId,
            'check_action' => 'checkin',
            'observ' => '',
            'nonce' => $nonce,
        ];
        $_REQUEST = $_POST;

        try {
            $this->_handleAjax('wem_checkin_ajax');
        } catch (\WPAjaxDieContinueException $exception) {
            // JSON response completed.
        }
    }

    private function assertDeniedAndUntouched(int $guestId, array $eventIds): void
    {
        $response = json_decode($this->_last_response, true);
        self::assertFalse((bool) $response['success']);

        foreach ($eventIds as $eventId) {
            $ledger = new \WEM_Movement_Ledger();
            self::assertSame([], $ledger->find_by_guest_event($guestId, (int) $eventId));
        }

        self::assertSame('', (string) get_post_meta($guestId, 'wem_checkin', true));
        self::assertSame('', (string) get_post_meta($guestId, 'wem_checkin_at', true));
        self::assertSame('', (string) get_post_meta($guestId, 'wem_checkin_by', true));
        self::assertSame('', (string) get_post_meta($guestId, 'wem_checkout', true));
        self::assertSame('', (string) get_post_meta($guestId, 'wem_checkout_at', true));
        self::assertSame('', (string) get_post_meta($guestId, 'wem_checkout_by', true));
    }
}
