<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

final class PublicInvitationProjectionTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        require_once dirname(__DIR__, 2) . '/wordpress-event-manager.php';

        $taxonomy = new \WEM_Taxonomy_Manager();
        $taxonomy->register_taxonomy();
    }

    public function testProjectionContainsOnlyGuestNameAndEventName(): void
    {
        self::assertTrue(
            class_exists('WEM_Public_Invitation_Projection'),
            'WEM-60 requires a dedicated minimal public projection.'
        );

        $guestId = self::factory()->post->create([
            'post_type' => 'invitado',
            'post_status' => 'publish',
            'post_title' => 'SECRET-TICKET-123',
        ]);
        update_post_meta($guestId, 'wem_nombre', 'Invitado Público');
        update_post_meta($guestId, 'wem_organizacion', 'Internal Org');
        update_post_meta($guestId, 'wem_mesa', 'Mesa 7');
        update_post_meta($guestId, 'wem_observaciones', 'Private observation');
        update_post_meta($guestId, 'wem_checkin', '1');
        update_post_meta($guestId, 'wem_checkin_at', '2026-10-08 14:00:00');

        $eventId = self::factory()->term->create([
            'taxonomy' => 'evento',
            'name' => 'Evento Público',
            'slug' => 'evento-publico',
        ]);
        wp_set_object_terms($guestId, [$eventId], 'evento', false);

        $projection = new \WEM_Public_Invitation_Projection();

        self::assertSame(
            [
                'guest_name' => 'Invitado Público',
                'event_name' => 'Evento Público',
            ],
            $projection->build($guestId, $eventId)
        );
    }

    public function testProjectionNeverFallsBackToPostTitleForGuestName(): void
    {
        $guestId = self::factory()->post->create([
            'post_type' => 'invitado',
            'post_status' => 'publish',
            'post_title' => 'SECRET-TICKET-456',
        ]);

        $eventId = self::factory()->term->create([
            'taxonomy' => 'evento',
            'name' => 'Evento Sin Nombre',
        ]);
        wp_set_object_terms($guestId, [$eventId], 'evento', false);

        $projection = new \WEM_Public_Invitation_Projection();

        self::assertSame(
            [
                'guest_name' => '',
                'event_name' => 'Evento Sin Nombre',
            ],
            $projection->build($guestId, $eventId)
        );
    }

    public function testProjectionFailsClosedOnGuestOrEventMismatch(): void
    {
        $guestId = self::factory()->post->create([
            'post_type' => 'invitado',
            'post_status' => 'publish',
        ]);
        update_post_meta($guestId, 'wem_nombre', 'Mismatch Guest');

        $eventOne = self::factory()->term->create([
            'taxonomy' => 'evento',
            'name' => 'Event One',
        ]);
        $eventTwo = self::factory()->term->create([
            'taxonomy' => 'evento',
            'name' => 'Event Two',
        ]);
        wp_set_object_terms($guestId, [$eventOne], 'evento', false);

        $projection = new \WEM_Public_Invitation_Projection();

        self::assertNull($projection->build($guestId, $eventTwo));
        self::assertNull($projection->build(999999, $eventOne));
    }
}
