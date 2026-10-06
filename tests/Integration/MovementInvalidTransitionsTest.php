<?php

declare(strict_types=1);

namespace WEM\Tests\Integration;

use RuntimeException;
use WP_UnitTestCase;

final class MovementInvalidTransitionsTest extends WP_UnitTestCase
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

    public function testInvalidTransitionsAppendNothingAndPreserveProjection(): void
    {
        $event = wp_insert_term('WEM-28 Invalid Event', 'evento');
        self::assertNotWPError($event);

        $eventId = (int) $event['term_id'];
        $guestId = self::factory()->post->create([
            'post_type' => 'invitado',
            'post_title' => 'WEM-28 Invalid Guest',
            'post_status' => 'publish',
        ]);
        wp_set_object_terms($guestId, [$eventId], 'evento', false);

        $actorId = self::factory()->user->create(['role' => 'subscriber']);
        $service = new \WEM_Movement_Service();
        $ledger = new \WEM_Movement_Ledger();

        $this->assertRejectedWithoutMutation(
            fn () => $service->checkout($guestId, $eventId, $actorId, 'staff_web'),
            'Guest is not inside.',
            $ledger,
            $guestId,
            $eventId,
            []
        );

        $this->assertRejectedWithoutMutation(
            fn () => $service->reentry($guestId, $eventId, $actorId, 'staff_web'),
            'Guest cannot reenter.',
            $ledger,
            $guestId,
            $eventId,
            []
        );

        $service->checkin($guestId, $eventId, $actorId, 'staff_web');

        $insideSnapshot = $this->projectionSnapshot($guestId);
        $this->assertRejectedWithoutMutation(
            fn () => $service->checkin($guestId, $eventId, $actorId, 'staff_web'),
            'Guest is already inside.',
            $ledger,
            $guestId,
            $eventId,
            ['checkin'],
            $insideSnapshot
        );

        $this->assertRejectedWithoutMutation(
            fn () => $service->reentry($guestId, $eventId, $actorId, 'staff_web'),
            'Guest cannot reenter.',
            $ledger,
            $guestId,
            $eventId,
            ['checkin'],
            $insideSnapshot
        );

        $service->checkout($guestId, $eventId, $actorId, 'staff_web');

        $outsideSnapshot = $this->projectionSnapshot($guestId);
        $this->assertRejectedWithoutMutation(
            fn () => $service->checkout($guestId, $eventId, $actorId, 'staff_web'),
            'Guest is not inside.',
            $ledger,
            $guestId,
            $eventId,
            ['checkin', 'checkout'],
            $outsideSnapshot
        );

        $this->assertRejectedWithoutMutation(
            fn () => $service->checkin($guestId, $eventId, $actorId, 'staff_web'),
            'Guest must reenter after checkout.',
            $ledger,
            $guestId,
            $eventId,
            ['checkin', 'checkout'],
            $outsideSnapshot
        );
    }

    private function assertRejectedWithoutMutation(
        callable $operation,
        string $message,
        \WEM_Movement_Ledger $ledger,
        int $guestId,
        int $eventId,
        array $expectedTypes,
        ?array $expectedProjection = null
    ): void {
        $expectedProjection ??= $this->projectionSnapshot($guestId);

        try {
            $operation();
            self::fail('Invalid transition must be rejected.');
        } catch (RuntimeException $exception) {
            self::assertSame($message, $exception->getMessage());
        }

        $history = $ledger->find_by_guest_event($guestId, $eventId);

        self::assertSame($expectedTypes, array_column($history, 'movement_type'));
        self::assertSame($expectedProjection, $this->projectionSnapshot($guestId));
    }

    private function projectionSnapshot(int $guestId): array
    {
        return [
            'checkin' => (string) get_post_meta($guestId, 'wem_checkin', true),
            'checkin_at' => (string) get_post_meta($guestId, 'wem_checkin_at', true),
            'checkin_by' => (string) get_post_meta($guestId, 'wem_checkin_by', true),
            'checkout' => (string) get_post_meta($guestId, 'wem_checkout', true),
            'checkout_at' => (string) get_post_meta($guestId, 'wem_checkout_at', true),
            'checkout_by' => (string) get_post_meta($guestId, 'wem_checkout_by', true),
        ];
    }
}
