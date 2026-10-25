<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use WP_UnitTestCase;

final class AtomicCheckinTransitionTest extends WP_UnitTestCase
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

    public function testAuthorizedPrevalidatedCheckinAppendsMovementAndUpdatesProjection(): void
    {
        self::assertTrue(
            class_exists('WEM_Movement_Service'),
            'WEM-27 requires a transactional movement service after WEM-13 authorization.'
        );

        // WP_UnitTestCase resets post/term identity between runs, while the
        // movement ledger is durable in the reused PHPUnit database.
        // Choose a fresh guest/event pair without erasing ledger history.
        $guestId = self::factory()->post->create([
            'post_type' => 'invitado',
            'post_title' => 'WEM-27 Guest',
            'post_status' => 'publish',
        ]);
        $eventId = 0;
        $ledger = new \WEM_Movement_Ledger();
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $event = wp_insert_term('WEM-27 Checkin Event ' . wp_generate_uuid4(), 'evento');
            self::assertNotWPError($event);
            $candidate = (int) $event['term_id'];
            if ($ledger->find_by_guest_event($guestId, $candidate) === []) {
                $eventId = $candidate;
                break;
            }
        }
        self::assertGreaterThan(
            0,
            $eventId,
            'No uncontaminated synthetic event identity available; persistent ledger must not be deleted.'
        );
        wp_set_object_terms($guestId, [$eventId], 'evento', false);

        $actorId = self::factory()->user->create(['role' => 'subscriber']);

        $service = new \WEM_Movement_Service();
        $result = $service->checkin($guestId, $eventId, $actorId, 'staff_web');

        self::assertTrue($result['ok']);
        self::assertSame('inside', $result['state']);

        $ledger = new \WEM_Movement_Ledger();
        $history = $ledger->find_by_guest_event($guestId, $eventId);

        self::assertCount(1, $history);
        self::assertSame('checkin', $history[0]['movement_type']);
        self::assertSame((string) $actorId, (string) $history[0]['actor_user_id']);
        self::assertSame('staff_web', $history[0]['source']);
        self::assertSame('accepted', $history[0]['decision']);

        self::assertSame('1', (string) get_post_meta($guestId, 'wem_checkin', true));
        self::assertNotSame('', (string) get_post_meta($guestId, 'wem_checkin_at', true));
        self::assertNotSame('', (string) get_post_meta($guestId, 'wem_checkin_by', true));
        self::assertSame('', (string) get_post_meta($guestId, 'wem_checkout', true));
        self::assertSame('', (string) get_post_meta($guestId, 'wem_checkout_at', true));
        self::assertSame('', (string) get_post_meta($guestId, 'wem_checkout_by', true));
    }
}
