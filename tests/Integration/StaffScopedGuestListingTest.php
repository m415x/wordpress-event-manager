<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_Ajax_UnitTestCase;

final class StaffScopedGuestListingTest extends WP_Ajax_UnitTestCase
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

    public function testAuthorizedStaffCanListOnlyGuestsFromRequestedScopedEvent(): void
    {
        $eventA = wp_insert_term('WEM-21 Event A', 'evento');
        $eventB = wp_insert_term('WEM-21 Event B', 'evento');

        self::assertNotWPError($eventA);
        self::assertNotWPError($eventB);

        $eventAId = (int) $eventA['term_id'];
        $eventBId = (int) $eventB['term_id'];

        $guestA = $this->createGuest('WEM-21 Ticket A', 'visible-a', $eventAId);
        $this->createGuest('WEM-21 Ticket B', 'hidden-b', $eventBId);

        update_post_meta($guestA, 'wem_observaciones', 'private-observation-a');

        $staffId = self::factory()->user->create(['role' => 'subscriber']);
        $staff = get_user_by('id', $staffId);
        self::assertInstanceOf(\WP_User::class, $staff);

        $staff->add_cap('wem_view_event_guests');
        update_user_meta($staffId, 'wem_authorized_event_ids', [$eventAId]);
        wp_set_current_user($staffId);

        $termA = get_term($eventAId, 'evento');
        self::assertInstanceOf(\WP_Term::class, $termA);

        $_POST = [
            'action' => 'wem_list_ajax',
            'evento' => $termA->slug,
            'q' => '',
            'mesa' => '',
        ];
        $_REQUEST = $_POST;

        try {
            $this->_handleAjax('wem_list_ajax');
        } catch (\WPAjaxDieContinueException $exception) {
            // HTML response is captured by WP_Ajax_UnitTestCase.
        }

        self::assertStringContainsString('visible-a', $this->_last_response);
        self::assertStringNotContainsString('hidden-b', $this->_last_response);
        self::assertStringNotContainsString('private-observation-a', $this->_last_response);
    }



    public function testListingExcludesGuestsWithMultipleEventoTerms(): void
    {
        $eventA = wp_insert_term('WEM-21 Cardinality Event A', 'evento');
        $eventB = wp_insert_term('WEM-21 Cardinality Event B', 'evento');

        self::assertNotWPError($eventA);
        self::assertNotWPError($eventB);

        $eventAId = (int) $eventA['term_id'];
        $eventBId = (int) $eventB['term_id'];

        $this->createGuest('WEM-21 Valid Ticket', 'valid-single-event', $eventAId);
        $invalidGuestId = $this->createGuest('WEM-21 Invalid Ticket', 'invalid-multi-event', $eventAId);
        wp_set_object_terms($invalidGuestId, [$eventAId, $eventBId], 'evento', false);

        $staffId = self::factory()->user->create(['role' => 'subscriber']);
        $staff = get_user_by('id', $staffId);
        self::assertInstanceOf(\WP_User::class, $staff);

        $staff->add_cap('wem_view_event_guests');
        update_user_meta($staffId, 'wem_authorized_event_ids', [$eventAId]);
        wp_set_current_user($staffId);

        $termA = get_term($eventAId, 'evento');
        self::assertInstanceOf(\WP_Term::class, $termA);

        $_POST = [
            'action' => 'wem_list_ajax',
            'evento' => $termA->slug,
            'q' => '',
            'mesa' => '',
        ];
        $_REQUEST = $_POST;

        try {
            $this->_handleAjax('wem_list_ajax');
        } catch (\WPAjaxDieContinueException $exception) {
            // HTML response is captured by WP_Ajax_UnitTestCase.
        }

        self::assertStringContainsString('valid-single-event', $this->_last_response);
        self::assertStringNotContainsString(
            'invalid-multi-event',
            $this->_last_response,
            'A guest with multiple evento terms must never appear in an operational roster.'
        );
    }

    public function testAuthorizedStaffListShortcodeRendersOnlyForScopedEvent(): void
    {
        $eventA = wp_insert_term('WEM-21 Shortcode Event A', 'evento');
        $eventB = wp_insert_term('WEM-21 Shortcode Event B', 'evento');

        self::assertNotWPError($eventA);
        self::assertNotWPError($eventB);

        $eventAId = (int) $eventA['term_id'];
        $eventBId = (int) $eventB['term_id'];

        $termA = get_term($eventAId, 'evento');
        $termB = get_term($eventBId, 'evento');

        self::assertInstanceOf(\WP_Term::class, $termA);
        self::assertInstanceOf(\WP_Term::class, $termB);

        $staffId = self::factory()->user->create(['role' => 'subscriber']);
        $staff = get_user_by('id', $staffId);
        self::assertInstanceOf(\WP_User::class, $staff);

        $staff->add_cap('wem_view_event_guests');
        update_user_meta($staffId, 'wem_authorized_event_ids', [$eventAId]);
        wp_set_current_user($staffId);

        do_action('plugins_loaded');

        $authorized = do_shortcode('[wem_list event="' . $termA->slug . '"]');
        self::assertStringContainsString('wem-list-wrap', $authorized);
        self::assertStringContainsString($termA->slug, $authorized);

        $crossEvent = do_shortcode('[wem_list event="' . $termB->slug . '"]');
        self::assertSame('<p>Guest access is temporarily unavailable.</p>', $crossEvent);

        wp_set_current_user(0);

        $anonymous = do_shortcode('[wem_list event="' . $termA->slug . '"]');
        self::assertSame('<p>Guest access is temporarily unavailable.</p>', $anonymous);
    }

    private function createGuest(string $ticket, string $name, int $eventId): int
    {
        $guestId = self::factory()->post->create(
            [
                'post_type' => 'invitado',
                'post_title' => $ticket,
                'post_status' => 'publish',
            ]
        );

        update_post_meta($guestId, 'wem_ticket', $ticket);
        update_post_meta($guestId, 'wem_nombre', $name);
        update_post_meta($guestId, 'wem_organizacion', 'WEM-21 Org');
        update_post_meta($guestId, 'wem_mesa', '1');
        wp_set_object_terms($guestId, [$eventId], 'evento', false);

        return $guestId;
    }
}
