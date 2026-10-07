<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_Ajax_UnitTestCase;

final class StaffOperationalClosureTest extends WP_Ajax_UnitTestCase
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

        $shortcodes = new \WEM_Shortcode_Manager();
        $shortcodes->register_shortcodes();
    }

    public function testAuthorizedStaffCheckinShortcodeIsScopedAndDoesNotDisclosePrivateObservations(): void
    {
        $eventA = wp_insert_term('WEM-24 Event A', 'evento');
        $eventB = wp_insert_term('WEM-24 Event B', 'evento');

        self::assertNotWPError($eventA);
        self::assertNotWPError($eventB);

        $eventAId = (int) $eventA['term_id'];
        $eventBId = (int) $eventB['term_id'];

        $guestA = $this->createGuest('WEM-24-TICKET-A', 'visible-staff-name', $eventAId);
        $this->createGuest('WEM-24-TICKET-B', 'cross-event-name', $eventBId);

        update_post_meta($guestA, 'wem_observaciones', 'private-admin-only-observation');

        $staffId = self::factory()->user->create(['role' => 'subscriber']);
        $staff = get_user_by('id', $staffId);
        self::assertInstanceOf(\WP_User::class, $staff);

        $staff->add_cap('wem_operate_event_guests');
        self::assertTrue(\WEM_Authorization::set_authorized_event_ids($staffId, [$eventAId]));
        wp_set_current_user($staffId);

        $termA = get_term($eventAId, 'evento');
        $termB = get_term($eventBId, 'evento');
        self::assertInstanceOf(\WP_Term::class, $termA);
        self::assertInstanceOf(\WP_Term::class, $termB);

        $_GET['ticket'] = 'WEM-24-TICKET-A';
        $authorized = do_shortcode('[wem_checkin event="' . $termA->slug . '"]');

        self::assertStringContainsString('wem-wrapper', $authorized);
        self::assertStringContainsString('visible-staff-name', $authorized);
        self::assertStringNotContainsString('private-admin-only-observation', $authorized);

        $_GET['ticket'] = 'WEM-24-TICKET-B';
        $crossEvent = do_shortcode('[wem_checkin event="' . $termB->slug . '"]');
        self::assertSame('<p>Guest access is temporarily unavailable.</p>', $crossEvent);

        wp_set_current_user(0);
        $_GET['ticket'] = 'WEM-24-TICKET-A';

        $anonymous = do_shortcode('[wem_checkin event="' . $termA->slug . '"]');
        self::assertSame('<p>Guest access is temporarily unavailable.</p>', $anonymous);
    }




    public function testListRowUsesTheRealEventPagePermalink(): void
    {
        update_option('permalink_structure', '');

        $event = wp_insert_term('WEM-24 Permalink Event', 'evento');
        self::assertNotWPError($event);

        $eventId = (int) $event['term_id'];
        $term = get_term($eventId, 'evento');
        self::assertInstanceOf(\WP_Term::class, $term);

        $pageId = self::factory()->post->create(
            [
                'post_type' => 'page',
                'post_title' => 'WEM-24 Permalink Event',
                'post_name' => $term->slug,
                'post_status' => 'publish',
            ]
        );

        $this->createGuest('WEM-24-PERMALINK', 'permalink-guest', $eventId);

        $staffId = self::factory()->user->create(['role' => 'subscriber']);
        $staff = get_user_by('id', $staffId);
        self::assertInstanceOf(\WP_User::class, $staff);

        $staff->add_cap('wem_view_event_guests');
        self::assertTrue(\WEM_Authorization::set_authorized_event_ids($staffId, [$eventId]));
        wp_set_current_user($staffId);

        $_POST = [
            'action' => 'wem_list_ajax',
            'evento' => $term->slug,
            'q' => '',
            'mesa' => '',
        ];
        $_REQUEST = $_POST;

        try {
            $this->_handleAjax('wem_list_ajax');
        } catch (\WPAjaxDieContinueException $exception) {
            // HTML response is captured by WP_Ajax_UnitTestCase.
        }

        $expectedUrl = add_query_arg('ticket', 'WEM-24-PERMALINK', get_permalink($pageId));

        self::assertStringContainsString(
            esc_url($expectedUrl),
            $this->_last_response,
            'Operational row navigation must honor the active WordPress permalink mode.'
        );
    }

    public function testListCheckinSuccessReloadsTheOperationalPage(): void
    {
        $event = wp_insert_term('WEM-24 List Refresh Event', 'evento');
        self::assertNotWPError($event);

        $eventId = (int) $event['term_id'];

        $staffId = self::factory()->user->create(['role' => 'subscriber']);
        $staff = get_user_by('id', $staffId);
        self::assertInstanceOf(\WP_User::class, $staff);

        $staff->add_cap('wem_view_event_guests');
        self::assertTrue(\WEM_Authorization::set_authorized_event_ids($staffId, [$eventId]));
        wp_set_current_user($staffId);

        $term = get_term($eventId, 'evento');
        self::assertInstanceOf(\WP_Term::class, $term);

        $list = do_shortcode('[wem_list event="' . $term->slug . '"]');

        self::assertStringContainsString(
            "if(res.success){\n                                location.reload();",
            $list,
            'A successful list check-in must refresh the whole operational page '
            . 'so colocated guest detail stays in sync.'
        );
    }

    public function testAuthenticatedStaffSessionCoversListCheckinCheckoutAndReentry(): void
    {
        $event = wp_insert_term('WEM-24 Lifecycle Event', 'evento');
        self::assertNotWPError($event);

        $eventId = (int) $event['term_id'];
        $guestId = $this->createGuest('WEM-24-LIFECYCLE', 'lifecycle-staff-name', $eventId);

        $staffId = self::factory()->user->create(['role' => 'subscriber']);
        $staff = get_user_by('id', $staffId);
        self::assertInstanceOf(\WP_User::class, $staff);

        $staff->add_cap('wem_view_event_guests');
        $staff->add_cap('wem_operate_event_guests');
        self::assertTrue(\WEM_Authorization::set_authorized_event_ids($staffId, [$eventId]));
        wp_set_current_user($staffId);

        $term = get_term($eventId, 'evento');
        self::assertInstanceOf(\WP_Term::class, $term);

        $list = do_shortcode('[wem_list event="' . $term->slug . '"]');
        self::assertStringContainsString('wem-list-wrap', $list);

        $_GET['ticket'] = 'WEM-24-LIFECYCLE';
        $checkinUi = do_shortcode('[wem_checkin event="' . $term->slug . '"]');
        self::assertStringContainsString('wem-wrapper', $checkinUi);
        self::assertStringContainsString('lifecycle-staff-name', $checkinUi);

        foreach (['checkin', 'checkout', 'checkin_again'] as $action) {
            $this->_last_response = '';
            $_POST = [
                'action' => 'wem_checkin_ajax',
                'post_id' => $guestId,
                'check_action' => $action,
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
            self::assertIsArray($response);
            self::assertTrue((bool) $response['success'], 'Action failed: ' . $action);
        }

        self::assertSame('1', (string) get_post_meta($guestId, 'wem_checkin', true));
        self::assertSame('', (string) get_post_meta($guestId, 'wem_checkout', true));
        self::assertNotSame('', (string) get_post_meta($guestId, 'wem_checkin_at', true));
        self::assertNotSame('', (string) get_post_meta($guestId, 'wem_checkin_by', true));
        self::assertSame('', (string) get_post_meta($guestId, 'wem_checkout_at', true));
        self::assertSame('', (string) get_post_meta($guestId, 'wem_checkout_by', true));
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
        update_post_meta($guestId, 'wem_organizacion', 'WEM-24 Org');
        update_post_meta($guestId, 'wem_mesa', '24');
        wp_set_object_terms($guestId, [$eventId], 'evento', false);

        return $guestId;
    }
}
