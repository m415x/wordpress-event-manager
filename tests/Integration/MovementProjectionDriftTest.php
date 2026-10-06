<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use RuntimeException;
use WP_UnitTestCase;

final class MovementProjectionDriftTest extends WP_UnitTestCase
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

    public function testCheckinFailsWithoutAutorepairWhenLedgerAndProjectionDisagree(): void
    {
        $event = wp_insert_term('WEM-27 Drift Event', 'evento');
        self::assertNotWPError($event);

        $eventId = (int) $event['term_id'];
        $guestId = self::factory()->post->create([
            'post_type' => 'invitado',
            'post_title' => 'WEM-27 Drift Guest',
            'post_status' => 'publish',
        ]);
        wp_set_object_terms($guestId, [$eventId], 'evento', false);

        $actorId = self::factory()->user->create(['role' => 'subscriber']);

        $ledger = new \WEM_Movement_Ledger();
        $ledger->append([
            'guest_id' => $guestId,
            'event_term_id' => $eventId,
            'movement_type' => 'checkin',
            'occurred_at' => '2026-10-06 12:00:00',
            'actor_user_id' => $actorId,
            'source' => 'staff_web',
            'decision' => 'accepted',
        ]);

        self::assertSame('', (string) get_post_meta($guestId, 'wem_checkin', true));

        $service = new \WEM_Movement_Service();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Movement ledger and projection are inconsistent.');

        try {
            $service->checkin($guestId, $eventId, $actorId, 'staff_web');
        } finally {
            $history = $ledger->find_by_guest_event($guestId, $eventId);

            self::assertCount(1, $history, 'Drift failure must not append another movement.');
            self::assertSame('', (string) get_post_meta($guestId, 'wem_checkin', true));
            self::assertSame('', (string) get_post_meta($guestId, 'wem_checkin_at', true));
            self::assertSame('', (string) get_post_meta($guestId, 'wem_checkin_by', true));
            self::assertSame('', (string) get_post_meta($guestId, 'wem_checkout', true));
        }
    }
}
