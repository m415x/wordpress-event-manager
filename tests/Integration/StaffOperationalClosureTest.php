<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

final class StaffOperationalClosureTest extends WP_UnitTestCase
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

        do_action('plugins_loaded');
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
