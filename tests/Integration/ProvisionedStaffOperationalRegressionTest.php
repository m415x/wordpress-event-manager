<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_Ajax_UnitTestCase;

final class ProvisionedStaffOperationalRegressionTest extends WP_Ajax_UnitTestCase
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

    public function testProvisionedViewerAndOperatorPreserveScopedOperationalBoundaries(): void
    {
        $eventAId = $this->createEvent('WEM-48 Event A');
        $eventBId = $this->createEvent('WEM-48 Event B');
        $guestA = $this->createGuest('WEM-48-A', 'visible-a', $eventAId);
        $guestB = $this->createGuest('WEM-48-B', 'hidden-b', $eventBId);

        $staffId = self::factory()->user->create(['role' => 'subscriber']);
        self::assertTrue(
            \WEM_Authorization::set_authorized_event_ids($staffId, [$eventAId])
        );
        self::assertTrue(
            \WEM_Staff_Provisioning::apply_preset(
                $staffId,
                \WEM_Staff_Provisioning::STATE_VIEWER
            )
        );
        wp_set_current_user($staffId);

        $eventA = get_term($eventAId, 'evento');
        self::assertInstanceOf(\WP_Term::class, $eventA);

        $this->dispatchList($eventA->slug);
        self::assertStringContainsString('visible-a', $this->_last_response);
        self::assertStringNotContainsString('hidden-b', $this->_last_response);

        $this->_last_response = '';
        $this->dispatchMovement($guestA, 'checkin');
        self::assertFalse((bool) json_decode($this->_last_response, true)['success']);
        self::assertSame('', (string) get_post_meta($guestA, 'wem_checkin', true));

        self::assertTrue(
            \WEM_Staff_Provisioning::apply_preset(
                $staffId,
                \WEM_Staff_Provisioning::STATE_OPERATOR
            )
        );

        foreach (['checkin', 'checkout', 'checkin_again'] as $action) {
            $this->_last_response = '';
            $this->dispatchMovement($guestA, $action);
            $response = json_decode($this->_last_response, true);
            self::assertIsArray($response);
            self::assertTrue((bool) $response['success'], 'Provisioned operator action failed: ' . $action);
        }

        $this->_last_response = '';
        $this->dispatchMovement($guestB, 'checkin');
        self::assertSame(
            ['success' => false, 'data' => ['code' => 'guest_access_unavailable']],
            json_decode($this->_last_response, true)
        );
        self::assertSame('', (string) get_post_meta($guestB, 'wem_checkin', true));
    }

    public function testRevokedProvisioningDeniesImmediatelyWhileScopeRemainsStored(): void
    {
        $eventId = $this->createEvent('WEM-48 Revoke Event');
        $guestId = $this->createGuest('WEM-48-REVOKE', 'revoked-guest', $eventId);

        $staffId = self::factory()->user->create(['role' => 'editor']);
        self::assertTrue(
            \WEM_Authorization::set_authorized_event_ids($staffId, [$eventId])
        );
        self::assertTrue(
            \WEM_Staff_Provisioning::apply_preset(
                $staffId,
                \WEM_Staff_Provisioning::STATE_OPERATOR
            )
        );
        self::assertTrue(
            \WEM_Staff_Provisioning::apply_preset(
                $staffId,
                \WEM_Staff_Provisioning::STATE_NONE
            )
        );

        self::assertSame([$eventId], \WEM_Authorization::get_authorized_event_ids($staffId));

        wp_set_current_user($staffId);
        $event = get_term($eventId, 'evento');
        self::assertInstanceOf(\WP_Term::class, $event);

        $this->dispatchList($event->slug);
        self::assertSame(
            ['success' => false, 'data' => ['code' => 'guest_access_unavailable']],
            json_decode($this->_last_response, true)
        );

        $this->_last_response = '';
        $this->dispatchMovement($guestId, 'checkin');
        self::assertSame(
            ['success' => false, 'data' => ['code' => 'guest_access_unavailable']],
            json_decode($this->_last_response, true)
        );
    }

    public function testProvisioningDoesNotRelaxGuestCardinalityOrPublicFailClosedBoundary(): void
    {
        $eventAId = $this->createEvent('WEM-48 Cardinality A');
        $eventBId = $this->createEvent('WEM-48 Cardinality B');
        $guestId = $this->createGuest('WEM-48-MULTI', 'multi-event-guest', $eventAId);

        wp_set_object_terms($guestId, [$eventAId, $eventBId], 'evento', false);

        $staffId = self::factory()->user->create(['role' => 'subscriber']);
        self::assertTrue(
            \WEM_Authorization::set_authorized_event_ids($staffId, [$eventAId, $eventBId])
        );
        self::assertTrue(
            \WEM_Staff_Provisioning::apply_preset(
                $staffId,
                \WEM_Staff_Provisioning::STATE_OPERATOR
            )
        );
        wp_set_current_user($staffId);

        $this->dispatchMovement($guestId, 'checkin');
        self::assertSame(
            ['success' => false, 'data' => ['code' => 'guest_access_unavailable']],
            json_decode($this->_last_response, true)
        );

        wp_set_current_user(0);
        $_GET['ticket'] = 'WEM-48-MULTI';

        $eventA = get_term($eventAId, 'evento');
        self::assertInstanceOf(\WP_Term::class, $eventA);

        self::assertSame(
            '<p>Guest access is temporarily unavailable.</p>',
            do_shortcode('[wem_checkin event="' . $eventA->slug . '"]')
        );
        self::assertSame(
            '<p>Guest access is temporarily unavailable.</p>',
            do_shortcode('[wem_list event="' . $eventA->slug . '"]')
        );
    }

    private function dispatchList(string $eventSlug): void
    {
        $_POST = [
            'action' => 'wem_list_ajax',
            'evento' => $eventSlug,
            'q' => '',
            'mesa' => '',
        ];
        $_REQUEST = $_POST;

        try {
            $this->_handleAjax('wem_list_ajax');
        } catch (\WPAjaxDieContinueException $exception) {
            // Expected transport completion.
        }
    }

    private function dispatchMovement(int $guestId, string $action): void
    {
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
            // Expected transport completion.
        }
    }

    private function createEvent(string $name): int
    {
        $term = wp_insert_term($name, 'evento');
        self::assertNotWPError($term);

        return (int) $term['term_id'];
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
        update_post_meta($guestId, 'wem_organizacion', 'WEM-48 Org');
        update_post_meta($guestId, 'wem_mesa', '48');
        wp_set_object_terms($guestId, [$eventId], 'evento', false);

        return $guestId;
    }
}
