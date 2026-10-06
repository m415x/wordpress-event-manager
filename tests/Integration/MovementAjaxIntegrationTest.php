<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_Ajax_UnitTestCase;

final class MovementAjaxIntegrationTest extends WP_Ajax_UnitTestCase
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

    public function testAuthorizedAjaxCheckinDelegatesToMovementServiceAndAppendsAcceptedFact(): void
    {
        $event = wp_insert_term('WEM-29 Event', 'evento');
        self::assertNotWPError($event);

        $eventId = (int) $event['term_id'];
        $guestId = self::factory()->post->create([
            'post_type' => 'invitado',
            'post_title' => 'WEM-29 Guest',
            'post_status' => 'publish',
        ]);
        wp_set_object_terms($guestId, [$eventId], 'evento', false);

        $actorId = self::factory()->user->create(['role' => 'subscriber']);
        $actor = get_user_by('id', $actorId);
        self::assertInstanceOf(\WP_User::class, $actor);

        $actor->add_cap('wem_operate_event_guests');
        \WEM_Authorization::set_authorized_event_ids($actorId, [$eventId]);
        wp_set_current_user($actorId);

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

        $response = json_decode($this->_last_response, true);
        self::assertTrue((bool) $response['success']);

        $ledger = new \WEM_Movement_Ledger();
        $history = $ledger->find_by_guest_event($guestId, $eventId);

        self::assertCount(1, $history);
        self::assertSame('checkin', $history[0]['movement_type']);
        self::assertSame((string) $actorId, (string) $history[0]['actor_user_id']);
        self::assertSame('staff_web', $history[0]['source']);
        self::assertSame('accepted', $history[0]['decision']);

        self::assertSame('1', (string) get_post_meta($guestId, 'wem_checkin', true));
    }
}
