<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use RuntimeException;
use WP_UnitTestCase;

final class MovementEventReassignmentTest extends WP_UnitTestCase
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

    public function testEventReassignmentIsAllowedOnlyOutsideAndNeverRewritesHistory(): void
    {
        self::assertTrue(
            method_exists('WEM_Movement_Service', 'reassign_event'),
            'WEM-28 requires an outside-only event reassignment operation.'
        );

        $eventA = wp_insert_term('WEM-28 Reassign Event A', 'evento');
        $eventB = wp_insert_term('WEM-28 Reassign Event B', 'evento');

        self::assertNotWPError($eventA);
        self::assertNotWPError($eventB);

        $eventAId = (int) $eventA['term_id'];
        $eventBId = (int) $eventB['term_id'];

        $guestId = self::factory()->post->create([
            'post_type' => 'invitado',
            'post_title' => 'WEM-28 Reassign Guest',
            'post_status' => 'publish',
        ]);
        wp_set_object_terms($guestId, [$eventAId], 'evento', false);

        $actorId = self::factory()->user->create(['role' => 'subscriber']);

        $service = new \WEM_Movement_Service();
        $service->checkin($guestId, $eventAId, $actorId, 'staff_web');

        try {
            $service->reassign_event($guestId, $eventAId, $eventBId);
            self::fail('Inside guest must not be reassigned.');
        } catch (RuntimeException $exception) {
            self::assertSame('Guest must be outside before event reassignment.', $exception->getMessage());
        }

        $currentTerms = wp_get_object_terms($guestId, 'evento', ['fields' => 'ids']);
        self::assertSame([$eventAId], array_map('intval', $currentTerms));

        $service->checkout($guestId, $eventAId, $actorId, 'staff_web');
        $service->reassign_event($guestId, $eventAId, $eventBId);

        $currentTerms = wp_get_object_terms($guestId, 'evento', ['fields' => 'ids']);
        self::assertSame([$eventBId], array_map('intval', $currentTerms));

        $ledger = new \WEM_Movement_Ledger();
        $eventAHistory = $ledger->find_by_guest_event($guestId, $eventAId);

        self::assertSame(
            ['checkin', 'checkout'],
            array_column($eventAHistory, 'movement_type')
        );

        $service->checkin($guestId, $eventBId, $actorId, 'staff_web');

        $eventBHistory = $ledger->find_by_guest_event($guestId, $eventBId);

        self::assertSame(['checkin'], array_column($eventBHistory, 'movement_type'));
        self::assertSame(
            ['checkin', 'checkout'],
            array_column($ledger->find_by_guest_event($guestId, $eventAId), 'movement_type'),
            'Historical event A movements must remain permanently scoped to event A.'
        );
    }
}
