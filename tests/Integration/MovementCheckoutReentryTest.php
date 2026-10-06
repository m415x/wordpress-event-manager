<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

final class MovementCheckoutReentryTest extends WP_UnitTestCase
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
    }

    public function testCheckoutAndReentryPreserveDistinctHistoricalMovements(): void
    {
        $event = wp_insert_term('WEM-28 Cycle Event', 'evento');
        self::assertNotWPError($event);

        $eventId = (int) $event['term_id'];
        $guestId = self::factory()->post->create([
            'post_type' => 'invitado',
            'post_title' => 'WEM-28 Cycle Guest',
            'post_status' => 'publish',
        ]);
        wp_set_object_terms($guestId, [$eventId], 'evento', false);

        $actorId = self::factory()->user->create(['role' => 'subscriber']);

        $service = new \WEM_Movement_Service();

        $checkin = $service->checkin($guestId, $eventId, $actorId, 'staff_web');
        self::assertSame('inside', $checkin['state']);

        $checkout = $service->checkout($guestId, $eventId, $actorId, 'staff_web');
        self::assertSame('outside', $checkout['state']);

        $reentry = $service->reentry($guestId, $eventId, $actorId, 'staff_web');
        self::assertSame('inside', $reentry['state']);

        $ledger = new \WEM_Movement_Ledger();
        $history = $ledger->find_by_guest_event($guestId, $eventId);

        self::assertSame(
            ['checkin', 'checkout', 'reentry'],
            array_column($history, 'movement_type')
        );
        self::assertLessThan((int) $history[1]['sequence'], (int) $history[0]['sequence']);
        self::assertLessThan((int) $history[2]['sequence'], (int) $history[1]['sequence']);

        self::assertSame('1', (string) get_post_meta($guestId, 'wem_checkin', true));
        self::assertSame('', (string) get_post_meta($guestId, 'wem_checkout', true));
        self::assertNotSame('', (string) get_post_meta($guestId, 'wem_checkin_at', true));
        self::assertNotSame('', (string) get_post_meta($guestId, 'wem_checkin_by', true));
        self::assertSame('', (string) get_post_meta($guestId, 'wem_checkout_at', true));
        self::assertSame('', (string) get_post_meta($guestId, 'wem_checkout_by', true));
    }
}
