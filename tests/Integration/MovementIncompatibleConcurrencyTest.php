<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use RuntimeException;
use WP_UnitTestCase;

final class MovementIncompatibleConcurrencyTest extends WP_UnitTestCase
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

    public function testIncompatibleTransitionsAreSerializedAgainstLockedGuestState(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/includes/class-movement-service.php');

        self::assertIsString($source);
        self::assertStringContainsString('START TRANSACTION', $source);
        self::assertStringContainsString('FOR UPDATE', $source);
        self::assertStringContainsString('projection_matches_ledger', $source);
        self::assertStringContainsString('assert_transition_is_valid', $source);

        $event = wp_insert_term('WEM-28 Concurrency Event', 'evento');
        self::assertNotWPError($event);

        $eventId = (int) $event['term_id'];
        $guestId = self::factory()->post->create([
            'post_type' => 'invitado',
            'post_title' => 'WEM-28 Concurrency Guest',
            'post_status' => 'publish',
        ]);
        wp_set_object_terms($guestId, [$eventId], 'evento', false);

        $actorId = self::factory()->user->create(['role' => 'subscriber']);

        $service = new \WEM_Movement_Service();
        $service->checkin($guestId, $eventId, $actorId, 'staff_web');

        $service->checkout($guestId, $eventId, $actorId, 'staff_web');

        try {
            $service->checkout($guestId, $eventId, $actorId, 'staff_web');
            self::fail('A second incompatible checkout must be rejected after serialized reread.');
        } catch (RuntimeException $exception) {
            self::assertSame('Guest is not inside.', $exception->getMessage());
        }

        $service->reentry($guestId, $eventId, $actorId, 'staff_web');

        try {
            $service->reentry($guestId, $eventId, $actorId, 'staff_web');
            self::fail('A second incompatible reentry must be rejected after serialized reread.');
        } catch (RuntimeException $exception) {
            self::assertSame('Guest cannot reenter.', $exception->getMessage());
        }

        $ledger = new \WEM_Movement_Ledger();
        $history = $ledger->find_by_guest_event($guestId, $eventId);

        self::assertSame(
            ['checkin', 'checkout', 'reentry'],
            array_column($history, 'movement_type')
        );
    }
}
